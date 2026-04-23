@extends('layouts.app')

@section('title', 'Nouveau virement')
@section('header', '💰 Nouveau virement')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-lg shadow p-6">
        <form action="{{ route('transfers.store') }}" method="POST">
            @csrf
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-2">Montant (DH)</label>
                    <input type="number" name="amount" required class="w-full border rounded-lg px-4 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">IBAN Destinataire</label>
                    <input type="text" name="to_iban" required class="w-full border rounded-lg px-4 py-2">
                </div>
                <input type="hidden" name="from_iban" value="SA123456789012345678901234">
                <input type="hidden" name="currency" value="MAD">
                <div>
                    <label class="block text-sm font-medium mb-2">Description</label>
                    <textarea name="description" rows="3" class="w-full border rounded-lg px-4 py-2"></textarea>
                </div>
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg w-full">Envoyer</button>
            </div>
        </form>
    </div>
</div>
@endsection
