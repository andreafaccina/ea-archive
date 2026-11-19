@extends('layout')
@section('crumb', __('Gestione broker'))
@section('content')
<a class="back" href="/admin">{{ __('← Amministrazione') }}</a>
<div class="section-heading"><div><h1>{{ __('Gestione broker') }}</h1><p>{{ __('Gestisci immagini, descrizioni e link affiliati. Solo i broker attivi sono visibili agli utenti.') }}</p></div><a class="button" href="{{ route('admin.brokers.create') }}">{{ __('Aggiungi broker +') }}</a></div>
@forelse($brokers as $broker)
    <article class="panel list-row">
        <img class="broker-thumbnail" src="{{ route('brokers.image', $broker->id) }}" referrerpolicy="no-referrer" alt="{{ $broker->name }}">
        <b>{{ $broker->name }}</b><span>{{ __('Ordine: :order', ['order' => $broker->sort_order]) }}</span><span class="badge">{{ $broker->is_active ? __('Attivo') : __('Nascosto') }}</span>
        <a class="button secondary small" href="{{ route('admin.brokers.edit', $broker->id) }}">{{ __('Modifica') }}</a>
        <details><summary>{{ __('Elimina') }}</summary><form method="post" action="{{ route('admin.brokers.destroy', $broker->id) }}">@csrf @method('DELETE')<p>{{ __('Eliminare definitivamente :name e la sua immagine?', ['name' => $broker->name]) }}</p><button class="button small">{{ __('Conferma eliminazione') }}</button></form></details>
    </article>
@empty
    <section class="panel"><p>{{ __('Non hai ancora aggiunto broker.') }}</p></section>
@endforelse
<div class="pagination">{{ $brokers->links() }}</div>
@endsection
