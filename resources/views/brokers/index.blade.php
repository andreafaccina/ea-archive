@extends('layout')
@section('title', 'Scegli il broker · EA Archive')
@section('crumb', 'Scegli il broker')
@section('content')
<div class="eyebrow">IL PRIMO PASSO PER IL TUO WORKSPACE</div>
<h1>Scegli il tuo broker.</h1>
<p>Scopri i broker disponibili e apri il conto sul sito del partner. Dopo la registrazione, torna nel workspace per inviare il numero di conto e il server alla verifica.</p>
<div class="notice">I collegamenti di registrazione sono link di affiliazione: EA Archive potrebbe ricevere una commissione. Valuta condizioni, costi e rischi direttamente sul sito del broker.</div>
<div class="cards">
    @forelse($brokers as $broker)
        <article class="card broker-card">
            <div class="broker-image"><img src="{{ route('brokers.image', $broker->id) }}" alt="{{ $broker->name }}" loading="lazy"></div>
            <div class="card-body">
                <h2>{{ $broker->name }}</h2>
                <p class="description broker-description">{{ $broker->description }}</p>
                <div class="broker-actions"><a class="button" href="{{ $broker->affiliate_url }}" target="_blank" rel="sponsored noopener noreferrer">Apri il conto sul sito del broker ↗</a><small>Link affiliato · si apre in una nuova scheda</small></div>
            </div>
        </article>
    @empty
        <section class="panel empty"><h2>Nessun broker disponibile</h2><p>L'amministratore non ha ancora pubblicato broker. Torna a visitare questa pagina per gli aggiornamenti.</p></section>
    @endforelse
</div>
<div class="pagination">{{ $brokers->links() }}</div>
<div class="developer-banner"><div><h2>Hai già aperto il conto?</h2><p>Invia numero conto e server. La verifica viene effettuata dall'amministratore.</p></div><a class="button secondary" href="/account">Vai al workspace →</a></div>
@endsection
