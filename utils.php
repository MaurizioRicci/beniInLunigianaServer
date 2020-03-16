<?php

// analizza $_POST e converte le stringhe vuote in null
function postEmptyStr2NULL() {
    $copy = array();
    foreach ($_POST as $key => $val) {
        if ($key !== 'username' && $key !== 'password') {
            $val = filter_input(INPUT_POST, $key, FILTER_CALLBACK, array('options' => 'emptyStr2NULL'));
        }
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

// converte le stringhe vuote (dopo trim()) in valori null & fail trim della stringa
function emptyStr2NULL($var) {
    if (!is_string($var))
        return $var;
    $trimmed = trim($var);
    return $trimmed == '' ? null : $trimmed;
}

// in pratica rinomino le chiavi dei beni. Questo poichè nel caso cambi nome una attributo nel
// db non occorrerebbe cambiare i riferimenti anche nel client. Basta solo applicare la modifica alle funzioni
// beniPostgres2JS e beniJS2Postgres
// tutto ciò che può servire al client per processare un bene
// Inoltre questa cosa mi assicura l'esistenza delle variabili dentro i miei script, basta testare non siano NULL
function beniPostgres2JS($PostgresDict) {
    return ['id' => getOrDefault($PostgresDict, 'id', ''),
        // id_utente serve per validare, segnalare beni. Quando il revisore tocca qualcosa in tmp_db serve id_utente
        'id_utente' => getOrDefault($PostgresDict, 'id_utente', ''), // in tmp_db è parte della chiave primaria per il bene
        'identificazione' => getOrDefault($PostgresDict, 'ident', ''),
        'descrizione' => getOrDefault($PostgresDict, 'descr', ''),
        'macroEpocaOrig' => getOrDefault($PostgresDict, 'meo', ''),
        'macroEpocaCar' => getOrDefault($PostgresDict, 'mec', ''),
        'toponimo' => getOrDefault($PostgresDict, 'topon', ''),
        'esistenza' => getOrDefault($PostgresDict, 'esist', ''),
        'comune' => getOrDefault($PostgresDict, 'comun', ''),
        'bibliografia' => getOrDefault($PostgresDict, 'bibli', ''),
        'schedatori_iniziali' => getOrDefault($PostgresDict, 'schedatori_iniziali', ''),
        'note' => getOrDefault($PostgresDict, 'note', ''),
        'geojson' => json_decode(getOrDefault($PostgresDict, 'geojson', '')),
        'centroid' => json_decode(getOrDefault($PostgresDict, 'centroid_geojson', '')),
        'status' => getOrDefault($PostgresDict, 'status', ''),
        'msg_validatore' => getOrDefault($PostgresDict, 'msg_validatore', '')
    ];
}

// stessa cosa per beniPostgres2JS
// tutto ciò che può servire al server per processare un bene
function beniJS2Postgres($JSDict) {
    return ['username' => getOrDefault($JSDict, 'username', ''),
        'password' => getOrDefault($JSDict, 'password', ''),
        'id' => getOrDefault($JSDict, 'id', ''),
        'id_utente' => getOrDefault($JSDict, 'id_utente', ''), // in tmp_db è parte della chiave primaria per il bene
        'ident' => getOrDefault($JSDict, 'identificazione', ''),
        'descr' => getOrDefault($JSDict, 'descrizione', ''),
        'meo' => getOrDefault($JSDict, 'macroEpocaOrig', ''),
        'mec' => getOrDefault($JSDict, 'macroEpocaCar', ''),
        'topon' => getOrDefault($JSDict, 'toponimo', ''),
        'esist' => getOrDefault($JSDict, 'esistenza', ''),
        'comun' => getOrDefault($JSDict, 'comune', ''),
        'bibl' => getOrDefault($JSDict, 'bibliografia', ''),
        'schedatori_iniziali' => getOrDefault($JSDict, 'schedatori_iniziali', ''),
        'note' => getOrDefault($JSDict, 'note', ''),
        'geom' => getOrDefault(getOrDefault($JSDict, 'polygon', []), 'latlngArr', ''),
        'status' => getOrDefault($JSDict, 'status', ''),
        'msg_validatore' => getOrDefault($JSDict, 'msg_validatore', ''),
        'switch_bene' => getOrDefault($JSDict, 'switch_bene', ''),
        'tmp_db' => getOrDefault($JSDict, 'tmp_db', false)
    ];
}

// in pratica rinomino le chiavi dei beni. Questo poichè nel caso cambi nome una attributo nel
// db non occorrerebbe cambiare i riferimenti anche nel client. Basta solo applicare la modifica alle funzioni
// beniPostgres2JS e beniJS2Postgres
// tutto ciò che può servire al client per processare un bene
function funzioniPostgres2JS($PostgresDict) {
    return ['id' => getOrDefault($PostgresDict, 'id', ''),
        'id_utente' => getOrDefault($PostgresDict, 'id_utente', ''), // in tmp_db è parte della chiave primaria per il bene
        'id_bene' => getOrDefault($PostgresDict, 'id_bene', ''),
        'denominazione' => getOrDefault($PostgresDict, 'denominazione', ''),
        'data_ante' => getOrDefault($PostgresDict, 'data_ante', ''),
        'data_poste' => getOrDefault($PostgresDict, 'data_poste', ''),
        'tipodata' => getOrDefault($PostgresDict, 'tipodata', ''),
        'ruolo' => getOrDefault($PostgresDict, 'ruolo', []),
        'id_bener' => getOrDefault($PostgresDict, 'id_bener', ''),
        'id_utente_bene' => getOrDefault($PostgresDict, 'id_utente_bene', ''),
        'id_utente_bener' => getOrDefault($PostgresDict, 'id_utente_bener', ''),
        'denominazioner' => getOrDefault($PostgresDict, 'denominazioner', ''),
        'ruolor' => getOrDefault($PostgresDict, 'ruolor', []),
        'funzione' => getOrDefault($PostgresDict, 'funzione', ''),
        'bibliografia' => getOrDefault($PostgresDict, 'bibliografia', ''),
        'note' => getOrDefault($PostgresDict, 'note', ''),
        'status' => getOrDefault($PostgresDict, 'status', ''),
        'msg_validatore' => getOrDefault($PostgresDict, 'msg_validatore', ''),
        'schedatori_iniziali' => getOrDefault($PostgresDict, 'schedatori_iniziali', ''),
    ];
}

// stessa cosa per beniPostgres2JS
// tutto ciò che può servire al server per processare un bene
function funzioniJS2Postgres($JSDict) {
    return ['username' => getOrDefault($JSDict, 'username', ''),
        'password' => getOrDefault($JSDict, 'password', ''),
        'id' => getOrDefault($JSDict, 'id', ''),
        'id_utente' => getOrDefault($JSDict, 'id_utente', ''), // in tmp_db è parte della chiave primaria per il bene
        'id_bene' => getOrDefault($JSDict, 'id_bene', ''),
        'denominazione' => getOrDefault($JSDict, 'denominazione', ''),
        'id_utente_bene' => getOrDefault($JSDict, 'id_utente_bene', ''),
        'id_utente_bener' => getOrDefault($JSDict, 'id_utente_bener', ''),
        'data_ante' => getOrDefault($JSDict, 'data_ante', ''),
        'data_poste' => getOrDefault($JSDict, 'data_poste', ''),
        'tipodata' => getOrDefault($JSDict, 'tipodata', ''),
        'ruolo' => dictEmptyStr2NULL(getOrDefault($JSDict, 'ruolo', [])),
        'id_bener' => getOrDefault($JSDict, 'id_bener', ''),
        'denominazioner' => getOrDefault($JSDict, 'denominazioner', ''),
        'ruolor' => dictEmptyStr2NULL(getOrDefault($JSDict, 'ruolor', [])),
        'funzione' => getOrDefault($JSDict, 'funzione', ''),
        'bibliografia' => getOrDefault($JSDict, 'bibliografia', ''),
        'note' => getOrDefault($JSDict, 'note', ''),
        'status' => getOrDefault($JSDict, 'status', ''),
        'msg_validatore' => getOrDefault($JSDict, 'msg_validatore', ''),
        'switch_funzione' => getOrDefault($JSDict, 'switch_funzione', ''),
        'tmp_db' => getOrDefault($JSDict, 'tmp_db', false)
    ];
}

// Se il valore ritrovato con la specifica chiave è diverso da NULL restituisce il valore.
// Se il valore ritrovato è NULL o se la chiave non esiste, restituisce il valore di default.
// Comodo per evitare null exeption in Javascript.
function getOrDefault($dict, $key, $defaultVal) {
    if (isset($dict[$key]))
        return $dict[$key];
    else
        return $defaultVal;
}

/* /
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
  }/ */
?>