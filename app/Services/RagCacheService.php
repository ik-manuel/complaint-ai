<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RagCacheService
{
    /**
     * Cache TTL in seconds.
     * Policy documents change infrequently - 24 hours is safe.
     */
    private int $ttl = 86400; // 24 hours

    /**
     * Get a cacheed RAG answer or generate and cache a new one.
     * 
     * @param   string      $question
     * @param   callable    $generator Called only on cache miss
     * @return array
     */
    public function remember(string $question, callable $generator): array
    {
        $cacheKey = $this->buildCacheKey($question);

        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            Log::info('RagCacheService: cache hit', [
                'question'  => substr($question, 0, 60),
                'cache_key' => $cacheKey,
            ]);

            // Mark as cached so caller knows no tokens were used
            $cached['from_cache'] = true;
            $cached['tokens']     = 0;
            return $cached;
        }

        log::info('RagCacheService: cache miss - generating', [
            'question' => substr($question, 0, 60),
        ]);

        // Generate fresh answer
        $result = $generator();

        // Only cache grounded answers - ungrounded ones may improve
        // as more documents are uploaded
        if ($result['grounded']) {
            Cache::put($cacheKey, $result, $this->ttl);

            Log::info('RagCacheService: answer cached', [
                'question' => substr($question, 0, 60),
                'ttl'      => $this->ttl,
            ]);
        }

        $result['from_cache'] = false;
        return $result;
    }

    /**
     * Invalidate all RAG cache entries.
     * Call this when a new document is uploaded or deleted.
     */
    public function invalidate(): void 
    {
        Cache::flush();

        Log::info('RagCacheService: cache invalidated - new document uploaded or deleted');
    }

    /**
     * Build a normalized cache key from a question.
     * Normalization ensures "What is your refund policy?"
     * and "what is your refund policy" hit the same cache entry.
     */
    private function buildCacheKey(string $question): string 
    {
        $normalized = strtolower(trim($question));
        $normalized = preg_replace('/[^a-z0-9\s]/', '', $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        return 'rag_answer_' . md5($normalized);
    }
}
