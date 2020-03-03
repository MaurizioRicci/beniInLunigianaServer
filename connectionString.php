<?php

// parametri db
$host = "localhost";
$port = "5432";
$db_name = "postgis_db";
$username = "postgres";
$password = "postgres";
// faccio la connessione
$conn_str = sprintf("host=%s port=%s dbname=%s user=%s password=%s", $host, $port, $db_name, $username, $password);
