# Avvio e produzione

Ultimo aggiornamento: 18 novembre 2026.

## Stato pubblicazione

Il 18 novembre 2026 l'utente ha confermato la pubblicazione della versione corrente. Nessun deployment eseguito o verificato dall'assistente in questa occasione; URL e configurazione effettiva non comunicati. Le istruzioni seguenti restano la guida per installazioni e aggiornamenti e non certificano quali passaggi siano stati eseguiti in produzione.

## Locale su questo server

Cartella di sviluppo attuale: `C:/Sviluppo/ea-archive`; eseguire i comandi da questa directory. In produzione usare la sottocartella `public` del deployment effettivo.

Questo server è dedicato allo sviluppo. Il database MySQL previsto risiederà su un server esterno ancora da configurare; non è richiesta l'installazione di MySQL su questa macchina. Le impostazioni di produzione vanno mantenute separate da quelle locali.

Installati PHP CLI in `C:/tools/php` e dipendenze Composer. PHP non è stato aggiunto al PATH globale. L'assistente non ha effettuato la pubblicazione o riconfigurato IIS; la pubblicazione esterna risulta confermata dall'utente il 18 novembre 2026.

```powershell
C:/tools/php/php.exe C:/tools/php/composer.phar install
Copy-Item .env.example .env
C:/tools/php/php.exe artisan key:generate
C:/tools/php/php.exe artisan migrate
C:/tools/php/php.exe artisan db:seed
C:/tools/php/php.exe artisan serve --host=127.0.0.1
```

Non ripetere la copia di `.env` o key:generate su un ambiente già configurato. Il seeder crea solo un autore sviluppatore con password casuale non pubblicata e schede dimostrative locali, non conti/admin predefiniti. Le schede demo non hanno sorgente compilabile.

Le nuove installazioni usano MySQL da `.env.example`: creare prima database e utente e configurare `.env` seguendo [DATABASE.md](DATABASE.md), prima di eseguire `migrate`. L'ambiente locale già inizializzato resta temporaneamente su SQLite in attesa dell'istanza MySQL; non sovrascriverne `.env`.

Creare l'admin dopo una normale registrazione:

```powershell
C:/tools/php/php.exe artisan archive:admin email@example.com
```

Il comando richiede che l'utente esista già. Non registra password in shell.

## Configurazione richiesta per rilascio pubblico

- IIS sito con physical path **solo `C:/Sviluppo/ea-archive/public`**, HTTPS, URL Rewrite e FastCGI PHP. Non esporre la root del repository. Il web.config nella root ne blocca l'accesso HTTP accidentale.
- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://dominio`, `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`. Conservare APP_KEY e segreti in secret store. Permessi scrittura solo storage/bootstrap/cache.
- MySQL con InnoDB e driver PHP `pdo_mysql`, charset `utf8mb4`, backup cifrati e prove restore. Il driver è abilitato nel PHP CLI di questo server; verificare separatamente PHP FastCGI e worker. SQLite resta per i test in memoria e temporaneamente per l'ambiente locale esistente. Vedi [DATABASE.md](DATABASE.md).
- Per immagini broker fino a 4 MB, configurare PHP `upload_max_filesize` almeno `4M` e `post_max_size` almeno `8M`, con limite IIS coerente. I limiti effettivi dipendono dalla configurazione FastCGI usata in produzione.
- Configurare i broker e i link affiliati HTTPS da **Amministrazione → Gestisci broker**; `BROKER_REGISTRATION_URL` non è più usato dalla UI. Applicare la migrazione `create_brokers_table` durante il rilascio. La verifica manuale richiede una procedura con prove esterne; API broker non implementata finché non viene scelto il fornitore. Vedi [BROKERS.md](BROKERS.md).
- Per compilazione e file EA configurare il servizio Windows separato e S3 privato secondo [EXTERNAL_SERVICES.md](EXTERNAL_SERVICES.md). Il sito non esegue più MetaEditor e non invia nuovi lavori alla coda Laravel compilers. Fermare il vecchio worker prima del cambio versione.
- Impostare `EA_FILESYSTEM_DISK=s3`, parametri AWS/S3 e token servizio; installare separatamente l'agente Windows. Il token non va condiviso con gli utenti. Non occorrono credenziali DB o S3 sul servizio.
- Per il cambio dalla versione pubblicata: backup, manutenzione, dipendenze, migrazione schema, configurazione S3 e collaudo del servizio, ricostruzione cache, riapertura. Non sono presenti file EA pregressi da recuperare: configurare direttamente il bucket privato, senza eseguire il comando di copia.
- Configurare PHP `upload_max_filesize` almeno `16M`, `post_max_size` almeno `20M` e limite proxy/IIS coerente per ricevere i compilati fino a 16 MB.
- `composer install --no-dev --optimize-autoloader`, `php artisan migrate --force`, `php artisan optimize`, smoke test e rollback applicativo con backup DB.
- Configurare logging centralizzato, allarmi coda/failure e cleanup degli artefatti secondo una policy definita. Il servizio Windows elimina i propri file temporanei; cancellare i residui dei crash con un job operativo supervisionato.
- Verifica email e recupero password sono implementati; collegamento conto, upload, build e recensioni richiedono email verificata. Configurare SMTP prima di abilitarli al pubblico: in locale il mailer scrive i link nei log privati.
- Prima del lancio: collaudo SMTP, termini/privacy, procedura supporto/revoca e cancellazione dati, revisione sicurezza, verifica compatibilità MetaEditor con un vero EA e conto demo. Questi passaggi non sono attestati dal test suite locale.

## Localizzazione

L'opzione immagine broker tramite URL richiede la migrazione `2026_11_18_175806_add_image_url_to_brokers_table`. L'URL viene salvato dall'admin e l'immagine viene caricata dal browser; nessuna configurazione di bucket o importazione lato server necessaria per questa opzione. Vedi [BROKERS.md](BROKERS.md).

Configurare `APP_LOCALE=it` e `APP_FALLBACK_LOCALE=en`; il selettore supporta italiano e inglese. Le preferenze utente richiedono la nuova migrazione. Vedi [LOCALIZATION.md](LOCALIZATION.md).

## Verifiche

Laravel Boost è stato installato tra le dipendenze di sviluppo come richiesto dalle istruzioni del repository; i relativi file AGENTS.md/CLAUDE.md sono stati aggiornati mantenendo la regola documentale. Non è richiesto in produzione con `composer install --no-dev`.

```powershell
C:/tools/php/php.exe artisan test
C:/tools/php/php.exe artisan route:list
C:/tools/php/php.exe artisan view:cache
```

Non è disponibile MetaEditor in questo ambiente: l'integrazione reale di compilazione deve essere collaudata su host Windows isolato. Senza servizio esterno disponibile i lavori rimangono in coda; non vengono prodotti download fittizi.
