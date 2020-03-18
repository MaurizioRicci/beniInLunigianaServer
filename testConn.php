<?php

include 'connectionString.php';

$conn = pg_connect($conn_str);

// banale test per vedere se ci si collega al db
if (!$conn) {
    echo "Connection string: " . $conn_str . "\n";
    die("Connection invalid");
} else {
    die("Connection valid");
}