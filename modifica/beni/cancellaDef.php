<?php

include('../../connection.php');
include('../../utils.php');
include('../../queryUtils.php');

header('Content-type: application/json');
$res = array();
$c = 0; // do un id progressivo alle query
$error = false;
http_response_code(500);
$My_POST = dictEmptyStr2NULL(funzioniJS2Postgres($_POST));

$user = risolviUtente($conn, $c++, $My_POST['username'], $My_POST['password']);
if (!isset($user) && !$error) {
    http_response_code(401);
    $res['msg'] = 'Username/Password invalidi';
    $error = true;
}

// se sei schedatore e se stai cercando di cancellare una cosa non tua
if ($user['role'] != 'revisore') {
    http_response_code(422);
    $error = true;
    $res['msg'] = 'Non puoi cancellare beni';
}

if (isset($user) && isset($My_POST['id']) && !$error) {

    pg_query('BEGIN') or die('Cant start transaction');
    $resp0 = $bene_referenziato = null;
    // controllo esistenza
    $esist = runPreparedQuery($conn, $c++, 'SELECT FROM benigeo WHERE id=$1', [$My_POST['id']]);
    if (pg_num_rows($esist['data']) <= 0) {
        http_response_code(422);
        $error = true;
        $res['msg'] = 'Il bene non esiste';
    }
    if (!$error) {
        // controllo se il bene è referenziato in qualche funzione definitiva o temporanea
        // o se qualcuno ha una modifica al bene
        $bene_referenziato = runPreparedQuery($conn, $c++,
                "SELECT null FROM funzionigeo WHERE id_bene=$1 OR id_bener=$1 UNION "
                . "SELECT null FROM tmp_db.funzionigeo WHERE id_bene=$1 OR id_bener=$1 UNION "
                . "SELECT null FROM tmp_db.benigeo WHERE id=$1",
                [$My_POST['id']]);
        // se il bene è referenziato da qualche funzione temp e se il bene non esiste in archivio definitivo
        // allora non si può cancellare
        if (pg_num_rows($bene_referenziato['data']) > 0) {
            http_response_code(422);
            $error = true;
            $res['msg'] = 'Il bene è usato in qualche funzione in archivio definitivo/temporaneo oppure un utente ha una modifica al bene in corso.';
        }
        if (!$error) {
            $resp0 = runPreparedQuery($conn, $c++, 'DELETE FROM benigeo WHERE id=$1', [$My_POST['id']]);
        }
    }

    $queryArr = array($bene_referenziato, $resp0);
    if (!$error && checkAllPreparedQuery($queryArr)) {
        if (pg_query('COMMIT')) {
            http_response_code(200);
            logTxt($conn, "Cancella bene", "ID utente: ${user['id']}, "
                    . "ID bene: ${My_POST['id']}");
        } else {
            $res['msg'] = $transazione_fallita_msg;
        }
    } else {
        pg_query('ROLLBACK');
        $failed_query = getFirstFailedQuery($queryArr);
        if (!isset($res['msg']) && isset($failed_query)) { //magari ho già scritto io un messaggio d'errore
            $res['msg'] = pg_result_error($failed_query['data']);
        }
        $msg = getOrDefault($res, 'msg', '');
        logTxt($conn, "Cancella bene fallito", "ID utente: ${user['id']}, "
                . "ID bene: ${My_POST['id']} - $msg");
    }
}

echo json_encode($res);
