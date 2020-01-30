<?php

include('connection.php');
include('utils.php');
include('queryUtils.php');
header('Content-type: application/json');
$c = 0; // do un id progressivo alle query
$error = false;
// analizza $_GET e converte le stringhe vuote in null
$My_POST = postEmptyStr2NULL();
$res = array();
http_response_code(400);

$user = risolviUtente($conn, $c++, $My_POST['username'], $My_POST['password']);
if (!isset($user)) {
    http_response_code(401);
    $res['msg'] = 'Username/Password invalidi';
    $error = true;
}

if (!$error) {
    $tableName = 'tmp_db.benigeo';
    // ottengo il primo buco negli id della tabella desiderata
    $query = runPreparedQuery($conn, $c++, "
        WITH missingID AS (   
        SELECT id+1 as id
        FROM $tableName t1
        WHERE id_utente=$1 AND NOT EXISTS (
          SELECT NULL
          FROM $tableName t2
          WHERE t2.id=(t1.id+1) AND id_utente=$1
        ) ORDER BY id LIMIT 1)
        SELECT MAX(id) as id
        FROM (
            SELECT id FROM missingID
            UNION
            SELECT max(id_bene)+1 as id FROM manipola_bene WHERE id_utente=$1
            UNION
            SELECT id_min as id FROM utenti where gid=$1
        ) as r", [$user['id']]);
    if ($query['ok']) {
        http_response_code(200);
        $row = pg_fetch_assoc($query['data']);
        $res['id'] = $row['id'];
    } else {
        http_response_code(500);
        $error = true;
        $res['msg'] = 'An error occured';
    }
}

echo json_encode($res);
