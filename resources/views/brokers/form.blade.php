@extends('layout')
@section('crumb', __('Gestione broker'))
@section('content')
<a class="back" href="{{ route('admin.brokers.index') }}">{{ __('← Gestione broker') }}</a>
<h1>{{ $broker ? __('Modifica broker') : __('Aggiungi un broker') }}</h1>
<form class="panel broker-form" method="post" action="{{ $broker ? route('admin.brokers.update', $broker->id) : route('admin.brokers.store') }}" enctype="multipart/form-data">
    @csrf
    @if($broker) @method('PUT') @endif
    <label>{{ __('Nome broker') }}<input name="name" maxlength="120" value="{{ old('name', $broker?->name) }}" required></label>
    <label>{{ __('Descrizione') }}<textarea name="description" maxlength="10000" required>{{ old('description', $broker?->description) }}</textarea></label>
    <label>{{ __('Link di registrazione con affiliazione · HTTPS') }}<input type="url" name="affiliate_url" maxlength="2048" value="{{ old('affiliate_url', $broker?->affiliate_url) }}" placeholder="https://broker.example/register?ref=..." required></label>
    @if($broker)<img class="broker-preview" src="{{ route('brokers.image', $broker->id) }}" referrerpolicy="no-referrer" alt="{{ __('Immagine attuale di :name', ['name' => $broker->name]) }}">@endif
    <label>{{ __('Immagine broker · JPG, PNG o WebP, massimo 4 MB') }}<input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>
    <label>{{ __('Oppure link immagine · HTTPS') }}<input type="url" name="image_url" maxlength="2048" value="{{ old('image_url') }}" placeholder="https://example.com/logo.png"></label>
    <small>{{ __('Inserisci il link diretto a un’immagine pubblica oppure carica un file.') }}</small>
    @if($broker)<p><small>{{ __('Lascia entrambi i campi vuoti per mantenere l’immagine attuale.') }}</small></p>@endif
    <label>{{ __('Ordine di visualizzazione') }}<input type="number" name="sort_order" min="0" max="1000000" value="{{ old('sort_order', $broker?->sort_order ?? 0) }}" required></label>
    <small>{{ __('I numeri più bassi vengono mostrati per primi.') }}</small>
    <label>{{ __('Visibilità') }}<select name="is_active"><option value="0" @selected((string)old('is_active', $broker?->is_active ?? 0)==='0')>{{ __('Nascosto') }}</option><option value="1" @selected((string)old('is_active', $broker?->is_active ?? 0)==='1')>{{ __('Attivo: visibile agli utenti') }}</option></select></label>
    <button class="button">{{ __('Salva broker →') }}</button>
</form>
@endsection
