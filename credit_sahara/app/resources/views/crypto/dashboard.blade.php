@extends('layouts.app')

@section('title', 'Administration Crypto - Wassel Pay')
@section('header', '🔐 Administration Cryptographique')

@section('content')
<div class="space-y-6">
    
    <!-- Statistiques -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 bg-blue-100 rounded-full">
                    <span class="text-2xl">🔄</span>
                </div>
                <div class="ml-4">
                    <p class="text-gray-500 text-sm">Rotations</p>
                    <p class="text-2xl font-bold">{{ $stats['total_rotations'] }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 bg-red-100 rounded-full">
                    <span class="text-2xl">🗑️</span>
                </div>
                <div class="ml-4">
                    <p class="text-gray-500 text-sm">Clés révoquées</p>
                    <p class="text-2xl font-bold">{{ $stats['total_revoked_keys'] }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 bg-green-100 rounded-full">
                    <span class="text-2xl">🔑</span>
                </div>
                <div class="ml-4">
                    <p class="text-gray-500 text-sm">Parts créées</p>
                    <p class="text-2xl font-bold">{{ $stats['shares_count'] }}/3</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 bg-purple-100 rounded-full">
                    <span class="text-2xl">✅</span>
                </div>
                <div class="ml-4">
                    <p class="text-gray-500 text-sm">Parts valides</p>
                    <p class="text-2xl font-bold">{{ $stats['shares_valid'] }}/{{ $stats['shares_count'] }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 1 : Rotation des clés -->
    <div class="bg-white rounded-lg shadow">
        <div class="border-b px-6 py-4 bg-gradient-to-r from-blue-50 to-white">
            <h3 class="text-lg font-semibold">🔄 Rotation automatique des clés ECC</h3>
            <p class="text-sm text-gray-500">Conforme ISO 27001 - Rotation tous les 90 jours</p>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- État de la clé -->
                <div class="bg-gray-50 rounded-lg p-4">
                    <h4 class="font-semibold mb-3">État actuel</h4>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Âge de la clé :</span>
                            <span class="font-mono">{{ $keyStatus['age_days'] }} jours</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2.5">
                            <div class="bg-blue-600 h-2.5 rounded-full" style="width: {{ min(100, ($keyStatus['age_days'] / 90) * 100) }}%"></div>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Expire dans :</span>
                            <span class="font-mono {{ $keyStatus['expires_in'] < 7 ? 'text-red-600 font-bold' : '' }}">{{ $keyStatus['expires_in'] }} jours</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Dernière modification :</span>
                            <span class="font-mono text-sm">{{ $keyStatus['last_modified'] ?? 'N/A' }}</span>
                        </div>
                    </div>
                </div>
                
                <!-- Actions Rotation -->
                <div class="bg-gray-50 rounded-lg p-4">
                    <h4 class="font-semibold mb-3">Actions</h4>
                    <form action="{{ route('crypto.force-rotation') }}" method="POST" class="space-y-3">
                        @csrf
                        <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                            🔄 Forcer la rotation
                        </button>
                    </form>
                    <p class="text-xs text-gray-500 mt-2 text-center">
                        ⚠️ La rotation génère une nouvelle paire de clés et archive l'ancienne
                    </p>
                </div>
            </div>
            
            <!-- Historique des rotations -->
            @if(count($rotationHistory) > 0)
            <div class="mt-6">
                <h4 class="font-semibold mb-3">📋 Historique des rotations</h4>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left">Date</th>
                                <th class="px-4 py-2 text-left">Ancienne clé</th>
                                <th class="px-4 py-2 text-left">Nouvelle clé</th>
                                <th class="px-4 py-2 text-left">Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rotationHistory as $history)
                            <tr class="border-b">
                                <td class="px-4 py-2">{{ \Carbon\Carbon::parse($history->rotated_at)->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-2 font-mono text-xs">{{ substr($history->old_key_fingerprint, 0, 16) }}...</td>
                                <td class="px-4 py-2 font-mono text-xs">{{ substr($history->new_key_fingerprint, 0, 16) }}...</td>
                                <td class="px-4 py-2">
                                    <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">✅ Succès</span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif
        </div>
    </div>

    <!-- Section 2 : Split-Key (Shamir 2/3) -->
    <div class="bg-white rounded-lg shadow">
        <div class="border-b px-6 py-4 bg-gradient-to-r from-purple-50 to-white">
            <h3 class="text-lg font-semibold">🔑 Split-Key - Shamir Secret Sharing (2/3)</h3>
            <p class="text-sm text-gray-500">Aucune personne seule ne peut compromettre la clé privée</p>
        </div>
        <div class="p-6">
            
            @if(empty($shares))
            <!-- Pas de parts -->
            <div class="text-center py-8">
                <div class="text-6xl mb-4">🔐</div>
                <h4 class="text-lg font-semibold mb-2">Clé non fractionnée</h4>
                <p class="text-gray-500 mb-4">Fractionnez la clé privée en 3 parts pour une sécurité maximale</p>
                <form action="{{ route('crypto.split-key') }}" method="POST">
                    @csrf
                    <button type="submit" class="bg-purple-600 text-white px-6 py-2 rounded-lg hover:bg-purple-700 transition">
                        🔒 Fractionner la clé
                    </button>
                </form>
            </div>
            @else
            <!-- Parts existantes -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                @foreach($shares as $share)
                <div class="border rounded-lg p-4 {{ isset($sharesIntegrity) && is_array($sharesIntegrity) ? (collect($sharesIntegrity)->firstWhere('file', basename($share['file']))['valid'] ?? false ? 'border-green-300 bg-green-50' : 'border-red-300 bg-red-50') : '' }}">
                    <div class="text-center">
                        <div class="text-3xl mb-2">
                            @if($share['role'] == 'admin_ceo') 👨‍💼
                            @elseif($share['role'] == 'dba_ops') 🗄️
                            @elseif($share['role'] == 'hsm_security') 🔒
                            @endif
                        </div>
                        <h4 class="font-semibold">
                            @if($share['role'] == 'admin_ceo') Administrateur / CEO
                            @elseif($share['role'] == 'dba_ops') DBA / Operations
                            @elseif($share['role'] == 'hsm_security') HSM / Sécurité
                            @endif
                        </h4>
                        <p class="text-xs text-gray-500 mt-1">Part #{{ $share['index'] }}</p>
                        <p class="text-xs font-mono mt-2">{{ $share['file'] }}</p>
                        <form action="{{ route('crypto.download-share', $share['role']) }}" method="GET" class="mt-3">
                            <button type="submit" class="w-full bg-gray-600 text-white px-3 py-1 rounded text-sm hover:bg-gray-700 transition">
                                📥 Télécharger
                            </button>
                        </form>
                    </div>
                </div>
                @endforeach
            </div>
            
            <!-- Actions Split-Key -->
            <div class="flex space-x-4">
                <form action="{{ route('crypto.split-key') }}" method="POST" class="flex-1">
                    @csrf
                    <button type="submit" class="w-full bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition">
                        🔄 Re-fractionner (supprime les anciennes parts)
                    </button>
                </form>
                <form action="{{ route('crypto.verify-shares') }}" method="POST" class="flex-1">
                    @csrf
                    <button type="submit" class="w-full bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                        ✅ Vérifier l'intégrité
                    </button>
                </form>
            </div>
            
            <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                <p class="text-sm text-yellow-800">
                    ⚠️ <strong>Règle Shamir 2/3 :</strong> Il faut au moins <strong>2 parts</strong> pour reconstruire la clé.
                    Distribuez ces parts à 3 responsables différents.
                </p>
            </div>
            @endif
        </div>
    </div>

    <!-- Section 3 : Schéma explicatif -->
    <div class="bg-white rounded-lg shadow">
        <div class="border-b px-6 py-4 bg-gradient-to-r from-gray-50 to-white">
            <h3 class="text-lg font-semibold">📐 Architecture cryptographique</h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-center">
                <div class="p-3 bg-blue-50 rounded-lg">
                    <div class="text-2xl mb-2">🔄</div>
                    <h4 class="font-semibold">Rotation 90 jours</h4>
                    <p class="text-xs text-gray-600">Conforme ISO 27001<br>Rotation automatique des clés ECC</p>
                </div>
                <div class="p-3 bg-purple-50 rounded-lg">
                    <div class="text-2xl mb-2">🔑</div>
                    <h4 class="font-semibold">Shamir 2/3</h4>
                    <p class="text-xs text-gray-600">Fractionnement clé privée<br>2 parts nécessaires sur 3</p>
                </div>
                <div class="p-3 bg-green-50 rounded-lg">
                    <div class="text-2xl mb-2">🧙</div>
                    <h4 class="font-semibold">ZK-SNARKs</h4>
                    <p class="text-xs text-gray-600">Preuve à divulgation nulle<br>Vérification sans révéler les données</p>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
