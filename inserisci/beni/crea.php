<?php

include('../../connection.php');
include('../../utils.php');
include('../../queryUtils.php');

header('Content-type: application/json');
$res = array();
$c = 0; // do un id progressivo alle query
$error = false;
http_response_code(500);
$My_POST = postEmptyStr2NULL();

$sched = risolviUtente($conn, $c++, $My_POST['username'], $My_POST['password']);
if (!isset($sched) && !$error) {
    http_response_code(401);
    $res['msg'] = 'Username/Password invalidi';
    $error = true;
}

if (!$error && !checkID($conn, $c++, $My_POST['username'], $My_POST['password'], $My_POST['id'])) {
    //richiesta sintatticamente corretta ma semanticamente errata
    http_response_code(422);
    $res['msg'] = 'Non hai accesso a questo id';
    $error = true;
}

if (isset($My_POST['id']) && !$error) {
    pg_query('BEGIN TRANSACTION ISOLATION LEVEL REPEATABLE READ') or die('Cant start transaction');
    $resp1 = $resp2 = null;
    //in base al ruolo utente scelgo in quale tabella mettere il bene
    if ($sched['role'] == 'master') {//senza revisione
        $resp1 = insertIntoBeniGeo($conn, $c++, $My_POST['id'], $My_POST['ident'],
                $My_POST['descr'], $My_POST['mec'], $My_POST['meo'], $My_POST['bibl'],
                $My_POST['note'], $My_POST['topon'], $My_POST['comun'], $My_POST['geom']);
        //manipolabene serve se è validato il bene
        $resp2 = insertIntoManipolaBene($conn, $c++, $sched['id'], $My_POST['id']);
    } else
        $resp1 = insertIntoBeniGeoTmp($conn, $c++, $My_POST['id'], $My_POST['ident'],
                $My_POST['descr'], $My_POST['mec'], $My_POST['meo'], $My_POST['bibl'],
                $My_POST['note'], $My_POST['topon'], $My_POST['comun'], $My_POST['geom'], $sched['id']);

    if (!$error && checkAllPreparedQuery(array($resp1, $resp2))) {
        pg_query('COMMIT');
        http_response_code(200);
    } else {
        pg_query('ROLLBACK');
        $failed_query = getFirstFailedQuery(array($resp1, $resp2));
        if (!isset($res['msg'])) //magari ho già scritto io un messaggio d'errore
            $res['msg'] = pg_result_error($failed_query['data']);
    }
}

echo json_encode($res);
?>