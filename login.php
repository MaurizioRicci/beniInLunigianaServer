<?php

include('connection.php');
include('utils.php');
include('queryUtils.php');
header('Content-type: application/json');
$c = 0;
http_response_code(500);
$My_POST = postEmptyStr2NULL();
$res = array(
    "role" => '',
    "id" => '',
    "msg" => ''
);

$user = risolviUtente($conn, $c++, $My_POST['username'], $My_POST['password']);
if (!isset($user)) {
    http_response_code(401);
    $res['msg'] = 'Username/Password invalidi';
    $error = true;
}
else {
    // comunico id utente e ruolo al client. Serve per scegliere quale interfaccia utente mostrare in base al ruolo.
    $res['role'] = $user['role'];
    $res['id'] = $user['id'];
}

echo json_encode($res);