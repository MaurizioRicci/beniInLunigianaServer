<?php

/* /
 * Questa funzione serve ai revisori per correggere un bene in revisione
 * che necessita modifiche.
 */
include('../../connection.php');
include('../../utils.php');
include('../../queryUtils.php');

header('Content-type: application/json');
$res = array();
$c = 0; // do un id progressivo alle query
$error = false;
http_response_code(500);
$My_POST = postEmptyStr2NULL();

$user = risolviUtente($conn, $c++, $My_POST['username'], $My_POST['password']);
if (!isset($user) && !$error) {
    http_response_code(401);
    $res['msg'] = 'Username/Password invalidi';
    $error = true;
}

if (isset($My_POST['id']) && !$error) {

    pg_query('BEGIN') or die('Cant start transaction');
    $resp1 = $queryID = null;

    if ($user['role'] == 'schedatore') {
        //può esserci un solo bene distinto in revisione
        $queryID = runPreparedQuery($conn, $c++,
                'SELECT id from tmp_db.benigeo where id=$1 and status=1 FOR UPDATE', array($My_POST['id']));
        // controllo che anche la select sia andata a buon fine
        $error = !$queryID['ok'];
        if (!error) {
            if (pg_num_rows($queryID['data']) > 0) {
                $resp1 = replaceIntoBeniGeoTmp($conn, $c++, $My_POST['id'], $My_POST['ident'],
                        $My_POST['descr'], $My_POST['mec'], $My_POST['meo'], $My_POST['bibl'],
                        $My_POST['note'], $My_POST['topon'], $My_POST['comun'], $My_POST['geom'],
                        $user['id'], $My_POST['status'], $My_POST['esist']);
            }
        }
    }
    // controllo tutte le query
    $queryArr = array($resp1, $queryID);
    if (!$error && checkAllPreparedQuery($queryArr)) {
        if (pg_query('COMMIT')) {
            http_response_code(200);
        } else {
            $res['msg'] = $transazione_fallita_msg;
        }
    } else {
        pg_query('ROLLBACK');
        $failed_query = getFirstFailedQuery($queryArr);
        if (!isset($res['msg']) && isset($failed_query)) //magari ho già scritto io un messaggio d'errore
            $res['msg'] = pg_result_error($failed_query['data']);
    }
}

echo json_encode($res);
?>