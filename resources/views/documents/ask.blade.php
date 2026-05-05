@extends('layouts.app')

@section('title', 'Ask - '. $document->title)

@section('content')

    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">
                Ask About: {{ $document->title }}
            </h2>
            <p class="text-sm text-gray-500 mt-1">
                {{ $document->total_chunks }} chunks · {{ $document->total_pages }} pages
            </p>
        </div>
        <a href="{{ route('documents.show', $document) }}"
           class="text-sm text-blue-600 hover:underline">
            ← Back to Document
        </a>
    </div>

    {{-- Question Form --}}
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <p class="text-sm text-gray-500 mb-4">
            Ask any question about this document. The AI will answer
            strictly from the document content — no guessing.
        </p>

        <form method="POST"
              action="{{ route('documents.ask', $document) }}">
            @csrf
            <div class="flex gap-3">
                <input
                    type="text"
                    name="question"
                    value="{{ $question }}"
                    placeholder="e.g. What are the cookie policies?"
                    autofocus
                    class="flex-1 rounded-lg border border-gray-300 px-4 py-2
                           text-sm focus:outline-none focus:ring-2
                           focus:ring-blue-500"
                />
                <button
                    type="submit"
                    class="px-6 py-2 bg-blue-600 text-white text-sm
                           font-medium rounded-lg hover:bg-blue-700
                           transition-colors">
                    Ask
                </button>
            </div>
        </form>
    </div>

    {{-- Answer --}}
    @if($result)
        <div class="bg-white rounded-lg shadow p-6 mb-6">

            {{-- Grounded indicator --}}
            <div class="flex items-center gap-2 mb-4">
                @if($result['grounded'])
                    <span class="inline-flex items-center gap-1 px-3 py-1
                                 bg-green-100 text-green-700 rounded-full text-xs font-medium">
                        ✅ Answered from document
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 px-3 py-1
                                 bg-yellow-100 text-yellow-700 rounded-full text-xs font-medium">
                        ⚠️ Not found in document
                    </span>
                @endif

                <span class="text-xs text-gray-400">
                    {{ $result['chunks_used'] }} chunk(s) reviewed
                    · {{ $result['tokens'] }} tokens
                </span>
            </div>

            {{-- Question --}}
            <div class="mb-3">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">
                    Question
                </p>
                <p class="text-sm font-medium text-gray-800">{{ $question }}</p>
            </div>

            {{-- Answer --}}
            <div class="mb-4">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">
                    Answer
                </p>
                <div class="text-sm text-gray-800 leading-relaxed bg-gray-50
                            rounded-lg p-4 border-l-4
                            {{ $result['grounded'] ? 'border-green-400' : 'border-yellow-400' }}">
                    {{ $result['answer'] }}
                </div>
            </div>

            {{-- Sources --}}
            @if(!empty($result['sources']))
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">
                        Sources Used
                    </p>
                    <div class="space-y-1">
                        @foreach($result['sources'] as $source)
                            <div class="flex items-center justify-between text-xs
                                        text-gray-500 bg-gray-50 px-3 py-1.5 rounded">
                                <span>
                                    {{ $source['document_title'] }}
                                    · Chunk #{{ $source['chunk_index'] }}
                                </span>
                                <span class="text-purple-600 font-medium">
                                    {{ number_format($source['similarity'] * 100, 1) }}% match
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @else
        {{-- Empty state --}}
        <div class="bg-white rounded-lg shadow p-12 text-center">
            <div class="text-4xl mb-4">💬</div>
            <p class="text-gray-500 text-sm">
                Ask a question above to get an answer from this document.
            </p>
        </div>
    @endif

@endsection