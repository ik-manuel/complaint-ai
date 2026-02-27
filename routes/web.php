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


// Test
Route::get('/force-summarize/{complaint}', function(\App\Models\Complaint $complaint) {
    $conversationService = app(\App\Services\ConversationService::class);
    
    if (!$complaint->conversation) {
        return response()->json(['error' => 'No conversation found']);
    }

    $messageCount = $complaint->conversation->messages()->count();
    
    // Force summarization
    $summary = $conversationService->summarizeConversation($complaint->conversation);
    
    return response()->json([
        'success' => true,
        'message_count' => $messageCount,
        'summary' => $summary,
        'conversation_id' => $complaint->conversation->id,
    ]);
});


// Test
Route::get('/test-summarization', function () {
    // Find a conversation or create one
    $conversation = \App\Models\Conversation::first();
    
    if (!$conversation) {
        return response()->json(['error' => 'No conversations found. Submit a complaint first.']);
    }

    // Add some test messages to trigger summarization
    $conversationService = app(\App\Services\ConversationService::class);
    
    $testMessages = [
        "The power button doesn't respond",
        "I tried holding it for 10 seconds",
        "Still nothing happens",
        "Should I try removing the battery?",
        "I removed the battery, still not working",
        "What about trying a different power adapter?",
        "Tried different adapter, no change",
        "Is the warranty still valid?",
        "When can I send it for repair?",
        "How long will repair take?",
    ];

    foreach ($testMessages as $msg) {
        $conversationService->addMessage($conversation, 'user', $msg);
        $conversationService->addMessage($conversation, 'assistant', "Let me help with that. " . $msg);
    }

    // Trigger summarization
    if ($conversationService->shouldSummarize($conversation)) {
        $summary = $conversationService->summarizeConversation($conversation);
        
        return response()->json([
            'success' => true,
            'message_count' => $conversation->messages()->count(),
            'summary' => $summary,
            'tokens_saved' => 'Sending 10 recent messages + summary instead of all ' . $conversation->messages()->count() . ' messages',
        ]);
    }

    return response()->json([
        'message' => 'Not enough messages for summarization yet',
        'current_count' => $conversation->messages()->count(),
    ]);
});