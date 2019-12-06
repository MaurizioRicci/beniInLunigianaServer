<?php

include('../../connection.php');
include('../../queryUtils.php');
include('../../utils.php');

header('Content-type: application/json');
// analizza $_POST e converte le stringhe vuote in null
$My_POST = dictEmptyStr2NULL(beniJS2Postgres($_POST));
$res = array();
$c = 0;
http_response_code(500);

if (isset($My_POST['id'])) {

    $id = $My_POST['id'];
    $query = "SELECT *, ST_AsGeoJSON(geom) as geojson, ST_AsGeoJSON(ST_Centroid(geom)) " .
            "as centroid_geojson FROM benigeo_e_schedatori WHERE id=$1";
    $params = [$id];

    // se tmpdb è settato ed è true allora ho bisogno anche di id_utente, username e password
    if (isset($My_POST['tmp_db'])) {
        $tmp_db = filter_var($My_POST['tmp_db'], FILTER_VALIDATE_BOOLEAN);
        if ($tmp_db) {
            $id_utente = $My_POST['id_utente'];
            // controllo che se viene richiesto un bene in revisione, chi lo richiede sia o un revisore o il proprietario
            if (isset($My_POST['username']) && isset($My_POST['id_utente'])) {
                $utente = risolviUtente($conn, $c++, $My_POST['username'], $My_POST['password']);

                if (($utente['id'] != $My_POST['id_utente']) &&
                        ($utente['role'] !== 'revisore')) {
                    http_response_code(422);
                    $res['msg'] = 'Sei uno scedatore, non puoi vedere i beni in revisione altrui';
                    echo json_encode($res);
                    return;
                }
            }
            // se cerco nel db temporaneo serve anche l'id utente
            $query = "SELECT *, ST_AsGeoJSON(geom) as geojson, ST_AsGeoJSON(ST_Centroid(geom)) " .
                    "as centroid_geojson FROM tmp_db.benigeo WHERE id=$1 AND id_utente=$2";
            $params = [$id, $id_utente];
        }
    }

    $result = runPreparedQuery($conn, $c++, $query, $params);
    if ($result['ok']) {
        while ($row = pg_fetch_assoc($result['data'])) {
            $res = beniPostgres2JS($row);
        }
        http_response_code(200);
    } else {
        $res['msg'] = pg_result_error($conn);
    }
}
exit(json_encode($res));
