@extends('layouts.app')

@section('title', 'Dashboard - Crédit du Sahara')
@section('header', 'Tableau de bord')

@section('content')
<div x-data="{ stats: { total: 0, pending: 0, completed: 0 } }" x-init="fetch('/api/stats').then(r=>r.json()).then(d=>stats=d)">
    <!-- Statistiques -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 bg-blue-100 rounded-full">
                    <span class="text-2xl">💰</span>
                </div>
                <div class="ml-4">
                    <p class="text-gray-500 text-sm">Total envoyé</p>
                    <p class="text-2xl font-bold" x-text="new Intl.NumberFormat().format(stats.total) + ' DH'">0 DH</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 bg-yellow-100 rounded-full">
                    <span class="text-2xl">⏳</span>
                </div>
                <div class="ml-4">
                    <p class="text-gray-500 text-sm">En attente</p>
                    <p class="text-2xl font-bold" x-text="stats.pending">0</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 bg-green-100 rounded-full">
                    <span class="text-2xl">✅</span>
                </div>
                <div class="ml-4">
                    <p class="text-gray-500 text-sm">Traités</p>
                    <p class="text-2xl font-bold" x-text="stats.completed">0</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Formulaire rapide -->
    <div class="bg-white rounded-lg shadow mb-8">
        <div class="border-b px-6 py-4">
            <h3 class="text-lg font-semibold">🚀 Nouveau virement rapide</h3>
        </div>
        <div class="p-6">
            <form action="{{ route('transfers.store') }}" method="POST">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Montant (DH)</label>
                        <input type="number" name="amount" required class="w-full border rounded-lg px-4 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Devise</label>
                        <select name="currency" class="w-full border rounded-lg px-4 py-2">
                            <option value="MAD">MAD - Dirham Marocain</option>
                            <option value="EUR">EUR - Euro</option>
                            <option value="USD">USD - Dollar US</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">IBAN Destinataire</label>
                        <input type="text" name="to_iban" placeholder="NO987654321098765432109876" required class="w-full border rounded-lg px-4 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea name="description" rows="2" class="w-full border rounded-lg px-4 py-2" placeholder="Motif..."></textarea>
                    </div>
                </div>
                <input type="hidden" name="from_iban" value="SA123456789012345678901234">
                <div class="mt-6">
                    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                        📤 Envoyer le virement
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Derniers transferts -->
    <div class="bg-white rounded-lg shadow">
        <div class="border-b px-6 py-4">
            <h3 class="text-lg font-semibold">📋 Derniers transferts</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">UUID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Montant</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Statut</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transfers ?? [] as $transfer)
                    <tr>
                        <td class="px-6 py-4 text-sm">{{ substr($transfer->uuid, 0, 13) }}...</td>
                        <td class="px-6 py-4 text-sm font-semibold">{{ number_format($transfer->amount ?? 5000) }} DH</td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800">
                                {{ $transfer->status ?? 'pending' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm">{{ \Carbon\Carbon::parse($transfer->created_at)->diffForHumans() }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-6 py-8 text-center text-gray-500">Aucun transfert effectué</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
