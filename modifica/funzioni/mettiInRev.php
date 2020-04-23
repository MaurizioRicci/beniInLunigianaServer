<?php

include('../../connection.php');
include('../../utils.php');
include('../../queryUtils.php');
header('Content-type: application/json');
$c = 0;
http_response_code(500);
$My_POST = postEmptyStr2NULL();
$res = [];

$user = risolviUtente($conn, $c++, $My_POST['username'], $My_POST['password']);
if (!isset($user)) {
    http_response_code(401);
    $res['msg'] = 'Username/Password invalidi';
    $error = true;
} else {
    $query = "WITH beni_tmp_utente_revisione AS (
                SELECT id
                FROM tmp_db.benigeo
                WHERE id_utente=$1 AND status=0
            )
            SELECT id as id_funzione, id_bene, id_bener FROM tmp_db.funzionigeo as f 
            WHERE id_utente=$1 AND status=2
            AND ( -- e
              ( -- o id_bene non esiste ne in arch def ne in arch. tmp dell'utente
		-- prestare attenzione che non deve essere null id_bene
                NOT EXISTS (SELECT null from beni_tmp_utente_revisione AS bur WHERE bur.id=f.id_bene)
                AND NOT EXISTS (SELECT null from benigeo WHERE id=f.id_bene)
                AND f.id_bene IS NOT NULL )
              OR ( 
		-- o id_bener non esiste ne in arch def ne in arch. tmp dell'utente
		-- prestare attenzione che non deve essere null id_bener
                NOT EXISTS (SELECT null from beni_tmp_utente_revisione AS bur WHERE bur.id=f.id_bener)
                AND NOT EXISTS (SELECT null from benigeo WHERE id=f.id_bener)
                AND f.id_bener IS NOT NULL )
            )
            ";
    $resp0 = runPreparedQuery($conn, $c++, $query, [$user['id']]);
    $id_mancanti = []; // salvo gli id dei beni referenziati che non sono ne in arch. definitivo ne in revisione
    $id_funzioni_non_inviare = []; // le funzioni che puntano a id di beni mancanti non devono essere inviate al controllo
    $txt = "";
    if ($resp0['ok']) {
        while ($row = pg_fetch_assoc($resp0['data'])) {
            array_push($id_mancanti, $row['id_bene']);
			array_push($id_mancanti, $row['id_bener']);
            array_push($id_funzioni_non_inviare, $row['id_funzione']);
        }
        $txt = join(",", $id_mancanti);
    }
    // dichiaro un array postgreSQL di interi
    // sono gli id delle funzioni che NON devono passare al revisore poichè hanno dei beni
    // che non sono ne in arch. definitivo ne sono stati mandati al revisore.
    $postgresArr_pt1 = join(',', $id_funzioni_non_inviare);
    $postgresArr_pt2 = "'{ $postgresArr_pt1 }'::int[]";
    $resp = runPreparedQuery($conn, $c++, 'UPDATE tmp_db.funzionigeo SET status=0 '
            . "WHERE id_utente=$1 AND status=2 AND id != ALL($postgresArr_pt2)", [$user['id']]);
    if ($resp['ok']) {
        http_response_code(200);
        $res['msg'] = $txt;
    } else {
        if (!isset($res['msg'])) //magari ho già scritto io un messaggio d'errore
            $res['msg'] = pg_result_error($resp['data']);
    }
}

echo json_encode($res);
