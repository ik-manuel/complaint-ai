@extends('layouts.app')

@section('title', 'Admin Dashboard')

@section('content')
<div class="mb-8">
    <h2 class="text-3xl font-bold text-gray-800 mb-6">Admin Dashboard</h2>
    
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-8">
        <div class="bg-white rounded-lg shadow p-6">
            <p class="text-gray-500 text-sm">Total Complaints</p>
            <p class="text-3xl font-bold text-gray-800">{{ $stats['total'] }}</p>
        </div>
        <div class="bg-blue-50 rounded-lg shadow p-6">
            <p class="text-blue-600 text-sm">New</p>
            <p class="text-3xl font-bold text-blue-700">{{ $stats['new'] }}</p>
        </div>
        <div class="bg-green-50 rounded-lg shadow p-6">
            <p class="text-green-600 text-sm">Responded</p>
            <p class="text-3xl font-bold text-green-700">{{ $stats['responded'] }}</p>
        </div>
        <div class="bg-gray-50 rounded-lg shadow p-6">
            <p class="text-gray-600 text-sm">Resolved</p>
            <p class="text-3xl font-bold text-gray-700">{{ $stats['resolved'] }}</p>
        </div>
        <div class="bg-red-50 rounded-lg shadow p-6">
            <p class="text-red-600 text-sm">High Urgency</p>
            <p class="text-3xl font-bold text-red-700">{{ $stats['high_urgency'] }}</p>
        </div>
    </div>

    <!-- Complaints Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ticket</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subject</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Urgency</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($complaints as $complaint)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-mono">
                        {{ $complaint->ticket_number }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900">{{ $complaint->customer->name }}</div>
                        <div class="text-sm text-gray-500">{{ $complaint->customer->email }}</div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-sm text-gray-900">{{ Str::limit($complaint->subject, 40) }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                            @if($complaint->urgency === 'high') bg-red-100 text-red-800
                            @elseif($complaint->urgency === 'medium') bg-yellow-100 text-yellow-800
                            @else bg-green-100 text-green-800
                            @endif
                        ">
                            {{ ucfirst($complaint->urgency) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 capitalize">
                        {{ str_replace('_', ' ', $complaint->category) }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm capitalize">
                        {{ $complaint->status }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {{ $complaint->created_at->format('M d, Y') }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <a href="{{ route('admin.show', $complaint) }}" class="text-blue-600 hover:text-blue-900">
                            View
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                        No complaints yet. Submit one to see it here!
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $complaints->links() }}
    </div>
</div>
@endsection