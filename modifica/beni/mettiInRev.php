<?php

include('../../connection.php');
include('../../utils.php');
include('../../queryUtils.php');
header('Content-type: application/json');
$c = 0;
http_response_code(500);
$My_POST = postEmptyStr2NULL();
$res = ["msg" => ''];

$user = risolviUtente($conn, $c++, $My_POST['username'], $My_POST['password']);
if (!isset($user)) {
    http_response_code(401);
    $res['msg'] = 'Username/Password invalidi';
    $error = true;
} else {
    $resp = runPreparedQuery($conn, $c++, 'UPDATE tmp_db.benigeo SET status=0 '
            . 'WHERE id_utente=$1 AND status=2', [$user['id']]);
    if ($resp['ok']) {
        http_response_code(200);
    } else {
        if (!isset($res['msg'])) //magari ho già scritto io un messaggio d'errore
            $res['msg'] = pg_result_error($resp['data']);
    }
}

echo json_encode($res);
