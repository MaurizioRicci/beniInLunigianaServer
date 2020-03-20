<?php

include('connection.php');
include('utils.php');
include('queryUtils.php');
header('Content-type: application/json');
$c = 0; // do un id progressivo alle query
$error = false;
// analizza $_GET e converte le stringhe vuote in null
$My_POST = postEmptyStr2NULL();
$res = array();
http_response_code(400);

$user = risolviUtente($conn, $c++, $My_POST['username'], $My_POST['password']);
if (!isset($user)) {
    http_response_code(401);
    $res['msg'] = 'Username/Password invalidi';
    $error = true;
}

if (!$error) {
    // ottengo il primo buco negli id della tabella desiderata
    // la query ha commenti all'interno
    $query = runPreparedQuery($conn, $c++, "
        WITH idMinMax AS (
            SELECT id_min,id_max FROM utenti where uid=$1
        ),
        idUsati AS ( -- id usati di beni temporanei e approvati
            SELECT id FROM tmp_db.benigeo WHERE id_utente=$1
                --considero solo gli id che può usare
                AND id>= (SELECT id_min FROM idMinMax)
                AND id<= (SELECT id_max FROM idMinMax)
            UNION
            SELECT id_bene as id FROM manipola_bene WHERE id_utente=$1
                AND id_bene>= (SELECT id_min FROM idMinMax)
                AND id_bene<= (SELECT id_max FROM idMinMax)
        ),
        missingID AS ( -- cerco il primo buco tra gli id usati
            SELECT id+1 as id
            FROM idUsati t1
            WHERE
            id>= (SELECT id_min FROM idMinMax) --il buco negli id deve essere usabile dall'utente
            AND id<= (SELECT id_max FROM idMinMax) --e' possibile che trovi id di beni non suoi che ha modificato altrimenti
            AND NOT EXISTS ( --cerco il primo buco
              SELECT NULL
              FROM idUsati t2
              WHERE t2.id=(t1.id+1)
            ) 
            AND NOT EXISTS ( -- non deve essere id di un bene già consolidato
              SELECT NULL
              FROM benigeo b
              WHERE b.id=t1.id
            )
            ORDER BY id LIMIT 1 -- rendo un solo id alla fine
        ),
        missingID2 AS (
            -- l'utente non ha usato degli id => e' nuovo
            SELECT CASE WHEN NOT EXISTS(SELECT NULL FROM idUsati)
                THEN (SELECT id_min FROM idMinMax)
            --l'utente ha usato degli id
            -- se esiste un buco negli id lo trova
            WHEN EXISTS(SELECT id FROM missingID) 
            THEN (SELECT id FROM missingID)
            -- altrimenti incrementa di 1 l'ultimo id usato
            ELSE (SELECT MAX(id)+1 FROM idUsati)
            END AS id 
        ),
        missingID3 AS (
            SELECT id
            FROM missingID2
            -- filtro gli id, incrementare di 1 l'ultimo id usato potrebbe essere
            -- un numero fuori intervallo
            WHERE id>= (SELECT id_min FROM idMinMax)
                AND id<= (SELECT id_max FROM idMinMax)
        )
        SELECT CASE WHEN EXISTS(SELECT id FROM missingID3)
            -- se ho trovato qualcosa lo rendo, altrimenti -1
            THEN (SELECT id FROM missingID3)
            ELSE -1 END AS id
        ", [$user['id']]);
    if ($query['ok']) {
        http_response_code(200);
        $row = pg_fetch_assoc($query['data']);
        $res['id'] = $row['id'];
    } else {
        http_response_code(500);
        $error = true;
        $res['msg'] = 'An error occured';
    }
}

echo json_encode($res);
