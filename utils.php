<?php
function beniPostgres2JS ($PostgresDict) {
	return array(
		'id' => getOrSet($PostgresDict,'id',null),
		'identificazione' => getOrSet($PostgresDict,'ident',null),
		'identificazione' => getOrSet($PostgresDict,'ident',null),
		'descrizione' => getOrSet($PostgresDict,'descr',null),
		'macroEpocaOrig' => getOrSet($PostgresDict,'meo',null),
		'macroEpocaCar' => getOrSet($PostgresDict,'mec',null),
		'toponimo' => getOrSet($PostgresDict,'topon',null),
		'esistenza' => getOrSet($PostgresDict,'esist',null),
		'comune' => getOrSet($PostgresDict,'comun',null),
		'bibliografia' => getOrSet($PostgresDict,'bibli',null),
		'schedatore' => getOrSet($PostgresDict,'sched',null),
		'note' => getOrSet($PostgresDict,'note',null),
		'geojson' => json_decode(getOrSet($PostgresDict,'geojson',null)),
		'centroid' => json_decode(getOrSet($PostgresDict,'centroid_geojson',null))
	);
}
function getOrSet($dict, $key, $defaultVal) {
	if(isset($dict[$key])) return $dict[$key];
	else return $defaultVal;
}

function logInsert($txt) {return logTitleTxt('Insert', $txt);}
function logUpdate($txt) {return logTitleTxt('Update', $txt);}
function logDelete($txt) {return logTitleTxt('Delete', $txt);}

// Non usare questa funzione
function logTitleTxt($title, $txt) {
	$query = "INSERT INTO logs.logs(title, txt) VALUES($1, $2)";
	$resp = runPreparedQuery($conn, $query, array($title, $txt));
	return $resp['ok'];
}
?>