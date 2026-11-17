@extends('layout')
@section('crumb', 'Gestione broker')
@section('content')
<a class="back" href="/admin">← Amministrazione</a>
<div class="section-heading"><div><h1>Gestione broker</h1><p>Gestisci immagini, descrizioni e link affiliati. Solo i broker attivi sono visibili agli utenti.</p></div><a class="button" href="{{ route('admin.brokers.create') }}">Aggiungi broker +</a></div>
@forelse($brokers as $broker)
    <article class="panel list-row">
        <img class="broker-thumbnail" src="{{ route('brokers.image', $broker->id) }}" alt="{{ $broker->name }}">
        <b>{{ $broker->name }}</b><span>Ordine: {{ $broker->sort_order }}</span><span class="badge">{{ $broker->is_active ? 'Attivo' : 'Nascosto' }}</span>
        <a class="button secondary small" href="{{ route('admin.brokers.edit', $broker->id) }}">Modifica</a>
        <details><summary>Elimina</summary><form method="post" action="{{ route('admin.brokers.destroy', $broker->id) }}">@csrf @method('DELETE')<p>Eliminare definitivamente {{ $broker->name }} e la sua immagine?</p><button class="button small">Conferma eliminazione</button></form></details>
    </article>
@empty
    <section class="panel"><p>Non hai ancora aggiunto broker.</p></section>
@endforelse
<div class="pagination">{{ $brokers->links() }}</div>
@endsection
