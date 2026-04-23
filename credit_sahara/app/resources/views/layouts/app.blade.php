<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Wassel Pay - @yield('title', 'Crédit du Sahara')</title>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .bg-primary { background-color: #1a56db; }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Sidebar -->
    <div class="flex h-screen">
        <div class="w-64 bg-primary text-white fixed h-full">
            <div class="p-6">
                <h1 class="text-2xl font-bold">🏦 Wassel Pay</h1>
                <p class="text-sm text-blue-200">Crédit du Sahara</p>
            </div>
            <nav class="mt-6">
                <a href="{{ url('/dashboard') }}" class="flex items-center px-6 py-3 hover:bg-blue-700">
                    <span class="mr-3">📊</span> Dashboard
                </a>
                <a href="{{ route('transfers.create') }}" class="flex items-center px-6 py-3 hover:bg-blue-700">
                    <span class="mr-3">💰</span> Nouveau virement
                </a>
                <a href="{{ route('transfers.index') }}" class="flex items-center px-6 py-3 hover:bg-blue-700">
                    <span class="mr-3">📋</span> Historique
                </a>
                <a href="{{ route('crypto.dashboard') }}" class="flex items-center px-6 py-3 hover:bg-blue-700 bg-blue-800">
                    <span class="mr-3">🔐</span> Crypto Admin
                </a>
            </nav>
        </div>
        
        <!-- Main Content -->
        <div class="flex-1 ml-64">
            <header class="bg-white shadow-sm">
                <div class="flex justify-between items-center px-8 py-4">
                    <h2 class="text-xl font-semibold text-gray-800">@yield('header')</h2>
                    <span class="text-gray-600">👤 Achraf</span>
                </div>
            </header>
            
            <main class="p-8">
                @if(session('success'))
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                        ✅ {{ session('success') }}
                    </div>
                @endif
                
                @if(session('error'))
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        ❌ {{ session('error') }}
                    </div>
                @endif
                
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
