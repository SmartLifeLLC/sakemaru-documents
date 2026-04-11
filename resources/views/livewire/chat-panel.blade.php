<div class="chat-container" wire:poll.1s="loadComments">
    {{-- Status Bar --}}
    @php
        $transitions = $this->getAvailableTransitions();
        $statusLabel = $this->getStatusLabel();
        $statusColor = $this->getStatusColor();
        $statusBg = $this->getStatusBgColor();
    @endphp
    <div class="flex items-center justify-between rounded-t-xl border border-gray-200 px-4 py-3 dark:border-gray-700 {{ $statusBg }}">
        <div class="flex items-center gap-2">
            <span class="inline-block h-2.5 w-2.5 rounded-full" style="background-color: {{ $statusColor }}"></span>
            <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $statusLabel }}</span>
        </div>
        @if(count($transitions) > 0)
            <div class="flex items-center gap-2">
                <select
                    wire:model="newStatus"
                    class="rounded-lg border-gray-300 bg-white py-1.5 pl-3 pr-8 text-xs font-medium text-gray-700 shadow-sm focus:border-blue-400 focus:ring-blue-400 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300"
                >
                    <option value="">変更先を選択</option>
                    @foreach($transitions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <button
                    wire:click="changeStatus"
                    type="button"
                    class="inline-flex items-center rounded-lg bg-gray-800 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-gray-700 dark:bg-gray-600 dark:hover:bg-gray-500"
                >
                    変更
                </button>
            </div>
        @endif
        @error('newStatus')
            <span class="text-xs text-red-500">{{ $message }}</span>
        @enderror
    </div>

    {{-- Chat Messages --}}
    <div
        class="overflow-y-auto border-x border-gray-200 bg-[#7494C0] px-4 py-5 dark:border-gray-700 dark:bg-[#2C3E50]"
        style="height: 480px;"
        id="chat-messages"
    >
        @php $prevDate = null; @endphp
        @forelse($comments as $index => $comment)
            @php
                $type = $comment['commenter_type'] ?? '';
                $isCompany = $type === 'company_user';
                $isSystem = $type === 'system';
                $name = $comment['commenter_name'] ?? "{$type}#{$comment['commenter_id']}";
                $time = \App\Livewire\ChatPanel::formatTime($comment['created_at'] ?? null);
                $body = $comment['body'] ?? '';
                $dateLabel = $comment['created_at'] ? \Carbon\Carbon::parse($comment['created_at'])->format('Y/n/j') : null;
            @endphp

            {{-- Date divider --}}
            @if($dateLabel && $dateLabel !== $prevDate)
                @php $prevDate = $dateLabel; @endphp
                <div class="my-4 flex items-center justify-center">
                    <div class="rounded-full bg-black/25 px-4 py-1 backdrop-blur-sm">
                        <span class="text-[11px] font-medium text-white">{{ $dateLabel }}</span>
                    </div>
                </div>
            @endif

            @if($isSystem)
                {{-- System message --}}
                <div class="my-3 flex justify-center">
                    <div class="rounded-full bg-black/20 px-4 py-1 backdrop-blur-sm">
                        <span class="text-xs text-white">{{ $body }}</span>
                        @if($time)
                            <span class="ml-2 text-[10px] text-white/60">{{ $time }}</span>
                        @endif
                    </div>
                </div>
            @elseif($isCompany)
                {{-- Admin (self) message: right side, green bubble like LINE --}}
                <div class="mb-3 flex justify-end gap-1.5">
                    <div class="flex flex-col items-end">
                        <span class="mb-1 text-[11px] font-medium text-white/80">{{ $name }}</span>
                        <div class="flex items-end gap-1">
                            <span class="mb-0.5 text-[10px] text-white/60">{{ $time }}</span>
                            <div class="relative max-w-xs rounded-2xl rounded-tr-sm bg-[#A8D97F] px-3.5 py-2.5 shadow-sm dark:bg-[#5B8C3E] sm:max-w-sm md:max-w-md">
                                <p class="whitespace-pre-wrap break-words text-[13px] leading-relaxed text-gray-900 dark:text-gray-100">{{ $body }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                {{-- Partner message: left side, white bubble --}}
                <div class="mb-3 flex justify-start gap-1.5">
                    <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-white text-sm font-bold text-gray-500 shadow-sm dark:bg-gray-600 dark:text-gray-300">
                        {{ mb_substr($name, 0, 1) }}
                    </div>
                    <div class="flex flex-col">
                        <span class="mb-1 text-[11px] font-medium text-white/80">{{ $name }}</span>
                        <div class="flex items-end gap-1">
                            <div class="relative max-w-xs rounded-2xl rounded-tl-sm bg-white px-3.5 py-2.5 shadow-sm dark:bg-gray-700 sm:max-w-sm md:max-w-md">
                                <p class="whitespace-pre-wrap break-words text-[13px] leading-relaxed text-gray-900 dark:text-gray-100">{{ $body }}</p>
                            </div>
                            <span class="mb-0.5 text-[10px] text-white/60">{{ $time }}</span>
                        </div>
                    </div>
                </div>
            @endif
        @empty
            <div class="flex h-full items-center justify-center">
                <div class="rounded-full bg-black/20 px-5 py-2 backdrop-blur-sm">
                    <span class="text-sm text-white/70">メッセージはまだありません</span>
                </div>
            </div>
        @endforelse
    </div>

    {{-- Input Area --}}
    <div class="rounded-b-xl border border-t-0 border-gray-200 bg-gray-50 px-3 py-3 dark:border-gray-700 dark:bg-gray-800">
        <form wire:submit="sendMessage" class="flex items-end gap-2">
            <div class="flex-1">
                <textarea
                    wire:model="newMessage"
                    placeholder="メッセージを入力..."
                    rows="1"
                    maxlength="5000"
                    class="block w-full resize-none rounded-2xl border-gray-300 bg-white px-4 py-2.5 text-sm shadow-sm transition placeholder:text-gray-400 focus:border-blue-400 focus:ring-1 focus:ring-blue-400 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder:text-gray-500"
                    x-data="{ resize() { $el.style.height = 'auto'; $el.style.height = Math.min($el.scrollHeight, 120) + 'px'; } }"
                    x-on:input="resize()"
                    x-on:keydown.enter.prevent="if (!$event.shiftKey) { $wire.sendMessage(); }"
                ></textarea>
            </div>
            <button
                type="submit"
                class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-blue-500 text-white shadow-sm transition hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-1 dark:bg-blue-600 dark:hover:bg-blue-500"
                title="送信"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
                    <path d="M3.105 2.288a.75.75 0 0 0-.826.95l1.414 4.926A1.5 1.5 0 0 0 5.135 9.25h6.115a.75.75 0 0 1 0 1.5H5.135a1.5 1.5 0 0 0-1.442 1.086l-1.414 4.926a.75.75 0 0 0 .826.95 28.897 28.897 0 0 0 15.293-7.155.75.75 0 0 0 0-1.114A28.897 28.897 0 0 0 3.105 2.288Z" />
                </svg>
            </button>
        </form>
        @error('newMessage')
            <p class="mt-1.5 px-2 text-xs text-red-500">{{ $message }}</p>
        @enderror
    </div>
</div>

@script
<script>
    $wire.on('commentsUpdated', () => {
        $nextTick(() => {
            const el = document.getElementById('chat-messages');
            if (el) el.scrollTop = el.scrollHeight;
        });
    });
    $nextTick(() => {
        const el = document.getElementById('chat-messages');
        if (el) el.scrollTop = el.scrollHeight;
    });
</script>
@endscript
