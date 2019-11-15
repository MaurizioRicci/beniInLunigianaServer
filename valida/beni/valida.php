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

/* /
 * Passi da seguire per validare. 
 * 1 utente deve essere revisore
 * 2 controllare esistenza bene in archivio temporaneo
 *  se il bene nell'archivio definitivo esiste già va sostituito NON cancellato!
 *  (per i vincoli d'integrità referenziale succede un casino se si cancella. Vedi le tabelle referenziate)
 * 3 inserire (eventualmente sostituire) il bene nell'archio temporaneo in quello definitivo
 * 4 recuperare id utente del bene da validare e segnarsi chi modifica cosa
 * 5 cancellare il bene nell'archivio temporaneo
 */
if (isset($My_POST['id']) && !$error) {
    // occorre proteggersi dalle possibili write skew risultanti 
    // dalla modifica/creazione concorrente dello stesso bene da validare.
    pg_query('BEGIN TRANSACTION ISOLATION LEVEL SERIALIZABLE') or die('Cant start transaction');
    $respBeneTmp = $respBene = $respMove = $respIns = $respUpdt = $respAuthor = $respDel2 = null;

    // PASSO 1. controllo il ruolo.
    if ($sched['role'] == 'revisore') {
        //PASSO 2
        $respBeneTmp = runPreparedQuery($conn, $c++,
                'SELECT id from tmp_db.benigeo where id=$1 FOR UPDATE', array($My_POST['id']));
        if (!$respBeneTmp['ok'] || pg_num_rows($respBeneTmp['data'] < 0)) {
            $res['msg'] = 'ID del bene in revisione non trovato. Forse altri revisori hanno approvato il bene.';
            $error = true;
        }

        $respBene = runPreparedQuery($conn, $c++,
                'SELECT id from public.benigeo where id=$1 FOR UPDATE', array($My_POST['id']));
        // controllo sia andata a buon fine la query senza sovrascrivere $error
        $error = $error || !$respBene['ok'];
        if (!$error) {
            // PASSO 3. aggiungo/rimpiazzo il bene nell'archivio definitivo. Se non esiste in tmp non fa niente
            $respMove = upsertBeneTmpToBeniGeo($conn, $c++, $My_POST['id']);
            // ottengo l'autore della modifica
            $respAuthor = runPreparedQuery($conn, $c++,
                    'SELECT id_utente FROM tmp_db.benigeo WHERE id=$1', array($My_POST['id']));
            // errore se: c'era già un errore o se la query è fallita o se la query non ha dato risultati
            $error = $error || !$respAuthor['ok'] || (pg_num_rows($respAuthor['data']) <= 0);
            // PASSO 4. segno l'autore della modifica (non il revisore)
            if (!$error) {
                $row = pg_fetch_row($respAuthor['data']);
                $respIns = insertIntoManipolaBene($conn, $c++, $row[0], $My_POST['id']);
                // PASSO 5
                $respDel2 = runPreparedQuery($conn, $c++,
                        'DELETE FROM tmp_db.benigeo WHERE id=$1', array($My_POST['id']));
            }
        }
    } else {
        $error = true;
        http_response_code(401);
        $res['msg'] = 'Operazione non permessa. Non sei un revisore';
    }

    // per sicurezza controllo tutte le query
    $queryArr = array($respBeneTmp, $respBene, $respMove, $respUpdt, $respAuthor, $respIns, $respDel2);
    if (!$error && checkAllPreparedQuery($queryArr)) {
        // se COMMIT è andato a buon fine
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
