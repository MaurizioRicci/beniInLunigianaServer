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
$My_POST = dictEmptyStr2NULL(beniJS2Postgres($_POST));

$utente = risolviUtente($conn, $c++, $My_POST['username'], $My_POST['password']);
if (!isset($utente) && !$error) {
    http_response_code(401);
    $res['msg'] = 'Username/Password invalidi';
    $error = true;
}
// Ottengo in beni inseriti da un certo utente
// se un utente ha fatto più modifiche mostra il bene una volta sola
$query_beni_aggiunti = 'SELECT * '
        . 'FROM benigeo_e_schedatori as b inner join (
             SELECT DISTINCT id_bene, id_utente FROM manipola_bene WHERE id_utente=$1) as m ON(b.id=m.id_bene)
             ORDER BY id';

// Ottengo in beni di un certo utente
$query_beni_temp = 'SELECT * '
        . 'FROM tmp_db.benigeo_e_schedatori WHERE id_utente=$1 ORDER BY id';


if (!$error) {
    $params = array($utente['id']);

    if (isset($My_POST['switch_bene'])) {
        switch ($My_POST['switch_bene']) {
            case 'miei_aggiunti':
                $query = $query_beni_aggiunti;
                break;
            case 'miei_temp':
                $query = $query_beni_temp;
                if ($utente['role'] == 'revisore') {
                    $params = [];
                    $query = 'SELECT * FROM tmp_db.benigeo_e_schedatori WHERE status=0 ORDER BY id';
                }
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
        } else
            echo pg_result_error($resp['data']);
    }
}

echo json_encode($res);
