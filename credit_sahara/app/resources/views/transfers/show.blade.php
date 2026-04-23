@extends('layouts.app')

@section('title', 'Détail transaction')
@section('header', '🔍 Détail de la transaction')

@section('content')
<div class="bg-white rounded-lg shadow p-6">
    <p><strong>UUID:</strong> {{ $transfer->uuid }}</p>
    <p><strong>Statut:</strong> {{ $transfer->status }}</p>
    <p><strong>Date:</strong> {{ $transfer->created_at }}</p>
    <a href="{{ route('transfers.index') }}" class="mt-4 inline-block bg-gray-500 text-white px-4 py-2 rounded">Retour</a>
</div>
@endsection
