<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GroqService
{
    private string $apiKey;
    private string $model;
    private string $baseUrl = 'https://api.groq.com/openai/v1';

    public function __construct(private TokenLogger $tokenLogger)
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
                ->post($this->baseUrl . '/chat/completions', $payload);

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
                ->post($this->baseUrl . '/chat/completions', $payload);
            
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
     * Stream a chat response token-by-token.
     * Calls $callback for each token chunk as it arrives.
     * Returns total token count when is complete
     * 
     * @param string|array $input       Prompt string or messages array
     * @param callable     $callback    Called with each token string chunk
     * @param array        $options     temperature, max_tokens, system, operation, etc.
     * @return array                    ['tokens' => int, 'content' => string (full response)] 
     */
    public function streamChat(string|array $prompt, callable $callback, array $options = []): array
    {
        if (is_string($prompt)) {
            $messages = [['role' => 'user', 'content' => $prompt]];

            if (isset($options['system'])) {
                array_unshift($messages, [
                    'role'    => 'system',
                    'content' => $options['system'],
                ]);
            }
        } else {
            $messages = $prompt;
        }

        $payload = [
            'model'          => $this->model,
            'messages'       => $messages,
            'temperature'    => $options['temperature'] ?? 0.3,
            'max_tokens'     => $options['max_tokens']  ?? 500,
            'stream'         => true,
            'stream_options' => ['include_usage' => true],
        ];

        $fullContent = '';
        $totalTokens = 0;

        try {
            // Use Guzzle directly for streaming — Laravel HTTP client
            // buffers the full response by default, defeating the purpose
            $client = new \GuzzleHttp\Client();

            $response = $client->post($this->baseUrl .'/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'text/event-stream',
                ],
                'json'   => $payload,
                'stream' => true,  
            ]);

            $body = $response->getBody();

            while (!$body->eof()) {
                $line = $this->readLine($body);

                if (empty($line) || !str_starts_with($line, 'data: ')) {
                    continue;
                }

                $data = substr($line, 6); // strip "data: " prefix

                if ($data === '[[DONE]') {
                    break;
                }

                $chunk = json_decode($data, true);

                if (!$chunk || empty($chunk['choices'][0]['delta']['content'])) {
                    continue;
                }

                $token = $chunk['choices'][0]['delta']['content'];
                $fullContent .= $token;

                // Fire callback with each token — the caller forwards it to the browser
                $callback($token);

                // Track usage if provided in chunk (Groq includes this at end)
                if (!empty($chunk['usage']['total_tokens'])) {
                    $totalTokens = $chunk['usage']['total_tokens'];
                }
            }

            // Log token usage if operation specified
            if (isset($options['operation']) && $totalTokens > 0) {
                $this->tokenLogger->log(
                    operation:      $options['operation'],
                    inputTokens:    $options['input_tokens']  ?? (int)($totalTokens * 0.8),
                    outputTokens:   $options['output_tokens'] ?? (int)($totalTokens * 0.2),
                    complaintId:    $options['complaint_id']    ?? null,
                    conversationId: $options['conversation_id'] ?? null,
                );
            }

            return [
                'content' => $fullContent,
                'tokens'  => $totalTokens,
            ];
        } catch (\Exception $e) {
            Log::error('GroqService: streamChat failed', [
                'error'     => $e->getMessage(),
                'operation' => $options['operation'] ?? 'unknown',
            ]);
            throw $e;
        }
    }

    /**
     * Read a single line from a stream body.
     * SSE sends lines terminated by \n - we read until we hit one.
     */
    private function readLine(\GuzzleHttp\Psr7\Stream|\Psr\Http\Message\StreamInterface $body): string
    {
        $line = '';

        while (!$body->eof()) {
            $char = $body->read(1);

            if ($char === "\n") {
                break;
            }

            $line .= $char;
        }

        return rtrim($line, "\r");
    }

    /**
     * Chat with full conversation history
     */
    public function chatWithHistory(array $messages, array $options = []): array
    {
        return $this->chat($messages, $options);
    }
}