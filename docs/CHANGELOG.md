# Registro sviluppo EA Archive

Ultimo aggiornamento: 18 novembre 2026.

Questo registro riguarda l'applicazione EA Archive. Il CHANGELOG.md nella root proviene dallo scheletro Laravel e non è il registro dello sviluppo dell'applicazione.

## 18 novembre 2026 - Nessun file EA pregresso da recuperare

- Ricevuta conferma dell'utente: non sono presenti sorgenti o compilati locali pregressi da trasferire.
- Semplificata la procedura di rilascio: configurazione diretta del bucket S3 privato vuoto; nessuna copia di file EA richiesta.
- Il comando di copia rimane opzionale per eventuali usi futuri. Nessuna cancellazione di dati, modifica al codice o operazione di storage effettuata.
- Aggiornati stato, architettura, deployment, guida servizi e istruzioni del progetto. Test non rieseguiti per modifica solo documentale.

## 18 novembre 2026 - Localizzazione e servizi esterni (sviluppo, non pubblicato)

- Implementati italiano e inglese tramite file Laravel lang/, helper __(), middleware di locale, selettore con CSRF, preferenza in sessione e users.locale, notifiche con HasLocalePreference. Contenuti degli autori mantenuti nella lingua originale.
- Sostituita la compilazione locale con API HTTPS per agente Windows esterno: prelievo transazionale, token globale e token per lavoro, scadenza 600 secondi, massimo tre tentativi, controllo dei permessi e completamento idempotente.
- Creato agente Python standard-library con template WinSW, wrapper login/server, verifica checksum, timeout MetaEditor, controllo log e cleanup. Nessun servizio installato su questa macchina.
- Aggiunti adapter S3, dischi/checksum per record e comando di copia verificata senza cancellazione degli originali. Immagini EA/broker non migrate. La vecchia classe job resta per compatibilità senza eseguire compilazioni locali.
- Applicata la nuova migrazione solo al database locale e documentata la procedura di aggiornamento della versione pubblicata, incluso lo spegnimento del vecchio worker.
- Verifiche: 48 test Laravel e 252 asserzioni; 5 test Python; viste compilate, route API verificate, Pint e audit Composer superati.
- Non collaudati bucket S3, MetaEditor reale, installazione WinSW, SMTP, MySQL remoto e resa visiva in browser. Nessun rilascio pubblico di queste modifiche.

## 18 novembre 2026 - Pubblicazione confermata

- L'utente ha comunicato che questa versione del sito risulta pubblicata.
- Aggiornati stato, indice e guida di deployment distinguendo la conferma ricevuta dai controlli locali.
- URL, revisione distribuita e configurazione effettiva di produzione non comunicati; nessuna verifica remota o nuova pubblicazione eseguita.
- Modifica solo documentale: test applicativi non rieseguiti.

## 17 novembre 2026 - Nuovo percorso progetto e integrazione logo

- Confermato il progetto spostato in `C:/Sviluppo/ea-archive`; aggiornati i percorsi documentati.
- Integrato il logo PNG esistente nel layout condiviso: sidebar desktop e intestazione mobile, link alla home e testo alternativo.
- Aggiunte regole CSS responsive senza modificare il file immagine; aggiornati BRAND.md, indice e stato.
- Verifica: viste Blade compilate. Nessun test aggiunto per la modifica di layout; verifica visiva in browser non eseguita.

## 17 novembre 2026 — Separazione ambiente di sviluppo e MySQL esterno

- Registrata la conferma dell'utente: questa macchina è dedicata allo sviluppo; MySQL sarà su un server esterno ancora da configurare.
- Aggiornati guida database, deployment, stato e istruzioni del progetto; documentata la separazione delle configurazioni e dei dati tra sviluppo e produzione.
- Nessun servizio locale installato, nessuna connessione remota o migrazione effettuata. Nessuna modifica applicativa; test non rieseguiti per questa precisazione documentale.

## 17 novembre 2026 — Scelta MySQL

- Adottato MySQL come database applicativo di riferimento per richiesta dell'utente, al posto di PostgreSQL previsto nell'architettura iniziale.
- Aggiornati default in config/database.php e `.env.example`, con database/utente dedicati, InnoDB, utf8mb4 e modalità strict.
- Abilitato `pdo_mysql` nel PHP CLI locale; nessuna riconfigurazione IIS eseguita.
- Aggiunta guida DATABASE.md e aggiornati architettura, deployment, indice, README, stato e istruzioni del progetto.
- Verifiche: driver PHP disponibile; nessun servizio MySQL rilevato e porta locale 3306 non raggiungibile. Suite esistente superata su SQLite in memoria: 30 test e 140 asserzioni. Formattazione Pint verificata.
- Nessun test d'integrazione MySQL eseguito: mancano istanza e credenziali. `.env` locale e dati SQLite preservati. Nessuna migrazione di dati effettuata.

## 17 novembre 2026 — Pagina broker e gestione amministrativa

- Aggiunta pagina autenticata `/brokers` con immagine, descrizione e link affiliato per ogni broker attivo.
- Aggiunto accesso dal menu e dal workspace, sostituendo il collegamento al singolo broker configurato in ambiente.
- Creati tabella e indice broker, controller dedicato e gestione admin con creazione, modifica, ordine, attivazione/disattivazione ed eliminazione confermata.
- Immagini private con validazione formato/dimensione, mantenimento quando non sostituite e rimozione alla sostituzione o eliminazione. Accesso alle immagini nascoste limitato agli admin.
- Link HTTPS senza credenziali incorporate, parametri affiliati conservati e indicazione di affiliazione nella UI. Modifiche registrate nell'audit.
- Installato Laravel Boost come dipendenza di sviluppo e generati i suoi file di istruzioni, come richiesto da AGENTS.md, mantenendo la regola di aggiornamento della documentazione.
- Verifiche: 17 test dedicati; suite completa con 30 test superati e 140 asserzioni; migrazione locale applicata, viste compilate, nuove route elencate, formattazione Pint e Composer audit senza avvisi.
- Documentati flussi ed endpoint in BROKERS.md; aggiornati indice, architettura, deployment e stato.
- Limiti: nessun broker reale preinserito; link e immagini devono essere caricati dall'admin. Apertura e verifica conto non automatizzate; nessuna verifica visiva in browser o pubblicazione Internet.

## 17 novembre 2026 — Organizzazione della documentazione

- Consolidata la cartella docs con indice e regole di aggiornamento.
- Aggiunto questo registro delle modifiche del progetto.
- Registrato in AGENTS.md l'obbligo di aggiornare la documentazione insieme alle modifiche future.
- Allineato lo stato della consegna con le verifiche eseguite durante il primo sviluppo.
- Verifica: lettura della documentazione e controllo dei collegamenti relativi. Nessuna modifica al comportamento dell'applicazione; test applicativi non rieseguiti per questa modifica documentale.

## 17 novembre 2026 — Primo MVP

- Creata la base Laravel 13 con PHP 8.4 e dipendenze Composer.
- Implementati catalogo, ricerca, dettaglio EA e interfaccia responsive in italiano.
- Implementati registrazione, login/logout, verifica email, recupero password e modifica nome profilo.
- Aggiunti conti broker con verifica amministrativa manuale.
- Implementati area sviluppatori, upload privato MQ5/immagine, moderazione e abilitazione sviluppatori.
- Implementati audit amministrativo, richiesta compilazione in coda, wrapper su conto/server MT5 e download autorizzati.
- Consentite recensioni solo dopo il download dell'EA.
- Documentati architettura, schema, endpoint, deployment e limiti di sicurezza.
- Verifiche eseguite: 13 test superati con 42 asserzioni, compilazione delle viste Blade, elenco route, risposta HTTP 200 per catalogo e CSS, Composer audit senza avvisi di vulnerabilità.
- Limiti: SMTP reale, broker API, compilazione MetaEditor e isolamento del worker non collaudati; IIS/FastCGI non configurato. Nessuna pubblicazione Internet. Tre schede demo, nessun EA reale incluso.
