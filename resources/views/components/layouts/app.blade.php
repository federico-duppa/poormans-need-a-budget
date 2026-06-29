@props(['title' => null, 'heading' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#047857">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Budget">
    <link rel="manifest" href="/manifest.json">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">
    <link rel="icon" type="image/png" href="/icons/icon-192.png">
    <title>{{ $title ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full bg-slate-50 text-slate-800 antialiased">
    <div class="mx-auto flex min-h-full max-w-2xl flex-col">
        {{-- Top bar --}}
        <header class="sticky top-0 z-20 flex items-center justify-between border-b border-slate-200 bg-white/90 px-4 py-3 backdrop-blur">
            <div class="flex items-center gap-2">
                <span class="text-lg font-bold text-emerald-700">{{ $heading ?? config('app.name') }}</span>
            </div>
            <div class="flex items-center gap-3">
                @auth
                    <span class="hidden text-sm text-slate-500 sm:inline">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-200">
                            Salir
                        </button>
                    </form>
                @endauth
            </div>
        </header>

        {{-- Main content --}}
        <main class="flex-1 px-4 pb-28 pt-4">
            {{ $slot }}
        </main>

        {{-- Botón flotante de carga rápida --}}
        @auth
            @if (! request()->routeIs('transactions.new'))
                <a href="{{ route('transactions.new') }}"
                   class="fixed bottom-20 left-1/2 z-30 flex h-14 w-14 -translate-x-1/2 items-center justify-center rounded-full bg-emerald-600 text-white shadow-lg shadow-emerald-600/30 transition hover:bg-emerald-700"
                   style="margin-bottom: env(safe-area-inset-bottom);"
                   aria-label="Nuevo movimiento">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-7 w-7">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                </a>
            @endif
        @endauth

        {{-- Bottom navigation (mobile-first) --}}
        @auth
            @php
                $nav = [
                    ['route' => 'budget', 'label' => 'Presupuesto', 'icon' => 'wallet'],
                    ['route' => 'accounts', 'label' => 'Cuentas', 'icon' => 'bank'],
                    ['route' => 'transactions', 'label' => 'Movimientos', 'icon' => 'list'],
                    ['route' => 'reports', 'label' => 'Reportes', 'icon' => 'chart'],
                ];
            @endphp
            <nav class="fixed inset-x-0 bottom-0 z-20 mx-auto max-w-2xl border-t border-slate-200 bg-white/95 backdrop-blur"
                 style="padding-bottom: env(safe-area-inset-bottom);">
                <div class="grid grid-cols-4">
                    @foreach ($nav as $item)
                        @php $active = request()->routeIs($item['route']); @endphp
                        <a href="{{ Route::has($item['route']) ? route($item['route']) : '#' }}"
                           class="flex flex-col items-center gap-1 py-2.5 text-[11px] font-medium {{ $active ? 'text-emerald-700' : 'text-slate-400' }}">
                            <x-nav-icon :name="$item['icon']" />
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </div>
            </nav>
        @endauth
    </div>

    @livewireScripts
</body>
</html>
