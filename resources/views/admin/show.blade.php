@extends('layouts.app')

@section('title', 'View Complaint')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('admin.index') }}" class="text-blue-600 hover:text-blue-800">
            ← Back to Dashboard
        </a>
    </div>

    <!-- Complaint Details -->
    <div class="bg-white rounded-lg shadow-md p-8 mb-6">
        <div class="flex justify-between items-start mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-800 mb-2">{{ $complaint->subject }}</h2>
                <p class="text-gray-600 font-mono">{{ $complaint->ticket_number }}</p>
            </div>
            <span class="px-3 py-1 text-sm font-semibold rounded-full capitalize
                @if($complaint->urgency === 'high') bg-red-100 text-red-800
                @elseif($complaint->urgency === 'medium') bg-yellow-100 text-yellow-800
                @else bg-green-100 text-green-800
                @endif
            ">
                {{ $complaint->urgency }} Urgency
            </span>
        </div>

        <div class="grid grid-cols-2 gap-4 mb-6 pb-6 border-b">
            <div>
                <p class="text-sm text-gray-500">Customer</p>
                <p class="font-semibold">{{ $complaint->customer->name }}</p>
                <p class="text-sm text-gray-600">{{ $complaint->customer->email }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Category</p>
                <p class="font-semibold capitalize">{{ str_replace('_', ' ', $complaint->category) }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Status</p>
                <p class="font-semibold capitalize">{{ $complaint->status }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Submitted</p>
                <p class="font-semibold">{{ $complaint->created_at->format('M d, Y H:i') }}</p>
            </div>
        </div>

        <div>
            <h3 class="font-semibold text-gray-700 mb-2">Complaint Message:</h3>
            <div class="bg-gray-50 rounded-lg p-4">
                <p class="text-gray-800 whitespace-pre-wrap">{{ $complaint->message }}</p>
            </div>
        </div>
    </div>


    @if($complaint->conversation && $complaint->conversation->summary)
    <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 mb-4">
        <div class="flex items-start justify-between mb-3">
            <div>
                <span class="text-purple-700 font-semibold">📝 AI Summary (Advanced Features)</span>
                <span class="text-xs text-purple-600 block mt-1">
                    Last updated: {{ $complaint->conversation->last_summarized_at->diffForHumans() }}
                </span>
            </div>
            <div class="text-xs text-purple-600 text-right">
                <div>📊 {{ $complaint->conversation->messages_summarized_count }} messages summarized</div>
                <div>💬 {{ $complaint->conversation->messages()->count() }} total messages</div>
                @php
                    $windowSize = match(true) {
                        $complaint->conversation->messages()->count() < 15 => 10,
                        $complaint->conversation->messages()->count() < 30 => 12,
                        $complaint->conversation->messages()->count() < 50 => 15,
                        $complaint->conversation->messages()->count() < 100 => 20,
                        default => 25,
                    };
                @endphp
                <div>🪟 Window size: {{ $windowSize }} messages</div>
            </div>
        </div>
        <p class="text-sm text-purple-900 italic bg-white rounded p-3">{{ $complaint->conversation->summary }}</p>
        
        <!-- Advanced Features Badge -->
        <div class="mt-3 flex gap-2">
            <span class="text-xs px-2 py-1 bg-purple-200 text-purple-800 rounded">Dynamic Window</span>
            <span class="text-xs px-2 py-1 bg-purple-200 text-purple-800 rounded">Smart Re-summarization</span>
            <span class="text-xs px-2 py-1 bg-green-200 text-green-800 rounded">
                Token Efficient
            </span>
        </div>
    </div>
    @endif
    

    <!-- Conversation History -->
    @if($complaint->conversation && $complaint->conversation->messages->count() > 0)
    <div class="bg-white rounded-lg shadow-md p-8 mb-6">
        <h3 class="text-xl font-bold text-gray-800 mb-4">💬 Full Conversation History</h3>
        
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
            <div class="grid grid-cols-3 gap-4 text-sm">
                <div>
                    <span class="text-blue-600 font-semibold">Messages:</span>
                    <span class="text-blue-800">{{ $complaint->conversation->messages->count() }}</span>
                </div>
                <div>
                    <span class="text-blue-600 font-semibold">Total Tokens:</span>
                    <span class="text-blue-800">{{ $complaint->conversation->total_tokens }}</span>
                </div>
                <div>
                    <span class="text-blue-600 font-semibold">Avg per message:</span>
                    <span class="text-blue-800">
                        {{ round($complaint->conversation->total_tokens / $complaint->conversation->messages->count()) }}
                    </span>
                </div>
            </div>
        </div>

        <div class="space-y-3 max-h-96 overflow-y-auto">
            @foreach($complaint->conversation->messages as $message)
                @if($message->role !== 'system')
                    <div class="border-l-4 pl-4 py-2
                        {{ $message->role === 'user' ? 'border-blue-500 bg-blue-50' : 'border-gray-500 bg-gray-50' }}
                    ">
                        <div class="flex justify-between items-start mb-1">
                            <span class="text-xs font-semibold
                                {{ $message->role === 'user' ? 'text-blue-800' : 'text-gray-700' }}
                            ">
                                {{ $message->role === 'user' ? '👤 Customer' : '🤖 AI Assistant' }}
                            </span>
                            <span class="text-xs text-gray-500">
                                {{ $message->created_at->format('M d, H:i') }} | {{ $message->tokens }} tokens
                            </span>
                        </div>
                        <p class="text-sm text-gray-800 whitespace-pre-wrap">{{ $message->content }}</p>
                    </div>
                @endif
            @endforeach
        </div>
    </div>
    @endif

    <!-- AI Generated Response -->
    @if($complaint->aiResponse)
    <div class="bg-white rounded-lg shadow-md p-8">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-gray-800">🤖 AI-Generated Response</h3>
            <span class="text-sm text-gray-500">{{ $complaint->aiResponse->tokens_used }} tokens used</span>
        </div>

        <form action="{{ route('admin.update-response', $complaint) }}" method="POST">
            @csrf
            <textarea 
                name="response_text"
                rows="8"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent mb-4"
            >{{ $complaint->aiResponse->response_text }}</textarea>

            <div class="flex justify-between items-center">
                <div class="space-x-3">
                    <button 
                        type="submit"
                        class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition"
                    >
                        Update Response
                    </button>

                    @if(!$complaint->aiResponse->approved)
                    <button 
                        type="button"
                        onclick="document.getElementById('approve-form').submit()"
                        class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 transition"
                    >
                        Approve & Send
                    </button>
                    @else
                    <span class="text-green-600 font-semibold">✓ Approved</span>
                    @endif
                </div>

                @if($complaint->status !== 'resolved')
                <button 
                    type="button"
                    onclick="document.getElementById('resolve-form').submit()"
                    class="bg-gray-600 text-white px-6 py-2 rounded-lg hover:bg-gray-700 transition"
                >
                    Mark as Resolved
                </button>
                @endif
            </div>
        </form>

        <form id="approve-form" action="{{ route('admin.approve', $complaint) }}" method="POST" class="hidden">
            @csrf
        </form>

        <form id="resolve-form" action="{{ route('admin.resolve', $complaint) }}" method="POST" class="hidden">
            @csrf
        </form>
    </div>
    @endif
</div>
@endsection