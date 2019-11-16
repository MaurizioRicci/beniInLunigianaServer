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
// Ottengo in beni inseriti da un certo utente
$query_beni_aggiunti_miei = 'SELECT 
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

// Ottengo tutti i beni inseriti
$query_beni_aggiunti_tutti = 'SELECT 
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
    FROM benigeo_e_schedatori';

// Ottengo in beni in revisione di un certo utente
$query_beni_revisione_miei = 'SELECT 
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
    FROM tmp_db.benigeo as b WHERE (b.status BETWEEN 0 AND 1) AND b.id_utente=$1';
// status = 0/1 per bene in attesa revisione/da rivedere

if (!$error) {
    $params = array($utente['id']);

    if ($utente['role'] == 'revisore') {
        // i revisori nel caso vogliano i beni in revisione devono averli tutti
        $params = array();
        // vado quindi a rimuovere la parte finale della query '...AND id_utente=x'
        // senza più il filtro i revisori vedono tutti i beni in revisione
        $index = strripos($query_beni_revisione_miei, 'AND');
        $query_beni_revisione = substr($query_beni_revisione, 0, $index - 1);
    }

    if (isset($My_POST['switch_bene'])) {
        switch ($My_POST['switch_bene']) {
            case 'miei_aggiunti':
                $query = $query_beni_aggiunti_miei;
                break;
            case 'miei_revisione':
                $query = $query_beni_revisione_miei;
                break;
            case 'tutti_aggiunti':
                $query = $query_beni_aggiunti_tutti;
                break;
        }
        //$query = $My_POST['switch_bene'] == 'aggiunti' ? $query_beni_aggiunti : $query_beni_revisione;
        $resp = runPreparedQuery($conn, $c++, $query, $params);

        if ($resp['ok']) {
            // $res['echo'] = intval($My_POST['echo']);
            //$res['filtered'] = pg_num_rows($resp['data']);
            //$res['data'] = [];
            while ($row = pg_fetch_assoc($resp['data'])) {
                array_push($res, beniPostgres2JS($row));
            }
            http_response_code(200);
        }
    }
}

echo json_encode($res);