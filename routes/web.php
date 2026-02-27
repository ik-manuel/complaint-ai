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