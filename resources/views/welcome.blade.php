<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>MoonCRM API</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Styles / Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased bg-gradient-to-br from-gray-900 to-gray-800 min-h-screen flex items-center justify-center">
<div class="max-w-4xl mx-auto px-4 py-8">
    <div class="bg-white/5 backdrop-blur-lg rounded-2xl p-8 shadow-2xl border border-white/10">
        <div class="flex items-center justify-center mb-8">
            <div class="bg-blue-500/10 p-4 rounded-full">
                <svg class="w-16 h-16 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
            </div>
        </div>

        <h1 class="text-3xl font-bold text-center text-white mb-4">MoonCRM API</h1>

        <div class="flex justify-center space-x-2 mb-8">
                    <span class="px-3 py-1 text-sm text-green-400 bg-green-400/10 rounded-full border border-green-400/20">
                        Active
                    </span>
            <span class="px-3 py-1 text-sm text-blue-400 bg-blue-400/10 rounded-full border border-blue-400/20">
                        v1.0
                    </span>
        </div>

        <div class="text-center text-gray-400 mb-8">
            <p class="mb-4">Güvenli ve güçlü API altyapısı ile hizmetinizdeyiz.</p>
            <div class="flex justify-center space-x-4">
                <div class="flex items-center">
                    <svg class="w-5 h-5 mr-2 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                    <span>Güvenli</span>
                </div>
                <div class="flex items-center">
                    <svg class="w-5 h-5 mr-2 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    <span>Hızlı</span>
                </div>
                <div class="flex items-center">
                    <svg class="w-5 h-5 mr-2 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/>
                    </svg>
                    <span>Ölçeklenebilir</span>
                </div>
            </div>
        </div>

        <div class="text-center text-sm text-gray-500">
            <p>© {{ date('Y') }} MoonCRM. Tüm hakları saklıdır.</p>
        </div>
    </div>
</div>

<div class="fixed bottom-4 right-4">
    <div class="flex items-center space-x-2 text-sm text-gray-400">
        <div class="flex items-center px-3 py-1 bg-white/5 rounded-full">
            <div class="w-2 h-2 bg-green-400 rounded-full mr-2"></div>
            <span>System Operational</span>
        </div>
    </div>
</div>
</body>
</html>
