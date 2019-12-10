<?php

// analizza $_POST e converte le stringhe vuote in null
function postEmptyStr2NULL() {
    $copy = array();
    foreach ($_POST as $key => $val) {
        $val = filter_input(INPUT_POST, $key, FILTER_CALLBACK, array('options' => 'emptyStr2NULL'));
        $copy[$key] = $val ? $val : null;
    }
    return $copy;
}

// analizza $_GET e converte le stringhe vuote in null
function getEmptyStr2NULL() {
    $copy = array();
    foreach ($_GET as $key => $val) {
        $val = filter_input(INPUT_GET, $key, FILTER_CALLBACK, array('options' => 'emptyStr2NULL'));
        $copy[$key] = $val ? $val : null;
    }
    return $copy;
}

// analizza un dizionario converte in null tutti i valori con stringa vuota o solo spazi
function dictEmptyStr2NULL($dict) {
    foreach ($dict as $key => $val) {
        $dict[$key] = emptyStr2NULL($val);
    }
    return $dict;
}

// converte le stringhe vuote (dopo trim()) in valori null
function emptyStr2NULL($var) {
    if(!is_string($var)) return $var;
    $trimmed = trim($var);
    return $trimmed == '' ? null : $trimmed;
}

// in pratica rinomino le chiavi dei beni. Questo poichè nel caso cambi nome una attributo nel
// db non occorrerebbe cambiare i riferimenti anche nel client. Basta solo applicare la modifica alle funzioni
// beniPostgres2JS e beniJS2Postgres
// tutto ciò che può servire al client per processare un bene
// Inoltre questa cosa mi assicura l'esistenza delle variabili dentro i miei script, basta testare non siano NULL
function beniPostgres2JS($PostgresDict) {
    return ['id' => getOrSet($PostgresDict, 'id', ''),
        'id_utente' => getOrSet($PostgresDict, 'id_utente', ''), // in tmp_db è parte della chiave primaria per il bene
        'identificazione' => getOrSet($PostgresDict, 'ident', ''),
        'descrizione' => getOrSet($PostgresDict, 'descr', ''),
        'macroEpocaOrig' => getOrSet($PostgresDict, 'meo', ''),
        'macroEpocaCar' => getOrSet($PostgresDict, 'mec', ''),
        'toponimo' => getOrSet($PostgresDict, 'topon', ''),
        'esistenza' => getOrSet($PostgresDict, 'esist', ''),
        'comune' => getOrSet($PostgresDict, 'comun', ''),
        'bibliografia' => getOrSet($PostgresDict, 'bibli', ''),
        'schedatori_iniziali' => getOrSet($PostgresDict, 'schedatori_iniziali', ''),
        'note' => getOrSet($PostgresDict, 'note', ''),
        'geojson' => json_decode(getOrSet($PostgresDict, 'geojson', '')),
        'centroid' => json_decode(getOrSet($PostgresDict, 'centroid_geojson', '')),
        'status' => getOrSet($PostgresDict, 'status', ''),
        'msg_validatore' => getOrSet($PostgresDict, 'msg_validatore', '')
        ];
}

// stessa cosa per beniPostgres2JS
// tutto ciò che può servire al server per processare un bene
function beniJS2Postgres($JSDict) {
    return ['username' => getOrSet($JSDict, 'username', ''),
        'password' => getOrSet($JSDict, 'password', ''),
        'id' => getOrSet($JSDict, 'id', ''),
        'id_utente' => getOrSet($JSDict, 'id_utente', ''), // in tmp_db è parte della chiave primaria per il bene
        'ident' => getOrSet($JSDict, 'identificazione', ''),
        'descr' => getOrSet($JSDict, 'descrizione', ''),
        'meo' => getOrSet($JSDict, 'macroEpocaOrig', ''),
        'mec' => getOrSet($JSDict, 'macroEpocaCar', ''),
        'topon' => getOrSet($JSDict, 'toponimo', ''),
        'esist' => getOrSet($JSDict, 'esistenza', ''),
        'comun' => getOrSet($JSDict, 'comune', ''),
        'bibl' => getOrSet($JSDict, 'bibliografia', ''),
        'schedatori_iniziali' => getOrSet($JSDict, 'schedatori_iniziali', ''),
        'note' => getOrSet($JSDict, 'note', ''),
        'geom' => getOrSet(getOrSet($JSDict, 'polygon', []), 'latlngArr', ''),
        'status' => getOrSet($JSDict, 'status', ''),
        'msg_validatore' => getOrSet($JSDict, 'msg_validatore', ''),
        'switch_bene' => getOrSet($JSDict, 'switch_bene', ''),
        'tmp_db' => getOrSet($JSDict, 'tmp_db', false)
        ];
}

// in pratica rinomino le chiavi dei beni. Questo poichè nel caso cambi nome una attributo nel
// db non occorrerebbe cambiare i riferimenti anche nel client. Basta solo applicare la modifica alle funzioni
// beniPostgres2JS e beniJS2Postgres
// tutto ciò che può servire al client per processare un bene
function funzioniPostgres2JS($PostgresDict) {
    return ['id' => getOrSet($PostgresDict, 'id', ''),
        'id_utente' => getOrSet($PostgresDict, 'id_utente', ''), // in tmp_db è parte della chiave primaria per il bene
        'id_bene' => getOrSet($PostgresDict, 'id_bene', ''),
        'denominazione' => getOrSet($PostgresDict, 'denominazione', ''),
        'data' => getOrSet($PostgresDict, 'data', ''),
        'tipodata' => getOrSet($PostgresDict, 'tipodata', ''),
        'ruolo' => getOrSet($PostgresDict, 'ruolo', []),
        'id_bener' => getOrSet($PostgresDict, 'id_bener', ''),
        'id_utente_bene' => getOrSet($PostgresDict, 'id_utente_bene', ''),
        'id_utente_bener' => getOrSet($PostgresDict, 'id_utente_bener', ''),
        'denominazioner' => getOrSet($PostgresDict, 'denominazioner', ''),
        'ruolor' => getOrSet($PostgresDict, 'ruolor', []),
        'funzione' => getOrSet($PostgresDict, 'funzione', ''),
        'bibliografia' => getOrSet($PostgresDict, 'bibliografia', ''),
        'note' => getOrSet($PostgresDict, 'note', ''),
        'status' => getOrSet($PostgresDict, 'status', ''),
        'msg_validatore' => getOrSet($PostgresDict, 'msg_validatore', ''),
        'schedatori_iniziali' => getOrSet($PostgresDict, 'schedatori_iniziali', ''),
        ];
}

// stessa cosa per beniPostgres2JS
// tutto ciò che può servire al server per processare un bene
function funzioniJS2Postgres($JSDict) {
    return ['username' => getOrSet($JSDict, 'username', ''),
        'password' => getOrSet($JSDict, 'password', ''),
        'id' => getOrSet($JSDict, 'id', ''),
        'id_utente' => getOrSet($JSDict, 'id_utente', ''), // in tmp_db è parte della chiave primaria per il bene
        'id_bene' => getOrSet($JSDict, 'id_bene', ''),
        'denominazione' => getOrSet($JSDict, 'denominazione', ''),
        'id_utente_bene' => getOrSet($JSDict, 'id_utente_bene', ''),
        'id_utente_bener' => getOrSet($JSDict, 'id_utente_bener', ''),
        'data' => getOrSet($JSDict, 'data', ''),
        'tipodata' => getOrSet($JSDict, 'tipodata', ''),
        'ruolo' => getOrSet($JSDict, 'ruolo', []),
        'id_bener' => getOrSet($JSDict, 'id_bener', ''),
        'denominazioner' => getOrSet($JSDict, 'denominazioner', ''),
        'ruolor' => getOrSet($JSDict, 'ruolor', []),
        'funzione' => getOrSet($JSDict, 'funzione', ''),
        'bibliografia' => getOrSet($JSDict, 'bibliografia', ''),
        'note' => getOrSet($JSDict, 'note', ''),
        'status' => getOrSet($JSDict, 'status', ''),
        'msg_validatore' => getOrSet($JSDict, 'msg_validatore', ''),
        'switch_funzione' => getOrSet($JSDict, 'switch_funzione', ''),
        'tmp_db' => getOrSet($JSDict, 'tmp_db', false)
        ];
}

// praticamente rimpiazza i valori inesistenti (manca la chiave) o i valori NULL
// con il valore di default. Comodo per evitare null exeption in Javascript.
function getOrSet($dict, $key, $defaultVal) {
    if (isset($dict[$key]))
        return $dict[$key];
    else
        return $defaultVal;
}
/*/
Da rivedere se serve
function logInsert($txt) {
    return logTitleTxt('Insert', $txt);
}

function logUpdate($txt) {
    return logTitleTxt('Update', $txt);
}

function logDelete($txt) {
    return logTitleTxt('Delete', $txt);
}

// Non usare questa funzione
function logTitleTxt($title, $txt) {
    $query = "INSERT INTO logs.logs(title, txt) VALUES($1, $2)";
    $resp = runPreparedQuery($conn, $query, array($title, $txt));
    return $resp['ok'];
}/*/

?>