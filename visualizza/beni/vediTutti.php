<?php

include('../../connection.php');
include('../../utils.php');

header('Content-type: application/json');

$res = [];
$query = "SELECT * FROM benigeo";

$resp = pg_query($conn, $query);
$res = pg_fetch_all($resp);

exit(json_encode($res));
