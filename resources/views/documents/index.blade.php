@extends('layouts.app')

@section('title', 'Policy Documents - ComplaintAI')

@section('content')

    <div class="flex items-center justify-between mb-6">
        <h2 class="text-2xl font-bold text-gray-800">📄 Policy Documents</h2>
        <a href="{{ route('documents.create') }}"
           class="px-4 py-2 bg-blue-600 text-white text-sm font-medium
                  rounded-lg hover:bg-blue-700 transition-colors">
            + Upload Document
        </a>
    </div>

    @if($documents->isEmpty())
        <div class="bg-white rounded-lg shadow p-12 text-center">
            <div class="text-4xl mb-4">📄</div>
            <p class="text-gray-500 text-sm">No documents uploaded yet.</p>
            <a href="{{ route('documents.create') }}"
               class="mt-4 inline-block text-blue-600 hover:underline text-sm">
                Upload your first document →
            </a>
        </div>
    @else
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="text-left px-6 py-3 text-gray-600 font-medium">Title</th>
                        <th class="text-left px-6 py-3 text-gray-600 font-medium">Pages</th>
                        <th class="text-left px-6 py-3 text-gray-600 font-medium">Chunks</th>
                        <th class="text-left px-6 py-3 text-gray-600 font-medium">Status</th>
                        <th class="text-left px-6 py-3 text-gray-600 font-medium">Uploaded</th>
                        <th class="text-left px-6 py-3 text-gray-600 font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($documents as $document)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <a href="{{ route('documents.show', $document) }}"
                                   class="font-medium text-blue-600 hover:underline">
                                    {{ $document->title }}
                                </a>
                            </td>
                            <td class="px-6 py-4 text-gray-600">
                                {{ $document->total_pages }}
                            </td>
                            <td class="px-6 py-4 text-gray-600">
                                {{ $document->total_chunks }}
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 rounded-full text-xs font-medium
                                    {{ $document->status === \App\Enums\DocumentStatus::Completed
                                        ? 'bg-green-100 text-green-700'
                                        : ($document->status === \App\Enums\DocumentStatus::Failed
                                            ? 'bg-red-100 text-red-700'
                                            : 'bg-yellow-100 text-yellow-700') }}">
                                    {{ $document->status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-gray-500">
                                {{ $document->created_at->diffForHumans() }}
                            </td>
                            <td class="px-6 py-4">
                                <form method="POST"
                                      action="{{ route('documents.destroy', $document) }}"
                                      onsubmit="return confirm('Delete this document and all its chunks?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="text-red-600 hover:underline text-xs">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

@endsection