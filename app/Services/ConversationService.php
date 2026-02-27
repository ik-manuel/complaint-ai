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
        
        $skipCount = max(0, $totalMessages - 10);

        $recentMessages = $conversation->messages()
            ->where('role', '!=', 'system')
            ->orderBy('created_at', 'asc') 
            ->skip($skipCount)  // Skip older messages
            ->take(10)  // Take last 10
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
     * Week 3 Day 3: Summarize conversation to reduce token usage
     */
    public function summarizeConversation(Conversation $conversation): string
    {
        // Get all messages except recent 5 (We'll keep those in full)
        $messagesToSummarize = $conversation->messages()
            ->where('role', '!=', 'system')
            ->orderBy('created_at', 'asc')
            ->limit($conversation->messages()->count() - 5)
            ->get();

        if ($messagesToSummarize->count() < 5) {
            // Too few messages to summarize
            return '';
        }

        // Build conversation text
        $conversationText = '';
        foreach ($messagesToSummarize as $message) {
            $role = $message->role === 'user' ? 'Customer' : 'Support';
            $conversationText .= "{$role}: {$message->content}\n\n";
        }

        // Create summarization prompt
        $prompt = "Summarize this customer support conversation concisely.
            Include:
            - Main issue/complaint
            - Key facts mentioned (order numbers, dates, etc.)
            - Steps already taken
            - Current status

            Keep it under 150 words.

            Conversation:
            {$conversationText}

            Summary:";

        try {
            $result = $this->groq->chat($prompt, [
                'temperature' => 0.1, // Low temp for factual summary
                'max_tokens'  => 300,
            ]);

            // Save summary
            $conversation->update([
                'summary'            => $result['content'],
                'last_summarized_at' => now(),
            ]);

            return $result['content'];

        } catch (\Exception $e) {
            \Log::error('Summarization failed: ' . $e->getMessage());
            return '';
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
        
        \Log::info('Checking summarization:', [
            'message_count' => $messageCount,
            'last_summarized' => $conversation->last_summarized_at,
            'has_summary' => !empty($conversation->summary),
        ]);

        // Never summarized before and have enough messages
        if (empty($conversation->summary) && $messageCount >= 15) {
            \Log::info('First time summarization triggered');
            return true;
        }

        // Already summarized - check if need to re-summarize
        if (!empty($conversation->summary)) {
            // Count messages since last summary
            $messagesSinceLastSummary = $conversation->messages()
                ->where('role', '!=', 'system')
                ->where('created_at', '>', $conversation->last_summarized_at)
                ->count();
            
            \Log::info('Messages since last summary: ' . $messagesSinceLastSummary);
            
            // Re-summarize every 15 new messages
            if ($messagesSinceLastSummary >= 15) {
                \Log::info('Re-summarization triggered');
                return true;
            }
        }

        return false;
    }
}