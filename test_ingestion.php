// test_ingestion.php
<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== DOCUMENT INGESTION DIAGNOSTICS ===\n\n";

// Stage 1: Document record
$document = \App\Models\Document::latest()->first();

if (!$document) {
    echo "❌ No documents found in DB\n";
    exit(1);
}

echo "✅ Document record:\n";
echo "   Title:        {$document->title}\n";
echo "   Status:       {$document->status->value}\n";
echo "   Pages:        {$document->total_pages}\n";
echo "   Total chunks: {$document->total_chunks}\n";
echo "   File size:    " . number_format($document->file_size / 1024, 1) . "KB\n\n";

if ($document->status === 'failed') {
    echo "❌ Ingestion failed: {$document->error_message}\n";
    exit(1);
}

// Stage 2: Chunks stored
$chunks = \App\Models\DocumentChunk::where('document_id', $document->id)
    ->orderBy('chunk_index')
    ->get();

echo "✅ Chunks in DB: {$chunks->count()}\n\n";

if ($chunks->isEmpty()) {
    echo "❌ No chunks found\n";
    exit(1);
}

// Stage 3: Chunk quality check
echo "=== CHUNK QUALITY CHECK ===\n\n";

$tokenCounts = $chunks->pluck('token_count');
echo "Token count stats:\n";
echo "   Min:     " . $tokenCounts->min() . "\n";
echo "   Max:     " . $tokenCounts->max() . "\n";
echo "   Average: " . round($tokenCounts->avg()) . "\n\n";

// Show first 3 chunks
echo "First 3 chunks:\n";
foreach ($chunks->take(3) as $chunk) {
    echo "---\n";
    echo "Chunk #{$chunk->chunk_index} ({$chunk->token_count} tokens):\n";
    echo substr($chunk->content, 0, 200) . "...\n\n";
}

// Stage 4: Embeddings stored
$withEmbeddings = \DB::select("
    SELECT COUNT(*) as count
    FROM document_chunks
    WHERE document_id = ?
      AND embedding IS NOT NULL
", [$document->id]);

$embeddingCount = $withEmbeddings[0]->count;
echo "✅ Chunks with embeddings: {$embeddingCount} / {$chunks->count()}\n\n";

// Stage 5: Similarity search on chunks
echo "=== CHUNK SIMILARITY SEARCH TEST ===\n\n";

$embeddingService = app(\App\Services\EmbeddingService::class);

// Use first chunk's content as query
$testQuery = substr($chunks->first()->content, 0, 100);
echo "Query (first 100 chars of chunk 1):\n\"{$testQuery}\"\n\n";

$queryEmbedding  = $embeddingService->embed($testQuery);
$embeddingString = $embeddingService->formatForStorage($queryEmbedding);

$similar = \DB::select("
    SELECT
        dc.chunk_index,
        dc.token_count,
        LEFT(dc.content, 120) AS content_preview,
        (1 - (dc.embedding <=> ?::vector)) AS similarity_score
    FROM document_chunks dc
    WHERE dc.document_id = ?
      AND dc.embedding IS NOT NULL
    ORDER BY dc.embedding <=> ?::vector ASC
    LIMIT 5
", [$embeddingString, $document->id, $embeddingString]);

echo "Most similar chunks to query:\n";
foreach ($similar as $result) {
    $score = number_format($result->similarity_score, 4);
    echo "  Chunk #{$result->chunk_index} [{$score}]: {$result->content_preview}...\n\n";
}

echo "=== DONE ===\n";