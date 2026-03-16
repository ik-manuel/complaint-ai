@extends('layouts.app')

@section('title', 'Conversation - ' . $complaint->ticket_number)

@section('content')
<div class="max-w-4xl mx-auto">
    <!-- Ticket Header -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <div class="flex justify-between items-start">
            <div>
                <h2 class="text-2xl font-bold text-gray-800 mb-2">{{ $complaint->subject }}</h2>
                <p class="text-gray-600 font-mono">{{ $complaint->ticket_number }}</p>
                <p class="text-sm text-gray-500 mt-2">Status: <span class="font-semibold capitalize">{{ $complaint->status }}</span></p>
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
    </div>

    <!-- Conversation Thread -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h3 class="text-xl font-bold text-gray-800 mb-4">💬 Conversation</h3>

        <div class="space-y-4 mb-6">
            @if($complaint->conversation && $complaint->conversation->messages->count() > 0)
                @foreach($complaint->conversation->messages as $message)
                    @if($message->role !== 'system')
                        <div class="flex {{ $message->role === 'user' ? 'justify-end' : 'justify-start' }}">
                            <div class="max-w-3/4 {{ $message->role === 'user' ? 'bg-blue-100' : 'bg-gray-100' }} rounded-lg p-4">
                                <div class="flex items-center gap-2 mb-2">
                                    @if($message->role === 'user')
                                        <span class="text-xs font-semibold text-blue-800">You</span>
                                    @else
                                        <span class="text-xs font-semibold text-gray-700">🤖 Support AI</span>
                                        <!-- NEW: Show if tools were used -->
                                        @if(str_contains($message->content, 'ticket') || str_contains($message->content, 'complaint'))
                                            <span class="text-xs bg-purple-200 text-purple-800 px-2 py-0.5 rounded">
                                                🔧 Used database tools
                                            </span>
                                        @endif
                                    @endif
                                    <span class="text-xs text-gray-500">{{ $message->created_at->diffForHumans() }}</span>
                                </div>
                                <p class="text-gray-800 whitespace-pre-wrap">{{ $message->content }}</p>
                                <div class="mt-2 text-xs text-gray-500">
                                    {{ $message->tokens }} tokens
                                </div>
                            </div>
                        </div>
                    @endif
                @endforeach

                <!-- Token Summary -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mt-4">
                    <div class="flex justify-between text-sm">
                        <span class="text-blue-800 font-semibold">📊 Conversation Stats:</span>
                        <span class="text-blue-600">
                            {{ $complaint->conversation->messages->count() }} messages | 
                            {{ $complaint->conversation->total_tokens }} total tokens
                        </span>
                    </div>
                </div>
            @else
                <p class="text-gray-500 text-center py-8">No messages yet. Start the conversation below!</p>
            @endif
        </div>

        <!-- Message Input -->
        <form action="{{ route('complaint.followup', $complaint->ticket_number) }}" method="POST">
            @csrf
            <div class="border-t pt-4">
                <label for="message" class="block text-sm font-medium text-gray-700 mb-2">
                    Send a follow-up message
                </label>
                <textarea 
                    name="message" 
                    id="message"
                    rows="4"
                    placeholder="Type your message here..."
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    required
                >{{ old('message') }}</textarea>

                <div class="flex justify-between items-center mt-4">
                    <p class="text-sm text-gray-500">
                        🤖 AI will remember this entire conversation
                    </p>
                    <button 
                        type="submit"
                        class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition"
                    >
                        Send Message
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Back to Home -->
    <div class="text-center">
        <a href="{{ route('complaint.create') }}" class="text-blue-600 hover:text-blue-800">
            ← Submit a new complaint
        </a>
    </div>
</div>
@endsection