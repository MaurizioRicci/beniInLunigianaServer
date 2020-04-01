# Beni Lunigiana - server

Script php per interfacciarsi con Postgres (con PostGIS). Necessita solo di PHP e PostgreSQL con l'estensione Postgis.
All'interno di dump_database c'è il dump dell'ultima versione del database da caricare. Mentre nella cartella
databaseSchemaInterattivo sono contenute varie pagine web che permttono l'esplorazione e la comprensione dello scherma del database.

## Impostare la connessione al database
Modificare le variabili (host, numero porta, username, password, nome del database) contenute nel file connectionString.php
