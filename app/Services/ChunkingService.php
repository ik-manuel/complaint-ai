<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class ChunkingService
{
    /**
     * Target chunk size in characters.
     * 300 tokens = 1200 characters (avg 4 chars per token)
     */
    private int $chunkSize;

    /**
     * Overlap between consecutive chunks in characters.
     * 50 tokens = 200 characters
     */
    private int $overlap;

    public function __construct(int $chunkSize = 1200, int $overlap = 200)
    {
        $this->chunkSize = $chunkSize;
        $this->overlap = $overlap;
    }

    /**
     * Split text into overlapping chunks at paragraph boundaries
     * 
     * Strategy: paragraph-aware chunking
     * 1. Split document into paragraphs
     * 2. Accumulate paragraphs untill chunk size is reached
     * 3. Start next chunk with overlap from previous chunk
     * 
     * @param string $text      Full document text
     * @param string $source    Source identifier for logging
     * @return array            Array of chunk strings
     */
    public function chunk(string $text, string $source = 'document'): array
    {
        // Normalize whitespace and line endings
        $text = $this->normalizeText($text);

        if (empty($text)) {
            Log::warning('ChunkingService: empty text provided', [
                'source' => $source,
            ]);
            return [];
        }

        // Split into paragraphs (double newline = paragraph boundary)
        $paragraphs = preg_split('/\n\s*\n/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $paragraphs = array_filter(
            array_map('trim', $paragraphs),
            fn($p) => strlen($p) >= 50 // discard very short paragraphs (headers, page numbers)
        );
        $paragraphs = array_values($paragraphs);

        if (empty($paragraphs)) {
            Log::warning('ChunkingService: no usable paragraphs found', [
                'source'      => $source,
                'text_length' => strlen($text),
            ]);
            return [];
        }

        $chunks = [];
        $currentChunk = '';
        $overlapBuffer = ''; //stores tail of previous chunk for overlap

        foreach ($paragraphs as $paragraph) {
            $proposedChunk = $currentChunk
                ? $currentChunk . "\n\n" . $paragraph
                : $paragraph;

            if (strlen($proposedChunk) <= $this->chunkSize) {
                // Paragraph fits - add to current chunk
                $currentChunk = $proposedChunk;
            } else {
                // Current chunk is full - save it
                if ($currentChunk) {
                    $chunks[] = $currentChunk;
                    $overlapBuffer = $this->extractOverlap($currentChunk);
                }

                // Start new chunk with overlap from previous + current paragrap
                $currentChunk = $overlapBuffer
                    ? $overlapBuffer . "\n\n" . $paragraph
                    : $paragraph;

                // Handle edge case: single paragraph exceeds chunk size
                if (strlen($currentChunk) > $this->chunkSize * 1.5) {
                    $subChunks = $this->splitLargeParagraph($currentChunk);
                    $lastSubChunk = array_pop($subChunks);

                    foreach ($subChunks as $subChunk) {
                        $chunks[] = $subChunk;
                    }

                    $currentChunk = $lastSubChunk;
                    $overlapBuffer = $this->extractOverlap($lastSubChunk);
                }
            }
        }

        // Save the final chunk
        if (trim($currentChunk)) {
            $chunks[] = $currentChunk;
        }

        Log::info('ChunkingService: chunking completed', [
            'source'          => $source,
            'paragraphs'      => count($paragraphs),
            'chunks_produced' => count($chunks),
            'avg_chunk_size'  => count($chunks)
                ? round(array_sum(array_map('strlen', $chunks)) / count($chunks))
                : 0,
        ]);

        return $chunks;
    }

    /**
     * Estimate token count for a string.
     * Rule of thumb: 1 token = 4 characters for English text.
     */
    public function estimateTokens(string $text): int 
    {
        return (int) ceil(strlen($text) / 4);
    }

    /**
     * Extract the tail of a chunk for use as overlap in the next chunk.
     */
    private function extractOverlap(string $chunk): string 
    {
        if (strlen($chunk) <= $this->overlap) {
            return $chunk;
        }

        // Take the last N characters, but start at a sentence boundary
        $tail = substr($chunk, -$this->overlap);

        // Find the first sentence boundary in the tail
        $sentenceStart = strpos($tail, '. ');

        if ($sentenceStart !== false && $sentenceStart < strlen($tail) - 50) {
            return substr($tail, $sentenceStart + 2);
        }

        return $tail;
    }

    /**
     * Split a paragrap that exceeds chunk size into smaller places.
     * Falls back to sentence splitting.
     */
    private function splitLargeParagraph(string $paragraph): array
    {
        // Split on sentence boundaries
        $sentences = preg_split('/(?<=[.!?])\s+/', $paragraph, -1, PREG_SPLIT_NO_EMPTY);

        $chunks = [];
        $currentChunk = '';

        foreach ($sentences as $sentence) {
            $proposed = $currentChunk ? $currentChunk . ' ' . $sentence : $sentence;

            if (strlen($proposed) <= $this->chunkSize) {
                $currentChunk = $proposed;
            } else {
                if ($currentChunk) {
                    $chunks[] = $currentChunk;
                }
                $currentChunk = $sentence;
            }
        }

        if (trim($currentChunk)) {
            $chunks[] = $currentChunk;
        }

        return $chunks ?: [$paragraph];
    }

    /**
     * Normalize text extracted from PDF.
     * PDFs often have inconsistent whitespace, ligatures, and encoding artifacts.
     */
    private function normalizeText(string $text): string 
    {
        // Normalize line endings
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // Replace common PDF ligatures
        $text = str_replace(['ﬁ', 'ﬂ', 'ﬀ', 'ﬃ', 'ﬄ'], ['fi', 'fl', 'ff', 'ffi', 'ffl'], $text);

        // Collapse multiple spaces into one
        $text = preg_replace('/ {2,}/', ' ', $text);

        // Collapse more than 2 consecutive newlines into 2
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        // Remove lines that are just page numbers (e.g. "- 12 -" or just "12")
        $text = preg_replace('/^\s*[\-–]\s*\d+\s*[\-–]\s*$/m', '', $text);
        $text = preg_replace('/^\s*\d+\s*$/m', '', $text);

        return trim($text);
    }

}
