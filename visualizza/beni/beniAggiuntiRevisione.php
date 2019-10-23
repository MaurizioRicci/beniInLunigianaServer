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

$utente = risolviUtente($conn, $c++, $My_POST['username'], $My_POST['password']);
if (!isset($utente) && !$error) {
    http_response_code(401);
    $res['msg'] = 'Username/Password invalidi';
    $error = true;
}

$query_beni_aggiunti = 'SELECT 
        b.id,
        b.ident,
        b.descr,
        b.meo,
        b.mec,
        b.topon,
        b.esist,
        b.comun,
        b.bibli,
        b.note,
        b.geom,
        m.id_utente,
        m.timestamp_utc
    FROM benigeo as b inner join manipola_bene as m ON(b.id=m.id_bene)
    WHERE m.id_utente=$1';

$query_beni_revisione = 'SELECT 
        b.id,
        b.ident,
        b.descr,
        b.meo,
        b.mec,
        b.topon,
        b.esist,
        b.comun,
        b.bibli,
        b.note,
        b.geom,
        b.id_utente,
        b.timestamp_utc,
        b.status,
        b.msg_validatore
    FROM tmp_db.benigeo as b WHERE b.id_utente=$1';

$query = isset($My_POST['switch_bene']) &&
        $My_POST['switch_bene'] == 'aggiunti' ? $query_beni_aggiunti : $query_beni_revisione;

if (!$error) {
    $params = array($utente['id']);

    if ($utente['role'] == 'master') {
        // i revisori nel caso vogliano i beni in revisione devono averli tutti
        $params = array();
        // vado quindi a rimuovere la parte finale della query '...WHERE id_utente=x'
        // senza più il filtro i revisori vedono tutti i beni in revisione
        $index = strripos($query_beni_revisione, 'WHERE');
        $query_beni_revisione = substr($query_beni_revisione, 0, $index - 1);
    }

    $resp = runPreparedQuery($conn, $c++, $query, $params);

    if ($resp['ok']) {
        while ($row = pg_fetch_assoc($resp['data'])) {
            array_push($res, beniPostgres2JS($row));
        }
        http_response_code(200);
    }
}

echo json_encode($res);
