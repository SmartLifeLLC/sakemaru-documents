<div class="w-full bg-slate-800 text-slate-200 h-10 flex items-center">
    <div class="flex items-center gap-4 px-4">
        <a href="{{ filament()->getUrl() }}" class="flex items-center gap-2 text-white">
            <span class="inline-flex items-center rounded bg-white px-2.5 py-1">
                <img src="{{ asset('images/logo.png') }}" alt="酒丸帳場" class="h-6 w-auto">
            </span>
            <span class="text-sm font-semibold">酒丸帳場</span>
        </a>
    </div>

    <nav class="flex items-center gap-3 px-4">
        @foreach ($menus as $menu)
            <div class="relative group">
                <button type="button" class="text-sm px-3 py-1 rounded-md text-slate-200 hover:text-white hover:bg-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/70">
                    {{ $menu['category']->label() }}
                </button>

                <div class="absolute left-0 top-full z-50 hidden min-w-56 pt-2 group-hover:block group-focus-within:block">
                    <div class="rounded-lg border border-slate-200 bg-white py-2 shadow-lg">
                        @foreach ($menu['items'] as $item)
                            <a href="{{ $item['url'] }}" class="group block px-4 py-2 text-sm text-slate-700 hover:bg-indigo-600 hover:text-white">
                                {{ $item['label'] }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </nav>
</div>
