# Localizzazione Laravel: italiano e inglese

Ultimo aggiornamento: 18 novembre 2026.

## Funzionamento

La UI usa il sistema nativo Laravel: helper `__()`, file `lang/it.json` e `lang/en.json`, file PHP per validazione, autenticazione e paginazione. Le stringhe italiane sono le chiavi JSON, una delle convenzioni supportate da Laravel. Non vengono aggiunti pacchetti di traduzione.

Il selettore nel layout invia `POST /language` con CSRF. Solo `it` ed `en` sono accettati. La scelta viene salvata in sessione e, se autenticato, in `users.locale`. Il middleware `SetLocale` sceglie sessione, preferenza utente, poi `APP_LOCALE`; usa `App::setLocale` e imposta attributo HTML `lang` e header `Content-Language`. Le risposte dipendenti dalla lingua non vengono condivise tramite cache pubblica.

Il cambio lingua mantiene il percorso precedente sul sito, senza redirect verso host esterni. Gli URL esistenti non cambiano e non ricevono prefissi di lingua, quindi restano validi anche i link firmati di verifica email e i link di reset. Non vengono preservati i parametri di ricerca nel cambio lingua.

La lingua dell'utente appena registrato viene conservata prima dell'invio della verifica email. Il modello implementa `HasLocalePreference` per le notifiche di verifica e recupero password. Le traduzioni italiane delle notifiche standard sono nel file JSON; per inglese vengono usati i testi standard.

## Configurazione e manutenzione

```dotenv
APP_LOCALE=it
APP_FALLBACK_LOCALE=en
```

Le lingue abilitate si trovano in `config/archive.php`. Per una nuova stringa statica aggiungere la stessa chiave ai due JSON e usare `__()` nel template o controller. Per nomi, ID e altri valori dinamici usare placeholder, per esempio `__('Ciao, :name.', ['name' => $user->name])`, lasciando a Blade l'escaping.

I valori del database e dei form (stati, ruoli e rischio) mantengono gli identificatori esistenti; cambia solo l'etichetta visualizzata. Descrizioni EA, nomi e descrizioni dei broker, strategie libere e recensioni restano nella lingua originale dell'autore: questa versione non introduce traduzione automatica o campi editoriali per lingua.

## Verifiche e limiti

Test coprono cambio lingua, persistenza, rifiuto di lingue non supportate, redirect locale, validazione, messaggi, registrazione e rendering inglese delle aree principali. Nessuna verifica visiva in browser o collaudo SMTP reale effettuato per questa modifica.

Riferimento: [localizzazione Laravel 13](https://laravel.com/docs/13.x/localization).
