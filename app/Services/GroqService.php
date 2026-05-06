<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GroqService
{
    private string $apiKey;
    private string $model;

    public function __construct(private TokenLogger $tokenLogger)
    {
        $this->apiKey = config('services.groq.api_key');
        $this->model = config('services.groq.model');
        // $this->tokenLogger = $tokenLogger;
    }

    /**
     * Support both simple prompts and full message arrays
     */
    public function chat($input, array $options = []): array
    {
        // Handle both formats
        if (is_string($input)) {
            $messages = [
                ['role' => 'user', 'content' => $input]
            ];

            // Add system message if provided
            if (isset($options['system'])) {
                array_unshift($messages, [
                    'role'    => 'system',
                    'content' => $options['system']
                ]);
            }
        } else {
            // Full messages array
            $messages = $input;
        }

        $payload = [
            'model'       => $this->model,
            'messages'    => $messages,
            'temperature' => $options['temperature'] ?? 0.3,
            'max_tokens'  => $options['max_tokens'] ?? 500,
        ];
       
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type'  => 'application/json',
                ])
                ->post('https://api.groq.com/openai/v1/chat/completions', $payload);

            if (!$response->successful()) {
                throw new Exception('Groq API error: ' . $response->body());
            }

            $data         = $response->json();
            $inputTokens  = $data['usage']['prompt_tokens']     ?? 0;
            $outputTokens = $data['usage']['completion_tokens'] ?? 0;

            // Log token usage if operation is specified
            if (isset($options['operation'])) {
                $this->tokenLogger->log(
                    operation:      $options['operation'],
                    inputTokens:    $inputTokens,
                    outputTokens:   $outputTokens,
                    complaintId:    $options['complaint_id']    ?? null,
                    conversationId: $options['conversation_id'] ?? null,
                    metadata:       $options['metadata']        ?? []
                );
            }

            return [
                'content'       => $data['choices'][0]['message']['content'],
                'tokens'        => $data['usage']['total_tokens'],
                'input_tokens'  => $inputTokens,
                'output_tokens' => $outputTokens,
                'finish_reason' => $data['choices'][0]['finish_reason'] ?? 'stop',
            ];

        } catch (Exception $e) {
            Log::error('GroqService: chat failed', [
                'error'     => $e->getMessage(),
                'operation' => $options['operation'] ?? 'unknown',
            ]);
            throw $e;
        }
    }

    /**
     * Function calling support
     * 
     * @param array $messages Conversation messages
     * @param array $tools Available function the AI can call
     * @param array Additional options (temperature, etc)
     */
    public function chatWithTools(array $messages, array $tools, array $options = []): array 
    {
        $payload = [
            'model'       => $this->model,
            'messages'    => $messages,
            'tools'       => $tools,
            'tool_choice' => 'auto',
            'temperature' => $options['temperature'] ?? 0.3,
            'max_tokens'  => $options['max_tokens'] ?? 500,
        ];

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type'  => 'application/json',
                ])
                ->post('https://api.groq.com/openai/v1/chat/completions', $payload);
            
            if (!$response->successful()) {
                throw new Exception('Groq API error: '. $response->body());
            }
  
            $data = $response->json();
            $inputTokens  = $data['usage']['prompt_tokens']     ?? 0;
            $outputTokens = $data['usage']['completion_tokens'] ?? 0;
            // Check if AI wants to call a function
            $message = $data['choices'][0]['message'];

            // Log if operation specified
            if (isset($options['operation'])) {
                $this->tokenLogger->log(
                    operation:      $options['operation'],
                    inputTokens:    $inputTokens,
                    outputTokens:   $outputTokens,
                    complaintId:    $options['complaint_id']    ?? null,
                    conversationId: $options['conversation_id'] ?? null,
                    metadata:       $options['metadata']        ?? []
                );
            }

            return [
                'content'       => $message['content'] ?? null,
                'tool_calls'    => $message['tool_calls'] ?? null,
                'tokens'        => $data['usage']['total_tokens'],
                'input_tokens'  => $inputTokens,
                'output_tokens' => $outputTokens,
                'finish_reason' => $data['choices'][0]['finish_reason'],
            ];
            
        } catch (Exception $e) {
            Log::error('GroqService: chatWithTools failed', [
                'error'     => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Chat with full conversation history
     */
    public function chatWithHistory(array $messages, array $options = []): array
    {
        return $this->chat($messages, $options);
    }
}