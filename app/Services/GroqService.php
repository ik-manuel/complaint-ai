<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GroqService
{
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = config('services.groq.api_key');
        $this->model = config('services.groq.model');
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
            'model' => $this->model,
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

            $data = $response->json();

            return [
                'content'       => $data['choices'][0]['message']['content'],
                'tokens'        => $data['usage']['total_tokens'],
                'finish_reason' => $data['choices'][0]['finish_reason'] ?? 'stop',
            ];

        } catch (Exception $e) {
            Log::error('Groq API Error: ' . $e->getMessage());
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