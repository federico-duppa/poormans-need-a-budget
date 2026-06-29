@props(['name'])

@php
    $paths = [
        'wallet' => '<path stroke-linecap="round" stroke-linejoin="round" d="M21 12a2.25 2.25 0 0 0-2.25-2.25H5.25A2.25 2.25 0 0 1 3 7.5m18 4.5v4.5A2.25 2.25 0 0 1 18.75 18.75H5.25A2.25 2.25 0 0 1 3 16.5V7.5m18 4.5h-3.75a1.5 1.5 0 1 0 0 3H21m0-7.5V6a2.25 2.25 0 0 0-2.25-2.25H5.25A2.25 2.25 0 0 0 3 6v1.5"/>',
        'bank' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 3 3 8.25h18L12 3Zm-7.5 5.25v9m4.5-9v9m6-9v9m4.5-9v9M3 20.25h18"/>',
        'list' => '<path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.008v.008H3.75V6.75Zm.375 5.25a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 5.25a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/>',
        'chart' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/>',
    ];
@endphp

<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6"
     stroke="currentColor" class="h-6 w-6">
    {!! $paths[$name] ?? '' !!}
</svg>
