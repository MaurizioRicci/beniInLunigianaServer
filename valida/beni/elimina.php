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

$sched = risolviUtente($conn, $c++, $My_POST['username'], $My_POST['password']);
if (!isset($sched) && !$error) {
    http_response_code(401);
    $res['msg'] = 'Username/Password invalidi';
    $error = true;
}

if (isset($My_POST['id']) && !$error) {
    // occorre proteggersi dalle possibili write skew risultanti 
    // dalla modifica/creazione concorrente dello stesso bene da validare.
    pg_query('BEGIN TRANSACTION ISOLATION LEVEL REPEATABLE READ') or die('Cant start transaction');
    $respDel = null;
    $queryArr = array($respDel);

    // PASSO 1. controllo il ruolo.
    if ($sched['role'] == 'revisore') {

        $respBene = runPreparedQuery($conn, $c++,
                'DELETE from tmp_db.benigeo where id=$1 and status=0', array($My_POST['id']));
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
        if (!isset($res['msg']) && isset($failed_query)) //magari ho già scritto io un messaggio d'errore
            $res['msg'] = pg_result_error($failed_query['data']);
    }
}

echo json_encode($res);
