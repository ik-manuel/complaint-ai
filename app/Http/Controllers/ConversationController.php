<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Services\ConversationService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConversationController extends Controller
{
    public function __construct(
        private ConversationService $conversationService
    ) {}

    /**
     * Stream an AI response token-by-token via Server-Sent Events.
     *
     * The frontend connects to this endpoint, sends the user message,
     * and receives tokens one at a time as the AI generates them.
     */
    public function stream(Request $request, Conversation $conversation): StreamedResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        $userMessage = $validated['message'];

        // Ensure conversation exists and has an associated complaint
        if (!$conversation->complaint) {
            return response()->stream(function () {
                $error = json_encode(['error' => 'Invalid conversation.']);
                echo "data: {$error}\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                    flush(); 
                }
            }, 200, [
                'Content-Type'  => 'text/event-stream',
                'Cache-Control' => 'no-cache',
            ]);
        }

        return response()->stream(function () use ($conversation, $userMessage) {

            // SSE requires specific headers — disable buffering so chunks
            // reach the browser immediately without waiting for the full response
            if (ob_get_level()) {
                ob_end_clean();
            }

            // Send SSE headers via echo (we're inside the stream closure)
            echo "retry: 3000\n\n";
            
            if (ob_get_level() > 0) {
                ob_flush();
                flush(); // Also flush PHP's internal buffer
            }

            try {
                $result = $this->conversationService->streamResponse(
                    $conversation,
                    $userMessage,
                    function (string $token) {
                        // Stop streaming if client disconnected
                        if (connection_aborted()) {
                            throw new \RuntimeException('Client disconnected');
                        }
                        // Format as SSE event and push to browser
                        $encoded = json_encode(['token' => $token]);
                        echo "data: {$encoded}\n\n";
                        
                        if (ob_get_level() > 0) {
                            ob_flush();
                        }
                        flush();
                    }
                );

                // Send final event with metadata (token count, tools used)
                $finalData = json_encode([
                    'done'       => true,
                    'tokens'     => $result['tokens'],
                    'tools_used' => $result['tools_used'] ?? [],
                    'rag_used'   => $result['rag_used']   ?? false,
                ]);
                echo "data: {$finalData}\n\n";
            
                if (ob_get_level() > 0) {
                    ob_flush();
                    flush(); // Also flush PHP's internal buffer
                }

            } catch (\Exception $e) {
                // Send error event so frontend can show a message
                $errorData = json_encode([
                    'error' => 'An error occurred. Please try again.',
                ]);
                echo "data: {$errorData}\n\n";
    
                if (ob_get_level() > 0) {
                    ob_flush();
                    flush(); // Also flush PHP's internal buffer
                }

                \Log::error('ConversationController: stream failed', [
                    'conversation_id' => $conversation->id,
                    'error'           => $e->getMessage(),
                ]);
            }

        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no',  // Disable nginx buffering
            'Connection'        => 'keep-alive',
        ]);
    }
}