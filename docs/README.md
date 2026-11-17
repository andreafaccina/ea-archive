# Documentazione EA Archive

Ultimo aggiornamento: 17 novembre 2026.

Questa cartella raccoglie la documentazione dello sviluppo del progetto.

| Documento | Contenuto |
|---|---|
| [ARCHITECTURE.md](ARCHITECTURE.md) | Architettura di sistema e UI, struttura file, schema database, endpoint e sicurezza della compilazione |
| [DEPLOYMENT.md](DEPLOYMENT.md) | Installazione, avvio locale, configurazione IIS, worker e requisiti di produzione |
| [STATUS.md](STATUS.md) | Funzionalità implementate, verifiche effettuate e limiti attuali |
| [CHANGELOG.md](CHANGELOG.md) | Registro cronologico delle modifiche al progetto |
| [BROKERS.md](BROKERS.md) | Pagina broker, gestione amministrativa, immagini e link affiliati |
| [DATABASE.md](DATABASE.md) | Configurazione MySQL, inizializzazione e separazione dei database di test |

## Regola di aggiornamento

La documentazione va aggiornata insieme a ogni modifica significativa al progetto, nello stesso intervento sul codice:

- Registrare nel changelog data, modifica, verifiche effettuate e limiti ancora presenti.
- Aggiornare ARCHITECTURE.md quando cambiano struttura, database, endpoint, ruoli, UI o flusso di compilazione.
- Aggiornare DEPLOYMENT.md quando cambiano dipendenze, configurazione, comandi, servizi o procedura di rilascio.
- Aggiornare STATUS.md quando cambia lo stato di una funzionalità, di un'integrazione o delle verifiche.
- Aggiungere all'indice ogni nuovo documento e aggiornare la data dei documenti modificati.

Descrivere lo stato effettivo: distinguere ciò che è implementato da ciò che è configurato, collaudato o pianificato. Non salvare password, token, chiavi, link di reset o dati personali reali nella documentazione. Usare percorsi relativi per i collegamenti tra documenti.

Gli aggiornamenti avvengono durante gli interventi di sviluppo; non è previsto un processo automatico in background.
