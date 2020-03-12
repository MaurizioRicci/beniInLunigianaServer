<?php

include('connection.php');
include('utils.php');
include('queryUtils.php');

$c = 0; // do un id progressivo alle query
$res = array();
http_response_code(200);

if (isset($_GET['comune'])) {

    $comune = $_GET['comune'] . "%";
    $query = runPreparedQuery($conn, $c++, "SELECT DISTINCT comun FROM benigeo WHERE comun ILIKE $1 ORDER BY comun ASC LIMIT 100",
            [$comune]);
    if ($query['ok']) {
        while ($row = pg_fetch_assoc($query['data'])) {
            $temp = ['value' => $row['comun']];
            array_push($res, $temp);
        }
    } else {
        http_response_code(500);
        $error = true;
        $res['msg'] = 'An error occured';
    }
}
header('Content-type: application/json');
echo json_encode($res);
