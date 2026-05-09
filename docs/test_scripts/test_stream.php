<?php
// test_stream.php — place in project root, run once, then delete

require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== STREAMING TEST ===\n\n";

// Get first conversation with its complaint
$conversation = \App\Models\Conversation::with('complaint.customer')->first();

if (!$conversation) {
    echo "No conversations found. Submit a complaint first.\n";
    exit(1);
}

echo "Testing conversation: {$conversation->id}\n";
echo "Complaint: {$conversation->complaint->ticket_number}\n";
echo "Customer:  {$conversation->complaint->customer->name}\n\n";

$conversationService = app(\App\Services\ConversationService::class);

// Test 1: Simple message (no tools, no RAG)
echo "--- Test 1: General message ---\n";
echo "Streaming tokens: ";

$result = $conversationService->streamResponse(
    $conversation,
    "Thank you for your help!",
    function (string $token) {
        echo $token;
        flush();
    }
);

echo "\n\nDone. Tokens: {$result['tokens']}\n";
echo "RAG used:   " . ($result['rag_used'] ? 'yes' : 'no') . "\n";
echo "Tools used: " . implode(', ', $result['tools_used'] ?: ['none']) . "\n\n";

// Test 2: Tool question
echo "--- Test 2: Tool question ---\n";
echo "Streaming tokens: ";

$result = $conversationService->streamResponse(
    $conversation,
    "What is the status of my complaint?",
    function (string $token) {
        echo $token;
        flush();
    }
);

echo "\n\nDone. Tokens: {$result['tokens']}\n";
echo "Tools used: " . implode(', ', $result['tools_used'] ?: ['none']) . "\n\n";

// Test 3: RAG question
echo "--- Test 3: Policy question (RAG) ---\n";
echo "Streaming tokens: ";

$result = $conversationService->streamResponse(
    $conversation,
    "What are your cookie policies?",
    function (string $token) {
        echo $token;
        flush();
    }
);

echo "\n\nDone. Tokens: {$result['tokens']}\n";
echo "RAG used: " . ($result['rag_used'] ? 'yes' : 'no') . "\n\n";

echo "=== ALL TESTS COMPLETE ===\n";