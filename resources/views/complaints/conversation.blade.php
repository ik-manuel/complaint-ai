@extends('layouts.app')

@section('title', 'Conversation - ' . $complaint->ticket_number)

@section('content')
<div class="max-w-4xl mx-auto">

    {{-- Ticket Header --}}
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <div class="flex justify-between items-start">
            <div>
                <h2 class="text-2xl font-bold text-gray-800 mb-2">{{ $complaint->subject }}</h2>
                <p class="text-gray-600 font-mono">{{ $complaint->ticket_number }}</p>
                <p class="text-sm text-gray-500 mt-2">
                    Status: <span class="font-semibold capitalize">{{ $complaint->status }}</span>
                </p>
            </div>
            <span class="px-3 py-1 text-sm font-semibold rounded-full capitalize
                @if($complaint->urgency === \App\Enums\ComplaintUrgency::High) bg-red-100 text-red-800
                @elseif($complaint->urgency === \App\Enums\ComplaintUrgency::Medium) bg-yellow-100 text-yellow-800
                @else bg-green-100 text-green-800
                @endif">
                {{ ucfirst($complaint->urgency->value) }} Urgency
            </span>
        </div>
    </div>

    {{-- Conversation Thread --}}
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h3 class="text-xl font-bold text-gray-800 mb-4">💬 Conversation</h3>

        {{-- Messages --}}
        <div id="messages-container" class="space-y-4 mb-6">
            @if($complaint->conversation && $complaint->conversation->messages->count() > 0)
                @foreach($complaint->conversation->messages as $message)
                    @if($message->role !== 'system')
                        <div class="flex {{ $message->role === \App\Enums\MessageRole::User ? 'justify-end' : 'justify-start' }}">
                            <div class="max-w-3/4 {{ $message->role === \App\Enums\MessageRole::User ? 'bg-blue-100' : 'bg-gray-100' }} rounded-lg p-4">
                                <div class="flex items-center gap-2 mb-2">
                                    @if($message->role === \App\Enums\MessageRole::User)
                                        <span class="text-xs font-semibold text-blue-800">You</span>
                                    @else
                                        <span class="text-xs font-semibold text-gray-700">🤖 Support AI</span>
                                        @if(str_contains($message->content, 'ticket') || str_contains($message->content, 'complaint'))
                                            <span class="text-xs bg-purple-200 text-purple-800 px-2 py-0.5 rounded">
                                                🔧 Used database tools
                                            </span>
                                        @endif
                                        @if(str_contains($message->content, 'Section') || str_contains($message->content, 'policy'))
                                            <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded">
                                                📄 From policy document
                                            </span>
                                        @endif
                                    @endif
                                    <span class="text-xs text-gray-500">{{ $message->created_at->diffForHumans() }}</span>
                                </div>
                                <p class="text-gray-800 whitespace-pre-wrap">{{ $message->content }}</p>
                                <div class="mt-2 text-xs text-gray-500">{{ $message->tokens }} tokens</div>
                            </div>
                        </div>
                    @endif
                @endforeach

                {{-- Conversation Stats --}}
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

        {{-- Token info (shown after each streamed response) --}}
        <div id="token-info" class="text-xs text-gray-400 mb-3 h-4"></div>

        {{-- Message Input --}}
        <form id="chat-form"
              data-stream-url="{{ route('conversation.stream', $complaint->conversation) }}">
            @csrf
            <div class="border-t pt-4">
                <label for="chat-input" class="block text-sm font-medium text-gray-700 mb-2">
                    Send a follow-up message
                </label>
                <textarea
                    name="message"
                    id="chat-input"
                    rows="4"
                    autocomplete="off"
                    placeholder="Ask about your complaint or our policies..."
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg
                           focus:ring-2 focus:ring-blue-500 focus:border-transparent
                           disabled:bg-gray-50 disabled:text-gray-400"
                    required
                ></textarea>

                <div class="flex justify-between items-center mt-4">
                    <p class="text-sm text-gray-500">
                        🤖 AI will remember this entire conversation
                    </p>
                    <button
                        type="submit"
                        id="send-button"
                        class="bg-blue-600 text-white px-6 py-2 rounded-lg
                               hover:bg-blue-700 transition
                               disabled:opacity-50 disabled:cursor-not-allowed">
                        Send Message
                    </button>
                </div>
            </div>
        </form>

    </div>{{-- end Conversation Thread card --}}

    {{-- Back link --}}
    <div class="text-center">
        <a href="{{ route('complaint.create') }}" class="text-blue-600 hover:text-blue-800">
            ← Submit a new complaint
        </a>
    </div>

</div>
@endsection

@push('scripts')
<style>
@keyframes blink {
    0%, 100% { opacity: 0.7; }
    50%       { opacity: 0;   }
}
</style>
<script>
(function () {
    const form       = document.getElementById('chat-form');
    const input      = document.getElementById('chat-input');
    const sendButton = document.getElementById('send-button');
    const container  = document.getElementById('messages-container');
    const tokenInfo  = document.getElementById('token-info');
    const streamUrl  = form.dataset.streamUrl;
    let isStreaming  = false;
    const csrfToken  = document.querySelector('meta[name="csrf-token"]')?.content
                       || '{{ csrf_token() }}';

    // Allow Ctrl+Enter to submit (textarea doesn't submit on Enter by default)
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
            e.preventDefault();
            form.dispatchEvent(new Event('submit'));
        }
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        if (isStreaming) return;

        const message = input.value.trim();
        if (!message) return;

        isStreaming = true;

        appendUserMessage(message);
        input.value = '';
        setLoading(true);

        const assistantBubble = appendAssistantBubble();
        let   fullContent     = '';

        fetch(streamUrl, {
            method:  'POST',
            headers: {
                'Content-Type':     'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN':     csrfToken,
                'Accept':           'text/event-stream',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: new URLSearchParams({ message }),
        })
        .then(function (response) {
            if (!response.ok) {
                throw new Error('Network error: ' + response.status);
            }

            const reader  = response.body.getReader();
            const decoder = new TextDecoder();
            let   buffer  = '';

            function readChunk() {
                return reader.read().then(function ({ done, value }) {
                    if (done) {
                        setLoading(false);
                        removeCursor(assistantBubble);
                        return;
                    }

                    buffer += decoder.decode(value, { stream: true });

                    const lines = buffer.split('\n');
                    buffer = lines.pop(); // keep incomplete line in buffer

                    lines.forEach(function (line) {
                        line = line.trim();
                        if (!line.startsWith('data: ')) return;

                        const jsonStr = line.slice(6);

                        try {
                            const data = JSON.parse(jsonStr);

                            if (data.error) {
                                showError(assistantBubble, data.error);
                                setLoading(false);
                                return;
                            }

                            if (data.token !== undefined) {
                                fullContent += data.token;
                                updateBubbleContent(assistantBubble, fullContent);
                                scrollToBottom();
                            }

                            if (data.done) {
                                setLoading(false);
                                removeCursor(assistantBubble);
                                showTokenInfo(data.tokens, data.tools_used);
                            }

                        } catch (err) {
                            // malformed chunk — skip
                        }
                    });

                    return readChunk();
                });
            }

            return readChunk();
        })
        .catch(function (err) {
            showError(assistantBubble, 'Connection error. Please try again.');
            setLoading(false);
            removeCursor(assistantBubble);
            console.error('Stream error:', err);
        });
    });

    // ── DOM helpers ──────────────────────────────────────────────────────────

    function appendUserMessage(text) {
        const wrapper = document.createElement('div');
        wrapper.className = 'flex justify-end';

        wrapper.innerHTML = `
            <div class="max-w-3/4 bg-blue-100 rounded-lg p-4">
                <div class="flex items-center gap-2 mb-2">
                    <span class="text-xs font-semibold text-blue-800">You</span>
                    <span class="text-xs text-gray-500">just now</span>
                </div>
                <p class="text-gray-800 whitespace-pre-wrap">${escapeHtml(text)}</p>
            </div>`;

        // Insert before the stats block if it exists, otherwise append
        const statsBlock = container.querySelector('.bg-blue-50');
        if (statsBlock) {
            container.insertBefore(wrapper, statsBlock);
        } else {
            container.appendChild(wrapper);
        }

        scrollToBottom();
    }

    function appendAssistantBubble() {
        // Wrapper matches existing message structure exactly
        const wrapper = document.createElement('div');
        wrapper.className = 'flex justify-start';

        const bubble = document.createElement('div');
        bubble.className = 'max-w-3/4 bg-gray-100 rounded-lg p-4';

        // Header row — matches existing assistant message header
        const header = document.createElement('div');
        header.className = 'flex items-center gap-2 mb-2';
        header.innerHTML = '<span class="text-xs font-semibold text-gray-700">🤖 Support AI</span>' +
                           '<span class="text-xs text-gray-500">just now</span>';

        // Content paragraph
        const content = document.createElement('p');
        content.className = 'text-gray-800 whitespace-pre-wrap';

        // Blinking cursor
        const cursor = document.createElement('span');
        cursor.className     = 'streaming-cursor';
        cursor.textContent   = '| '; //'▋';
        cursor.style.cssText = 'display:inline-block;' +
                               'animation:blink 1s step-end infinite;' +
                               'margin-left:1px;opacity:0.7;';

        content.appendChild(cursor);
        bubble.appendChild(header);
        bubble.appendChild(content);
        wrapper.appendChild(bubble);

        // Insert before stats block so it appears in the right position
        const statsBlock = container.querySelector('.bg-blue-50');
        if (statsBlock) {
            container.insertBefore(wrapper, statsBlock);
        } else {
            container.appendChild(wrapper);
        }

        scrollToBottom();
        return content; // return the <p> element — that's what we update
    }

    function updateBubbleContent(contentEl, text) {
        const cursor = contentEl.querySelector('.streaming-cursor');

        contentEl.innerHTML = '';

        // Rebuild with line breaks preserved
        const lines = text.split('\n');
        lines.forEach(function (line, i) {
            contentEl.appendChild(document.createTextNode(line));
            if (i < lines.length - 1) {
                contentEl.appendChild(document.createElement('br'));
            }
        });

        if (cursor) {
            contentEl.appendChild(cursor);
        }
    }

    function removeCursor(contentEl) {
        const cursor = contentEl.querySelector('.streaming-cursor');
        if (cursor) cursor.remove();

        // Add token count row to match existing message structure
        const bubble = contentEl.closest('.bg-gray-100');
        if (bubble && !bubble.querySelector('.token-count')) {
            const tokenDiv = document.createElement('div');
            tokenDiv.className = 'mt-2 text-xs text-gray-500 token-count';
            tokenDiv.textContent = '—';
            bubble.appendChild(tokenDiv);
        }
    }

    function showError(contentEl, message) {
        contentEl.innerHTML =
            `<span class="text-red-600">${escapeHtml(message)}</span>`;
    }

    function showTokenInfo(tokens, toolsUsed) {
        // Update the token-info div below the messages
        if (tokenInfo) {
            let text = tokens ? `${tokens} tokens used` : '';
            if (toolsUsed && toolsUsed.length > 0) {
                text += ` · tools: ${toolsUsed.join(', ')}`;
            }
            tokenInfo.textContent = text;
        }

        // Also update the token count inside the bubble
        const tokenCount = container.querySelector('.token-count');
        if (tokenCount && tokens) {
            tokenCount.textContent = `${tokens} tokens`;
        }
    }

    function setLoading(loading) {
        isStreaming            = loading;
        sendButton.disabled    = loading;
        input.disabled         = loading;
        sendButton.textContent = loading ? 'Sending…' : 'Send Message';
    }

    function scrollToBottom() {
        const lastMessage = container.lastElementChild;
        if (lastMessage) {
            lastMessage.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    function escapeHtml(str) {
        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function showTokenInfo(tokens, toolsUsed) {
        // Update the small token-info div
        if (tokenInfo) {
            let text = tokens ? `${tokens} tokens used` : '';
            if (toolsUsed && toolsUsed.length > 0) {
                text += ` · tools: ${toolsUsed.join(', ')}`;
            }
            tokenInfo.textContent = text;
        }

        // Update token count on the streamed bubble
        const tokenCount = container.querySelector('.token-count');
        if (tokenCount && tokens) {
            tokenCount.textContent = `${tokens} tokens`;
        }

        // Update conversation stats block
        const statsBlock = container.querySelector('.bg-blue-50 .text-blue-600');
        if (statsBlock) {
            // Count non-system message divs currently in the container
            const messageCount = container.querySelectorAll(
                '.flex.justify-start, .flex.justify-end'
            ).length;

            statsBlock.textContent = `${messageCount} messages | tokens updating...`;
        }
    }

})();
</script>
@endpush