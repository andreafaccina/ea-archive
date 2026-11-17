# Broker e link di affiliazione

Ultimo aggiornamento: 17 novembre 2026.

## Percorso utente

La voce **Scegli il broker** nel menu e il collegamento nel workspace aprono `/brokers`. La pagina richiede un account autenticato; non richiede ancora la verifica email, così l'utente può consultare i broker prima di collegare un conto.

Ogni scheda contiene immagine, nome, descrizione e pulsante di registrazione. Il pulsante apre il link affiliato configurato dall'amministratore in una nuova scheda, mantenendo i parametri di affiliazione. La pagina segnala che i collegamenti sono affiliati; gli attributi del link sono `sponsored noopener noreferrer`.

Dopo la registrazione sul sito del broker, l'utente torna nel workspace e invia login e server MT5. Questo flusso non crea automaticamente conti presso il broker e non verifica automaticamente l'affiliazione o la proprietà del conto.

## Gestione amministrativa

Da **Amministrazione → Gestisci broker** (`/admin/brokers`):

1. Premere **Aggiungi broker**.
2. Inserire nome, descrizione e URL HTTPS della registrazione con i parametri di affiliazione.
3. Caricare immagine JPG, PNG o WebP, massimo 4 MB.
4. Impostare l'ordine: i valori più bassi vengono prima; a parità si usa l'ID crescente.
5. Scegliere **Attivo** per pubblicare oppure **Nascosto** per salvare senza mostrare agli utenti.
6. Salvare. La scheda compare immediatamente nell'elenco utente se attiva.

**Modifica** permette di cambiare tutti i campi. Se non viene caricata una nuova immagine, viene mantenuta quella attuale. Una nuova immagine sostituisce la precedente e ne rimuove il file. L'eliminazione richiede di aprire **Elimina** e premere **Conferma eliminazione**: rimuove scheda e immagine. Nascondere è l'alternativa reversibile alla cancellazione.

Creazione, modifica ed eliminazione sono registrate in `audit_logs`, senza memorizzare l'URL affiliato nel log. Utenti standard e sviluppatori non possono gestire broker. Solo l'admin può vedere le immagini delle schede nascoste.

## Implementazione

- Tabella `brokers`: `id`, `name`, `description`, `affiliate_url`, `image_path`, `is_active`, `sort_order`, timestamp. Indice su visibilità, ordine e ID.
- `BrokerController` usa il query builder come i flussi esistenti. Dati validati lato server, testo renderizzato con escaping Blade, link solo HTTPS senza credenziali incorporate.
- Immagini sul disco privato `local`, cartella `broker-images`, servite da endpoint autenticato. SVG non accettati.
- Nessuna relazione obbligatoria introdotta tra `brokers` e `broker_accounts`: rimuovere una scheda non altera i conti o gli EA già scaricati.
- Elenchi paginati: 12 schede per gli utenti, 20 per l'amministratore.
- Non vengono precaricati nomi, loghi o link affiliati di broker reali: l'amministratore deve inserirli.
- La precedente variabile `BROKER_REGISTRATION_URL` non viene più usata dalla UI; la fonte dei link di registrazione è ora la tabella gestita dall'admin.

## Endpoint

| Metodo | Percorso | Accesso |
|---|---|---|
| GET | `/brokers` | utenti autenticati, solo attivi |
| GET | `/brokers/{broker}/image` | utenti autenticati, attivi; admin anche nascosti |
| GET | `/admin/brokers` | admin, elenco completo |
| GET | `/admin/brokers/create` | admin, form creazione |
| POST | `/admin/brokers` | admin, creazione multipart |
| GET | `/admin/brokers/{broker}/edit` | admin, form modifica |
| PUT | `/admin/brokers/{broker}` | admin, aggiornamento multipart con method spoofing |
| DELETE | `/admin/brokers/{broker}` | admin, eliminazione |

Tutte le mutazioni usano sessione, CSRF e autorizzazione amministrativa lato server.

## Verifiche e rilascio

Migrazione applicata sul database locale; su altri ambienti eseguire `php artisan migrate --force` durante il deployment. Non serve configurare URL nel file `.env`. Infrastruttura e permessi storage restano quelli descritti in [DEPLOYMENT.md](DEPLOYMENT.md).

17 test dedicati verificano permessi, ordinamento, visibilità, link affiliati, escaping, immagini, CRUD e audit. Suite completa: 30 test superati, 140 asserzioni; viste compilate e route verificate. Non eseguita verifica visiva in browser né apertura di account broker reali.
