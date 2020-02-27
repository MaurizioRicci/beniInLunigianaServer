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

if (!$error) {
    pg_query('BEGIN TRANSACTION ISOLATION LEVEL REPEATABLE READ') or die('Cant start transaction');
    $resp1 = $resp2 = $resp3 = $queryID = null;
    // controllo benireferenziati. Cerco o in archivio definitivo o in quelli temporanei dell'utente
    // b1 esiste in archivio definitivo
    $b1_def = esisteBene($conn, $c++, $My_POST['id_bene'], null);
    //  b1 esiste in archivio temporaneo come bene di utente corrente
    $b1_tmp = esisteBene($conn, $c++, $My_POST['id_bene'], $user['id']);
    $b2_def = esisteBene($conn, $c++, $My_POST['id_bener'], null);
    $b2_tmp = esisteBene($conn, $c++, $My_POST['id_bener'], $user['id']);
    $curr_id_utente_bene = $b1_def ? null : $user['id'];
    $curr_id_utente_bener = $b2_def ? null : $user['id'];
    $b1_esiste = $b1_def || $b1_tmp;
    $b2_esiste = $b2_def || $b2_tmp;
    if (!$b1_esiste || !$b2_esiste) {
        $b_inesistente = $b1_esiste ? $My_POST['id_bener'] : $My_POST['id_bene'];
        http_response_code(422);
        $error = true;
        $res['msg'] = "Il bene $b_inesistente non esiste.";
    }

    if (!$error) {
        //in base al ruolo utente scelgo in quale tabella mettere il bene
        if ($user['role'] == 'revisore') {

            //senza revisione
            // controllo che il bene non esista già
            $queryID = runPreparedQuery($conn, $c++,
                    'SELECT id from funzionigeo where id=$1', [$My_POST['id']]);
            if (pg_num_rows($queryID['data']) > 0) {
                //richiesta sintatticamente corretta ma semanticamente errata
                http_response_code(422);
                $res['msg'] = "La funzione con id ${My_POST['id']} esiste già";
                $error = true;
            } else {
                if (!$error) {
                    $resp1 = insertIntoFunzioniGeo($conn, $c++, $My_POST['id_bene'], $My_POST['id_bener'],
                            $My_POST['denominazione'], $My_POST['denominazioner'],
                            $My_POST['data_ante'], $My_POST['data_poste'],
                            $My_POST['tipodata'], $My_POST['funzione'],
                            $My_POST['bibliografia'], $My_POST['note'], $user['id'], $My_POST['status']);
                    //manipolafunzione serve se è validata la funzione
                    $id_funzione = getIdFunzione($resp1);
                    if (isset($id_funzione)) {
                        $resp2 = insertIntoManipolaFunzione($conn, $c++, $user['id'], $id_funzione);
                        $resp3 = insertFunzioniGeoRuoli($conn, $c++, $id_funzione, $user['id'],
                                $My_POST['ruolo'], $My_POST['ruolor'], false);
                        $error = $error || !$resp1['ok'] || !$resp2['ok'] || !$resp3['ok'];
                    }
                }
            }
        } else if ($user['role'] == 'schedatore') {
            if (!$error) {
                $resp1 = insertIntoFunzioniGeoTmp($conn, $c++, $My_POST['id_bene'], $My_POST['id_bener'],
                        $My_POST['denominazione'], $My_POST['denominazioner'],
                        $My_POST['data_ante'], $My_POST['data_poste'],
                        $My_POST['tipodata'], $My_POST['funzione'], $My_POST['bibliografia'],
                        $My_POST['note'], $user['id'], $curr_id_utente_bene,
                        $curr_id_utente_bener, $My_POST['status']);
                $id_funzione = getIdFunzione($resp1);
                if (isset($id_funzione)) {
                    $resp2 = insertFunzioniGeoRuoli($conn, $c++, $id_funzione, $user['id'], $My_POST['ruolo'],
                            $My_POST['ruolor'], true);
                }
            }
        }
    }
    $queryArr = array($resp1, $queryID, $resp2, $resp3);
    if (!$error && checkAllPreparedQuery($queryArr)) {
        if (pg_query('COMMIT')) {
            http_response_code(200);
            logTxt($conn, "Crea funzione", "ID utente: ${user['id']}, "
                    . "ID funzione: ${My_POST['id']}, ID utente funzione: ${My_POST['id_utente']}");
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
