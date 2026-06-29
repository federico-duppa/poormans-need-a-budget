<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#047857">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Budget">
    <link rel="manifest" href="/manifest.json">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">
    <link rel="icon" type="image/png" href="/icons/icon-192.png">
    <title>{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-gradient-to-b from-emerald-700 to-emerald-900 text-white antialiased">
    <div class="mx-auto flex min-h-full max-w-md flex-col items-center justify-center px-6 py-16 text-center">
        <div class="mb-6 flex h-16 w-16 items-center justify-center rounded-2xl bg-white/15 text-3xl">💸</div>
        <h1 class="text-3xl font-bold tracking-tight">{{ config('app.name') }}</h1>
        <p class="mt-3 text-emerald-100">
            Presupuesto familiar base-cero: dale un trabajo a cada peso y controlá
            los gastos del grupo, en pesos y dólares.
        </p>

        <div class="mt-10 w-full">
            @if (session('error'))
                <div class="mb-4 rounded-xl bg-red-500/20 px-4 py-3 text-sm text-red-50 ring-1 ring-red-300/40">
                    {{ session('error') }}
                </div>
            @endif

            @if (Route::has('auth.google.redirect'))
                <a href="{{ route('auth.google.redirect') }}"
                   class="flex w-full items-center justify-center gap-3 rounded-xl bg-white px-5 py-3.5 font-semibold text-slate-700 shadow-lg transition hover:bg-slate-50">
                    <svg class="h-5 w-5" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09Z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23Z"/><path fill="#FBBC05" d="M5.84 14.1c-.22-.66-.35-1.36-.35-2.1s.13-1.44.35-2.1V7.06H2.18A10.99 10.99 0 0 0 1 12c0 1.77.42 3.45 1.18 4.94l3.66-2.84Z"/><path fill="#EA4335" d="M12 4.75c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.46 1.46 14.97.5 12 .5 7.7.5 3.99 2.97 2.18 6.56l3.66 2.84C6.71 6.68 9.14 4.75 12 4.75Z"/></svg>
                    Entrar con Google
                </a>
            @else
                <div class="rounded-xl bg-white/10 px-5 py-3.5 text-sm text-emerald-100">
                    El login con Google se habilita en la siguiente fase.
                </div>
            @endif
            <p class="mt-4 text-xs text-emerald-200">
                Acceso restringido a los miembros autorizados de la familia.
            </p>
        </div>
    </div>
</body>
</html>
