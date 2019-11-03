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

function dictEmptyStr2NULL($dict) {
    foreach ($dict as $key => $val) {
        $dict[$key] = emptyStr2NULL($val);
    }
    return $dict;
}

// converte le stringhe vuote (dopo trim()) in valori null
function emptyStr2NULL($var) {
    $trimmed = trim($var);
    return $trimmed == '' ? null : $trimmed;
}

function beniPostgres2JS($PostgresDict) {
    return array(
        'id' => getOrSet($PostgresDict, 'id', ''),
        'identificazione' => getOrSet($PostgresDict, 'ident', ''),
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
    );
}

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