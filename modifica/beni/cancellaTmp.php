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
if ($user['role'] == 'schedatore' && ($user['id'] !== $My_POST['id_utente'])) {
    http_response_code(422);
    $error = true;
    $res['msg'] = 'Non puoi cancellare beni di altri utenti';
}

if (isset($user) && isset($My_POST['id']) && !$error) {

    pg_query('BEGIN TRANSACTION ISOLATION LEVEL SERIALIZABLE') or die('Cant start transaction');
    $resp0 = null;
    // controllo esistenza
    $esist = runPreparedQuery($conn, $c++, 'SELECT FROM tmp_db.benigeo '
            . 'WHERE id=$1 AND id_utente=$2', [$My_POST['id'], $My_POST['id_utente']]);
    if (pg_num_rows($esist['data']) <= 0) {
        http_response_code(422);
        $error = true;
        $res['msg'] = 'Il bene non esiste';
    }
    if (!$error) {
        // controllo se il bene è referenziato in qualche funzioni temporanea dell'utente
        $bene_referenziato = runPreparedQuery($conn, $c++, 'SELECT FROM tmp_db.funzionigeo '
                . 'WHERE id_utente=$1 AND (id_bene=$2 OR id_bener=$2)',
                [$My_POST['id_utente'], $My_POST['id']]);
        // se il bene è referenziato da qualche funzione temp e se il bene non esiste in archivio definitivo
        // allora non si può cancellare
        if (pg_num_rows($bene_referenziato['data']) > 0 &&
                !esisteBene($conn, $c++, $My_POST['id'], null)) {
            http_response_code(422);
            $error = true;
            $res['msg'] = 'Il bene esiste solo in archivio temporaneo ed è referenziato'
                    . ' da funzioni in archivio temp. Non si può cancellare.';
        }
        if (!$error) {
            $resp0 = runPreparedQuery($conn, $c++, 'DELETE FROM tmp_db.benigeo '
                    . 'WHERE id=$1 AND id_utente=$2', [$My_POST['id'], $My_POST['id_utente']]);
        }
    }

    $queryArr = array($resp0);
    if (!$error && checkAllPreparedQuery($queryArr)) {
        if (pg_query('COMMIT')) {
            http_response_code(200);
            logTxt($conn, "Cancella bene temp", "ID utente: ${user['id']}, "
                    . "ID bene: ${My_POST['id']}, ID utente bene: ${My_POST['id_utente']}");
        } else {
            $res['msg'] = $transazione_fallita_msg;
        }
    } else {
        pg_query('ROLLBACK');
        $failed_query = getFirstFailedQuery($queryArr);
        if (!isset($res['msg']) && isset($failed_query)) { //magari ho già scritto io un messaggio d'errore
            $res['msg'] = pg_result_error($failed_query['data']);
        }
    }
}

echo json_encode($res);
