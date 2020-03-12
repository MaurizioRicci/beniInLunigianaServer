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
$My_POST = dictEmptyStr2NULL(funzioniJS2Postgres($_POST));
$user = risolviUtente($conn, $c++, $My_POST['username'], $My_POST['password']);
if (!isset($user) && !$error) {
    http_response_code(401);
    $res['msg'] = 'Username/Password invalidi';
    $error = true;
}
/* /
 * Passi da seguire per validare. 
 * 0 i beni devono esistere in archivio definitivo
 * 1 utente deve essere revisore
 * 2 controllare esistenza bene in archivio temporaneo
 *  se la funzione nell'archivio definitivo esiste già va sostituita NON cancellato!
 *  (per i vincoli d'integrità referenziale succede un casino se si cancella. Vedi le tabelle referenziate)
 * 3 inserire (eventualmente sostituire) la funzione nell'archio temporanea in quello definitiva
 * 4 recuperare ruolo e ruolor, inserirli poi i ruoli in archivio definitivo
 * 5 segnarsi chi modifica cosa
 * 6 cancellare la funzione nell'archivio temporaneo
 */
if (isset($My_POST['id']) && !$error) {
    // al massimo faccio il loop due volte nel caso la transazione fallisca
    for ($tCount = 0; $tCount < 2; $tCount++) {
            // occorre proteggersi dalle possibili write skew risultanti 
    // dalla modifica/creazione concorrente dello stesso bene da validare.
        pg_query('BEGIN TRANSACTION ISOLATION LEVEL SERIALIZABLE') or die('Cant start transaction');
        $respFunzioneTmp = $respFunzione = $resp0 = $resp1 = null;
        $resp2 = $resp3 = $resp4 = $resp4 = $resp6 = $resp7 = null;

        // PASSO 0 controllo benireferenziati. Cerco in archivio definitivo => funzione approvata richiede beni approvati
        $b1_esiste = esisteBene($conn, $c++, $My_POST['id_bene'], null);
        $b2_esiste = esisteBene($conn, $c++, $My_POST['id_bener'], null);
        if ($b1_esiste && !isset($My_POST['id_bener'])) {
            // ok bene 1 esiste e bene2=null
        } else if ($b2_esiste && !isset($My_POST['id_bene'])) {
            // ok bene 2 esiste e bene1=null
        } else if ($b1_esiste && $b2_esiste) {
            // ok entrambi i beni esistono
        } else {
            $b_inesistente = $b1_esiste ? $My_POST['id_bener'] : $My_POST['id_bene'];
            http_response_code(422);
            $error = true;
            $res['msg'] = "Il bene $b_inesistente non esiste.";
        }
        if (!$error) {
            // PASSO 1. controllo il ruolo.
            if ($user['role'] == 'revisore') {
                //PASSO 2
                $respFunzioneTmp = runPreparedQuery($conn, $c++,
                        'SELECT id from tmp_db.funzionigeo where id=$1 and id_utente=$2 FOR UPDATE',
                        [$My_POST['id'], $My_POST['id_utente']]);
                if (!$respFunzioneTmp['ok'] || pg_num_rows($respFunzioneTmp['data']) <= 0) {
                    $res['msg'] = 'ID della funzione in revisione non trovato. Forse altri revisori hanno approvato la funzione.';
                    $error = true;
                }
                // metto un lock sulla funzione in archivio definitivo se esiste
                $respFunzione = runPreparedQuery($conn, $c++,
                        'SELECT id from public.funzionigeo where id=$1 FOR UPDATE', [$My_POST['id']]);
                // controllo sia andata a buon fine la query senza sovrascrivere $error
                $error = $error || !$respFunzione['ok'];
                if (!$error) {
                    if (isset($My_POST['id_utente'])) {
                        // PASSO 3
                        $resp0 = replaceIntoFunzioniGeoTmp($conn, $c++, $My_POST['id'], $My_POST['id_bene'],
                                $My_POST['id_bener'], $My_POST['denominazione'], $My_POST['denominazioner'],
                                $My_POST['data_ante'], $My_POST['data_poste'],
                                $My_POST['tipodata'], $My_POST['funzione'], $My_POST['bibliografia'],
                                $My_POST['note'], $My_POST['id_utente'], $My_POST['id_utente_bene'],
                                $My_POST['id_utente_bener'], $My_POST['status']);
                        $resp1 = upsertFunzioneTmpToFunzioniGeo($conn, $c++, $My_POST['id'], $My_POST['id_utente']);
                        // ottengo l'id della funzione definitiva assegnata dal db
                        $id_funzione_db = getIdFunzione($resp1);
                        $resp2 = runPreparedQuery($conn, $c++,
                                "UPDATE tmp_db.funzionigeo SET msg_validatore=NULL WHERE id=$1 AND id_utente=$2",
                                [$id_funzione_db, $My_POST['id_utente']]);
                        // cancello i ruoli precedenti in archivio definitivo per sostituirli
                        $resp3 = runPreparedQuery($conn, $c++,
                                "DELETE FROM public.funzionigeo_ruoli WHERE id_funzione=$1", [$id_funzione_db]);
                        // inserisco i ruoli dei vari beni associati alla funzione in archivio definitivo
                        // PASSO 4
                        $resp4 = insertFunzioniGeoRuoli($conn, $c++, $id_funzione_db, $My_POST['id_utente'], $My_POST['ruolo'],
                                $My_POST['ruolor'], false);
                        // aggiunge N ruoli con N query preparate => devo incrementare l'id delle query preparate
                        $maxLength = max(count($My_POST['ruolo']), count($My_POST['ruolor']));
                        $c += $maxLength + 1;
                        $error = $error || !$resp0['ok'] || !$resp1['ok'] || !$resp2['ok'] || !$resp3['ok'];
                        //manipolafunzione serve se è validato il bene, registra chi ha modificato
                        // PASSO 5
                        $resp5 = insertIntoManipolaFunzione($conn, $c++, $My_POST['id_utente'], $id_funzione_db);
                        // PASSO 6
                        // cancello ruoli e funzione dal db temporaneo
                        $resp6 = runPreparedQuery($conn, $c++,
                                'DELETE FROM tmp_db.funzionigeo_ruoli WHERE id_funzione=$1 AND id_utente=$2',
                                [$id_funzione_db, $My_POST['id_utente']]);
                        $resp7 = runPreparedQuery($conn, $c++,
                                'DELETE FROM tmp_db.funzionigeo WHERE id=$1 AND id_utente=$2',
                                [$id_funzione_db, $My_POST['id_utente']]);
                    }
                }
            } else {
                $error = true;
                http_response_code(401);
                $res['msg'] = 'Operazione non permessa. Non sei un revisore';
            }
        }
        // per sicurezza controllo tutte le query
        $queryArr = array($respFunzioneTmp, $respFunzione, $resp0, $resp1,
            $resp2, $resp3, $resp4, $resp4, $resp6, $resp7);
        if (!$error && checkAllPreparedQuery($queryArr)) {
            // se COMMIT è andato a buon fine
            if (pg_query('COMMIT')) {
                // se la transazione è andata a buon fine non la riprovo
                $tCount = PHP_INT_MAX;
                http_response_code(200);
                logTxt($conn, "Valida funzione", "ID utente: ${user['id']}, "
                        . "ID funzione: ${My_POST['id']}, ID utente funzione: ${My_POST['id_utente']}");
            } else {
                $res['msg'] = $transazione_fallita_msg;
            }
        } else {
            pg_query('ROLLBACK');
            $failed_query = getFirstFailedQuery($queryArr);
            if (!isset($res['msg']) && isset($failed_query)) //magari ho già scritto io un messaggio d'errore
                $res['msg'] = pg_result_error($failed_query['data']);
            $msg = getOrDefault($res, 'msg', '');
            logTxt($conn, "Valida funzione fallita", "ID utente: ${user['id']}, "
                    . "ID funzione: ${My_POST['id']} - $msg");
        }
    }
}
echo json_encode($res);
