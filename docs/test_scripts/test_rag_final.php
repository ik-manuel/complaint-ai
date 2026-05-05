// test_rag_final.php
<?php

require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$ragService = app(\App\Services\RagService::class);

$tests = [
    // Should be grounded
    ['q' => 'What are the cookie policies?',           'expect_grounded' => true],
    ['q' => 'How is personal data collected?',         'expect_grounded' => true],
    ['q' => 'How do you treat data from minors?',      'expect_grounded' => true],

    // Should NOT be grounded (not in document)
    ['q' => 'What is the penalty for late payment?',   'expect_grounded' => false],
    ['q' => 'Who is the CEO?',                         'expect_grounded' => false],
    ['q' => 'What is your phone number?',              'expect_grounded' => false],
];

echo "=== FINAL RAG TEST SUITE ===\n\n";

$passed = 0;
$failed = 0;

foreach ($tests as $test) {
    $result = $ragService->answer($test['q']);
    $pass   = $result['grounded'] === $test['expect_grounded'];

    if ($pass) {
        $passed++;
        echo "✅ PASS | ";
    } else {
        $failed++;
        echo "❌ FAIL | ";
    }

    echo "Grounded: " . ($result['grounded'] ? 'YES' : 'NO');
    echo " (expected: " . ($test['expect_grounded'] ? 'YES' : 'NO') . ")";
    echo " | Chunks: {$result['chunks_used']}";
    echo " | Q: {$test['q']}\n";
}

echo "\n";
echo "Results: {$passed} passed, {$failed} failed\n";
echo "Score: " . round(($passed / count($tests)) * 100) . "%\n";