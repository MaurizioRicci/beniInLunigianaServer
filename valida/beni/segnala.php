<?php

include('../../connection.php');
include('../../utils.php');
include('../../queryUtils.php');

header('Content-type: application/json');
$res = array();
$c = 0; // do un id progressivo alle query
$error = false;
http_response_code(500);
// analizza $_POST e converte le stringhe vuote in null
$My_POST = postEmptyStr2NULL();

$user = risolviUtente($conn, $c++, $My_POST['username'], $My_POST['password']);
if (!isset($user) && !$error) {
    http_response_code(401);
    $res['msg'] = 'Username/Password invalidi';
    $error = true;
}
if (!isset($My_POST['msg_validatore'])) {
    $error = true;
    $res['msg'] = 'Per segnalare devi scrivere un messaggio per lo schedatore';
}
if (isset($My_POST['id']) && !$error) {
    // occorre proteggersi dalle possibili write skew risultanti 
    // dalla modifica/creazione concorrente dello stesso bene da validare.
    pg_query('BEGIN TRANSACTION') or die('Cant start transaction');
    $respBene = null;

    // PASSO 1. controllo il ruolo.
    if ($user['role'] == 'revisore') {

        $respBene = runPreparedQuery($conn, $c++,
                // status: 0 se revisione, 1 se necessita correzioni
                'UPDATE tmp_db.benigeo SET status=1, msg_validatore=$1 where id=$2 and id_utente=$3 and status=0',
                array($My_POST['msg_validatore'], $My_POST['id'], $My_POST['id_utente']));
        // vediamo se ha cancellato qualcosa
        if (pg_num_rows($respBene['data']) < 0) {
            $res['msg'] = 'ID del bene in revisione non trovato';
            $error = true;
        }
        // controllo sia andata a buon fine la query senza sovrascrivere $error
        $error = $error || !$respBene['ok'];
    } else {
        $error = true;
        http_response_code(401);
        $res['msg'] = 'Operazione non permessa. Non sei un revisore';
    }

    // per sicurezza controllo tutte le query
    $queryArr = array($respBene);
    if (!$error && checkAllPreparedQuery($queryArr)) {
        //se COMMIT è andato a buon fine
        if (pg_query('COMMIT')) {
            http_response_code(200);
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
