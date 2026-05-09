@extends('layouts.app')

@section('title', $document->title  . ' - ComplaintAI')

@section('content')
    <div class="mb-4 flex justify-end">
        <a href="{{ route('documents.index') }}"
           class="text-sm text-blue-600 hover:underline">
             ← Back to Documents
        </a>
    </div>
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-2xl font-bold text-gray-800">{{ $document->title }}</h2>
        <a href="{{ route('documents.ask', $document) }}"
           class="px-4 py-2 bg-blue-600 text-white text-sm font-medium
                rounded-lg hover:bg-blue-700 transition-colors">
             💬 Ask Questions
        </a> 
    </div>

    {{-- Document Stats --}}
    <div class="grid grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <div id="page-count" class="text-2xl font-bold text-blue-600">{{ $document->total_pages }}</div>
            <div class="text-xs text-gray-500 mt-1">Pages</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <div id="chunk-count" class="text-2xl font-bold text-purple-600">{{ $document->total_chunks }}</div>
            <div class="text-xs text-gray-500 mt-1">Chunks</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <div class="text-2xl font-bold text-green-600">
                {{ number_format($document->file_size / 1024, 1) }}KB
            </div>
            <div class="text-xs text-gray-500 mt-1">File Size</div>
        </div>
        <div id="status-card" class="bg-white rounded-lg shadow p-4 text-center">
            <span id="status-badge" class="px-3 py-1 rounded-full text-sm font-medium
                {{ $document->status === \App\Enums\DocumentStatus::Completed
                    ? 'bg-green-100 text-green-700'
                    : ($document->status === \App\Enums\DocumentStatus::Failed
                        ? 'bg-red-100 text-red-700'
                        : 'bg-yellow-100 text-yellow-700') }}">
                {{ $document->status->value }}
            </span>
            <div class="text-xs text-gray-500 mt-1">Status</div>
        </div>
    </div>

    @if($document->status === \App\Enums\DocumentStatus::Failed)
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
            <p class="text-sm text-red-700">
                <strong>Error:</strong> {{ $document->error_message }}
            </p>
        </div>
    @endif

    {{-- Chunks --}}
    <div id="chunks-list">
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
    </div>

@endsection


@pushIf(in_array($document->status->value, ['pending', 'processing']), 'scripts')
<script>
(function () {
    const statusUrl  = '{{ route("documents.status", $document) }}';
    const statusBadge = document.getElementById('status-badge');
    const chunkCount  = document.getElementById('chunk-count');
    const pageCount   = document.getElementById('page-count');
    const chunksList  = document.getElementById('chunks-list');

    let pollInterval = null;

    function poll() {
        fetch(statusUrl, {
            headers: { 'Accept': 'application/json' }
        })
        .then(r => r.json())
        .then(function (data) {

            // Update status badge with correct styling
            if (statusBadge) {
                statusBadge.textContent = data.status;
                statusBadge.className   = badgeClass(data.status);
            }

            // Always update the stats
            if (chunkCount) chunkCount.textContent = data.total_chunks;
            if (pageCount)  pageCount.textContent  = data.total_pages;

            if (data.status === 'completed') {
                clearInterval(pollInterval);

                // Reload the chunks section without full page reload
                fetch(window.location.href)
                    .then(r => r.text())
                    .then(function (html) {
                        const parser   = new DOMParser();
                        const newDoc   = parser.parseFromString(html, 'text/html');
                        const newChunks = newDoc.getElementById('chunks-list');

                        if (chunksList && newChunks) {
                            chunksList.innerHTML = newChunks.innerHTML;
                        }
                    });
            }

            if (data.status === 'failed') {
                clearInterval(pollInterval);

                // Show error message in a div if it exists
                const errorDiv = document.querySelector('.bg-red-50');
                if (errorDiv && data.error_message) {
                    errorDiv.textContent = data.error_message;
                }
            }
        })
        .catch(function (err) {
            console.error('Status poll failed:', err);
        });
    }

    function badgeClass(status) {
        const base = 'px-2 py-1 rounded-full text-xs font-medium';
        const map  = {
            pending:    base + ' bg-yellow-100 text-yellow-700',
            processing: base + ' bg-blue-100 text-blue-700',
            completed:  base + ' bg-green-100 text-green-700',
            failed:     base + ' bg-red-100 text-red-700',
        };
        return map[status] || base;
    }

    // Poll every 3 seconds
    pollInterval = setInterval(poll, 3000);

    // Initial poll immediately
    poll();
})();
</script>
@endpushIf
