@extends('layouts.app')

@section('title', 'Upload Document - ComplaintAI')

@section('content')

    <div class="flex items-center justify-between mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Upload Policy Document</h2>
        <a href="{{ route('documents.index') }}"
           class="text-sm text-blue-600 hover:underline">
            ← Back to Documents
        </a>
    </div>

    <div class="max-w-2xl bg-white rounded-lg shadow p-8">

        <p class="text-sm text-gray-500 mb-6">
            Upload a PDF policy document. The system will extract text,
            split it into chunks, generate embeddings, and store everything
            ready for semantic search and RAG retrieval.
        </p>

        <form method="POST"
              action="{{ route('documents.store') }}"
              enctype="multipart/form-data">
            @csrf

            {{-- Title --}}
            <div class="mb-5">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Document Title
                </label>
                <input type="text"
                       name="title"
                       value="{{ old('title') }}"
                       placeholder="e.g. Refund Policy 2026"
                       class="w-full rounded-lg border border-gray-300 px-4 py-2
                              text-sm focus:outline-none focus:ring-2 focus:ring-blue-500
                              @error('title') border-red-400 @enderror" />
                @error('title')
                    <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- File --}}
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    PDF File (max 10MB)
                </label>
                <input type="file"
                       name="file"
                       accept=".pdf"
                       class="w-full text-sm text-gray-600 border border-gray-300
                              rounded-lg px-3 py-2 file:mr-4 file:py-1 file:px-3
                              file:rounded file:border-0 file:text-sm
                              file:bg-blue-50 file:text-blue-700
                              hover:file:bg-blue-100
                              @error('file') border-red-400 @enderror" />
                @error('file')
                    <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Warning --}}
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-6">
                <p class="text-xs text-yellow-700">
                    ⏱ Processing time depends on document size.
                    A 10-page document takes approximately 30-60 seconds
                    as each chunk is embedded locally via Ollama.
                </p>
            </div>

            <button type="submit"
                    class="w-full py-2 bg-blue-600 text-white text-sm font-medium
                           rounded-lg hover:bg-blue-700 transition-colors">
                Upload and Process Document
            </button>
        </form>
    </div>

@endsection