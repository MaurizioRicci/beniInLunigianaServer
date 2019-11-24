<?php

include('connectionMaintenance.php');
include('utils.php');
include('queryUtils.php');

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

// Controllo se è richiesta la lista utenti
if (isset($My_POST['usersList'])) {
    if ($sched['role'] == 'revisore') {
        $query = runPreparedQuery($conn, $c++, "SELECT * FROM utenti", []);
        if ($query['ok']) {
            while ($row = pg_fetch_assoc($query['data'])) {
                array_push($res, $row);
            }
            http_response_code(200);
            echo json_encode($res);
        }
    } else {
        http_response_code(401);
        $res['msg'] = 'Operazione non permessa. Non sei un revisore';
        echo json_encode($res);
    }
    // dopo aver ottenuto (o no) gli utenti esco. Se ottengo gli utenti http_resp=200
    return;
}

// Da qua in poi non è richiesta la lista utenti
if (!$error) {
    pg_query('BEGIN') or die('Cant start transaction');
    $respMod = $respIns = null;

    // PASSO 1. controllo il ruolo.
    if ($sched['role'] == 'revisore') {
        // PASSO 2. aggiungo nuovi utenti
        if (isset($My_POST['ins'])) {
            foreach ($My_POST['ins'] as $userIns) {
                // ignoro direttamente gli utenti senza password e username
                if (!isset($userIns['username']) && !isset($userIns['password']))
                    continue;
                if (!$error) {
                    // agiungo utente corrente
                    $respIns = runPreparedQuery($conn, $c++,
                            'INSERT INTO utenti(username, password, role, iniziali, nome, cognome, id_min, id_max)
                            VALUES($1, $2, $3, $4, $5, $6, $7, $8)', array(
                        $userIns['username'], $userIns['password'], $userIns['role'], $userIns['iniziali'],
                        $userIns['nome'], $userIns['cognome'], $userIns['id_min'], $userIns['id_max']
                    ));
                    // controllo sia andata a buon fine la query senza sovrascrivere $error
                    $error = $error || !$respIns['ok'];
                } else {
                    break;
                }
            }
        }
        // PASSO 3. aggiorno utenti
        if (isset($My_POST['mod'])) {
            foreach ($My_POST['mod'] as $userMod) {
                // ignoro direttamente gli utenti senza password e username
                if (!isset($userMod['username']) && !isset($userMod['password']))
                    continue;
                if (!$error) {
                    // aggiorno utente corrente
                    $respMod = runPreparedQuery($conn, $c++,
                            'UPDATE utenti SET username=$1, password=$2, role=$3, iniziali=$4,
                            nome=$5, cognome=$6, id_min=$7, id_max=$8 WHERE gid=$9', array(
                        $userMod['username'], $userMod['password'], $userMod['role'], $userMod['iniziali'],
                        $userMod['nome'], $userMod['cognome'], $userMod['id_min'], $userMod['id_max'], $userMod['gid']
                    ));
                    // controllo sia andata a buon fine la query senza sovrascrivere $error
                    $error = $error || !$respMod['ok'];
                } else {
                    break;
                }
            }
        }
    } else {
        $error = true;
        http_response_code(401);
        $res['msg'] = 'Operazione non permessa. Non sei un revisore';
    }
    // per sicurezza controllo tutte le query
    $queryArr = [$respMod, $respIns];
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
