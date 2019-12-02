<?php

/* /
 * Tutte le query usate con parametri sono preparate. 
 * Internamente viene usato pg_prepare e pg_send_execute; essi necessitano di uno statementID. Questo
 * viene passatao come parametro alle varie funzioni
 */

$transazione_fallita_msg = 'Impossibile completare la transazione';
/* /
 * Controlla il range di ID (estremi inclusi) che un utente può usare. Vero se l'id è valido, Falso se fuori dal range permesso.
 * Occorre prima controllare l'esistenza dell'utente con risolviUtente(...)
 */

function checkID($conn, $stmtID, $username, $password, $id_to_check) {
    if (isset($username) && isset($password)) {
        $query = "SELECT id_min, id_max FROM public.utenti WHERE username=$1 and password=$2";
        $resp = runPreparedQuery($conn, $stmtID, $query, array($username, $password));
        if ($resp['ok']) {
            $row = pg_fetch_assoc($resp['data']);
            return intval($row['id_min']) <= intval($id_to_check) &&
                    intval($row['id_max']) >= intval($id_to_check);
        }
    }
    return false;
}

/* /
 * valida un utente e ne estrae il ruolo. Restituisce null se non è stato trovato.
 * Altrimenti un dizionario con id&role (ruolo)
 */

function risolviUtente($conn, $stmtID, $username, $password) {
    if (isset($username) && isset($password)) {
        $query = "SELECT gid, role FROM utenti WHERE username=$1 and password=$2";
        $resp = runPreparedQuery($conn, $stmtID, $query, array($username, $password));
        if ($resp['ok'] && pg_num_rows($resp['data']) > 0) {
            $row = pg_fetch_assoc($resp['data']);
            return array(
                'id' => $row['gid'],
                'role' => $row['role']
            );
        }
    }
    return null;
}

function latLngArrToGeomTxt($latLngArr) {
    if (is_null($latLngArr) || count($latLngArr) <= 0)
        return 'NULL';
    $strArr = [];
    $initialPairTxt = join(' ', $latLngArr[0]);
    foreach ($latLngArr as $latLngPair) {
        array_push($strArr, "$latLngPair[0] $latLngPair[1]");
    }
    // l'ultimo elemento deve essere uguale al primo per chiudere il poligono
    array_push($strArr, $initialPairTxt);
    $txt = "MULTIPOLYGON(((" . join(',', $strArr) . ")))";
    $ST_GeomFromText = "ST_GeomFromText('$txt'::text, 4326)";
    return $ST_GeomFromText;
}

function replaceIntoBeniGeo($conn, $stmtID, $id, $ident, $descr, $mec, $meo, $bibl, $note,
        $topon, $comun, $geom, $esist) {
    $geomTxt = latLngArrToGeomTxt($geom);
    $tablename = 'public.benigeo';
    $query = "update $tablename SET ident=$1, descr=$2, mec=$3, meo=$4, bibli=$5," .
            " note=$6, topon=$7, comun=$8, geom=$geomTxt, esist=$9 WHERE id=$10";
    return runPreparedQuery($conn, $stmtID, $query, array(
        $ident, $descr, $mec, $meo, $bibl, $note, $topon, $comun, $esist, $id
    ));
}

function insertIntoBeniGeo($conn, $stmtID, $id, $ident, $descr, $mec, $meo, $bibl, $note,
        $topon, $comun, $geom, $esist) {
    $geomTxt = latLngArrToGeomTxt($geom);
    $tablename = 'public.benigeo';
    $query = "INSERT INTO $tablename(id, ident, descr, mec, meo, bibli, note, topon, comun, geom, esist) " .
            "VALUES($1,$2,$3,$4,$5,$6,$7,$8,$9,$geomTxt,$10";
    return runPreparedQuery($conn, $stmtID, $query, array(
        $id, $ident, $descr, $mec, $meo, $bibl, $note, $topon, $comun, $esist
    ));
}

function insertIntoBeniGeoTmp($conn, $stmtID, $id, $ident, $descr, $mec, $meo, $bibl, $note,
        $topon, $comun, $geom, $user_id, $status, $esist) {
    $geomTxt = latLngArrToGeomTxt($geom);
    $tablename = 'tmp_db.benigeo';
    $query = "INSERT INTO $tablename(id, ident, descr, mec, meo, bibli, note, topon, comun, geom, id_utente, status, esist) " .
            "VALUES($1,$2,$3,$4,$5,$6,$7,$8,$9,$geomTxt,$10,$11, $12)";
    return runPreparedQuery($conn, $stmtID, $query,
            [$id, $ident, $descr, $mec, $meo, $bibl, $note, $topon, $comun, $user_id, $status, $esist]);
}

function replaceIntoBeniGeoTmp($conn, $stmtID, $id, $ident, $descr, $mec, $meo, $bibl, $note,
        $topon, $comun, $geom, $user, $status, $esist) {
    $geomTxt = latLngArrToGeomTxt($geom);
    $tablename = 'tmp_db.benigeo';
    $query = "update $tablename SET ident=$1, descr=$2, mec=$3, meo=$4, bibli=$5," .
            " note=$6, topon=$7, comun=$8, geom=$geomTxt, id_utente=$9, status=$10, esist=$11 WHERE id=$12 and id_utente=$9";
    return runPreparedQuery($conn, $stmtID, $query, array(
        $ident, $descr, $mec, $meo, $bibl, $note, $topon, $comun, $user, $status, $esist, $id
    ));
}

function upsertIntoBeniGeoTmp($conn, $stmtID, $id, $ident, $descr, $mec, $meo, $bibl, $note,
        $topon, $comun, $geom, $user, $status, $esist) {
    $geomTxt = latLngArrToGeomTxt($geom);
    $query = "INSERT INTO tmp_db.benigeo(id, id_utente, ident, descr, mec, meo, bibli, note, topon, esist, comun, geom, status) 
            VALUES($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $geomTxt, $12)
            ON CONFLICT (id, id_utente) DO UPDATE SET id = $1,
            id_utente = $2,
            ident = $3, descr = $4,
            mec = $5, meo = $6,
            bibli = $7, note = $8,
            topon = $9, esist = $10,
            comun = $11, geom = $geomTxt, status = $12";
    return runPreparedQuery($conn, $stmtID, $query, [$id, $user, $ident,
        $descr, $mec, $meo, $bibl, $note, $topon, $esist, $comun, $status]);
}

function insertIntoManipolaBene($conn, $stmtID, $userID, $beneID) {
    $query = "INSERT INTO public.manipola_bene(id_utente, id_bene) " .
            "VALUES($1,$2)";
    return runPreparedQuery($conn, $stmtID, $query, array($userID, $beneID));
}

/* /
 * Copia un bene temporaneo nell'archivio definitivo. Notare che se il bene nell'archivio
 * definitivo esiste già, questo verrà rimpiazzato dal bene nell'archivio temporaneo.
 */

function upsertBeneTmpToBeniGeo($conn, $stmtID, $id, $id_utente) {
    $query = "WITH tmp_bene AS (
                SELECT * from tmp_db.benigeo WHERE id=$1 and id_utente=$2
            )
            INSERT INTO public.benigeo(id, ident, descr, mec, meo, bibli, note, topon, esist, comun, geom) 
            SELECT id, ident, descr, mec, meo, bibli, note, topon, esist, comun, geom FROM tmp_bene
            ON CONFLICT (id) DO UPDATE SET id = (SELECT id FROM tmp_bene),
            ident = (SELECT ident FROM tmp_bene), descr = (SELECT descr FROM tmp_bene),
            mec = (SELECT mec FROM tmp_bene), meo = (SELECT meo FROM tmp_bene),
            bibli = (SELECT bibli FROM tmp_bene), note = (SELECT note FROM tmp_bene),
            topon = (SELECT topon FROM tmp_bene), esist = (SELECT esist FROM tmp_bene),
            comun = (SELECT comun FROM tmp_bene), geom = (SELECT geom FROM tmp_bene)";
    return runPreparedQuery($conn, $stmtID, $query, [$id, $id_utente]);
}

/* /
 * Prepara e esegue una query. Restituisce un dizionario con chiavi:
 * ok: vero se è andata a buon fine, falso altrimenti
 * data: contiene il risultato della query preparata (è il risultato di pg_get_result(...))
 */

function runPreparedQuery($conn, $stmtID, $query, $paramsArr) {
    $res = ['ok' => false, 'data' => array()];
    $result = pg_prepare($conn, $stmtID, $query);
    if ($result) {
        pg_send_execute($conn, $stmtID, $paramsArr);
        $result = pg_get_result($conn);
        $error = pg_result_error($result);
        $res['data'] = $result;
        if ($error == '') {
            $res['ok'] = true;
        }
    }
    return $res;
}

//controlla che le query preparate eseguite siano andate a buon fine. I null sono ignorati
function checkAllPreparedQuery($pQueryArr) {
    $ok = true;
    foreach ($pQueryArr as $value) {
        if (isset($value))
            $ok = $ok && $value['ok'];
    }
    return $ok;
}

//da una lista di query preparate eseguite ottiene la prima con un errore
function getFirstFailedQuery($pQueryArr) {
    $query = null;
    foreach ($pQueryArr as $value) {
        if (isset($value) && !$value['ok']) {
            $query = $value;
            return $query;
        }
    }
}

?>