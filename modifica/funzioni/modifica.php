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

if (isset($My_POST['id']) && !$error) {

    pg_query('BEGIN TRANSACTION ISOLATION LEVEL REPEATABLE READ') or die('Cant start transaction');
    $resp0 = $resp1 = $resp2 = $resp3 = $resp4 = $resp5 = $queryID = null;

    // controllo benireferenziati
    $b1 = esisteBene($conn, $c++, $My_POST['id_bene'], $My_POST['id_utente_bene']);
    $b2 = esisteBene($conn, $c++, $My_POST['id_bener'], $My_POST['id_utente_bener']);
    if (!$b1 || !$b2) {
        $b_inesistente = $b1 ? $b2 : $b1;
        http_response_code(422);
        $error = true;
        $res['msg'] = "Il bene $b_inesistente non esiste.";
    }

    if (!$error) {
        //in base al ruolo utente scelgo in quale tabella mettere il bene
        if ($user['role'] == 'revisore') {
            // la PK delle funzioni temporanee è id_funzione e id_utente (ovvero il proprietario)
            // questo poichè altri utenti potrebbero volero modificare (si serve per la modifica) la stessa funzione
            if (isset($My_POST['id_utente'])) {
                // se viene fornito anche id_utente allora è parte della chiave per un bene in archivio temporaneo
                $queryFunzioneTmp = 'SELECT id from tmp_db.funzionigeo where id=$1 AND id_utente=$2 FOR UPDATE';
                $paramsFunzioneTmp = [$My_POST['id'], $My_POST['id_utente']];
                $queryID = runPreparedQuery($conn, $c++, $queryFunzioneTmp, $paramsFunzioneTmp);
            } else {
                // se c'è solo id del bene allora si sta cercando un bene in archivio definitivo
                $queryFunzione = 'SELECT id from benigeo where id=$1 FOR UPDATE';
                $paramsFunzione = [$My_POST['id']];
                $queryID = runPreparedQuery($conn, $c++, $queryFunzione, $paramsFunzione);
            }

            if (pg_num_rows($queryID['data']) <= 0) {
                //richiesta sintatticamente corretta ma semanticamente errata
                http_response_code(422);
                $res['msg'] = "La funzione con id ${My_POST['id']} non esiste";
                $error = true;
            } else {
                if (isset($My_POST['id_utente'])) {
                    // se viene fornito anche id_utente allora è parte della chiave per una funzione in archivio temporaneo
                    // copio la funzione temporaneo in archivio definitivo e cancello la funzione temporanea
                    $resp0 = replaceIntoFunzioniGeoTmp($conn, $c++, $My_POST['id'], $My_POST['id_bene'],
                            $My_POST['id_bener'], $My_POST['denominazione'], $My_POST['denominazioner'],
                            $My_POST['data'], $My_POST['tipodata'], $My_POST['funzione'], $My_POST['bibliografia'],
                            $My_POST['note'], $My_POST['id_utente'], $My_POST['id_utente_bene'],
                            $My_POST['id_utente_bener'], $My_POST['status']);
                    $resp1 = upsertFunzioneTmpToFunzioniGeo($conn, $c++, $My_POST['id'], $My_POST['id_utente']);
                    $resp2 = runPreparedQuery($conn, $c++,
                            "UPDATE tmp_db.funzionigeo SET msg_validatore=NULL WHERE id=$1 AND id_utente=$2",
                            [$My_POST['id'], $My_POST['id_utente']]);
                    // inserisco i ruoli dei vari beni associati alla funzione in archivio definitivo
                    $resp3 = insertFunzioniGeoRuoli($conn, $c++, $My_POST['id'], $My_POST['ruolo'],
                            $My_POST['ruolor'], false);
                    $error = $error || !$resp0['ok'] || !$resp1['ok'] || !$resp2['ok'] || !$resp3['ok'];
                    // cancello ruoli e funzione dal db temporaneo
                    $resp4 = runPreparedQuery($conn, $c++,
                            'DELETE FROM tmp_db.funzionigeo_ruoli WHERE id_funzione=$1 AND id_utente=$2',
                            [$My_POST['id'], $My_POST['id_utente']]);
                    $resp5 = runPreparedQuery($conn, $c++,
                            'DELETE FROM tmp_db.funzionigeo WHERE id=$1 AND id_utente=$2',
                            [$My_POST['id'], $My_POST['id_utente']]);
                } else {
                    // sto modifcando una funzione già consolidata
                    $resp1 = replaceIntoFunzioniGeo($conn, $c++, $My_POST['id'], $My_POST['id_bene'],
                            $My_POST['id_bener'], $My_POST['denominazione'], $My_POST['denominazioner'],
                            $My_POST['data'], $My_POST['tipodata'], $My_POST['funzione'],
                            $My_POST['bibliografia'], $My_POST['note']);
                    //manipolafunzione serve se è validato il bene, registra chi ha modificato
                    $resp2 = insertIntoManipolaFunzione($conn, $c++, $user['id'], $My_POST['id']);
                    // inserisco i ruoli dei vari beni associati alla funzione in archivio definitivo
                    $resp3 = insertFunzioniGeoRuoli($conn, $c++, $My_POST['id'], $My_POST['ruolo'],
                            $My_POST['ruolor'], false);
                }
            }
        } if ($user['role'] == 'schedatore') {

            // la PK delle funzioni temporane è id_funzione e id_utente (ovvero il proprietario)
            // questo poichè altri utenti potrebbero voler modificare (si serve per la modifica) la stessa funzione
            $queryID = runPreparedQuery($conn, $c++,
                    'SELECT id from tmp_db.funzionigeo where id=$1 AND id_utente=$2 and status=0
                        FOR UPDATE', [$My_POST['id'], $user['id']]);

            if (pg_num_rows($queryID['data']) > 0) {
                //richiesta sintatticamente corretta ma semanticamente errata
                http_response_code(422);
                $res['msg'] = "Hai già una modifica alla funzione con id ${My_POST['id']} in sospeso";
                $error = true;
            } else {
                $resp1 = upsertIntoFunzioniGeoTmp($conn, $c++, $My_POST['id'], $My_POST['id_bene'],
                        $My_POST['id_bener'], $My_POST['denominazione'], $My_POST['denominazioner'],
                        $My_POST['data'], $My_POST['tipodata'], $My_POST['funzione'], $My_POST['bibliografia'],
                        $My_POST['note'], $user['id'], $My_POST['id_utente_bene'],
                        $My_POST['id_utente_bener'], $My_POST['status']);
                $resp2 = runPreparedQuery($conn, $c++,
                        "UPDATE tmp_db.funzionigeo SET msg_validatore=NULL WHERE id=$1 AND id_utente=$2",
                        [$My_POST['id'], $user['id']]);
                // inserisco i ruoli dei vari beni associati alla funzione in archivio temporaneo
                $resp3 = runPreparedQuery($conn, $c++,
                        'DELETE FROM tmp_db.funzionigeo_ruoli WHERE id_funzione=$1 AND id_utente=$2',
                        [$My_POST['id'], $user['id']]);
                $resp4 = insertFunzioniGeoRuoli($conn, $c++, $My_POST['id'], $user['id'],
                        $My_POST['ruolo'], $My_POST['ruolor'], true);
            }
        }
    }

    $queryArr = array($resp1, $queryID, $resp2, $resp3, $resp4, $resp5);
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