<?php

include('connection.php');
include('utils.php');
include('queryUtils.php');
header('Content-type: application/json');
$c = 0; // do un id progressivo alle query
$error = false;
// analizza $_GET e converte le stringhe vuote in null
$My_GET = getEmptyStr2NULL();
$res = array();
http_response_code(200);

if (isset($My_GET['identificazione'])) {

    $identificazione = $My_GET['identificazione'] . "%";
    $query = runPreparedQuery($conn, $c++, "SELECT id, ident FROM benigeo WHERE ident ILIKE $1 ORDER BY ident ASC LIMIT 100",
            [$identificazione]);
    if ($query['ok']) {
        while ($row = pg_fetch_assoc($query['data'])) {
            $temp = array(
                'id' => $row['id'],
                'value' => $row['ident']);
            array_push($res, $temp);
        }
    } else {
        http_response_code(500);
        $error = true;
        $res['msg'] = 'An error occured';
    }
}

echo json_encode($res);
?>