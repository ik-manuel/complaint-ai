@extends('layouts.app')

@section('title', 'Complaint Submitted')

@section('content')
<div class="max-w-2xl mx-auto bg-white rounded-lg shadow-md p-8 text-center">
    <div class="mb-6">
        <svg class="mx-auto h-16 w-16 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
    </div>

    <h2 class="text-3xl font-bold mb-4 text-gray-800">Complaint Submitted Successfully!</h2>
    
    <div class="bg-gray-50 rounded-lg p-6 mb-6">
        <p class="text-sm text-gray-600 mb-2">Your Ticket Number</p>
        <p class="text-2xl font-mono font-bold text-blue-600">{{ $complaint->ticket_number }}</p>
    </div>

    <div class="text-left bg-blue-50 border border-blue-200 rounded-lg p-6 mb-6">
        <h3 class="font-semibold text-blue-900 mb-3">🤖 AI Analysis Complete:</h3>
        <div class="space-y-2 text-sm">
            <div class="flex justify-between">
                <span class="text-gray-700">Urgency Level:</span>
                <span class="font-semibold capitalize
                    @if($complaint->urgency === 'high') text-red-600
                    @elseif($complaint->urgency === 'medium') text-yellow-600
                    @else text-green-600
                    @endif
                ">
                    {{ $complaint->urgency }}
                </span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-700">Category:</span>
                <span class="font-semibold capitalize">{{ str_replace('_', ' ', $complaint->category) }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-700">Status:</span>
                <span class="font-semibold capitalize">{{ $complaint->status }}</span>
            </div>
        </div>
    </div>

    <p class="text-gray-600 mb-6">
        Our AI has analyzed your complaint and generated a response. 
        A support agent will review it and respond within 24 hours.
    </p>

    <div class="space-x-4">
        <a href="{{ route('complaint.conversation', $complaint->ticket_number) }}" 
        class="inline-block bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition">
            💬 Continue Conversation
        </a>
        <a href="{{ route('complaint.create') }}" 
        class="inline-block bg-gray-200 text-gray-800 px-6 py-2 rounded-lg hover:bg-gray-300 transition">
            Submit Another
        </a>
    </div>
</div>
@endsection