<?php
function beniPostgres2JS ($PostgresDict) {
	return array(
		'id' => $PostgresDict['id'],
		'identificazione' => $PostgresDict['ident'],
		'identificazione' => $PostgresDict['ident'],
		'descrizione' => $PostgresDict['descr'],
		'macroEpocaOrig' => $PostgresDict['meo'],
		'macroEpocaCar' => $PostgresDict['mec'],
		'toponimo' => $PostgresDict['topon'],
		'esistenza' => $PostgresDict['esist'],
		'comune' => $PostgresDict['comun'],
		'bibliografia' => $PostgresDict['bibli'],
		'schedatore' => $PostgresDict['sched'],
		'note' => $PostgresDict['note']
	);
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