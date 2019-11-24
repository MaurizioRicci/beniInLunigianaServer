<?php

$host = "localhost";
$db_name = "postgis_db";
$username = "maintenance";
$password = "maintenance";


$conn_str = sprintf("host=%s dbname=%s user=%s password=%s", $host, $db_name, $username, $password);
$conn = pg_connect($conn_str);

if (!$conn) {
    // 503 Service Unavailable
    http_response_code(503);
    die("Connection invalid");
}
