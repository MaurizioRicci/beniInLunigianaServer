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

$utente = risolviUtente($conn, $c++, $My_POST['username'], $My_POST['password']);
if (!isset($utente) && !$error) {
    http_response_code(401);
    $res['msg'] = 'Username/Password invalidi';
    $error = true;
}
// Ottengo in beni inseriti da un certo utente
$query_funzioni_aggiunte = 'SELECT * '
        . 'FROM funzionigeo_ruoli_schedatori as b inner join manipola_funzione as m ON(b.id=m.id_funzione)
    WHERE m.id_utente=$1';

// Ottengo le funzioni di un certo utente
$query_funzioni_temp = 'SELECT * '
        . 'FROM tmp_db.funzionigeo_e_ruoli WHERE id_utente=$1';

if (!$error) {
    $params = array($utente['id']);
    if (isset($My_POST['switch_funzione'])) {
        switch ($My_POST['switch_funzione']) {
            case 'miei_aggiunti':
                $query = $query_funzioni_aggiunte;
                break;
            case 'miei_temp':
                $query = $query_funzioni_temp;
                if ($utente['role'] == 'revisore') {
                    $params = [];
                    $query = 'SELECT * FROM tmp_db.funzionigeo_e_ruoli WHERE status=0';
                }
                break;
        }
        $resp = runPreparedQuery($conn, $c++, $query, $params);

        if ($resp['ok']) {
            while ($row = pg_fetch_assoc($resp['data'])) {
                array_push($res, funzioniPostgres2JS($row));
            }
            http_response_code(200);
        } else
            echo pg_result_error($resp['data']);
    }
}

echo json_encode($res);
