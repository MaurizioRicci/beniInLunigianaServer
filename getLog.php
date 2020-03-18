<?php

include('connection.php');
include('utils.php');
include('queryUtils.php');
header('Content-type: application/json');
$c = 0; // do un id progressivo alle query
$error = false;
// analizza $_GET e converte le stringhe vuote in null
$My_GET = getEmptyStr2NULL();
$res = array();
http_response_code(200);

$limit = isset($My_GET['limit']) &&
        filter_var($My_GET['limit'], FILTER_VALIDATE_INT) ? $My_GET['limit'] : 100;

// Mostro i log che ho scritto
// accetto il parametro limit per determinare il numero di log da vedere. Con il tempo crescono e più di
//500-1000 è bene non inserirli nel browser pena il rallentamento
$query = runPreparedQuery($conn, $c++, "SELECT * from logs.logs ORDER BY date DESC LIMIT $1", [$limit]);
if ($query['ok']) {
    while ($row = pg_fetch_assoc($query['data'])) {
        array_push($res, $row);
    }
} else {
    http_response_code(500);
    $error = true;
    $res['msg'] = 'An error occured';
}


echo json_encode($res);
