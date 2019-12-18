<?php

include('../../connection.php');
include('../../utils.php');
include('../../queryUtils.php');

header('Content-type: application/json');
$res = array();
$c = 0; // do un id progressivo alle query
$error = false;
http_response_code(200);
$My_POST = dictEmptyStr2NULL(beniJS2Postgres($_POST));

if (isset($My_POST['geom']) && count($My_POST['geom']) > 0 && !$error) {
    $maxDist = 50;
    $geomTxt = latLngArrToGeomTxt($My_POST['geom']);
    // converto in 3857 per avere le distance in metri
    $resp = runPreparedQuery($conn, $c++, "SELECT *,
            ST_DISTANCE(ST_Transform($geomTxt,3857), ST_Transform(geom,3857)) as dist
        FROM benigeo 
        WHERE id <> $1 AND      
        ST_DWITHIN(ST_Transform($geomTxt,3857), ST_Transform(geom,3857), $2)
        ORDER BY dist",
            [$My_POST['id'], $maxDist]);

    if (!$error && $resp['ok']) {
        while ($row = pg_fetch_assoc($resp['data'])) {
            array_push($res, $row);
        }
    }
}

echo json_encode($res);
?>