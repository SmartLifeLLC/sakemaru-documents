<x-filament-panels::page>
    {{-- Meta Information Header --}}
    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="grid grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-3 lg:grid-cols-6">
            <div>
                <div class="text-[11px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">取引先</div>
                <div class="mt-0.5 text-sm font-semibold text-gray-900 dark:text-white">{{ $record->invoiceDocument?->partner_name ?? '-' }}</div>
            </div>
            <div>
                <div class="text-[11px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">文書種別</div>
                <div class="mt-0.5 text-sm text-gray-700 dark:text-gray-300">{{ $record->invoiceDocument?->document_type ?? '-' }}</div>
            </div>
            <div>
                <div class="text-[11px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">依頼種別</div>
                <div class="mt-0.5 text-sm text-gray-700 dark:text-gray-300">{{ $this->getRequestTypeLabel() }}</div>
            </div>
            <div>
                <div class="text-[11px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">ステータス</div>
                <div class="mt-0.5">
                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold"
                        style="background-color: {{ $this->getStatusColor() }}20; color: {{ $this->getStatusColor() }}">
                        <span class="h-1.5 w-1.5 rounded-full" style="background-color: {{ $this->getStatusColor() }}"></span>
                        {{ $this->getStatusLabel() }}
                    </span>
                </div>
            </div>
            <div>
                <div class="text-[11px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">依頼日時</div>
                <div class="mt-0.5 text-sm text-gray-700 dark:text-gray-300">{{ $record->created_at?->format('Y/m/d H:i') ?? '-' }}</div>
            </div>
            <div>
                <div class="text-[11px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">UUID</div>
                <div class="mt-0.5 truncate font-mono text-xs text-gray-500 dark:text-gray-400" title="{{ $record->request_uuid }}">{{ $record->request_uuid }}</div>
            </div>
        </div>

        @if($record->description)
            <div class="mt-3 border-t border-gray-100 pt-3 dark:border-gray-800">
                <div class="text-[11px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">依頼内容</div>
                <div class="mt-1 text-sm leading-relaxed text-gray-700 dark:text-gray-300">{{ $record->description }}</div>
            </div>
        @endif
    </div>

    {{-- Chat Panel --}}
    <livewire:chat-panel :requestId="$record->id" />
</x-filament-panels::page>
