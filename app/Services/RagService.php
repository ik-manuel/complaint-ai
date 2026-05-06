<?php

namespace App\Services;

use App\Models\Document;
use App\Services\EmbeddingService;
use App\Services\GroqService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class RagService
{
    /**
     * Maximum chunks to inject into prompt.
     * More chunks = more context but higher token cost.
     * 3-5 is the production sweet spot.
     */
    private int $maxChunks = 4;

    /**
     * Minimum similarity score for a chunk to be inlcuded
     * Below this, chunks add noise rather than signal
     */
    private float $similarityThreshold = 0.55;

    /**
     * Create a new class instance.
     */
    public function __construct(
        private EmbeddingService $embeddingService,
        private GroqService $groqService,
        private RagCacheService $cacheService,
    ) { }

    /**
     * Use cache for document-wide questions (no specific document filter)
     * Per-document questions are not cached as content differs
     */
    public function answer(string $question, ?int $documentId = null): array
    {
        if ($documentId === null) {
            return $this->cacheService->remember(
                $question,
                fn() => $this->generateAnswer($question, $documentId)
            );
        }

        return $this->generateAnswer($question, $documentId);
    }

    /**
     * Core RAG method: answer a question using retrieved document chunks.
     * answer generation - extracted from answer() for cache wrapping.
     * 
     * @param string   $question      User's question
     * @param int|null $documentId    Limit retrieval to specific document (null = all)
     * @return array {
     *   answer: string,
     *   chunks_used: int,
     *   sources: array,
     *   tokens: int,
     *   grounded: bool
     * }
     */
    public function generateAnswer(string $question, ?int $documentId): array
    {
        Log::info('RagService: answering question', [
            'question'    => $question,
            'document_id' => $documentId,
        ]);

        // Step 1: Retrieve relavant chunks
        $chunks = $this->embeddingService->findRelevantChunks(
            searchQuery: $question,
            documentId:  $documentId,
            limit:       $this->maxChunks,
            threshold:   $this->similarityThreshold
        );

        Log::info('RagService: chunks retrieved', [
            'count' => $chunks->count(),
        ]);

        // Step 2: Handle case where no relevant chunks found
        if ($chunks->isEmpty()) {
            Log::info('RagService: no document or relevant chunks found', [
                'question' => $question,
            ]);

            // Check if the problem is no documents at all
            $documentCount = Document::where('status', 'completed')->count();

            $message = $documentCount === 0
                ? "Our policy documents have not been uploaded yet. Please contact our support team directly for assistance with your question."
                : "I could not find specific information about that in our current policy documents. Please contact our support team directly for assistance.";

            return [
                'answer'      => $message,
                'chunks_used' => 0,
                'sources'     => [],
                'tokens'      => 0,
                'grounded'    => false,
            ];
        }

        // Step 3: Build context from retrieved chunks
        $context = $this->buildContext($chunks);

        // Step 4: Build system prompt string
        $systemPrompt = $this->buildPrompt($context);

        // Step 5: Generate answer from LLM
        $response = $this->groqService->chat($question, [
            'system'          => $systemPrompt,
            'temperature'     => 0.1, // Low temperature = more faithfull to source
            'max_tokens'      => 600,
            'operation'       => 'rag_answer',
            'complaint_id'    => $complaint_id    ?? null,
            'conversation_id' => $conversation_id ?? null,
            'metadata'        => ['chunks_used' => $chunks->count()],
        ]);

        // Step 6: Build source references for transparency
        $sources = $chunks->map(fn($chunk) => [
            'document_title' => $chunk->document_title,
            'chunk_index'    => $chunk->chunk_index,
            'similarity'     => round($chunk->similarity_score, 4),
        ])->toArray();

        Log::info('RagService: answer generated', [
            'question'    => $question,
            'chunks_used' => $chunks->count(),
            'tokens'      => $response['tokens'],
            'grounded'    => true,
        ]);

        return [
            'answer'      => $response['content'],
            'chunks_used' => $chunks->count(),
            'sources'     => $sources,
            'tokens'      => $response['tokens'],
            'grounded'    => $chunks->isNotEmpty() &&
                            !str_contains(
                                $response['content'],
                                "I don't have specific information"
                            ),
        ];
    }

    /**
     * Build a numbered context string fromretrieved chunks.
     * Each chunk is clearly delineated so the LLM can reference them.
     */
    private function buildContext(Collection $chunks): string
    {
        $context = '';

        foreach ($chunks as $index => $chunk) {
            $number   = $index + 1;
            $context .= "[Section {$number} - {$chunk->document_title}]\n";
            $context .= trim($chunk->content);
            $context .= "\n\n";
        }

        return trim($context);
    }

    /**
     * Build the full system prompt for the RAG.
     * 
     * The system prompt is the most important part of RAG -
     * It constrains the LLM to answer ONLY from the provided context.
     */
    private function buildPrompt(string $context): string
    {
        return <<<PROMPT
            You are a helpful customer service assistant for a company.
            You answer questions STRICTLY based on the policy documents provided below.

            STRICT RULES:
            1. Answer ONLY using information from the provided policy sections.
            2. If the answer is not in the provided sections, say:
            "I don't have specific information about that in our current policy documents."
            3. Do NOT use your general knowledge to fill gaps.
            4. Do NOT make up or assume policy details not explicitly stated.
            5. Keep answers concise and professional.
            6. If quoting directly, indicate which section it comes from.

            POLICY DOCUMENT SECTIONS:
            {$context}
            PROMPT;
            
    }
}
