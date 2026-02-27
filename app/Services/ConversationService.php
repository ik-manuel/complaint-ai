<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Complaint;
use Exception;
use Illuminate\Support\Facades\Log;

class ConversationService
{
    public function __construct(private GroqService $groq) { }

    /**
     * Create a new conversation for a complaint
     */
    public function createConversation(Complaint $complaint): Conversation
    {
        // Role-based system prompt
        $systemPrompt = $this->getSystemPromptForUrgency($complaint->urgency);

        return $conversation = Conversation::create([
            'complaint_id' => $complaint->id,
            'system_prompt' => $systemPrompt,
        ]);
    }

    /**
     * Add a message to the conversation
     */
    public function addMessage(
        Conversation $conversation, 
        string $role, 
        string $content
    ): Message {
        $tokens = Message::estimateTokens($content);

        $message = $conversation->messages()->create([
            'role'    => $role,
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
        $this->addMessage($conversation, 'user', $userMessage);

        // DEBUG: Log message count
        $messageCount = $conversation->messages()->count();
        \Log::info('Conversation state:', [
            'conversation_id' => $conversation->id,
            'total_messages' => $messageCount,
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
            'message_count' => count($messages),
            'messages' => $messages,
        ]);

        // Get AI response
        $result = $this->groq->chatWithHistory($messages, [
            'temperature' => 0.3,
            'max_tokens' => 500,
        ]);

        // Save AI response to conversation
        $this->addMessage($conversation, 'assistant', $result['content']);

        return $result;
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

        // Week 3 Day 3: Include summary if exists
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
     * Different prompts for different urgency levels
     */
    private function getSystemPromptForUrgency(string $urgency): string
    {
        return match($urgency) {
            'high' => "You are a senior customer service manager. Be direct, empathetic, and action-oriented. You have access to the full conversation history and can reference previous messages.",
            
            'medium' => "You are a professional customer support agent. Be helpful, clear, and solution-focused. You remember previous interactions and can provide context-aware responses.",
            
            'low' => "You are a friendly customer support representative. Be warm, patient, and informative. You maintain conversation context and can answer follow-up questions.",
            
            default => "You are a professional customer support agent with access to conversation history."
        };
    }

    /**
     * Dynamic window size based on conversation length
     */
    private function getRecentMessageWindow(int $totalMessages): int
    {
        return match(true) {
            $totalMessages < 15 => min($totalMessages, 10),  // All or 10
            $totalMessages < 30 => 12,   // Small: 12 messages
            $totalMessages < 50 => 15,   // Medium: 15 messages
            $totalMessages < 100 => 20,  // Large: 20 messages
            default => 25,               // Very large: 25 messages
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
            $role = $message->role === 'user' ? 'Customer' : 'Support';
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
                'temperature' => 0.1,
                'max_tokens' => 300,
            ]);

            $conversation->update([
                'summary' => $result['content'],
                'messages_summarized_count' => $count,
                'last_summarized_at' => now(),
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
            'new_to_summarize' => $newMessagesToSummarize,
            'total_in_new_summary' => $newCount,
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
            $role = $message->role === 'user' ? 'Customer' : 'Support';
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
            4. Stays under 200 words

            UPDATED SUMMARY:";

        try {
            $result = $this->groq->chat($prompt, [
                'temperature' => 0.1,
                'max_tokens' => 400, // Slightly more for merged summary
            ]);

            $conversation->update([
                'summary' => $result['content'],
                'messages_summarized_count' => $newCount,
                'last_summarized_at' => now(),
            ]);

            \Log::info('Smart re-summarization complete', [
                'new_messages_processed' => $newMessagesToSummarize,
                'total_now_summarized' => $newCount,
                'tokens_used' => $result['tokens'],
                'token_savings' => 'Only processed ' . $newMessagesToSummarize . ' messages instead of ' . $newCount,
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