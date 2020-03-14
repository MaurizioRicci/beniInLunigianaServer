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
    $query = runPreparedQuery($conn, $c++, "
        WITH idMinMax AS (
            SELECT id_min,id_max FROM utenti where uid=$1
        ),
        idUsati AS ( -- id usati di beni temporanei e approvati
            SELECT id FROM tmp_db.benigeo WHERE id_utente=$1
            UNION
            SELECT id_bene as id FROM manipola_bene WHERE id_utente=$1
        ),
        countIdUsati AS ( --conto il numero di id usati
            SELECT COUNT(*) as c
            FROM idUsati
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
        )
        SELECT COALESCE(id, -1) as id --rendo -1 nel caso abbia finito gli id
        FROM (                             -- il valore -1 non viene accettato
            SELECT CASE
                --se non ha ancora usato id allora gli rendo id_min
                WHEN (SELECT c from countIdUsati)=0 THEN id_min
                -- se ha usato qualche id gli rendo il primo id libero
                -- se non ci sono id liberi id vale NULL
                ELSE (SELECT id FROM missingID)
                END as id
            FROM idMinMax
            ) as r", [$user['id']]);
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
