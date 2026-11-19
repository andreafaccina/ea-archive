# Broker e link di affiliazione

Ultimo aggiornamento: 18 novembre 2026.

## Percorso utente

La voce **Scegli il broker** nel menu e il collegamento nel workspace aprono `/brokers`. La pagina richiede un account autenticato; non richiede ancora la verifica email, così l'utente può consultare i broker prima di collegare un conto.

Ogni scheda contiene immagine, nome, descrizione e pulsante di registrazione. Il pulsante apre il link affiliato configurato dall'amministratore in una nuova scheda, mantenendo i parametri di affiliazione. La pagina segnala che i collegamenti sono affiliati; gli attributi del link sono `sponsored noopener noreferrer`.

Dopo la registrazione sul sito del broker, l'utente torna nel workspace e invia login e server MT5. Questo flusso non crea automaticamente conti presso il broker e non verifica automaticamente l'affiliazione o la proprietà del conto.

## Gestione amministrativa

Da **Amministrazione → Gestisci broker** (`/admin/brokers`):

1. Premere **Aggiungi broker**.
2. Inserire nome, descrizione e URL HTTPS della registrazione con i parametri di affiliazione.
3. Caricare un'immagine JPG, PNG o WebP, massimo 4 MB, **oppure** inserire nel campo **Oppure link immagine · HTTPS** il collegamento diretto a un'immagine pubblica online. Non compilare entrambe le alternative.
4. Impostare l'ordine: i valori più bassi vengono prima; a parità si usa l'ID crescente.
5. Scegliere **Attivo** per pubblicare oppure **Nascosto** per salvare senza mostrare agli utenti.
6. Salvare. La scheda compare immediatamente nell'elenco utente se attiva.

**Modifica** permette di cambiare tutti i campi. Lasciando vuoti sia file sia link si mantiene l'immagine attuale. Un nuovo file sostituisce quello precedente e rimuove l'eventuale URL; un nuovo URL sostituisce il file locale, che viene eliminato dopo il salvataggio. L'eliminazione richiede di aprire **Elimina** e premere **Conferma eliminazione**: rimuove scheda ed eventuale file locale. Le immagini ospitate su siti esterni non vengono cancellate. Nascondere è l'alternativa reversibile alla cancellazione.

Creazione, modifica ed eliminazione sono registrate in `audit_logs`, senza memorizzare gli URL nel log. Utenti standard e sviluppatori non possono gestire broker. L'endpoint del sito permette solo all'admin di richiedere l'immagine di una scheda nascosta; un'immagine già pubblica sul sito esterno mantiene le regole di accesso del suo hosting.

## Implementazione

- Tabella `brokers`: `id`, `name`, `description`, `affiliate_url`, `image_path` nullable, `image_url` nullable, `is_active`, `sort_order`, timestamp. Indice su visibilità, ordine e ID. Per ogni nuova scheda è richiesta una delle due sorgenti immagine.
- `BrokerController` usa il query builder come i flussi esistenti. Dati validati lato server, testo renderizzato con escaping Blade, link solo HTTPS senza credenziali incorporate.
- Immagini caricate sul disco privato `local`, cartella `broker-images`, servite da endpoint autenticato. SVG non accettati per il caricamento.
- Per le immagini via URL, l'endpoint autenticato redirige il browser al link HTTPS senza credenziali incorporate. Laravel non effettua richieste HTTP al sito remoto e non importa il file. Il tipo, la disponibilità e le eventuali restrizioni anti-hotlink dipendono dall'hosting esterno.
- Immagini con `referrerpolicy="no-referrer"` e redirect con lo stesso header; la CSP consente immagini HTTPS esterne, mantenendo bloccati script esterni. Il fornitore dell'immagine riceve la richiesta del browser, non le credenziali del sito.
- Nessuna relazione obbligatoria introdotta tra `brokers` e `broker_accounts`: rimuovere una scheda non altera i conti o gli EA già scaricati.
- Elenchi paginati: 12 schede per gli utenti, 20 per l'amministratore.
- Non vengono precaricati nomi, loghi o link affiliati di broker reali: l'amministratore deve inserirli.
- La precedente variabile `BROKER_REGISTRATION_URL` non viene più usata dalla UI; la fonte dei link di registrazione è ora la tabella gestita dall'admin.

## Endpoint

| Metodo | Percorso | Accesso |
|---|---|---|
| GET | `/brokers` | utenti autenticati, solo attivi |
| GET | `/brokers/{broker}/image` | utenti autenticati, attivi; admin anche nascosti; file locale oppure redirect all'immagine HTTPS |
| GET | `/admin/brokers` | admin, elenco completo |
| GET | `/admin/brokers/create` | admin, form creazione |
| POST | `/admin/brokers` | admin, creazione multipart |
| GET | `/admin/brokers/{broker}/edit` | admin, form modifica |
| PUT | `/admin/brokers/{broker}` | admin, aggiornamento multipart con method spoofing |
| DELETE | `/admin/brokers/{broker}` | admin, eliminazione |

Tutte le mutazioni usano sessione, CSRF e autorizzazione amministrativa lato server.

## Verifiche e rilascio

Migrazioni applicate sul database locale; per l'URL immagine aggiunta `2026_11_18_175806_add_image_url_to_brokers_table`. Su altri ambienti eseguire `php artisan migrate --force` durante il deployment. Nessun dato esistente viene cancellato dalla migrazione di aggiornamento. Il rollback della nuova migrazione viene rifiutato se ci sono broker con solo URL: prima occorre sostituirli con file caricati o ripristinare un backup coerente. Non serve configurare URL immagine nel file `.env`.

28 test dedicati verificano permessi, ordinamento, visibilità, link affiliati, escaping, immagini locali e remote, sostituzioni, validazione, CRUD e audit. Suite completa: 59 test superati, 303 asserzioni; viste compilate. Non eseguita verifica visiva in browser, caricamento di URL reali o pubblicazione di questa modifica.
