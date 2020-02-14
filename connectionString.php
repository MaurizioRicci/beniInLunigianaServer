<?php
// parametri db
$host = "localhost";
$db_name = "postgis_db";
$username = "postgres";
$password = "postgres";
// faccio la connessione
$conn_str = sprintf("host=%s dbname=%s user=%s password=%s", $host, $db_name, $username, $password);
