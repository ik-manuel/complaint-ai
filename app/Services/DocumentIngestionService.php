<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentChunk;
use App\Enums\DocumentStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser as PdfParser;


class DocumentIngestionService
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        private ChunkingService $chunker,
        private EmbeddingService $embedder
    ) {}

    /**
     * Full ingestio pipeline:
     * Upload -> Extract text -> Chunk -> Embed -> Store
     * 
     * @param UploadedFile $file Uploaded PDF file
     * @param string       $title Human-readable document title
     * @return Document      The created document record
     */
    public function ingest(UploadedFile $file, string $title): Document
    {
        // Step 1: Store the file
        $filename = $this->storeFile($file);

        // Step 2: Create document record (status: pending)
        $document = Document::create([
            'title'     => $title,
            'filename'  => $filename,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'status'    => DocumentStatus::Pending,
        ]);

        Log::info('DocumentIngestionService: starting ingestion', [
            'document_id' => $document->id,
            'title'       => $title,
            'file_size'   => $file->getSize(),
        ]);

        try {
            // Step 3: Extract text from PDF
            $document->update(['status' => DocumentStatus::Processing]);
            [$fullText, $pageCount] = $this->extractText($filename);

            Log::info('DocumentIngestionService: text extracted', [
                'document_id' => $document->id,
                'pages'       => $pageCount,
                'text_length' => strlen($fullText),
            ]);

            // Step 4: Split into chunks
            $chunks = $this->chunker->chunk($fullText, $title);

            if (empty($chunks)) {
                throw new \RuntimeException('No usable text could be extracted from this document.');
            }

            Log::info('DocumentIngestionService: chunking completed', [
                'document_id'  => $document->id,
                'chunks_count' => count($chunks),
            ]);

            //Step 5: Embed and store each chunk
            $this->storeChunks($document, $chunks);

            // Step 6: Mark as completed
            $document->update([
                'status'       => DocumentStatus::Completed,
                'total_chunks' => count($chunks),
                'total_pages'  => $pageCount,
            ]);

            Log::info('DocumentIngestionService: ingestion completed', [
                'document_id'  => $document->id,
                'total_chunks' => count($chunks),
            ]);

        } catch (\Exception $e) {
            $document->update([
                'status'        => DocumentStatus::Failed,
                'error_message' => $e->getMessage(),
            ]);

            Log::info('DocumentIngestion: ingestion failed', [
                'document_id' => $document->id,
                'error'       => $e->getMessage(),
            ]);

            throw $e;
        }

        return $document->fresh();
    }

    /**
     * Extract text from PDF, returning [fullText, pageCount].
     */
    private function extractText(string $filename): array
    {
        $path = Storage::path('documents/' . $filename);
        $parser = new PdfParser();
        $pdf = $parser->parseFile($path);
        $pages = $pdf->getPages();

        $fullText = '';
        $pageCount = count($pages);

        foreach ($pages as $pageNumber => $page) {
            $pageText = $page->getText();

            if (trim($pageText)) {
                // Add page marker to help with context
                $fullText .= "\n\n" . $pageText;
                // Analysis state the above code did not implement
                // What it comment stated "Add page marker..."
                // So below is the actual implementation 
                // $fullText .= "\n\n[Page " . ($pageNumber + 1) . "]\n\n" . $pageText;
            }
        }

        return [trim($fullText), $pageCount];
    }

    /**
     * Embed each chunk and store in document_chunk table.
     * Uses a transaction to ensure all-or-nothing storage.
     */
    private function storeChunks(Document $document, array $chunks): void 
    {
        DB::transaction(function() use ($document, $chunks) {
            foreach ($chunks as $index => $chunkText) {
                // Generate embedding for this chunk
                $embedding = $this->embedder->embed($chunkText);
                $embeddingString = $this->embedder->formatForStorage($embedding);
                $tokenCount = $this->chunker->estimateTokens($chunkText);

                // Insert chunk record
                $chunkId = DB::table('document_chunks')->insertGetId([
                    'document_id' => $document->id,
                    'chunk_index' => $index,
                    'content'     => $chunkText,
                    'token_count' => $tokenCount,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);

                // Store embedding in vector column
                DB::statement(
                    "UPDATE document_chunks SET embedding = ?::vector WHERE id = ?",
                    [$embeddingString, $chunkId]
                );

                Log::info('DocumentIngestionService: chunk stored', [
                    'document_id' => $document->id,
                    'chunk_index' => $index,
                    'token_count' => $tokenCount,
                ]);
            }
        });
    }

    /**
     * Store uploaded file in the documents storage directory.
     */
    private function storeFile(UploadedFile $file): string 
    {
        // FOR TEXT PURPOSE TRY USING BELOW 'Str::uuid()' IN PLACE OF 'uniqid()'
        // REMOVE ABOVE COMMENT AFTER DECIDING ON WHICH IS BETTER
        $filename = uniqid('doc_', true) . '.' . $file->getClientOriginalExtension();
        Storage::putFileAs('documents', $file, $filename);
        
        return $filename;
    }

    /**
     * Delete a document and all its chunks from storage and DB.
     * Cascade delete handle DB chunks via foreign key.
     */
    public function delete(Document $document): void 
    {
        Storage::delete('document/' . $document->filename);
        $document->delete();

        Log::info('DocumentIngestionService: document deleted', [
            'document_id' => $document->id,
            'title'       => $document->title,
        ]);
    }

}
