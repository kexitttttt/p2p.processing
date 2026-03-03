<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="winter">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <script>
            (function() {
                try {
                    var saved = localStorage.getItem('theme');
                    if (saved) {
                        document.documentElement.setAttribute('data-theme', saved);
                    }
                } catch (e) {
                    // silent
                }
            })();
        </script>

        <!-- Scripts -->
        @routes
<script>
        // Принудительно используем текущий домен и порт для генерации ссылок
        if (typeof Ziggy !== 'undefined') {
            Ziggy.url = window.location.origin; // Например: http://127.0.0.1:8000
            // Очищаем порт из конфига, чтобы Ziggy не дублировал его, если он там есть
            Ziggy.port = null; 
        }
    </script>
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
