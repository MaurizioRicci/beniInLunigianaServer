<?php

include('connection.php');
include('utils.php');
include('queryUtils.php');

header('Content-type: application/json');
$res = array();
$c = 0; // do un id progressivo alle query
$error = false;
http_response_code(500);
// analizza $_POST e converte le stringhe vuote in null
$My_POST = postEmptyStr2NULL();

$null2EmptyStr = function ($el) {
    return $el == null ? '' : $el;
};

$user = risolviUtente($conn, $c++, $My_POST['username'], $My_POST['password']);
if (!isset($user) && !$error) {
    http_response_code(401);
    $res['msg'] = 'Username/Password invalidi';
    $error = true;
}

// Controllo se è richiesta la lista utenti
if (isset($My_POST['usersList'])) {
    if ($user['role'] == 'revisore') {
        $query = "SELECT U.*, COUNT(distinct B.id) as nbeni_tmp, COUNT(distinct F.id) as nfunzioni_tmp"
                . " FROM utenti U LEFT JOIN tmp_db.benigeo B ON (B.id_utente=U.uid)"
                . " LEFT JOIN tmp_db.funzionigeo F ON (F.id_utente=U.uid)"
                . " GROUP BY U.uid"
                . " ORDER BY U.id_min";
        $query = runPreparedQuery($conn, $c++, $query, []);
        if ($query['ok']) {
            while ($row = pg_fetch_assoc($query['data'])) {
                $newRow = array_map($null2EmptyStr, $row);
                array_push($res, $newRow);
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

// Mi accerto che nel range di id assegnato all'utente non siano compresi beni già approvati o beni temporanei di altri utenti
function testID($conn, $stmtID, $idMin, $idMax, $id_utente) {
    // se id utente non c'è è perchè si aggiunge l'utente
    // qualsiasi id utente è diverso da -1 => è auto increment parte da 1
    // scarto gli id dello stesso utente, altrimenti non si potrebbe incrementare solo id_max di quel dato utente.
    $query = "SELECT id FROM benigeo AS b JOIN manipola_bene AS m ON(b.id=m.id_bene)
                WHERE b.id>=$1 AND b.id<=$2 AND m.id_utente <> $3
             UNION
             SELECT id FROM tmp_db.benigeo WHERE id>=$1 AND id<=$2 AND id_utente <> $3";
    $resp_ID_OK = runPreparedQuery($conn, $stmtID, $query, [$idMin, $idMax, $id_utente]);
    return $resp_ID_OK['ok'] && pg_num_rows($resp_ID_OK['data']) <= 0;
}

// Da qua in poi non è richiesta la lista utenti
if (!$error) {
    pg_query('BEGIN ISOLATION LEVEL SERIALIZABLE') or die('Cant start transaction');
    $respMod = $respIns = null;
    // PASSO 1. controllo il ruolo.
    if ($user['role'] == 'revisore') {
        // PASSO 2. aggiungo nuovi utenti
        if (isset($My_POST['ins'])) {
            foreach ($My_POST['ins'] as $userIns) {
                // ignoro direttamente gli utenti senza password e username
                if (!isset($userIns['username']) && !isset($userIns['password']))
                    continue;
                if (!$error) {
                    if (!testID($conn, $c++, $userIns['id_min'], $userIns['id_max'], -1)) {
                        $error = true;
                        $res['msg'] = "Il range di id assegnato a ${userIns['username']} collide con quello di beni approvati o con quello di altri beni in revisione.";
                    } else {
                        // agiungo utente corrente
                        $respIns = runPreparedQuery($conn, $c++,
                                'INSERT INTO utenti(username, password, role, iniziali, nome, cognome, id_min, id_max, email)
                            VALUES($1, $2, $3, $4, $5, $6, $7, $8, $9)', array(
                            $userIns['username'], $userIns['password'], $userIns['role'], $userIns['iniziali'],
                            $userIns['nome'], $userIns['cognome'], $userIns['id_min'], $userIns['id_max'], $userIns['email']
                        ));
                        // controllo sia andata a buon fine la query senza sovrascrivere $error
                        $error = $error || !$respIns['ok'];
                    }
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
                    if (!testID($conn, $c++, $userMod['id_min'], $userMod['id_max'], $userMod['uid'])) {
                        $error = true;
                        $res['msg'] = "Il range di id assegnato a ${userMod['username']} collide con quello di beni approvati o con quello di altri beni in revisione.";
                    } else {
                        // aggiorno utente corrente
                        $respMod = runPreparedQuery($conn, $c++,
                                'UPDATE utenti SET username=$1, password=$2, role=$3, iniziali=$4,
                            nome=$5, cognome=$6, id_min=$7, id_max=$8, email=$9 WHERE uid=$10', array(
                            $userMod['username'], $userMod['password'], $userMod['role'], $userMod['iniziali'],
                            $userMod['nome'], $userMod['cognome'], $userMod['id_min'], $userMod['id_max'],
                            $userMod['email'], $userMod['uid']
                        ));
                        // controllo sia andata a buon fine la query senza sovrascrivere $error
                        $error = $error || !$respMod['ok'];
                    }
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
