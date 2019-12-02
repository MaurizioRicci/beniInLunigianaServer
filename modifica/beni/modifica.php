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

if (isset($My_POST['id']) && !$error) {

    pg_query('BEGIN TRANSACTION ISOLATION LEVEL REPEATABLE READ') or die('Cant start transaction');
    $resp0 = $resp1 = $resp2 = $resp3 = $queryID = null;

    //in base al ruolo utente scelgo in quale tabella mettere il bene
    if ($user['role'] == 'revisore') {
        // la PK dei beni temporanei è id_bene e id_utente (ovvero il proprietario)
        // questo poichè altri utenti potrebbero volero modificare (si serve per la modifica) lo stesso bene
        if (isset($My_POST['id_utente'])) {
            // se viene fornito anche id_utente allora è parte della chiave per un bene in archivio temporaneo
            $queryBeneTmp = 'SELECT id from tmp_db.benigeo where id=$1 AND id_utente=$2';
            $paramsBeneTmp = [$My_POST['id'], $My_POST['id_utente']];
            $queryID = runPreparedQuery($conn, $c++, $queryBeneTmp, $paramsBeneTmp);
        } else {
            // se c'è solo id del bene allora si sta cercando un bene in archivio definitivo
            $queryBene = 'SELECT id from benigeo where id=$1';
            $paramsBene = [$My_POST['id']];
            $queryID = runPreparedQuery($conn, $c++, $queryBene, $paramsBene);
        }

        if (pg_num_rows($queryID['data']) <= 0) {
            //richiesta sintatticamente corretta ma semanticamente errata
            http_response_code(422);
            $res['msg'] = "Il bene con id ${My_POST['id']} non esiste";
            $error = true;
        } else {
            if (isset($My_POST['id_utente'])) {
                // se viene fornito anche id_utente allora è parte della chiave per un bene in archivio temporaneo
                // copio il bene temporaneo in archivio definitivo e cancello il bene temporaneo
                $resp0 = replaceIntoBeniGeoTmp($conn, $c++, $My_POST['id'], $My_POST['ident'],
                        $My_POST['descr'], $My_POST['mec'], $My_POST['meo'], $My_POST['bibl'],
                        $My_POST['note'], $My_POST['topon'], $My_POST['comun'], $My_POST['geom'],
                        $My_POST['id_utente'], $My_POST['status'], $My_POST['esist']);
                $resp1 = upsertBeneTmpToBeniGeo($conn, $c++, $My_POST['id'], $My_POST['id_utente']);
                $resp2 = runPreparedQuery($conn, $c++,
                        "UPDATE tmp_db.benigeo SET msg_validatore=NULL WHERE id=$1 AND id_utente=$2"
                        , [$My_POST['id'], $My_POST['id_utente']]);
                $error = $error || !$resp0['ok'] || !$resp1['ok'] || !$resp2['ok'];
                $resp3 = runPreparedQuery($conn, $c++,
                        'DELETE FROM tmp_db.benigeo WHERE id=$1 AND id_utente=$2',
                        [$My_POST['id'], $My_POST['id_utente']]);
            } else {
                $resp1 = replaceIntoBeniGeo($conn, $c++, $My_POST['id'], $My_POST['ident'],
                        $My_POST['descr'], $My_POST['mec'], $My_POST['meo'], $My_POST['bibl'],
                        $My_POST['note'], $My_POST['topon'], $My_POST['comun'], $My_POST['geom'], $My_POST['esist']);
                //manipolabene serve se è validato il bene
                $resp2 = insertIntoManipolaBene($conn, $c++, $user['id'], $My_POST['id']);
            }
        }
    } if ($user['role'] == 'schedatore') {

        // la PK dei beni temporanei è id_bene e id_utente (ovvero il proprietario)
        // questo poichè altri utenti potrebbero voler modificare (si serve per la modifica) lo stesso bene
        $queryID = runPreparedQuery($conn, $c++,
                'SELECT id from tmp_db.benigeo where id=$1 AND id_utente=$2 and status=0', [$My_POST['id'], $user['id']]);

        if (pg_num_rows($queryID['data']) > 0) {
            //richiesta sintatticamente corretta ma semanticamente errata
            http_response_code(422);
            $res['msg'] = "Hai già una modifica al bene con id ${My_POST['id']} in sospeso";
            $error = true;
        } else {
            $resp1 = upsertIntoBeniGeoTmp($conn, $c++, $My_POST['id'], $My_POST['ident'],
                    $My_POST['descr'], $My_POST['mec'], $My_POST['meo'], $My_POST['bibl'],
                    $My_POST['note'], $My_POST['topon'], $My_POST['comun'], $My_POST['geom'],
                    $user['id'], $My_POST['status'], $My_POST['esist']);
            $resp2 = runPreparedQuery($conn, $c++,
                    "UPDATE tmp_db.benigeo SET msg_validatore=NULL WHERE id=$1 AND id_utente=$2"
                    , [$My_POST['id'], $My_POST['id_utente']]);
        }
    }
    $queryArr = array($resp1, $queryID, $resp2, $resp3);
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