@extends('layouts.app')

@section('title', 'Submit Complaint')

@section('content')
<div class="max-w-2xl mx-auto bg-white rounded-lg shadow-md p-8">
    <h2 class="text-3xl font-bold mb-6 text-gray-800">Submit a Complaint</h2>
    
    <p class="text-gray-600 mb-8">
        Our AI-powered system will analyze your complaint and generate a response. 
        A support agent will review it before sending.
    </p>

    <form action="{{ route('complaint.store') }}" method="POST" class="space-y-6">
        @csrf

        <div>
            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Your Name</label>
            <input 
                type="text" 
                name="name" 
                id="name" 
                value="{{ old('name') }}"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                required
            >
        </div>

        <div>
            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Your Email</label>
            <input 
                type="email" 
                name="email" 
                id="email" 
                value="{{ old('email') }}"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                required
            >
        </div>

        <div>
            <label for="subject" class="block text-sm font-medium text-gray-700 mb-2">Subject</label>
            <input 
                type="text" 
                name="subject" 
                id="subject" 
                value="{{ old('subject') }}"
                placeholder="Brief description of your issue"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                required
            >
        </div>

        <div>
            <label for="message" class="block text-sm font-medium text-gray-700 mb-2">Complaint Details</label>
            <textarea 
                name="message" 
                id="message" 
                rows="6"
                placeholder="Please describe your issue in detail..."
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                required
            >{{ old('message') }}</textarea>
            <p class="text-sm text-gray-500 mt-1">Minimum 10 characters</p>
        </div>

        <div class="flex justify-end">
            <button 
                type="submit"
                class="bg-blue-600 text-white px-8 py-3 rounded-lg hover:bg-blue-700 transition duration-200 font-semibold"
            >
                Submit Complaint
            </button>
        </div>
    </form>
</div>

<div class="max-w-2xl mx-auto mt-8 bg-blue-50 border border-blue-200 rounded-lg p-6">
    <h3 class="font-semibold text-blue-900 mb-2">🤖 How our AI helps:</h3>
    <ul class="text-blue-800 space-y-2 text-sm">
        <li>✓ Automatically classifies urgency level</li>
        <li>✓ Categorizes your issue type</li>
        <li>✓ Generates personalized response based on urgency</li>
        <li>✓ Human review before sending</li>
    </ul>
</div>
@endsection