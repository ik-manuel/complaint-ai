<?php

namespace App\Services;

use App\Enums\ComplaintUrgency;
use App\Enums\MessageRole;
use App\Models\Complaint;
use App\Models\Conversation;
use App\Models\Document;
use App\Models\Message;
use Exception;
use Illuminate\Support\Facades\Log;

class ConversationService
{
    public function __construct(
        private GroqService $groq,
        private ToolService $toolService,
        private SmartToolLoader $smartToolLoader,
        private RagService $ragService,
        private EmbeddingService $embeddingService,
        ) { }

    /**
     * Create a new conversation for a complaint
     */
    public function createConversation(Complaint $complaint): Conversation
    {
        // Role-based system prompt
        $systemPrompt = $this->getSystemPromptForUrgency($complaint->urgency->value);

        return $conversation = Conversation::create([
            'complaint_id'  => $complaint->id,
            'system_prompt' => $systemPrompt,
        ]);
    }

    /**
     * Add a message to the conversation
     */
    public function addMessage(
        Conversation $conversation, 
        string|MessageRole $role, 
        string $content
    ): Message {
        $tokens = Message::estimateTokens($content);

        $message = $conversation->messages()->create([
            'role'    => $role instanceof MessageRole ? $role->value : $role,
            'content' => $content,
            'tokens'  => $tokens,
            'sent_at' => now(),
        ]);

        // Update total tokens
        $conversation->increment('total_tokens', $tokens);

        return $message;
    }

    /**
     * Get AI response with conversation memory!
     */
    public function getAiResponse(Conversation $conversation, string $userMessage): array
    {
        // Add user message to conversation
        $this->addMessage($conversation, MessageRole::User, $userMessage);

        // DEBUG: Log message count
        $messageCount = $conversation->messages()->count();
        \Log::info('Conversation state:', [
            'conversation_id'  => $conversation->id,
            'total_messages'   => $messageCount,
            'should_summarize' => $this->shouldSummarize($conversation),
        ]);

        // Check if we should summarize
        if ($this->shouldSummarize($conversation)) {
            \Log::info('Triggering summarization for conversation: ' . $conversation->id);
            $this->summarizeConversation($conversation);
        }

        // Build messages array with history
        $messages = $this->buildMessagesForApi($conversation);

        // TEMPORARY DEBUG: Log what we're sending
        \Log::info('API Request Messages:', [
            'conversation_id' => $conversation->id,
            'message_count'   => count($messages),
            'messages'        => $messages,
        ]);

        // Get AI response
        $result = $this->groq->chatWithHistory($messages, [
            'temperature'     => 0.3,
            'max_tokens'      => 500,
            'operation'       => 'conversation_turn',
            'complaint_id'    => $complaint_id    ?? null,
            'conversation_id' => $conversation_id ?? null,
            'metadata'        => ['round' => $round],
        ]);

        // Save AI response to conversation
        $this->addMessage($conversation, MessageRole::Assistant, $result['content']);

        return $result;
    }

    /**
     * AI response with memory + smart tool loading!
     */
    public function getAiResponseWithTools(Conversation $conversation, string $userMessage): array
    {
        // Add user message
        $this->addMessage($conversation, MessageRole::User, $userMessage);

        // Summarize if needed
        if ($this->shouldSummarize($conversation)) {
            $this->summarizeConversation($conversation);
        }

        // Conversation complaint
        $complaint = $conversation->complaint;

        // Route policy questions through RAG
        if ($this->smartToolLoader->isPolicyQuestion($userMessage)) {
            return $this->handleWithRag($conversation, $userMessage, $complaint);
        }

        // Existing tool-based flow for non-policy questions
        return $this->handleWithTools($conversation, $userMessage, $complaint);
    
    }

    /**
     * Streaming entry point conversation responses.
     * Routes to the same handleWithRag/handleWithTools methods,
     * passing $onToken to enable streaming in the generation step.
     * 
     * @param Conversation  $conversation
     * @param string        $userMessage
     * @param callable      $onToken        Call with each token as it arrives
     * @return array
     */
    public function streamResponse(
        Conversation $conversation,
        string $userMessage,
        callable $onToken
    ): array {
        // Add user message
        $this->addMessage($conversation, MessageRole::User, $userMessage);

        // Summarize if needed
        if ($this->shouldSummarize($conversation)) {
            $this->summarizeConversation($conversation);
        }

        // Conversation complaint
        $complaint = $conversation->complaint;

        // Route policy questions through RAG
        if ($this->smartToolLoader->isPolicyQuestion($userMessage)) {
            return $this->handleWithRag($conversation, $userMessage, $complaint, $onToken);
        }

        // Existing tool-based flow for non-policy questions
        return $this->handleWithTools($conversation, $userMessage, $complaint, $onToken);
    }

    /**
     * Build messages array for API (with memory!)
     * Include conversation history
     */
    private function buildMessagesForApi(Conversation $conversation): array
    {
        $messages = [];

        // Always include system prompt first
        if ($conversation->system_prompt) {
            $messages[] = [
                'role'    => 'system',
                'content' => $conversation->system_prompt,
            ];
        }

        // Include summary if exists
        if ($conversation->summary) {
            $messages[] = [
                'role'    => 'system',
                'content' => "Previous conversation summary: " . $conversation->summary,
            ];
        }

        // Get total count, skip older ones, take recent 10
        $totalMessages = $conversation->messages()
            ->where('role', '!=', 'system')
            ->count();
        
        // Dynamic window size!
        $windowSize = $this->getRecentMessageWindow($totalMessages);
        
        $skipCount = max(0, $totalMessages - $windowSize);

        $recentMessages = $conversation->messages()
            ->where('role', '!=', 'system')
            ->orderBy('created_at', 'asc') 
            ->skip($skipCount) 
            ->take($windowSize) 
            ->get();

        foreach ($recentMessages as $message) {
            $messages[] = $message->toApiFormat();
        }

        return $messages;
    }

    /**
     * Handle policy questions using RAG.
     * When $onToken is provided, the LLM generation step streams token-by-token.
     * When $onToken is null, behaves exactly as before (backward compatible).
     * 
     * Searches uploaded documents and answer from their content. 
     */
    private function handleWithRag(
        Conversation $conversation, 
        string $userMessage, 
        Complaint $complaint,
        ?callable $onToken = null
    ): array {
        Log::info('ConversationService: routing to RAG', [
            'conversation_id' => $conversation->id,
            'message'         => $userMessage,
        ]);
        
        try {
            // Retrieval phase — synchronous DB query, cannot stream
            $chunks = $this->embeddingService->findRelevantChunks(
                searchQuery:     $userMessage,
                limit:     4,
                threshold: 0.55
            );

            // No relevant chunks found — send fallback message
            if ($chunks->isEmpty()) {
                $documentCount = Document::where('status', 'completed')->count();

                $message = $documentCount === 0
                    ? "Our policy documents have not been uploaded yet. Please contact our support team directly."
                    : "I could not find specific information about that in our current policy documents. Please contact our support team directly.";

                // Stream the fallback message if callback provided
                if ($onToken) {
                    foreach (str_split($message, 4) as $chunk) {
                        $onToken($chunk);
                    }
                }

                $this->addMessage($conversation, MessageRole::Assistant, $message);

                return [
                    'response'   => $message,
                    'tokens'     => 0,
                    'tools_used' => [],
                    'rag_used'   => false,
                ];
            }

            // Build RAG prompt from retrieved chunks
            $context      = $this->ragService->buildContext($chunks);
            $systemPrompt = $this->ragService->buildPrompt($context);

            $groqOptions = [
                'system'          => $systemPrompt,
                'temperature'     => 0.1,
                'max_tokens'      => 600,
                'operation'       => 'rag_answer',
                'complaint_id'    => $complaint->id,
                'conversation_id' => $conversation->id,
                'metadata'        => ['chunks_used' => $chunks->count()],
            ];

            // Generation phase — stream if callback provided, regular if not
            if ($onToken) {
                $result = $this->groq->streamChat($userMessage, $onToken, $groqOptions);
            } else {
                $result = $this->groq->chat($userMessage, $groqOptions);
            }

            $this->addMessage($conversation, MessageRole::Assistant, $result['content']);

            return [
                'response'   => $result['content'],
                'tokens'     => $result['tokens'],
                'tools_used' => [],
                'rag_used'   => true,
            ];

        } catch (\Exception $e) {
            Log::error('ConversationService: handleWithRag failed', [
                'error' => $e->getMessage(),
            ]);

            // Fallback: pass $onToken through so streaming still works
            return $this->handleWithTools($conversation, $userMessage, $complaint, $onToken);
        }
    }

    /**
     * Handle data/action questions using function calling tools.
     * When $onToken is provided:
     *   - Tool execution rounds remain synchronous (JSON must be complete)
     *   - Final text generation round uses streamChat() for true streaming
     * When $onToken is null, behaves exactly as before (backward compatible).
     */
    private function handleWithTools(
        Conversation $conversation, 
        string $userMessage, 
        Complaint $complaint,
        ?callable $onToken = null
    ): array {
        // Build conversation history
        $messages = $this->buildMessagesForApi($conversation);
        
        // Build context from complaint
        $context = [
            'ticket_number'  => $complaint->ticket_number,
            'customer_email' => $complaint->customer->email,
        ];

        // Smart tool loading - only relevant tools
        $tools = $this->smartToolLoader->getConversationTools($userMessage, $context);

        // Fast path: no tools needed (greeting, general message)
        // Skip tool loop entirely — go straight to generation
        if (empty($tools)) {
            return $this->generateFinalResponse(
                $conversation,
                $complaint,
                $messages,
                $onToken
            );
        }

        // Enrich system message with complaint context
        // This helps AI know it can use the ticket/email without user having to specify
        if (!empty($messages[0]) && $messages[0]['role'] === 'system') {
            $messages[0]['content'] .= "\n\n
                CURRENT COMPLAINT CONTEXT:
                - Ticket Number: {$complaint->ticket_number}
                - Customer Email: {$complaint->customer->email}
                - Customer Name: {$complaint->customer->name}
                
                STRICT RULES:
                1. Call ALL needed tools in ONE response - never split across rounds
                2. After receiving tool results, always give a final text answer immediately
                3. Never call a tool if you already have that data in the conversation
                4. For greetings or thanks, answer directly without any tools
                
                RESPONSE RULES:
                - Answer what was asked, then stop
                - Do NOT ask follow-up questions unless the user seems confused
                - Do NOT offer next steps unless the user asks
                - Keep responses concise and factual
                ";
        }

        $totalTokens = 0;
        $toolsUsed = [];
        $maxRounds = 3; // Low limit - if prompt is good, will rarely need more than 2

        $round = 0;

        /*
        * Why a loop?
        *
        * The AI sometimes needs multiple rounds:
        *   Round 1 → AI decides which tool(s) to call
        *   Round 2 → AI receives tool results, gives final answer
        *   Round 3 → Rare: AI calls another tool before answering
        *
        * The explicit two-call pattern only handles rounds 1 and 2.
        * This loop handles all cases, with maxRounds as a safety limit.
        */
        while ($round < $maxRounds) {
            $round++;

            Log::info("ConversationService: tool loop - round {$round} of max {$maxRounds}");

            $response = $this->groq->chatWithTools($messages, $tools, [
                'temperature'     => 0.3,
                'max_tokens'      => 500,
                'operation'       => 'conversation_turn',
                'complaint_id'    => $complaint->id,
                'conversation_id' => $conversation->id,
                'metadata'        => ['round' => $round],
            ]);

            $totalTokens += $response['tokens'];

            // ── EXIT CONDITION ──────────────────────────────────────────
            // AI returned a text answer with no further tool requests.
            // This is the normal exit path on round 2 (or round 1 if
            // no tools were needed after all).
            // Clean text response
            if (!empty($response['content']) && empty($response['tool_calls'])) {
                if ($onToken) {
                    // True streaming: re-request the final answer via streamChat
                    // with all accumulated messages (including tool results)
                    // so the user sees real token-by-token output
                    $streamResult = $this->groq->streamChat(
                        $messages,  // full conversation including tool results
                        $onToken,
                        [
                            'temperature'     => 0.3,
                            'max_tokens'      => 500,
                            'operation'       => 'conversation_turn',
                            'complaint_id'    => $complaint->id,
                            'conversation_id' => $conversation->id,
                        ]
                    );

                    // Use the streamed content as the saved message
                    $this->addMessage($conversation, MessageRole::Assistant, $streamResult['content']);
                    $totalTokens += $streamResult['tokens'];

                    return [
                        'response'   => $streamResult['content'],
                        'tokens'     => $totalTokens,
                        'tools_used' => $toolsUsed,
                        'rag_used'   => false,
                    ];
                }
                // Non-streaming path
                $this->addMessage($conversation, MessageRole::Assistant, $response['content']);

                return [
                    'response'   => $response['content'],
                    'tokens'     => $totalTokens,
                    'tools_used' => $toolsUsed,
                    'rag_used'   => false,
                    'rounds'     => $round, // Track for monitoring
                ];
            }

            // ── TOOL EXECUTION ───────────────────────────────────────────
            // AI requested tool(s). Execute each one, add results to
            // $messages, then loop back for the next round.
            // Tool call requested
            if (!empty($response['tool_calls'])) {
                $messages[] = [
                    'role'       => 'assistant',
                    'content'    => $response['content'],
                    'tool_calls' => $response['tool_calls'],
                ];

                foreach ($response['tool_calls'] as $toolCall) {
                    $functionName = $toolCall['function']['name'];
                    $arguments = json_decode($toolCall['function']['arguments'], true) ?: [];
                    $toolsUsed[] = $functionName;
                    
                    try {
                        $toolResult = $this->toolService->executeTool($functionName, $arguments);
                    } catch (\Exception $e) {
                        $toolResult = ['error' => e->getMessage()];
                    }

                    $messages[] = [
                        'role'         => 'tool',
                        'tool_call_id' => $toolCall['id'],
                        'name'         => $functionName,
                        'content'      => json_encode($toolResult),
                    ];
                }

                continue;
            }

            break;
        } 

        $fallback = 'I encountered an issue processing your request. Please try again.';

        if ($onToken) {
            foreach (str_split($fallback, 4) as $chunk) {
                $onToken($chunk);
            }
        }

        $this->addMessage($conversation, MessageRole::Assistant, $fallback);

        return [
            'response'   => $fallback,
            'tokens'     => $totalTokens,
            'tools_used' => $toolsUsed,
            'rag_used'   => false,
        ];
    }

    /**
     * Generate a direct response when no tools are needed.
     * Used for greetings, general conversation, and simple questions.
     * Streams if $onToken is provided.
     */
    private function generateFinalResponse(
        Conversation $conversation,
        Complaint $complaint,
        array $messages,
        ?callable $onToken = null
    ): array {
        $groqOptions = [
            'temperature'     => 0.3,
            'max_tokens'      => 500,
            'operation'       => 'conversation_turn',
            'complaint_id'    => $complaint->id,
            'conversation_id' => $conversation->id,
        ];

        if ($onToken) {
            $response = $this->groq->streamChat($messages, $onToken, $groqOptions);
        } else {
            // Non-streaming: pass messages array directly to chat()
            $response = $this->groq->chat($messages, $groqOptions);
        }

        $this->addMessage($conversation, MessageRole::Assistant, $response['content']);

        return [
            'response'   => $response['content'],
            'tokens'     => $response['tokens'],
            'tools_used' => [],
            'rag_used'   => false,
        ];
    }

    /**
     * Different prompts for different urgency levels
     */
    private function getSystemPromptForUrgency(string $urgency): string
    {
        return match($urgency) {
            ComplaintUrgency::High => "You are a senior customer service manager. Be direct, empathetic, and action-oriented. You have access to the full conversation history and can reference previous messages.",
            
            ComplaintUrgency::Medium => "You are a professional customer support agent. Be helpful, clear, and solution-focused. You remember previous interactions and can provide context-aware responses.",
            
            ComplaintUrgency::Low => "You are a friendly customer support representative. Be warm, patient, and informative. You maintain conversation context and can answer follow-up questions.",
            
            default => "You are a professional customer support agent with access to conversation history."
        };
    }

    /**
     * Dynamic window size based on conversation length
     */
    private function getRecentMessageWindow(int $totalMessages): int
    {
        return match(true) {
            $totalMessages < 15  => min($totalMessages, 10),  // All or 10
            $totalMessages < 30  => 12,   // Small: 12 messages
            $totalMessages < 50  => 15,   // Medium: 15 messages
            $totalMessages < 100 => 20,  // Large: 20 messages
            default              => 25,               // Very large: 25 messages
        };
    }

    /**
     * Summarize conversation to reduce token usage
     */
    public function summarizeConversation(Conversation $conversation): string
    {
        $totalMessages = $conversation->messages()
            ->where('role', '!=', 'system')
            ->count();

        if ($totalMessages < 15) {
            return '';
        }

        // Use dynamic window size
        $windowSize = $this->getRecentMessageWindow($totalMessages);
        $messagesToSummarizeCount = $totalMessages - $windowSize;

        if ($messagesToSummarizeCount < 5) {
            return '';
        }

        // Check if this is first summarization or re-summarization
        $isFirstSummarization = empty($conversation->summary);
        
        if ($isFirstSummarization) {
            // First time: Summarize all messages up to window
            return $this->performFirstSummarization($conversation, $messagesToSummarizeCount, $windowSize);
        } else {
            // Re-summarization: Only summarize NEW messages
            return $this->performSmartReSummarization($conversation, $messagesToSummarizeCount, $windowSize);
        }

    }

    /**
     * First time summarization - summarize all old messages
     */
    private function performFirstSummarization(Conversation $conversation, int $count, int $windowSize): string
    {
        \Log::info('First summarization:', [
            'messages_to_summarize' => $count,
            'window_size' => $windowSize,
        ]);

        $messagesToSummarize = $conversation->messages()
            ->where('role', '!=', 'system')
            ->orderBy('created_at', 'asc')
            ->limit($count)
            ->get();

        $conversationText = '';
        foreach ($messagesToSummarize as $message) {
            $role = $message->role === MessageRole::User ? 'Customer' : 'Support';
            $conversationText .= "{$role}: {$message->content}\n\n";
        }

        $prompt = "Summarize this customer support conversation concisely.

            Include:
            - Main issue/complaint
            - Key facts (order numbers, dates, product names)
            - Steps already taken
            - Important outcomes

            Keep under 150 words.

            Conversation:
            {$conversationText}

            Summary:";

        try {
            $result = $this->groq->chat($prompt, [
                'temperature'     => 0.1,
                'max_tokens'      => 300,
                'operation'       => 'summarization',
                'conversation_id' => $conversation_id ?? null,
                'metadata'        => ['summarization' => 'first_summerization'],
            ]);

            $conversation->update([
                'summary'                   => $result['content'],
                'messages_summarized_count' => $count,
                'last_summarized_at'        => now(),
            ]);

            \Log::info('First summary created', [
                'messages_summarized' => $count,
                'tokens_used' => $result['tokens'],
            ]);

            return $result['content'];

        } catch (\Exception $e) {
            \Log::error('First summarization failed:', [
                'error' => $e->getMessage(),
            ]);
            return '';
        }

    }


    /**
     * Smart re-summarization - only summarize NEW messages since last summary
     */
    private function performSmartReSummarization(Conversation $conversation, int $newCount, int $windowSize): string
    {
        $previouslySummarized = $conversation->messages_summarized_count;
        $newMessagesToSummarize = $newCount - $previouslySummarized;

        if ($newMessagesToSummarize < 5) {
            \Log::info('Not enough new messages for re-summarization', [
                'new_messages' => $newMessagesToSummarize,
            ]);
            return $conversation->summary; // Return existing summary
        }

        \Log::info('Smart re-summarization:', [
            'previously_summarized' => $previouslySummarized,
            'new_to_summarize'      => $newMessagesToSummarize,
            'total_in_new_summary'  => $newCount,
        ]);

        // Get only NEW messages that weren't in previous summary
        $newMessages = $conversation->messages()
            ->where('role', '!=', 'system')
            ->orderBy('created_at', 'asc')
            ->skip($previouslySummarized)
            ->limit($newMessagesToSummarize)
            ->get();

        $newConversationText = '';
        foreach ($newMessages as $message) {
            $role = $message->role === MessageRole::User ? 'Customer' : 'Support';
            $newConversationText .= "{$role}: {$message->content}\n\n";
        }

        $prompt = "You previously created this summary of a customer conversation:

            PREVIOUS SUMMARY:
            {$conversation->summary}

            Now there are NEW messages in the conversation:

            NEW MESSAGES:
            {$newConversationText}

            Create an UPDATED summary that:
            1. Keeps important information from the previous summary
            2. Integrates the new information
            3. Maintains chronological flow
            4. Stays under 300 words

            UPDATED SUMMARY:";

        try {
            $result = $this->groq->chat($prompt, [
                'temperature'     => 0.1,
                'max_tokens'      => 400, // Slightly more for merged summary
                'operation'       => 'summarization',
                'conversation_id' => $conversation_id ?? null,
                'metadata'        => ['summarization' => 're_summerization'],
            ]);

            $conversation->update([
                'summary'                   => $result['content'],
                'messages_summarized_count' => $newCount,
                'last_summarized_at'        => now(),
            ]);

            \Log::info('Smart re-summarization complete', [
                'new_messages_processed' => $newMessagesToSummarize,
                'total_now_summarized'   => $newCount,
                'tokens_used'            => $result['tokens'],
                'token_savings'          => 'Only processed ' . $newMessagesToSummarize . ' messages instead of ' . $newCount,
            ]);

            return $result['content'];

        } catch (\Exception $e) {
            \Log::error('Smart re-summarization failed:', [
                'error' => $e->getMessage(),
            ]);
            return $conversation->summary; // Return existing summary on error
        }
    }

    /**
     *  Check if conversation should be summarized
     */
    public function shouldSummarize(Conversation $conversation): bool
    {
        $messageCount = $conversation->messages()
            ->where('role', '!=', 'system')
            ->count();

        // Never summarized before and have enough messages
        if (empty($conversation->summary) && $messageCount >= 15) {
            return true;
        }

        // Already summarized - check if need to re-summarize
        if (!empty($conversation->summary)) {
            // Count messages since last summary
            $messagesSinceLastSummary = $conversation->messages()
                ->where('role', '!=', 'system')
                ->where('created_at', '>', $conversation->last_summarized_at)
                ->count();
                        
            // Re-summarize every 15 new messages
            if ($messagesSinceLastSummary >= 15) {
                return true;
            }
        }

        return false;
    }
}