<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\DocumentIngestionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DocumentController extends Controller
{
    public function __construct(
        private DocumentIngestionService $ingestionService
    ) {}

    /**
     * List all uploaded documents.
     */
    public function index(): View
    {
        $documents = Document::latest()->get();

        return view('documents.index', compact('documents'));
    }

    /**
     * Show the upload form.
     */
    public function create(): View
    {
        return view('documents.create');
    }

    /**
     * Handle document upload and trigger ingestion pipeline.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'file'  => 'required|file|mimes:pdf|max:10240', // 10MB max
        ]);

        // Check for duplicate title
        $existing = Document::where('title', $validated['title'])
            ->where('status', 'completed')
            ->first();

        if ($existing) {
            return back()
                ->withInput()
                ->withErrors([
                    'title' => "A completed document with this title already exists. 
                                Delete the existing one first if you want to re-upload."
                ]);
        }

        try {
            $document = $this->ingestionService->ingest(
                $request->file('file'),
                $validated['title']
            );

            return redirect()
                ->route('documents.show', $document)
                ->with('success', "Document \"{$document->title}\" ingested successfully. {$document->total_chunks} chunks created.");
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['file' => 'Ingestion failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Show document details and its chunks.
     */
    public function show(Document $document): View
    {
        $chunks = $document->chunks()
            ->orderBy('chunk_index')
            ->get();

        return view('documents.show', compact('document', 'chunks'));
    }

    /**
     * Delete a document and its chunks.
     */
    public function destroy(Document $document): RedirectResponse
    {
        $title = $document->title;

        $this->ingestionService->delete($document);

        return redirect()
            ->route('documents.index')
            ->with('success', "Document \"{$title}\" deleted successfully.");
    }

}
