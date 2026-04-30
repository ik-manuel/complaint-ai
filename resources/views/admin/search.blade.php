@extends('layouts.app')

@section('title', 'Semantic Search - ComplaintAI')

@section('content')

    {{-- Page Header --}}
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-2xl font-bold text-gray-800">🔍 Semantic Complaint Search</h2>
        <a href="{{ route('admin.index') }}"
           class="text-sm text-blue-600 hover:underline">
            ← Back to Dashboard
        </a>
    </div>

    {{-- Search Form --}}
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <p class="text-sm text-gray-500 mb-4">
            Search complaints by meaning, not keywords. Try
            <em>"customer angry about delivery"</em> or
            <em>"payment issue at checkout"</em>.
        </p>

        <form method="GET" action="{{ route('admin.search') }}">
            <div class="flex gap-3">
                <input
                    type="text"
                    name="q"
                    value="{{ $query }}"
                    placeholder="Describe what you are looking for..."
                    autofocus
                    class="flex-1 rounded-lg border border-gray-300 px-4 py-2
                           text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
                <button
                    type="submit"
                    class="px-6 py-2 bg-blue-600 text-white text-sm font-medium
                           rounded-lg hover:bg-blue-700 transition-colors"
                >
                    Search
                </button>
            </div>
        </form>
    </div>

    {{-- Error State --}}
    @if($error)
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
            <p class="text-sm text-red-700">{{ $error }}</p>
        </div>
    @endif

    {{-- Results --}}
    @if($query !== '')

        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-sm font-medium text-gray-700">
                @if($results->isEmpty())
                    No results found for
                    <span class="font-semibold">"{{ $query }}"</span>
                @else
                    {{ $results->count() }} result(s) for
                    <span class="font-semibold">"{{ $query }}"</span>
                @endif
            </h3>
            <span class="text-xs text-gray-400">Ranked by semantic similarity</span>
        </div>

        @if($results->isNotEmpty())
            <div class="space-y-3">
                @foreach($results as $result)
                    <a href="{{ route('admin.show', $result->id) }}"
                       class="block bg-white rounded-lg shadow hover:shadow-md
                              transition-shadow p-5 border border-gray-100">

                        <div class="flex items-start justify-between gap-4">

                            {{-- Left: Complaint Info --}}
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-2 flex-wrap">
                                    <span class="text-xs font-mono text-gray-400">
                                        {{ $result->ticket_number }}
                                    </span>
                                    <span class="text-xs px-2 py-0.5 rounded-full font-medium
                                        {{ $result->urgency === 'high'
                                            ? 'bg-red-100 text-red-700'
                                            : ($result->urgency === 'medium'
                                                ? 'bg-yellow-100 text-yellow-700'
                                                : 'bg-green-100 text-green-700') }}">
                                        {{ $result->urgency }}
                                    </span>
                                    <span class="text-xs px-2 py-0.5 rounded-full
                                        {{ $result->status === 'new' ? 'bg-blue-100 text-blue-800' :
                                        ($result->status === 'resolved' ? 'bg-green-100 text-green-800' :
                                        ($result->status === 'responded' ? 'bg-yellow-100 text-yellow-800' :
                                        'bg-gray-100 text-gray-600')) }}">
                                        {{ $result->status }}
                                    </span>
                                    <span class="text-xs px-2 py-0.5 rounded-full
                                                 bg-blue-50 text-blue-600">
                                        {{ Str::headline($result->category) }}
                                    </span>
                                </div>

                                <p class="text-sm font-medium text-gray-900 truncate">
                                    {{ $result->subject }}
                                </p>

                                <p class="text-xs text-gray-500 mt-1">
                                    {{ $result->customer_name }}
                                    · {{ $result->customer_email }}
                                </p>
                            </div>

                            {{-- Right: Similarity Score --}}
                            <div class="text-right shrink-0">
                                <div class="text-lg font-bold text-purple-600">
                                    {{ number_format($result->similarity_score * 100, 1) }}%
                                </div>
                                <div class="text-xs text-gray-400">match</div>
                                <div class="mt-1 w-16 bg-gray-200 rounded-full h-1.5">
                                    <div class="bg-purple-500 h-1.5 rounded-full"
                                         style="width: {{ number_format($result->similarity_score * 100, 1) }}%">
                                    </div>
                                </div>
                            </div>

                        </div>
                    </a>
                @endforeach
            </div>
        @endif

    @else

        {{-- Empty State --}}
        <div class="bg-white rounded-lg shadow p-12 text-center">
            <div class="text-4xl mb-4">🔍</div>
            <p class="text-gray-500 text-sm">
                Enter a search query above to find semantically similar complaints.
            </p>
            <div class="mt-4 text-xs text-gray-400 space-y-1">
                <p>Tip: Use natural language, not keywords.</p>
                <p>"angry customer about refund" finds billing complaints</p>
                <p>"package never arrived" finds shipping complaints</p>
            </div>
        </div>

    @endif

@endsection