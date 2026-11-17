# Stato della consegna

Ultimo aggiornamento: 17 novembre 2026.

## Verifiche effettuate

Dopo l'aggiunta della gestione broker: 30 test superati con 140 asserzioni, inclusi 17 test dedicati ai broker. Migrazione applicata localmente, viste Blade compilate, otto nuove route elencate e Composer audit senza avvisi. Verifica visiva in browser non effettuata.

Durante il primo sviluppo: 13 test superati, 42 asserzioni; viste Blade compilate; route elencate; catalogo e CSS con risposta HTTP 200; Composer audit senza avvisi di vulnerabilità. Non è stata effettuata una verifica visiva in browser.

## Funzionalità e limiti

Ambienti confermati dall'utente: questa macchina serve solo per sviluppo; MySQL sarà su un server esterno da configurare. Nessuna installazione MySQL locale prevista. Host e credenziali esterni non ancora disponibili; nessun collegamento remoto effettuato.

Database di riferimento aggiornato a MySQL per scelta dell'utente: default applicativo e `.env.example` configurati, InnoDB e utf8mb4. Driver `pdo_mysql` abilitato nel PHP CLI. Nessun servizio MySQL rilevato sul server e porta locale 3306 non raggiungibile durante il controllo; nessun database MySQL creato o migrazione dati effettuata. `.env` locale e SQLite esistente preservati in attesa della configurazione dell'istanza. Vedi [DATABASE.md](DATABASE.md).

Aggiunta la pagina autenticata **Scegli il broker** con immagini, descrizioni e link affiliati. Gestione amministrativa completa: creazione, modifica, ordine, visibilità ed eliminazione, con audit. Vedi [BROKERS.md](BROKERS.md). Nessun broker reale precaricato; la verifica dei conti resta manuale.

Implementati catalogo e dettaglio, ricerca, registrazione/login/logout, verifica email, reset password, profilo, conti in verifica manuale, upload sorgenti e immagine, abilitazione sviluppatori, moderazione, audit, compilazione in coda, download privati e recensioni dopo download.

Il catalogo locale contiene tre schede dimostrative. Non contiene EA reali o metriche finanziarie verificate. Nessun amministratore con password predefinita. Nessuna pubblicazione Internet.

Non collaudati: SMTP, account broker reali, compilazione MetaEditor/EX5 e isolamento OS del worker. Nessun collegamento API broker finché non viene scelto il partner. Il server ha ora PHP CLI e Composer in C:/tools/php, ma IIS/FastCGI non è stato configurato.

La base è eseguibile e testata localmente; il rilascio pubblico richiede i passaggi in DEPLOYMENT.md. La capacità a milioni di utenti richiede infrastruttura distribuita, load test e metriche: non viene dichiarata come già verificata.
