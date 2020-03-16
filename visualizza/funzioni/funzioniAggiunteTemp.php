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
// Ottengo le funzioni inserite/modificate da un certo utente
// se un utente ha fatto più modifiche mostra la funzione una volta sola
$query_funzioni_aggiunte = 'SELECT * '
        . 'FROM funzionigeo_ruoli_schedatori as b inner join (
             SELECT DISTINCT id_funzione, id_utente FROM manipola_funzione WHERE id_utente=$1) as m ON(b.id=m.id_funzione)
             ORDER BY id';

// Ottengo le funzioni di un certo utente
$query_funzioni_temp = 'SELECT * '
        . 'FROM tmp_db.funzionigeo_e_ruoli_schedatore WHERE id_utente=$1 ORDER BY id';

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
                    $query = "SELECT *,"
                            . " exists (select NULL from benigeo as b1 where b1.id=id_bene) as bene_approvato,"
                            . " exists (select NULL from benigeo as b2 where b2.id=id_bener) as bener_approvato"
                            . " FROM tmp_db.funzionigeo_e_ruoli_schedatore"
                            . " WHERE status=0 OR status=1 ORDER BY id";
                }
                break;
        }
        $resp = runPreparedQuery($conn, $c++, $query, $params);

        if ($resp['ok']) {
            while ($row = pg_fetch_assoc($resp['data'])) {
                $tmp = funzioniPostgres2JS($row);
                $tmp['bene_approvato'] = getOrDefault($row, 'bene_approvato', 'true');
                $tmp['bener_approvato'] = getOrDefault($row, 'bener_approvato', 'true');
                array_push($res, $tmp);
            }
            http_response_code(200);
        } else
            echo pg_result_error($resp['data']);
    }
}

echo json_encode($res);
