<?php
	$host = "localhost";
	$db_name = "postgis_db";
	$username = "postgres";
	$password = "mau";
	
	
	$conn_str = sprintf("host=%s dbname=%s user=%s password=%s", $host, $db_name, $username, $password);
    $conn = pg_connect($conn_str);
	
	if(!$conn) { 
		http_response_code(500);
		die("Connection invalid");
	}
?>