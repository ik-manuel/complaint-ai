<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use App\Models\Complaint;

class EmbeddingService
{
    private string $ollamaUrl;
    private string $model;

    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        $this->ollamaUrl = config('services.ollama.url');
        $this->model     = config('services.ollama.embedding_model');
    }

    /**
     * Convert text into a 768-dimension embedding vector
     */
    public function embed(string $text): array
    {
        // Validate text input
        if (empty($text) || strlen($text) > 10000) { // Arbitrary limit
            throw new \InvalidArgumentException('Text must be non-empty and under 10,000 characters.');
        }

        try {
            $response = Http::timeout(30)
                ->post("{$this->ollamaUrl}/api/embeddings", [
                    'model'  => $this->model,
                    'prompt' => $text,
                ]);
            
            if (!$response->successful()) {
                throw new \Exception('Ollama error: ' . $response->body());
            }

            // Validate the response structure
            $data = $response->json();
            if (!isset($data['embedding']) || !is_array($data['embedding'])) {
                throw new \Exception('Invalid embedding response from Ollama: ' . $response->body());
            }
            
            $embedding = $data['embedding'];

            Log::info('Embedding generated', [
                'dimensions'   => count($embedding),
            ]);

            return $embedding;

        } catch (\ConnectionException $e) {
            // This fires when Ollama/Model is not running
            throw new \RuntimeException(
                'Could not connect to Ollama\Model. Is it running? Error: ' . $e->getMessage()
            );
        }
    }

    /**
     * Calculate cosine similarity between two vectors
     */
    public function cosineSimilarity(array $vectorA, array $vectorB): float 
    {
        if (count($vectorA) !== count($vectorB)) {
            throw new \InvalidArgumentException('Vectors must have the same dimensions for similarity calculation.');
        }

        $dotProduct = 0;
        $magnitudeA = 0;
        $magnitudeB = 0;

        foreach ($vectorA as $i => $valueA) {
            $dotProduct += $valueA * $vectorB[$i];
            $magnitudeA += $valueA * $valueA;
            $magnitudeB += $vectorB[$i] * $vectorB[$i];
        }

        if ($magnitudeA == 0 || $magnitudeB == 0) return 0.0;

        return $dotProduct / (sqrt($magnitudeA) * sqrt($magnitudeB));
    }

    /**
     * Format PHP array → PostgreSQL vector string
     * PHP:  [0.234, -0.891, 0.445]
     * PG:   '[0.234,-0.891,0.445]'
     */
    public function formatForStorage(array $embedding): string 
    {
        return '[' . implode(',', $embedding) . ']';
    }

    /**
     * Parse PostgreSQL vector string → PHP array
     * PG:   '[0.234,-0.891,0.445]'
     * PHP:  [0.234, -0.891, 0.445]
     */
    public function parseFromStorage(string $stored): array 
    {
        $cleaned = trim($stored, '[]');
        return array_map('floatval', explode(',', $cleaned));
    }

    /**
     * Build text to embed from a complaint
     * Combining subject + message gives richer semantic representation
     * than embedding either field alone
     */
    public function buildComplaintText(Complaint $complaint): string
    {
        return $complaint->subject . '. ' . $complaint->message;
    }

    /**
     * Validate LIMIT to prevent SQL injection
     */
    private function limit(int $limit): int
    {
        return max(1, min((int)$limit, 100));
    }

    /**
     * Find the most similar complaints to a given complaint
     * Uses pgvector's <=> operator (cosine distance)
     * 
     * @param \App\Models\Complaint $complaint The complaint to compare against
     * @param int  $limit   How many similar complaint to return
     * @return Collection
     */
    public function findSimilarComplaints(Complaint $complaint, int $limit = 3): Collection
    {
        // Query the embedding directly from DB instead of relying on model cast
        $row = DB::selectOne(
            "SELECT embedding::text AS embedding_text FROM complaints WHERE id = ?",
            [$complaint->id]
        );

        // DIAGNOSTIC: Log exactly what we receive
        Log::info('EmbeddingService: findSimilarComplaints called', [
            'complaint_id'   => $complaint->id,
            'ticket_number'  => $complaint->ticket_number,
            'db_row_found'   => $row ? 'yes' : 'no',
            'has_embedding'  => ($row && !empty($row->embedding_text)) ? 'yes' : 'no',
        ]);

        // can't find similar if this complaint has no embedding yet
        if (!$row || empty($row->embedding_text) || $row->embedding_text === 'null') {
            Log::warning('findSimilarComplaints: no embedding in DB', [
                'complaint_id' => $complaint->id,
            ]);
            return collect();
        }

        // Validate limit
        $limit = $this->limit($limit);

        // 0.45 threshold based on nomic-embed-text calibration threshold 
        // Below this score, results are not meaningfully related
        $minimumSimilarity = 0.50;

        try {
            // Use subquery: pgvector <=> operator doesn't work well with JOIN on same table
            // So we calculate similarity first, then join with customers
            $query = "
                SELECT 
                    sub.id, 
                    sub.ticket_number,
                    sub.subject,
                    sub.status,
                    sub.urgency,
                    sub.category,
                    cu.name AS customer_name,
                    cu.email AS customer_email,
                    sub.similarity_score
                FROM (
                    SELECT 
                        c.id,
                        c.ticket_number,
                        c.subject,
                        c.status,
                        c.urgency,
                        c.category,
                        c.customer_id,
                        (1 - (c.embedding <=> ?::vector)) AS similarity_score
                    FROM complaints c
                    WHERE c.id != ?
                      AND c.embedding IS NOT NULL
                    ORDER BY c.embedding <=> ?::vector ASC
                    LIMIT {$limit}
                ) sub
                INNER JOIN customers cu ON cu.id = sub.customer_id
                WHERE sub.similarity_score >= {$minimumSimilarity}
                ORDER BY sub.similarity_score DESC
            ";
            $results = DB::select($query, [$row->embedding_text, $complaint->id, $row->embedding_text]);

            return collect($results);

        } catch (\Exception $e) {
            Log::error('EmbeddingService: findSimilarComplaints query failed', [
                'complaint_id' => $complaint->id,
                'error'        => $e->getMessage(),
            ]);

            return collect();
        }

    }

    /**
     * Search complaints by a raw text query
     * Used for admin semantic search feature
     * 
     * @param string $searchQuery Natural language search query
     * @param int $limit How many results to return
     * @return Collection
     */
    public function searchByText(string $searchQuery, int $limit = 5): Collection
    {
        // Convert search query to embedding
        $queryEmbedding  = $this->embed($searchQuery);
        $embeddingString = $this->formatForStorage($queryEmbedding);

        // Validate limit
        $limit = $this->limit($limit);

        // 0.45 threshold based on nomic-embed-text calibration threshold 
        $minimumSimilarity = 0.45;

        try {
            // Use subquery: pgvector <=> operator doesn't work well with JOIN on same table
            // So we calculate similarity first, then join with customers
            $query = "
                SELECT 
                    sub.id, 
                    sub.ticket_number,
                    sub.subject,
                    sub.status,
                    sub.urgency,
                    sub.category,
                    cu.name AS customer_name,
                    cu.email AS customer_email,
                    sub.similarity_score
                FROM (
                    SELECT 
                        c.id,
                        c.ticket_number,
                        c.subject,
                        c.status,
                        c.urgency,
                        c.category,
                        c.customer_id,
                        (1 - (c.embedding <=> ?::vector)) AS similarity_score
                    FROM complaints c
                    WHERE c.embedding IS NOT NULL
                    ORDER BY c.embedding <=> ?::vector ASC
                    LIMIT {$limit}
                ) sub
                INNER JOIN customers cu ON cu.id = sub.customer_id
                WHERE sub.similarity_score >= {$minimumSimilarity}
                ORDER BY sub.similarity_score DESC
            ";
            $results = DB::select($query, [$embeddingString, $embeddingString]);

            Log::info('EmbeddingService: searchByText completed', [
                'query'         => $searchQuery,
                'results_count' => count($results),
            ]);

            return collect($results);

        } catch (\Exception $e) {
            Log::error('EmbeddingService: searchByText query failed', [
                'query' => $searchQuery,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /** 
     * Find the most relevant document chunks for a given query.
     * This is the retrieval step in the RAG pipeline.
     * 
     * @param string    $searchQuery       Natural language question
     * @param int|null  $documentId  Limit search to specific document (null = all documents)
     * @param int       $limit       Number of chunks to retrieve
     * @param float     $threshold   Minimum similarity score
     * @return \Illuminate\Support\Collection
     */
    public function findRelevantChunks(
        string $searchQuery,
        ?int $documentId = null,
        int $limit = 5,
        float $threshold = 0.45
    ): Collection {

        $queryEmbedding = $this->embed($searchQuery);
        $embeddingString = $this->formatForStorage($queryEmbedding);

        // Validate limit
        $limit = $this->limit($limit);

        // Build document filter clause
        $documentFilter = $documentId ? "AND dc.document_id = {$documentId}" : '';

        try {
            $query = "
                SELECT
                    sub.id,
                    sub.document_id,
                    sub.chunk_index,
                    sub.content,
                    sub.token_count,
                    sub.similarity_score,
                    d.title AS document_title
                FROM (
                    SELECT
                        dc.id,
                        dc.document_id,
                        dc.chunk_index,
                        dc.content,
                        dc.token_count,
                        (1 - (dc.embedding <=> ?::vector)) AS similarity_score
                    FROM document_chunks dc
                    WHERE dc.embedding IS NOT NULL
                    {$documentFilter}
                    ORDER BY dc.embedding <=> ?::vector ASC
                    LIMIT {$limit}
                ) sub
                 INNER JOIN documents d ON d.id = sub.document_id
                 WHERE sub.similarity_score >= {$threshold}
                 ORDER BY sub.similarity_score DESC
            ";
            $results = DB::select($query, [$embeddingString, $embeddingString]);

            Log::info('EmbeddingService: findRelevantChunks completed', [
                'query'         => substr($searchQuery, 0, 60),
                'document_id'   => $documentId,
                'results_count' => count($results),
                'threshold'     => $threshold,
            ]);

            return collect($results);

        } catch (\Exception $e) {
            Log::error('EmbeddingService: findRelevantChunks failed', [
                'query' => $searchQuery,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }
}
