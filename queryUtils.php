<?php
function checkID ($conn, $stmtID, $username, $password, $id_to_check) {
	if(isset($username)&&isset($password)){
		$query = "SELECT id_min, id_max FROM public.utenti WHERE username=$1 and password=$2";
		$resp = runPreparedQuery($conn, $stmtID, $query, array($username, $password));
		if($resp['ok']) {
			$row = pg_fetch_assoc($resp['data']);
			return intval($row['id_min']) <= intval($id_to_check) &&
				intval($row['id_max']) >= intval($id_to_check);
		}
	}
	return false;
}

function risolviUtente($conn, $stmtID, $username, $password) {
	//valida un utente e ne estrae il ruolo
	if(isset($username)&&isset($password)){
		$query = "SELECT gid, role FROM utenti WHERE username=$1 and password=$2";
		$resp = runPreparedQuery($conn, $stmtID, $query, array($username, $password));
		if($resp['ok']) {
			$row = pg_fetch_assoc($resp['data']);
			return array(
				'id' => $row['gid'],
				'role' => $row['role']
			);
		}
	}
	return null;
}

function replaceIntoBeniGeo($conn, $stmtID, $id, $ident, $descr, $mec, $meo, $sched, $bibl, $note,
 $topon, $comun, $geom){
	$tablename = 'public.benigeo';
	$query = "update $tablename SET ident=$1, descr=$2, mec=$3, meo=$4, bibli=$5," .
	" note=$6, topon=$7, comun=$8, geom=ST_GeomFromText($9, 4326)) WHERE id=$10";
	return runPreparedQuery($conn, $stmtID, $query, array(
		$ident, $descr, $mec, $meo, $bibl, $note, $topon, $comun, $geom, $id
	));
 }

function insertIntoBeniGeo($conn, $stmtID, $id, $ident, $descr, $mec, $meo, $sched, $bibl, $note,
 $topon, $comun, $geom){
	$tablename = 'public.benigeo';
	$query = "INSERT INTO $tablename(id, ident, descr, mec, meo, bibli, note, topon, comun, geom) ".
	"VALUES($1,$2,$3,$4,$5,$6,$7,$8,$9,ST_GeomFromText($10, 4326))";
	return runPreparedQuery($conn, $stmtID, $query, array(
		$id, $ident, $descr, $mec, $meo, $bibl, $note, $topon, $comun, $geom
	));
}

function insertIntoBeniGeoTmp($conn, $stmtID, $id, $ident, $descr, $mec, $meo, $bibl, $note,
 $topon, $comun, $geom, $user_id){
	$tablename = 'tmp_db.benigeo';
	$query = "INSERT INTO $tablename(id, ident, descr, mec, meo, bibli, note, topon, comun, geom, user_id) ".
	"VALUES($1,$2,$3,$4,$5,$6,$7,$8,$9,ST_GeomFromText($10, 4326),$11)";
	return runPreparedQuery($conn, $stmtID, $query, array(
		$id, $ident, $descr, $mec, $meo, $bibl, $note, $topon, $comun, $geom, $user_id
	));
}

function insertIntoManipolaBene($conn, $stmtID, $userID, $beneID) {
	$query = "INSERT INTO public.manipola_bene(id_utente, id_bene) ".
	"VALUES($1,$2)";
	return runPreparedQuery($conn,$stmtID, $query, array($userID, $beneID));
}

function runPreparedQuery($conn, $stmtID, $query, $paramsArr){
	$res = array('ok' => false, 'data'=> array());
	$result = pg_prepare($conn, $stmtID, $query);
	if($result){
		pg_send_execute($conn, $stmtID, $paramsArr);
		$result = pg_get_result($conn);
		$error = pg_result_error ($result);
		$res['data'] = $result;
		if($error == '') {
			$res['ok'] = true;
		}
	}
	return $res;
}

//controlla che le query preparate eseguite siano andate a buon fine. I null sono ignorati
function checkAllPreparedQuery($pQueryArr){
	$ok = true;
	foreach ($pQueryArr as $value)
		if(isset($value)) $ok = $ok && $value['ok'];
	return $ok;
}

//da una lista di query preparate eseguite ottiene la prima con un errore
function getFirstFailedQuery($pQueryArr){
	$query = null;
	foreach ($pQueryArr as $value)
		if(isset($value) && !$value['ok']) $query = $value;
	return $query;	
}
?>