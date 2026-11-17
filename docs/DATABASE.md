# Database MySQL

Ultimo aggiornamento: 17 novembre 2026.

Per scelta dell'utente, il database applicativo di riferimento è **MySQL**. La configurazione predefinita e `.env.example` usano `mysql`, InnoDB, charset `utf8mb4`, collation `utf8mb4_unicode_ci` e modalità strict.

## Configurazione

L'utente ha confermato che questa macchina è dedicata allo sviluppo e che MySQL sarà su un server esterno ancora da configurare. Non installare MySQL qui. Host, accesso di rete e credenziali saranno definiti per l'istanza esterna. Se si userà tale istanza anche durante lo sviluppo, creare un database dedicato allo sviluppo, distinto da quello di produzione.

Creare sull'istanza MySQL il database `ea_archive`, con charset e collation indicati, e un utente dedicato. Non utilizzare root nell'applicazione. L'utente delle migrazioni deve poter creare e modificare tabelle, indici e vincoli; in produzione si possono separare le credenziali di deploy da quelle applicative.

Inserire nel file locale `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=mysql.example.invalid
DB_PORT=3306
DB_DATABASE=ea_archive
DB_USERNAME=ea_archive
DB_PASSWORD="password-da-configurare-localmente"
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci
```

Sostituire host, database, utente e password con i dati reali. Non condividere le credenziali in chat o nel repository. Se presente, `DB_URL` ha precedenza sui parametri di connessione: allinearlo oppure rimuoverlo. Per un server remoto configurare TLS con verifica del certificato e `MYSQL_ATTR_SSL_CA` secondo l'infrastruttura.

L'host dell'esempio è un segnaposto non utilizzabile. Conservare configurazioni `.env` e database separati per sviluppo e produzione; non collegare i test automatici al database di produzione.

Abilitare `pdo_mysql` nel PHP usato da CLI, IIS/FastCGI e worker. L'abilitazione del PHP CLI non riconfigura automaticamente IIS.

## Inizializzazione di un database vuoto

```powershell
C:/tools/php/php.exe artisan config:clear
C:/tools/php/php.exe artisan migrate --no-interaction
```

In produzione usare `migrate --force` durante il deployment, poi ricostruire la cache di configurazione e riavviare i worker. La creazione delle tabelle include utenti, sessioni, cache, code, conti, EA, download, recensioni, audit e broker.

Non usare `migrate:fresh` su database con dati. Le migrazioni creano lo schema: non trasferiscono i dati da SQLite. Un eventuale trasferimento deve preservare ID, hash password, riferimenti ai file e relazioni, con backup e verifica prima del passaggio.

## Ambiente locale e test

Il precedente `.env` locale resta su SQLite finché non viene fornita un'istanza MySQL configurata. Il file SQLite esistente non viene cancellato né convertito. Non viene attestata una connessione MySQL funzionante prima di avere host e credenziali.

La suite PHPUnit continua a usare SQLite in memoria (`phpunit.xml`), separato dal database applicativo. Questi test verificano i flussi applicativi ma non dimostrano compatibilità e concorrenza su un server MySQL reale. Prima del rilascio occorre applicare le migrazioni e collaudare i flussi su un database MySQL dedicato; non eseguire test con `RefreshDatabase` sul database applicativo reale.
