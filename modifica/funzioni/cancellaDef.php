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
    $res['msg'] = 'Non puoi cancellare funzioni';
}

if (isset($user) && isset($My_POST['id']) && !$error) {

    pg_query('BEGIN') or die('Cant start transaction');
    $resp0 = $resp1 = $funzione_in_modifica = null;
    // controllo esistenza
    $esist = runPreparedQuery($conn, $c++, 'SELECT FROM funzionigeo WHERE id=$1', [$My_POST['id']]);
    if (pg_num_rows($esist['data']) <= 0) {
        http_response_code(422);
        $error = true;
        $res['msg'] = 'La funzione non esiste';
    }
    // Controllo se qualcuno ha una modifica alla funzione
    $funzione_in_modifica = runPreparedQuery($conn, $c++,
            "SELECT null FROM tmp_db.funzionigeo WHERE id=$1",
            [$My_POST['id']]);
    if (pg_num_rows($funzione_in_modifica['data']) > 0) {
        http_response_code(422);
        $error = true;
        $res['msg'] = 'Impossibile cancellare, qualche utente ha una modifica a questa funzione.';
    }
    if (!$error) {
        $resp0 = runPreparedQuery($conn, $c++, 'DELETE FROM funzionigeo_ruoli '
                . 'WHERE id_funzione=$1', [$My_POST['id']]);
        $resp1 = runPreparedQuery($conn, $c++, 'DELETE FROM funzionigeo '
                . 'WHERE id=$1', [$My_POST['id']]);
    }

    $queryArr = array($funzione_in_modifica, $resp0, $resp1);
    if (!$error && checkAllPreparedQuery($queryArr)) {
        if (pg_query('COMMIT')) {
            http_response_code(200);
            logTxt($conn, "Cancella funzione", "ID utente: ${user['id']}, "
                    . "ID funzione: ${My_POST['id']}");
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
        logTxt($conn, "Cancella funzione fallita", "ID utente: ${user['id']}, "
                . "ID funzione: ${My_POST['id']} - $msg");
    }
}

echo json_encode($res);
