<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ComplaintController;
use App\Http\Controllers\AdminController;

// Public Routes - Complaint Submission
Route::get('/', [ComplaintController::class, 'create'])->name('complaint.create');
Route::post('/complaints', [ComplaintController::class, 'store'])->name('complaint.store');
Route::get('/complaints/success/{ticketNumber}', [ComplaintController::class, 'success'])->name('complaint.success');

// Customer follow-up routes
Route::get('/complaints/{ticketNumber}/conversation', [ComplaintController::class, 'conversation'])->name('complaint.conversation');
Route::post('/complaints/{ticketNumber}/follow-up', [ComplaintController::class, 'followUp'])->name('complaint.followup');

// Admin Routes - Dashboard
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminController::class, 'index'])->name('index');
    Route::get('/complaints/{complaint}', [AdminController::class, 'show'])->name('show');
    Route::post('/complaints/{complaint}/approve', [AdminController::class, 'approve'])->name('approve');
    Route::post('/complaints/{complaint}/update-response', [AdminController::class, 'updateResponse'])->name('update-response');
    Route::post('/complaints/{complaint}/resolve', [AdminController::class, 'resolve'])->name('resolve');
});



// TEST
Route::get('/test-dynamic-window', function() {
    $conversationService = app(\App\Services\ConversationService::class);
    
    // Test with different conversation lengths
    $testCases = [
        ['messages' => 10, 'expected_window' => 10],
        ['messages' => 20, 'expected_window' => 12],
        ['messages' => 40, 'expected_window' => 15],
        ['messages' => 80, 'expected_window' => 20],
        ['messages' => 150, 'expected_window' => 25],
    ];
    
    $results = [];
    foreach ($testCases as $test) {
        $reflection = new ReflectionClass($conversationService);
        $method = $reflection->getMethod('getRecentMessageWindow');
        $method->setAccessible(true);
        
        $window = $method->invoke($conversationService, $test['messages']);
        
        $results[] = [
            'total_messages' => $test['messages'],
            'window_size' => $window,
            'coverage' => round(($window / $test['messages']) * 100, 1) . '%',
            'expected' => $test['expected_window'],
            'correct' => $window === $test['expected_window'] ? '✓' : '✗',
        ];
    }
    
    return response()->json($results);
});


Route::get('/test-smart-resummarize/{complaint}', function(\App\Models\Complaint $complaint) {
    if (!$complaint->conversation) {
        return response()->json(['error' => 'No conversation']);
    }
    
    $conversationService = app(\App\Services\ConversationService::class);
    $conversation = $complaint->conversation;
    
    $before = [
        'summary' => $conversation->summary,
        'messages_summarized' => $conversation->messages_summarized_count,
        'total_messages' => $conversation->messages()->count(),
    ];
    
    // Force re-summarization
    $newSummary = $conversationService->summarizeConversation($conversation);
    
    $after = [
        'summary' => $newSummary,
        'messages_summarized' => $conversation->fresh()->messages_summarized_count,
        'total_messages' => $conversation->messages()->count(),
    ];
    
    return response()->json([
        'before' => $before,
        'after' => $after,
        'type' => empty($before['summary']) ? 'first_summarization' : 'smart_re_summarization',
    ]);
});


Route::get('/test-smart-tools', function() {
    $groq = new \App\Services\GroqService();
    $toolService = new \App\Services\ToolService();
    $smartLoader = new \App\Services\SmartToolLoader($toolService);
    
    $question = request('q', 'Calculate 15% of 2000 and tell me the time');
    
    $relevantTools = $smartLoader->getRelevantTools($question);
    
    $messages = [
        [
            'role' => 'system',
            'content' => 'You are a helpful assistant. 

            IMPORTANT: When user asks multiple questions:
            - Call ALL necessary tools at once
            - For get_current_time: if no timezone mentioned, call it with empty arguments {} - it defaults to UTC
            - Do NOT ask user for missing optional parameters - use the defaults

            Available tools and their defaults:
            - calculate: requires expression
            - get_current_time: timezone optional (defaults to UTC)
            - get_string_length: requires text'
        ],
        [
            'role' => 'user',
            'content' => $question
        ]
    ];
    
    if (empty($relevantTools)) {
        $response = $groq->chat($question, [
            'system' => 'You are a helpful assistant.'
        ]);
        
        return response()->json([
            'question' => $question,
            'optimization' => 'no_tools_sent',
            'answer' => $response['content'],
            'tokens' => $response['tokens'],
        ]);
    }
    
    // First API call - AI decides which tools to use
    $response = $groq->chatWithTools($messages, $relevantTools);
    
    // Check if AI wants to use tools
    if ($response['tool_calls']) {
        \Log::info('AI called tools:', [
            'count' => count($response['tool_calls']),
            'tools' => array_map(fn($t) => $t['function']['name'], $response['tool_calls'])
        ]);
        
        // Add AI's decision to call tools
        $messages[] = [
            'role' => 'assistant',
            'content' => $response['content'],
            'tool_calls' => $response['tool_calls'],
        ];
        
        // Execute ALL tool calls
        foreach ($response['tool_calls'] as $toolCall) {
            $functionName = $toolCall['function']['name'];
            $arguments = json_decode($toolCall['function']['arguments'], true) ?: [];
            
            \Log::info('Executing tool:', [
                'name' => $functionName,
                'arguments' => $arguments
            ]);
            
            $functionResult = $toolService->executeTool($functionName, $arguments);
            
            // Add tool result to conversation
            $messages[] = [
                'role' => 'tool',
                'tool_call_id' => $toolCall['id'],
                'name' => $functionName,
                'content' => json_encode($functionResult),
            ];
        }
        
        \Log::info('All tools executed, getting final answer');
        
        // Second API call - AI generates final answer using all tool results
        $finalResponse = $groq->chatWithTools($messages, $relevantTools);
        
        return response()->json([
            'question' => $question,
            'optimization' => 'relevant_tools_only',
            'tools_sent' => count($relevantTools) . ' of ' . count($toolService->getAvailableTools()),
            'tools_called' => array_map(fn($t) => $t['function']['name'], $response['tool_calls']),
            'answer' => $finalResponse['content'],
            'total_tokens' => $response['tokens'] + $finalResponse['tokens'],
        ]);
    }
    
    return response()->json([
        'question' => $question,
        'answer' => $response['content'],
        'tokens' => $response['tokens'],
    ]);
});


// Database function call tools
Route::get('/test-database-tools', function() {
    $groq = new \App\Services\GroqService();
    $toolService = new \App\Services\ToolService();
    
    $question = request('q', 'How many complaints do we have?');
    
    \Log::info('Database tool test:', ['question' => $question]);
    
    // Get all tools (for database queries, we need them)
    $allTools = $toolService->getAvailableTools();
    
    $messages = [
        [
            'role' => 'system',
            'content' => 'You are a helpful customer service assistant with access to the complaint database. 

                Use database tools to answer questions about complaints, customers, and statistics.

                Available database tools:
                - get_complaint_by_ticket: Look up a specific ticket by number
                - get_customer_complaints: Get all complaints for a customer email
                - search_complaints: Search by category, urgency, or status
                - get_complaint_statistics: Get overall statistics

                When tools return "found: false", tell the user the item was not found.'
        ],
        [
            'role' => 'user',
            'content' => $question
        ]
    ];
    
    try {
        // First API call - AI decides which tools to use
        $response = $groq->chatWithTools($messages, $allTools);
        
        \Log::info('Initial AI response:', [
            'has_tool_calls' => isset($response['tool_calls']),
            'tool_calls_count' => isset($response['tool_calls']) ? count($response['tool_calls']) : 0,
        ]);

        // Check if AI wants to use tools
        if (!empty($response['tool_calls'])) {
            // Add AI's decision to messages
            $messages[] = [
                'role' => 'assistant',
                'content' => $response['content'],
                'tool_calls' => $response['tool_calls'],
            ];
            
            // Execute ALL tool calls
            foreach ($response['tool_calls'] as $toolCall) {
                $functionName = $toolCall['function']['name'];
                
                // FIX: Handle null or empty arguments
                $argumentsJson = $toolCall['function']['arguments'] ?? '{}';
                $arguments = json_decode($argumentsJson, true) ?: [];
                
                \Log::info('Executing tool:', [
                    'function' => $functionName,
                    'arguments' => $arguments,
                    'arguments_json' => $argumentsJson,
                ]);
                
                try {
                    $functionResult = $toolService->executeTool($functionName, $arguments);
                    
                    \Log::info('Tool result:', [
                        'function' => $functionName,
                        'result' => $functionResult,
                    ]);
                    
                    // Add tool result to conversation
                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $toolCall['id'],
                        'name' => $functionName,
                        'content' => json_encode($functionResult),
                    ];
                    
                } catch (\Exception $e) {
                    \Log::error('Tool execution error:', [
                        'function' => $functionName,
                        'error' => $e->getMessage(),
                    ]);
                    
                    // Add error as tool result
                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $toolCall['id'],
                        'name' => $functionName,
                        'content' => json_encode(['error' => $e->getMessage()]),
                    ];
                }
            }
            
            \Log::info('All tools executed, getting final answer');
            \Log::info('Messages being sent:', ['count' => count($messages)]);
            
            // Second API call - AI generates final answer using all tool results
            $finalResponse = $groq->chatWithTools($messages, $allTools);
         
            \Log::info('Final AI response:', $finalResponse);
            
            return response()->json([
                'success' => true,
                'question' => $question,
                'tools_used' => array_map(fn($t) => $t['function']['name'], $response['tool_calls']),
                'answer' => $finalResponse['content'] ?? 'No answer generated',
                'total_tokens' => ($response['tokens'] ?? 0) + ($finalResponse['tokens'] ?? 0),
                'debug' => [
                    'tool_calls_count' => count($response['tool_calls']),
                    'messages_count' => count($messages),
                    'finish_reason' => $finalResponse['finish_reason'] ?? null,
                ]
            ]);
        }
        
        // No tools needed
        return response()->json([
            'success' => true,
            'question' => $question,
            'answer' => $response['content'],
            'tokens' => $response['tokens'] ?? 0,
            'tools_used' => 'none',
        ]);
        
    } catch (\Exception $e) {
        \Log::error('Database tools test error:', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'question' => $question,
        ], 500);
    }
});