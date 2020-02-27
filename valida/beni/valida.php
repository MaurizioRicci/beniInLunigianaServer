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
$My_POST = dictEmptyStr2NULL(beniJS2Postgres($_POST));

$user = risolviUtente($conn, $c++, $My_POST['username'], $My_POST['password']);
if (!isset($user) && !$error) {
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
 * 4 segnarsi chi modifica cosa
 * 5 cancellare il bene nell'archivio temporaneo
 * 6 le funzioni dell'utente che puntavano a un bene temporaneo devono ora puntare a uno definitivo
 */
if (isset($My_POST['id']) && !$error) {
    // occorre proteggersi dalle possibili write skew risultanti 
    // dalla modifica/creazione concorrente dello stesso bene da validare.
    pg_query('BEGIN TRANSACTION ISOLATION LEVEL SERIALIZABLE') or die('Cant start transaction');
    $resp0 = $resp1 = $resp2 = $resp3 = $resp4 = $resp5 = null;

    // PASSO 1. controllo il ruolo.
    if ($user['role'] == 'revisore') {
        //PASSO 2
        $respBeneTmp = runPreparedQuery($conn, $c++,
                'SELECT id from tmp_db.benigeo where id=$1 and id_utente=$2 FOR UPDATE', [$My_POST['id'], $My_POST['id_utente']]);
        if (!$respBeneTmp['ok'] || pg_num_rows($respBeneTmp['data']) <= 0) {
            $res['msg'] = 'ID del bene in revisione non trovato. Forse altri revisori hanno approvato il bene.';
            $error = true;
        }
        if (isset($My_POST['id_utente'])) {
            // se viene fornito anche id_utente allora è parte della chiave per un bene in archivio temporaneo
            // copio il bene temporaneo in archivio definitivo e cancello il bene temporaneo
            // PASSO 3
            $resp0 = replaceIntoBeniGeoTmp($conn, $c++, $My_POST['id'], $My_POST['ident'],
                    $My_POST['descr'], $My_POST['mec'], $My_POST['meo'], $My_POST['bibl'],
                    $My_POST['note'], $My_POST['topon'], $My_POST['comun'], $My_POST['geom'],
                    $My_POST['id_utente'], $My_POST['status'], $My_POST['esist']);
            $resp1 = upsertBeneTmpToBeniGeo($conn, $c++, $My_POST['id'], $My_POST['id_utente']);
            // segno chi ha fatto cosa. PASSO 4
            $resp2 = insertIntoManipolaBene($conn, $c++, $My_POST['id_utente'], $My_POST['id']);
            $error = $error || !$resp0['ok'] || !$resp1['ok'] || !$resp2['ok'];
            // PASSO 5
            $resp3 = runPreparedQuery($conn, $c++,
                    'DELETE FROM tmp_db.benigeo WHERE id=$1 AND id_utente=$2',
                    [$My_POST['id'], $My_POST['id_utente']]);
            // PASSO 6
            $resp4 = runPreparedQuery($conn, $c++,
                    'UPDATE FROM tmp_db.funzionigeo WHERE id_bene=$1 AND id_utente_bene=$2 SET id_utente_bene=NULL',
                    [$My_POST['id'], $My_POST['id_utente']]);
            $resp5 = runPreparedQuery($conn, $c++,
                    'UPDATE FROM tmp_db.funzionigeo WHERE id_bener=$1 AND id_utente_bener=$2 SET id_utente_bener=NULL',
                    [$My_POST['id'], $My_POST['id_utente']]);
        }
    } else {
        $error = true;
        http_response_code(401);
        $res['msg'] = 'Operazione non permessa. Non sei un revisore';
    }

    // per sicurezza controllo tutte le query
    $queryArr = array($resp0, $resp1, $resp2, $resp3, $resp4, $resp5);
    if (!$error && checkAllPreparedQuery($queryArr)) {
        // se COMMIT è andato a buon fine
        if (pg_query('COMMIT')) {
            http_response_code(200);
            logTxt($conn, "Valida bene", "ID utente: ${user['id']}, "
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
