@extends('layout')
@section('title', __('Scegli il broker · EA Archive'))
@section('crumb', __('Scegli il broker'))
@section('content')
<div class="eyebrow">{{ __('IL PRIMO PASSO PER IL TUO WORKSPACE') }}</div>
<h1>{{ __('Scegli il tuo broker.') }}</h1>
<p>{{ __('Scopri i broker disponibili e apri il conto sul sito del partner. Dopo la registrazione, torna nel workspace per inviare il numero di conto e il server alla verifica.') }}</p>
<div class="notice">{{ __('I collegamenti di registrazione sono link di affiliazione: EA Archive potrebbe ricevere una commissione. Valuta condizioni, costi e rischi direttamente sul sito del broker.') }}</div>
<div class="cards">
    @forelse($brokers as $broker)
        <article class="card broker-card">
            <div class="broker-image"><img src="{{ route('brokers.image', $broker->id) }}" alt="{{ $broker->name }}" loading="lazy"></div>
            <div class="card-body">
                <h2>{{ $broker->name }}</h2>
                <p class="description broker-description">{{ $broker->description }}</p>
                <div class="broker-actions"><a class="button" href="{{ $broker->affiliate_url }}" target="_blank" rel="sponsored noopener noreferrer">{{ __('Apri il conto sul sito del broker ↗') }}</a><small>{{ __('Link affiliato · si apre in una nuova scheda') }}</small></div>
            </div>
        </article>
    @empty
        <section class="panel empty"><h2>{{ __('Nessun broker disponibile') }}</h2><p>{{ __('L\'amministratore non ha ancora pubblicato broker. Torna a visitare questa pagina per gli aggiornamenti.') }}</p></section>
    @endforelse
</div>
<div class="pagination">{{ $brokers->links() }}</div>
<div class="developer-banner"><div><h2>{{ __('Hai già aperto il conto?') }}</h2><p>{{ __('Invia numero conto e server. La verifica viene effettuata dall\'amministratore.') }}</p></div><a class="button secondary" href="/account">{{ __('Vai al workspace →') }}</a></div>
@endsection
