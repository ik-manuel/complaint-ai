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


Route::get('/test-function-calling', function() {
    $groq = new \App\Services\GroqService();
    $toolService = new \App\Services\ToolService();
    
    // Get available tools
    $tools = $toolService->getAvailableTools();
    
    // User question that requires calculation
    // $messages = [
    //     [
    //         'role' => 'user',
    //         'content' => 'What is the capital of Niger?'
    //     ]
    // ];

    // THE ABOVE MESSAGE ARRAY ALLOW THE AI TO TRY TO USE TOOL FOR QUESTION THAT DOES NOT REQUIRE
    // TOOL - I AM USING SYSTEM PROMPT APPROACH TO INSTRUCT IT ON WHEN TO AND WHEN NOT TO.
    // THIS IS AS A RESULT WHEN THE AI TRY TO USE A SEARCH TOOL FOR QUESTION THAT DOES NOT REQUIRE 
    // TOOL OR TOOL NOT DEFINE - EG 'WHAT IS THE CAPITAL OF NIGERIA?' > ERROR (TRIED USING TOOL)
    // 'WHAT IS THE CAPITAL OF GHANA?' > ACCRA (RESPONDED WELL)

    // Use system prompt for my accurate ai tool chioce
    $messages = [
        [
            'role' => 'system',
            'content' => 'You are a helpful assistant. Only use tools when:
            1. You need to perform calculations (use calculate)
            2. You need current time (use get_current_time)
            3. You need to count characters (use get_string_length)
            
            For general knowledge questions (capitals, facts, definitions), answer directly from your knowledge without using tools.'
        ],
        [
            'role' => 'user',
            'content' => 'What is the capital of Nigeria?'
        ]
    ];
    
    \Log::info('Initial user message:', ['messages' => $messages]);
    
    // Step 1: Send to AI with tools
    $response = $groq->chatWithTools($messages, $tools);
    
    \Log::info('AI Response:', $response);
    
    // Step 2: Check if AI wants to call a function
    if ($response['tool_calls']) {
        $toolCall = $response['tool_calls'][0];
        $functionName = $toolCall['function']['name'];
        $arguments = json_decode($toolCall['function']['arguments'], true);
        
        \Log::info('AI wants to call function:', [
            'function' => $functionName,
            'arguments' => $arguments,
        ]);
        
        // Step 3: Execute the function
        $functionResult = $toolService->executeTool($functionName, $arguments);
        
        \Log::info('Function result:', $functionResult);
        
        // Step 4: Add function result to conversation
        $messages[] = [
            'role' => 'assistant',
            'content' => null,
            'tool_calls' => $response['tool_calls'],
        ];
        
        $messages[] = [
            'role' => 'tool',
            'tool_call_id' => $toolCall['id'],
            'name' => $functionName,
            'content' => json_encode($functionResult),
        ];
        
        \Log::info('Messages with function result:', ['messages' => $messages]);
        
        // Step 5: Get final response from AI
        $finalResponse = $groq->chatWithTools($messages, $tools);
        
        return response()->json([
            'success' => true,
            'ai_decided_to_use' => $functionName,
            'function_arguments' => $arguments,
            'function_result' => $functionResult,
            'final_ai_response' => $finalResponse['content'],
            'total_tokens' => $response['tokens'] + $finalResponse['tokens'],
        ]);
    }
    
    // If no function call, just return the response
    return response()->json([
        'success' => true,
        'response' => $response['content'],
        'tool_calls' => 'none',
        'total_tokens' => $response['tokens'],
    ]);
});