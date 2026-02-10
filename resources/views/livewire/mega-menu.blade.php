<div class="w-full bg-slate-800 text-slate-200 h-10 flex items-center">
    <div class="flex items-center gap-4 px-4">
        <a href="{{ filament()->getUrl() }}" class="flex items-center gap-2 text-white">
            <img src="{{ asset('images/logo.png') }}" alt="酒丸帳場" class="h-6 w-auto">
            <span class="text-sm font-semibold">酒丸帳場</span>
        </a>
    </div>

    <nav class="flex items-center gap-3 px-4">
        @foreach ($menus as $menu)
            <div class="relative group">
                <button type="button" class="text-sm px-3 py-1 rounded-md text-slate-200 hover:text-white hover:bg-slate-700">
                    {{ $menu['category']->label() }}
                </button>

                <div class="absolute left-0 mt-2 hidden min-w-56 rounded-lg border border-slate-200 bg-white shadow-lg group-hover:block">
                    <div class="py-2">
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
