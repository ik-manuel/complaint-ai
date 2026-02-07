<?php

namespace App\Services;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class GroqService
{
    private string $apiKey;
    private string $model;

    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        // Load API key and model 
        $this->apiKey = config('services.groq.api_key');
        $this->model  = config('services.groq.model');
    }

    /**
     * Send a chat completion request to Groq API.
     *
     * @param string $prompt The user prompt
     * @param array $options Optional parameters (system instructions, temperature, max_tokens)
     * @return array ['content' => string, 'tokens' => int]
     * @throws Exception on API error
     */
    public function chat( string $prompt, array $options = []): array
    {
        // Build messages array for chat completion
        $messages = [
            ['role' => 'user', 'content' => $prompt]
        ];

        // If system instructions are provided, append them to the beginning of the messages array
        if (isset($options['system'])) {
            array_unshift($messages, [
                'role'    => 'system',
                'content' => $options['system']
            ]);
        }

        // Prepare payload for Groq API
        $payload = [
            'model'       => $this->model,
            'messages'    => $messages,
            'temperature' => $options['temperature'] ?? 0.3,
            'max_tokens'  => $options['max_tokens'] ?? 500,
        ];

        // Make and handle API request to Groq
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
                'content' => $data['choices'][0]['message']['content'],
                'tokens'  => $data['usage']['total_tokens'],
            ];

        } catch (Exception $e) {
            Log::error('Groq API error: ' . $e->getMessage());
            throw $e;
        }

    }

}
