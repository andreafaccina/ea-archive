@extends('layout')
@section('crumb', 'Gestione broker')
@section('content')
<a class="back" href="{{ route('admin.brokers.index') }}">← Gestione broker</a>
<h1>{{ $broker ? 'Modifica broker' : 'Aggiungi un broker' }}</h1>
<form class="panel broker-form" method="post" action="{{ $broker ? route('admin.brokers.update', $broker->id) : route('admin.brokers.store') }}" enctype="multipart/form-data">
    @csrf
    @if($broker) @method('PUT') @endif
    <label>Nome broker<input name="name" maxlength="120" value="{{ old('name', $broker?->name) }}" required></label>
    <label>Descrizione<textarea name="description" maxlength="10000" required>{{ old('description', $broker?->description) }}</textarea></label>
    <label>Link di registrazione con affiliazione · HTTPS<input type="url" name="affiliate_url" maxlength="2048" value="{{ old('affiliate_url', $broker?->affiliate_url) }}" placeholder="https://broker.example/register?ref=..." required></label>
    @if($broker)<img class="broker-preview" src="{{ route('brokers.image', $broker->id) }}" alt="Immagine attuale di {{ $broker->name }}">@endif
    <label>Immagine broker · JPG, PNG o WebP, massimo 4 MB<input type="file" name="image" accept="image/jpeg,image/png,image/webp" @required(!$broker)></label>
    @if($broker)<small>Lascia vuoto per mantenere l'immagine attuale.</small>@endif
    <label>Ordine di visualizzazione<input type="number" name="sort_order" min="0" max="1000000" value="{{ old('sort_order', $broker?->sort_order ?? 0) }}" required></label>
    <small>I numeri più bassi vengono mostrati per primi.</small>
    <label>Visibilità<select name="is_active"><option value="0" @selected((string)old('is_active', $broker?->is_active ?? 0)==='0')>Nascosto</option><option value="1" @selected((string)old('is_active', $broker?->is_active ?? 0)==='1')>Attivo: visibile agli utenti</option></select></label>
    <button class="button">Salva broker →</button>
</form>
@endsection
