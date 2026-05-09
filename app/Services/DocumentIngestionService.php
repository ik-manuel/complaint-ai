<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Jobs\ProcessDocumentJob;
use App\Models\Document;
use App\Services\RagCacheService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;


class DocumentIngestionService
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        private RagCacheService $ragCacheService,
    ) {}

    /**
     * Full ingestio pipeline:
     * Upload -> Extract text -> Chunk -> Embed -> Store
     * and dispatch a background job to handle chunking + embedding.
     *
     * Returns immediately — processing happens asynchronously.
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

        // Dispatch background job — returns instantly
        ProcessDocumentJob::dispatch($document->id);

        // Invalidate RAG cache - new document means new possible answers
        $this->ragCacheService->invalidate();

        return $document;

    }

    /**
     * Store uploaded file in the documents storage directory.
     */
    private function storeFile(UploadedFile $file): string 
    {
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

        // Invalidate cache - deleted document changes available answers
        $this->ragCacheService->invalidate();

        Log::info('DocumentIngestionService: document deleted', [
            'document_id' => $document->id,
            'title'       => $document->title,
        ]);
    }

}
