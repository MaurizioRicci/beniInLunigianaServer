<?php
include('../../connection.php');
include('../../queryUtils.php');

header('Content-type: application/json');
$res = array();
$c = 0;// do un id progressivo alle query
$error = false;
http_response_code(500);

$sched = risolviUtente($conn, $c++, $_POST['username'], $_POST['password']);
if(!isset($sched) && !$error){
	http_response_code(401);
	$res['msg'] = 'Username/Password invalidi';
	$error = true;
}

if(!!$error && !checkID($conn, $c++,$_POST['username'], $_POST['password'], $_POST['id'])){
	//richiesta sintatticamente corretta ma semanticamente errata
	http_response_code(422);
	$res['msg'] = 'Non hai accesso a questo id';
	$error = true;
}

foreach($_POST as $key => $val)
	if($val == '')$_POST[$key] = null;
	
if (isset($_POST['id']) && !$error) {

	pg_query('BEGIN') or die('Cant start transaction');
	$resp1 = $resp2 = null;
	//in base al ruolo utente scelgo in quale tabella mettere il bene
	if($sched['role'] == 'master'){
		$resp1 = insertIntoBeniGeo($conn, $c++, $_POST['id'], $_POST['ident'],
			$_POST['descr'], $_POST['mec'], $_POST['meo'], $_POST['bibl'],
			$_POST['note'], $_POST['topon'], $_POST['comun'], $_POST['geom']);
		//manipolabene serve se è validato il bene
		$resp2 = insertIntoManipolaBene($conn, $c++, $sched['id'], $_POST['id']);
	}
	else
		$resp1 = insertIntoBeniGeoTmp($conn, $c++,$_POST['id'], $_POST['ident'],
			$_POST['descr'], $_POST['mec'], $_POST['meo'], $_POST['bibl'],
			$_POST['note'], $_POST['topon'], $_POST['comun'], $_POST['geom']);

	if (checkAllPreparedQuery(array($resp1, $resp2))){
		pg_query('COMMIT');
		http_response_code(200);
	}
	else{
		pg_query('ROLLBACK');
		$failed_query = getFirstFailedQuery(array($resp1, $resp2));
		$res['msg'] = pg_result_error($failed_query['data']);
	}
}

echo json_encode($res);
?>