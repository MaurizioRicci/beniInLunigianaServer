<?php
include 'myErrorHandler.php';
include 'myShutDownFunction.php';
// parametri db
$host = "localhost";
$db_name = "postgis_db";
$username = "postgres";
$password = "mau";
// faccio la connessione
$conn_str = sprintf("host=%s dbname=%s user=%s password=%s", $host, $db_name, $username, $password);
$conn = pg_connect($conn_str);

// dichiaro un gestore di errori. In particolare intercetto gli undefined index
// in tal caso faccio terminare il programma con exit
$myErrorHandler = set_error_handler('myErrorHandler');
// dichiaro una funzione da eseguire dopo che si verifica exit()
// in tal caso lancio un rollback abortendo la transazione corrente (se presente)
register_shutdown_function('myShutDownFunction', $conn);

if (!$conn) {
    // 503 Service Unavailable
    http_response_code(503);
    die("Connection invalid");
} else {
    // controllo se il sistema deve essere offline
    $resp = pg_query($conn, 'SELECT * from system_status LIMIT 1');
    $online = pg_fetch_assoc($resp)['online'];
    if ($online == 'f' || strtolower($online) == 'false') {
        // 503 Service Unavailable
        http_response_code(503);
        exit();
    }
}
