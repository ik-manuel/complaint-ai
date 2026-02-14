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