<?php

include 'connectionString.php';

$conn = pg_connect($conn_str);

if (!$conn) {
    echo "Connection string: " . $conn_str . "\n";
    die("Connection invalid");
} else {
    die("Connection valid");
}