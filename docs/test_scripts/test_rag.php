// test_rag.php
<?php

require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== RAG SYSTEM TEST ===\n\n";

$ragService = app(\App\Services\RagService::class);

$questions = [
    "What are the cookie policies?",
    "How is personal data collected and used?",
    "What rights do users have over their data?",
    "What is the penalty for late payment?",  // Not in document
    "Who is the CEO of the company?",          // Not in document
    "What happens to personal data after account deletion?",
    "How can users contact the company?",
];

foreach ($questions as $question) {
    echo "Q: {$question}\n";
    echo str_repeat('-', 60) . "\n";

    $result = $ragService->answer($question);

    echo "Grounded: " . ($result['grounded'] ? 'YES' : 'NO') . "\n";
    echo "Chunks used: {$result['chunks_used']}\n";
    echo "Tokens: {$result['tokens']}\n";
    echo "Answer:\n{$result['answer']}\n";

    if (!empty($result['sources'])) {
        echo "\nSources:\n";
        foreach ($result['sources'] as $source) {
            echo "  [{$source['similarity']}] {$source['document_title']} ";
            echo "- Chunk #{$source['chunk_index']}\n";
        }
    }

    echo "\n" . str_repeat('=', 60) . "\n\n";
}