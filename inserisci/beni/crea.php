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
    $resp1 = $resp2 = $queryID = null;

    //in base al ruolo utente scelgo in quale tabella mettere il bene
    if ($sched['role'] == 'master') {//senza revisione
        $resp1 = insertIntoBeniGeo($conn, $c++, $My_POST['id'], $My_POST['ident'],
                $My_POST['descr'], $My_POST['mec'], $My_POST['meo'], $My_POST['bibl'],
                $My_POST['note'], $My_POST['topon'], $My_POST['comun'], $My_POST['geom']);
        //manipolabene serve se è validato il bene
        $resp2 = insertIntoManipolaBene($conn, $c++, $sched['id'], $My_POST['id']);
    } else if ($sched['role'] == 'basic') {
        //può esserci un solo bene distinto in revisione
        // se gli id assegnati agli utenti non si sovrappongono, non dovrebbe mai
        //succedere che due utenti possano creare un bene con lo stesso id, non si sa mai..
        $queryID = runPreparedQuery($conn, $c++,
                'SELECT id from tmp_db.benigeo where id=$1', array($My_POST['id']));
        if (pg_num_rows($queryID['data']) > 0) {
            //richiesta sintatticamente corretta ma semanticamente errata
            http_response_code(422);
            $res['msg'] = 'Un utente ha già in attesa di revisione questo bene';
            $error = true;
        } else {
            //non possono esserci più crea bene concorrenti poichè violerebbero
            // la pk (id) di tmp_db.benigeo. Quindi la query sotto fallirebbe facendo fallire la transazione.
            $resp1 = insertIntoBeniGeoTmp($conn, $c++, $My_POST['id'], $My_POST['ident'],
                    $My_POST['descr'], $My_POST['mec'], $My_POST['meo'], $My_POST['bibl'],
                    $My_POST['note'], $My_POST['topon'], $My_POST['comun'], $My_POST['geom'], $sched['id']);
        }
    }

    $queryArr = array($resp1, $queryID, $resp2);
    if (!$error && checkAllPreparedQuery($queryArr)) {
        if (pg_query('COMMIT')) {
            http_response_code(200);
        } else {
            $res['msg'] = $transazione_fallita_msg;
        }
    } else {
        pg_query('ROLLBACK');
        $failed_query = getFirstFailedQuery($queryArr);
        if (!isset($res['msg']) && isset($failed_query)) //magari ho già scritto io un messaggio d'errore
            $res['msg'] = pg_result_error($failed_query['data']);
    }
}

echo json_encode($res);
?>