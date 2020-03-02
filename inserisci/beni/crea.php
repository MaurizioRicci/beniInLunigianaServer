<?php

include('../../connection.php');
include('../../utils.php');
include('../../queryUtils.php');

header('Content-type: application/json');
$res = array();
$c = 0; // do un id progressivo alle query
$error = false;
http_response_code(500);
$My_POST = dictEmptyStr2NULL(beniJS2Postgres($_POST));

$user = risolviUtente($conn, $c++, $My_POST['username'], $My_POST['password']);
if (!isset($user) && !$error) {
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
    if ($user['role'] == 'revisore') {

        //senza revisione
        // controllo che il bene non esista già
        $queryID = runPreparedQuery($conn, $c++,
                'SELECT id from benigeo where id=$1', [$My_POST['id']]);
        if (pg_num_rows($queryID['data']) > 0) {
            //richiesta sintatticamente corretta ma semanticamente errata
            http_response_code(422);
            $res['msg'] = "Il bene con id ${My_POST['id']} esiste già";
            $error = true;
        } else {
            $resp1 = insertIntoBeniGeo($conn, $c++, $My_POST['id'], $My_POST['ident'],
                    $My_POST['descr'], $My_POST['mec'], $My_POST['meo'], $My_POST['bibl'],
                    $My_POST['note'], $My_POST['topon'], $My_POST['comun'], $My_POST['geom'], $My_POST['esist']);
            //manipolabene serve se è validato il bene
            $resp2 = insertIntoManipolaBene($conn, $c++, $user['id'], $My_POST['id']);
            $error = $error || !$resp1['ok'] || !$resp2['ok'];
        }
    } else if ($user['role'] == 'schedatore') {

        // la PK dei beni temporanei è id_bene e id_utente (ovvero il proprietario)
        // questo poichè altri utenti potrebbero volero modificare (si serve per la modifica) lo stesso bene
        $queryID = runPreparedQuery($conn, $c++,
                'SELECT id from tmp_db.benigeo where id=$1 and id_utente=$2', [$My_POST['id'], $user['id']]);
        if (pg_num_rows($queryID['data']) > 0) {
            //richiesta sintatticamente corretta ma semanticamente errata
            http_response_code(422);
            $res['msg'] = "Hai già in attesa di revisione il bene ${My_POST['id']}";
            $error = true;
        } else {
            //non possono esserci più crea bene concorrenti poichè violerebbero
            // la pk (id) di tmp_db.benigeo. Quindi la query sotto fallirebbe facendo fallire la transazione.
            $resp1 = insertIntoBeniGeoTmp($conn, $c++, $My_POST['id'], $My_POST['ident'],
                    $My_POST['descr'], $My_POST['mec'], $My_POST['meo'], $My_POST['bibl'],
                    $My_POST['note'], $My_POST['topon'], $My_POST['comun'], $My_POST['geom'],
                    $user['id'], $My_POST['status'], $My_POST['esist']);
        }
    }

    $queryArr = array($resp1, $queryID, $resp2);
    if (!$error && checkAllPreparedQuery($queryArr)) {
        if (pg_query('COMMIT')) {
            http_response_code(200);
            logTxt($conn, "Crea bene", "ID utente: ${user['id']}, "
                    . "ID bene: ${My_POST['id']}");
        } else {
            $res['msg'] = $transazione_fallita_msg;
        }
    } else {
        pg_query('ROLLBACK');
        $failed_query = getFirstFailedQuery($queryArr);
        if (!isset($res['msg']) && isset($failed_query)) //magari ho già scritto io un messaggio d'errore
            $res['msg'] = pg_result_error($failed_query['data']);
        $msg = getOrDefault($res, 'msg', '');
        logTxt($conn, "Crea bene fallito", "ID utente: ${user['id']}, "
                . "ID bene: ${My_POST['id']} - $msg");
    }
}

echo json_encode($res);
?>