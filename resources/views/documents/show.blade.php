@extends('layouts.app')

@section('title', $document->title  . ' - ComplaintAI')

@section('content')

    <div class="flex items-center justify-between mb-6">
        <h2 class="text-2xl font-bold text-gray-800">{{ $document->title }}</h2>
        <a href="{{ route('documents.index') }}"
           class="text-sm text-blue-600 hover:underline">
            ← Back to Documents
        </a>
    </div>

    {{-- Document Stats --}}
    <div class="grid grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <div class="text-2xl font-bold text-blue-600">{{ $document->total_pages }}</div>
            <div class="text-xs text-gray-500 mt-1">Pages</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <div class="text-2xl font-bold text-purple-600">{{ $document->total_chunks }}</div>
            <div class="text-xs text-gray-500 mt-1">Chunks</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <div class="text-2xl font-bold text-green-600">
                {{ number_format($document->file_size / 1024, 1) }}KB
            </div>
            <div class="text-xs text-gray-500 mt-1">File Size</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <span class="px-3 py-1 rounded-full text-sm font-medium
                {{ $document->status === \App\Enums\DocumentStatus::Completed
                    ? 'bg-green-100 text-green-700'
                    : ($document->status === \App\Enums\DocumentStatus::Failed
                        ? 'bg-red-100 text-red-700'
                        : 'bg-yellow-100 text-yellow-700') }}">
                {{ $document->status }}
            </span>
            <div class="text-xs text-gray-500 mt-1">Status</div>
        </div>
    </div>

    @if($document->status === 'failed')
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
            <p class="text-sm text-red-700">
                <strong>Error:</strong> {{ $document->error_message }}
            </p>
        </div>
    @endif

    {{-- Chunks --}}
    @if($chunks->isNotEmpty())
        <h3 class="text-lg font-semibold text-gray-800 mb-3">
            Chunks ({{ $chunks->count() }})
        </h3>

        <div class="space-y-3">
            @foreach($chunks as $chunk)
                <div class="bg-white rounded-lg shadow p-4 border-l-4 border-purple-400">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-medium text-purple-600">
                            Chunk #{{ $chunk->chunk_index + 1 }}
                        </span>
                        <span class="text-xs text-gray-400">
                            ~{{ $chunk->token_count }} tokens
                        </span>
                    </div>
                    <p class="text-sm text-gray-700 leading-relaxed">
                        {{ Str::limit($chunk->content, 300) }}
                    </p>
                </div>
            @endforeach
        </div>
    @endif

@endsection