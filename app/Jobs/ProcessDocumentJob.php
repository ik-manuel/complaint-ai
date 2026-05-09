<?php

namespace App\Jobs;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Services\ChunkingService;
use App\Services\EmbeddingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser as PdfParser;

class ProcessDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times the job may be attempted before failing.
     * Embedding a large document can occasionally time out —
     * we allow 2 retries before giving up.
     */
    public int $tries = 3;

    /**
     * Maximum seconds this job may run before being killed.
     * 20-page PDF × ~3s per chunk × avg 20 chunks = ~60s.
     * We allow 5 minutes to be safe for large documents.
     */
    public int $timeout = 300;

    /**
     * Wait this many seconds before retrying after a failure.
     * Gives Ollama time to recover if it was temporarily unavailable.
     */
    public int $backoff = 30;

    public function __construct(
        private readonly int $documentId
    ) {}

    /**
     * Execute the job.
     * This runs in the background worker process, completely
     * independent of the HTTP request that dispatched it.
     */
    public function handle(
        ChunkingService  $chunker,
        EmbeddingService $embedder
    ): void {
        $document = Document::findOrFail($this->documentId);

        if (!$document) {
            Log::warning('ProcessDocumentJob: document not found', [
                'document_id' => $this->documentId,
            ]);
            return;
        }

        // Guard: don't reprocess an already completed document
        if ($document->status === DocumentStatus::Completed) {
            Log::info('ProcessDocumentJob: document already processed, skipping', [
                'document_id' => $this->documentId,
            ]);
            return;
        }

        Log::info('ProcessDocumentJob: starting', [
            'document_id' => $this->documentId,
            'title'       => $document->title,
        ]);

        try {
            $document->update(['status' => DocumentStatus::Processing]);

            // Step 1: Extract text from PDF
            [$fullText, $pageCount] = $this->extractText($document);

            Log::info('ProcessDocumentJob: text extracted', [
                'document_id' => $this->documentId,
                'pages'       => $pageCount,
                'text_length' => strlen($fullText),
            ]);

            // Step 2: Chunk the text
            $chunks = $chunker->chunk($fullText, $document->title);

            if (empty($chunks)) {
                throw new \RuntimeException(
                    'No usable text could be extracted from this document.'
                );
            }

            Log::info('ProcessDocumentJob: chunking complete', [
                'document_id' => $this->documentId,
                'chunks'      => count($chunks),
            ]);

            // Step 3: Embed and store each chunk
            // Delete any partial chunks from a previous failed attempt
            DB::table('document_chunks')
                ->where('document_id', $document->id)
                ->delete();

            DB::transaction(function () use ($document, $chunks, $embedder, $chunker) {
                foreach ($chunks as $index => $chunkText) {
                    $embedding       = $embedder->embed($chunkText);
                    $embeddingString = $embedder->formatForStorage($embedding);
                    $tokenCount      = $chunker->estimateTokens($chunkText);

                    $chunkId = DB::table('document_chunks')->insertGetId([
                        'document_id' => $document->id,
                        'chunk_index' => $index,
                        'content'     => $chunkText,
                        'token_count' => $tokenCount,
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]);

                    DB::statement(
                        "UPDATE document_chunks SET embedding = ?::vector WHERE id = ?",
                        [$embeddingString, $chunkId]
                    );

                    Log::info('ProcessDocumentJob: chunk embedded', [
                        'document_id' => $this->documentId,
                        'chunk'       => $index + 1,
                        'total'       => count($chunks),
                    ]);
                }
            });

            // Step 4: Mark complete
            $document->update([
                'status'       => DocumentStatus::Completed,
                'total_chunks' => count($chunks),
                'total_pages'  => $pageCount,
            ]);

            Log::info('ProcessDocumentJob: completed', [
                'document_id'  => $this->documentId,
                'total_chunks' => count($chunks),
            ]);

        } catch (\Exception $e) {
            $document->update([
                'status'        => DocumentStatus::Failed,
                'error_message' => $e->getMessage(),
            ]);

            Log::error('ProcessDocumentJob: failed', [
                'document_id' => $this->documentId,
                'error'       => $e->getMessage(),
                'attempt'     => $this->attempts(),
            ]);

            // Re-throw so Laravel knows the job failed
            // and can retry or move to failed_jobs table
            throw $e;
        }
    }

    /**
     * Handle a job that has exhausted all retry attempts.
     * Called by Laravel after $tries attempts all fail.
     */
    public function failed(\Throwable $exception): void
    {
        $document = Document::findOrFail($this->documentId);

        if ($document) {
            $document->update([
                'status'        => DocumentStatus::Failed,
                'error_message' => 'Processing failed after ' . $this->tries .
                                   ' attempts: ' . $exception->getMessage(),
            ]);
        }

        Log::error('ProcessDocumentJob: permanently failed', [
            'document_id' => $this->documentId,
            'error'       => $exception->getMessage(),
        ]);
    }

    /**
     * Extract text from the stored PDF file.
     * Returns [fullText, pageCount].
     */
    private function extractText(Document $document): array
    {
        $path   = Storage::path('documents/' . $document->filename);
        $parser = new PdfParser();
        $pdf    = $parser->parseFile($path);
        $pages  = $pdf->getPages();

        $fullText  = '';
        $pageCount = count($pages);

        foreach ($pages as $page) {
            $pageText = $page->getText();
            if (trim($pageText)) {
                $fullText .= "\n\n" . $pageText;
            }
        }

        return [trim($fullText), $pageCount];
    }
}