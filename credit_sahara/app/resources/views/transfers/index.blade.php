@extends('layouts.app')

@section('title', 'Historique')
@section('header', '📋 Historique des virements')

@section('content')
<div class="bg-white rounded-lg shadow">
    <div class="p-6">
        <table class="w-full">
            <thead>
                <tr class="border-b">
                    <th class="text-left py-2">UUID</th>
                    <th class="text-left py-2">Montant</th>
                    <th class="text-left py-2">Statut</th>
                    <th class="text-left py-2">Date</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transfers as $transfer)
                <tr class="border-b">
                    <td class="py-2 text-sm">{{ substr($transfer->uuid, 0, 13) }}...</td>
                    <td class="py-2">{{ number_format($transfer->amount ?? 5000) }} DH</td>
                    <td class="py-2">{{ $transfer->status ?? 'pending' }}</td>
                    <td class="py-2">{{ \Carbon\Carbon::parse($transfer->created_at)->format('d/m/Y H:i') }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="py-4 text-center">Aucun virement</td>
                </tr>
                @endforelse
            </tbody>
        </table>
        {{ $transfers->links() ?? '' }}
    </div>
</div>
@endsection
