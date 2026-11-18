# EA Archive
website for download expert advisor for registered user after affiliation to a broker.

MVP Laravel 13 / PHP 8.4 per un catalogo di Expert Advisor MetaTrader 5 compilati per conti broker verificati.

Database applicativo scelto: **MySQL** (InnoDB, utf8mb4). Il collegamento all'istanza effettiva è da configurare; l'ambiente locale esistente resta temporaneamente su SQLite.

La documentazione dello sviluppo si trova in [docs/README.md](docs/README.md), con indice, stato del progetto e registro delle modifiche. Va aggiornata insieme alle modifiche al codice.

- [Architettura, schema database, endpoint e struttura UI](docs/ARCHITECTURE.md)
- [Avvio locale e deployment IIS](docs/DEPLOYMENT.md)
- [Stato e limiti della consegna](docs/STATUS.md)
- [Pagina broker e gestione link affiliati](docs/BROKERS.md)
- [Configurazione MySQL](docs/DATABASE.md)

## Avvio su questo ambiente

Cartella del progetto: `C:/Sviluppo/ea-archive`. Eseguire i comandi da questa directory.

Le dipendenze e il database locale sono gi? inizializzati.

```powershell
C:/tools/php/php.exe artisan serve --host=127.0.0.1
```

Aprire http://127.0.0.1:8000 sul server. Registrare un account; il mailer locale scrive i link di verifica e reset in storage/logs/laravel.log (file privato).

Per promuovere un utente registrato ad amministratore:

```powershell
C:/tools/php/php.exe artisan archive:admin email@example.com
```

## Verifica

```powershell
C:/tools/php/php.exe artisan test
```

MetaEditor, broker API e SMTP reali non sono configurati. Non distribuire pubblicamente prima dei passaggi indicati nella guida di deployment. Nessun EA reale ? incluso nelle schede demo.
