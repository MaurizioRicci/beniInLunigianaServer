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

if (isset($My_POST['id']) && !$error) {

    pg_query('BEGIN TRANSACTION ISOLATION LEVEL REPEATABLE READ') or die('Cant start transaction');
    $resp1 = $resp2 = $queryID = null;
    $queryArr = array($resp1, $queryID, $resp2);

    //in base al ruolo utente scelgo in quale tabella mettere il bene
    if ($sched['role'] == 'master') {//senza revisione
        $resp1 = replaceIntoBeniGeo($conn, $c++, $My_POST['id'], $My_POST['ident'],
                $My_POST['descr'], $My_POST['mec'], $My_POST['meo'], $My_POST['bibl'],
                $My_POST['note'], $My_POST['topon'], $My_POST['comun'], $My_POST['geom']);
        //manipolabene serve se è validato il bene
        $resp2 = insertIntoManipolaBene($conn, $c++, $sched['id'], $My_POST['id']);
    } if ($sched['role'] == 'basic') {
        //può esserci un solo bene distinto in revisione
        $queryID = runPreparedQuery($conn, $c++,
                'SELECT id from tmp_db.benigeo where id=$1', array($My_POST['id']));
        // controllo che anche la select sia andata a buon fine
        $error = !$queryID['ok'];
        if (!error) {
            if (pg_num_rows($queryID['data']) > 0) {
                //richiesta sintatticamente corretta ma semanticamente errata
                http_response_code(422);
                $res['msg'] = 'Un utente ha già una modifica per questo bene in attesa di revisione';
                $error = true;
            } else {
                $resp1 = insertIntoBeniGeoTmp($conn, $c++, $My_POST['id'], $My_POST['ident'],
                        $My_POST['descr'], $My_POST['mec'], $My_POST['meo'], $My_POST['bibl'],
                        $My_POST['note'], $My_POST['topon'], $My_POST['comun'], $My_POST['geom'], $sched['id']);
            }
        }
    }

    if (!$error && checkAllPreparedQuery($queryArr)) {
        pg_query('COMMIT');
        http_response_code(200);
    } else {
        pg_query('ROLLBACK');
        $failed_query = getFirstFailedQuery($queryArr);
        if (!isset($res['msg']) && isset($failed_query)) //magari ho già scritto io un messaggio d'errore
            $res['msg'] = pg_result_error($failed_query['data']);
    }
}

echo json_encode($res);
?>