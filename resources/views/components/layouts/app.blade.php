<!DOCTYPE html>
<html
    lang="ko"
    x-data="{ dark: localStorage.theme === 'dark' }"
    :class="dark ? 'dark' : ''"
    @keydown.window="$event.key === 'd' && (dark = !dark, localStorage.theme = dark ? 'dark' : 'light')"
>
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'DivCue' }}</title>

    {{-- Tailwind + Vite --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Livewire --}}
    @livewireStyles
</head>

<body class="min-h-screen bg-gray-100 text-gray-900 dark:bg-gray-900 dark:text-gray-100 antialiased">

{{-- 화면 콘텐츠 --}}
{{ $slot }}

{{-- Livewire --}}
@livewireScripts
</body>
</html>
