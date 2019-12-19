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

/* /
 * Rende la funzione Postgres che si occupa di creare un timestamp UTC.
 * Essa è una stringa che rappresenta una funzione per Postgres, pertando non deve finire
 * tra gli argomenti di una query preparata (sennò non esegue la funzione). Essa è sicura però.
 */

function timestamp_utc_txt() {
    return "timezone('UTC'::text, CURRENT_TIMESTAMP)";
}

/* /
 * Vero l'id specificato appartiene all'utente specificato da username & password
 */

function checkID($conn, $stmtID, $username, $password, $id_to_check) {
    if (isset($username) && isset($password)) {
        $query = "SELECT id_min, id_max FROM public.utenti WHERE username=$1 and password=$2";
        $resp = runPreparedQuery($conn, $stmtID, $query, [$username, $password]);
        if ($resp['ok']) {
            $row = pg_fetch_assoc($resp['data']);
            return intval($row['id_min']) <= intval($id_to_check) &&
                    intval($row['id_max']) >= intval($id_to_check);
        }
    }
    return false;
}

/* /
 * Valida un utente e ne estrae il ruolo. Restituisce null se non è stato trovato.
 * Altrimenti un dizionario con id&role (ruolo)
 */

function risolviUtente($conn, $stmtID, $username, $password) {
    if (isset($username) && isset($password)) {
        $query = "SELECT gid, role FROM utenti WHERE username=$1 and password=$2";
        $resp = runPreparedQuery($conn, $stmtID, $query, [$username, $password]);
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

/* /
 * Prende un array di array con il seguente formato: [ [lat1,lon1], .... [latN,lonN]]
 * ogni array dentro l'array è una coppia di vertici di un poligono. La funzione prende tutti i vertici
 * e li converte in una stringa binaria di geometria per PostGIS. Anche questa funzione rende una funzione
 * da far eseguire a Postgres, pertando non deve essere un parametro di una query preparata.
 */

function latLngArrToGeomTxt($latLngArr) {
    if (is_null($latLngArr) || count($latLngArr) <= 0) {
        return 'NULL';
    }
    $strArr = [];
    $initialPairTxt = join(' ', $latLngArr[0]);
    foreach ($latLngArr as $latLngPair) {
        array_push($strArr, "$latLngPair[0] $latLngPair[1]");
    }
    // l'ultimo elemento deve essere uguale al primo per chiudere il poligono
    array_push($strArr, $initialPairTxt);
    $txt = "MULTIPOLYGON(((" . join(',', $strArr) . ")))";
    $txtEsc = pg_escape_string($txt);
    $ST_GeomFromText = "ST_GeomFromText('$txtEsc', 4326)";
    return $ST_GeomFromText;
}

/* /
 * Dice se esiste un certo bene con un certo id con un certo proprietario (opzionale)
 */

function esisteBene($conn, $stmtID, $idBene, $idUtenteBene) {
    $resp = null;
    if (!isset($idUtenteBene)) {
        $resp = runPreparedQuery($conn, $stmtID, "SELECT id from benigeo WHERE id=$1",
                [$idBene]);
    } else {
        $resp = runPreparedQuery($conn, $stmtID, "SELECT id from tmp_db.benigeo WHERE id=$1 and id_utente=$2",
                [$idBene, $idUtenteBene]);
    }
    return isset($resp) && $resp['ok'] && pg_num_rows($resp['data']) > 0;
}

/* /
  Da qui in poi ci sono un po' di funzioni di utilità per manipolare le tabelle dei beni,
  delle funzioni, dei beni temporanei e delle funzioni temporanee.
  Le funzioni all'incirca seguono la sintassi {insert|replace|upsert}Into<tabella>[Tmp]

  Per esempio: replaceIntoBeniGeoTmp -> replace nella tabella benigeo di archivio temporaneo.
  Rimpiazza quindi un bene nell'archivio temporaneo; pertanto prenderà come parametri
  id_bene, id_utente, descrizione etc...

  NB: nell'archivio temporaneo id_bene e id_utente sono una chiave per un bene (stessa cosa per le funzioni)
  PS: upsert è la combinazione di update+insert; se l'elemento non esiste si crea, se esiste si aggiorna. Per
  ulteriori approfondimenti si veda la clausola ON CONFLICT del comando INSERT.
 */

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

function replaceIntoFunzioniGeo($conn, $stmtID, $id, $idbene, $idbener, $denom, $denomr,
        $data, $data_ante, $data_poste, $tipodata, $funzione, $bibl, $note) {
    $tablename = 'funzionigeo';
    $query = "update $tablename SET id_bene=$1, denominazione=$2, data=$3, "
            . "data_ante=$4, data_poste=$5, tipodata=$6,"
            . "funzione=$7, id_bener=$8, denominazioner=$9,"
            . "bibliografia=$10, note=$11 WHERE id=$12";
    return runPreparedQuery($conn, $stmtID, $query,
            [$idbene, $denom, $data, $data_ante, $data_poste, $tipodata,
                $funzione, $idbener, $denomr, $bibl, $note, $id]);
}

function insertIntoBeniGeo($conn, $stmtID, $id, $ident, $descr, $mec, $meo, $bibl, $note,
        $topon, $comun, $geom, $esist) {
    $geomTxt = latLngArrToGeomTxt($geom);
    $tablename = 'public.benigeo';
    $query = "INSERT INTO $tablename(id, ident, descr, mec, meo, bibli, note, topon, comun, geom, esist) " .
            "VALUES($1,$2,$3,$4,$5,$6,$7,$8,$9,$geomTxt,$10)";
    return runPreparedQuery($conn, $stmtID, $query, array(
        $id, $ident, $descr, $mec, $meo, $bibl, $note, $topon, $comun, $esist
    ));
}

function insertIntoFunzioniGeo($conn, $stmtID, $idbene, $idbener, $denom, $denomr,
        $data, $data_ante, $data_poste, $tipodata, $funzione, $bibl, $note) {
    $tablename = 'funzionigeo';
    $query = "INSERT INTO $tablename(id_bene, denominazione, data, data_ante, data_poste, tipodata,"
            . " funzione, id_bener, denominazioner, bibliografia, note) " .
            "VALUES($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11) RETURNING id";
    return runPreparedQuery($conn, $stmtID, $query,
            [$idbene, $denom, $data, $data_ante, $data_poste, $tipodata, $funzione, $idbener, $denomr, $bibl, $note]);
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

function insertIntoFunzioniGeoTmp($conn, $stmtID, $idbene, $idbener, $denom, $denomr,
        $data, $data_ante, $data_poste, $tipodata, $funzione, $bibl, $note, $id_utente,
        $id_utente_bene, $id_utente_bener, $status) {
    $tablename = 'tmp_db.funzionigeo';
    $query = "INSERT INTO $tablename(id_bene, denominazione, data, data_ante,data_poste,"
            . " tipodata, funzione, id_bener, denominazioner,"
            . "bibliografia, note, id_utente, id_utente_bene, id_utente_bener, status) " .
            "VALUES($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12,$13) RETURNING id";
    return runPreparedQuery($conn, $stmtID, $query,
            [$idbene, $denom, $data, $data_ante, $data_poste, $tipodata, $funzione, $idbener,
                $denomr, $bibl, $note, $id_utente, $id_utente_bene, $id_utente_bener, $status]);
}

function insertFunzioniGeoRuoli($conn, $stmtID, $id_funzione, $id_utente, $ruoloArr,
        $ruolorArr, $tmp_db) {
    $lastQuery = null;
    $maxLength = max(count($ruoloArr), count($ruolorArr));
    $tablename = 'funzionigeo_ruoli';
    if ($tmp_db) {
        $query = "INSERT INTO tmp_db.$tablename(id_funzione, id_utente, ruolo, ruolor) VALUES($1,$2,$3,$4)";
        $params1 = [$id_funzione, $id_utente];
    } else {
        $query = "INSERT INTO $tablename(id_funzione, ruolo, ruolor) VALUES($1,$2,$3)";
        $params = [$id_funzione];
    }
    for ($c = 0; $c < $maxLength; $c++) {
        $curr_ruolo = $c < count($ruoloArr) ? $ruoloArr[$c] : null;
        $curr_ruolor = $c < count($ruolorArr) ? $ruolorArr[$c] : null;
        // aggiungo i rimanenti parametri
        $params = array_merge($params1, [$curr_ruolo, $curr_ruolor]);
        $lastQuery = runPreparedQuery($conn, $stmtID++, $query, $params);
        if (!$lastQuery['ok']) {
            break;
        }
    }
    return $lastQuery;
}

function replaceIntoBeniGeoTmp($conn, $stmtID, $id, $ident, $descr, $mec, $meo, $bibl, $note,
        $topon, $comun, $geom, $user, $status, $esist) {
    $geomTxt = latLngArrToGeomTxt($geom);
    $timestamp_utc_txt = timestamp_utc_txt();
    $tablename = 'tmp_db.benigeo';
    $query = "update $tablename SET ident=$1, descr=$2, mec=$3, meo=$4, bibli=$5," .
            " note=$6, topon=$7, comun=$8, geom=$geomTxt, id_utente=$9, status=$10,"
            . "timestamp_utc_txt = $timestamp_utc_txt, esist=$11 WHERE id=$12 and id_utente=$9";
    return runPreparedQuery($conn, $stmtID, $query, array(
        $ident, $descr, $mec, $meo, $bibl, $note, $topon, $comun, $user, $status, $esist, $id
    ));
}

function replaceIntoFunzioniGeoTmp($conn, $stmtID, $id, $idbene, $idbener, $denom, $denomr,
        $data, $data_ante, $data_poste, $tipodata, $funzione, $bibl, $note, $id_utente,
        $id_utente_bene, $id_utente_bener, $status) {
    $tablename = 'tmp_db.funzionigeo';
    $timestamp_utc_txt = timestamp_utc_txt();
    $query = "update $tablename SET id_bene=$1, denominazione=$2, data=$3, data_ante=$4,"
            . " data_poste=$5, tipodata=$6, funzione=$7, id_bener=$8, denominazioner=$9,"
            . "bibliografia=$10, note=$11, id_utente=$12, status=$13, id_utente_bene=$14,"
            . "id_utente_bener=15, timestamp_utc_txt=$timestamp_utc_txt WHERE id=$16 and id_utente=$12";
    return runPreparedQuery($conn, $stmtID, $query,
            [$idbene, $denom, $data, $data_ante, $data_poste, $tipodata, $funzione,
                $idbener, $denomr, $bibl, $note, $id_utente, $status, $id_utente_bene,
                $id_utente_bener, $id]);
}

function upsertIntoBeniGeoTmp($conn, $stmtID, $id, $ident, $descr, $mec, $meo, $bibl, $note,
        $topon, $comun, $geom, $user, $status, $esist) {
    $geomTxt = latLngArrToGeomTxt($geom);
    $timestamp_utc_txt = timestamp_utc_txt();
    $query = "INSERT INTO tmp_db.benigeo(id, id_utente, ident, descr, mec, meo, bibli, note, topon, esist, comun, geom, status) 
            VALUES($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $geomTxt, $12)
            ON CONFLICT (id, id_utente) DO UPDATE SET id = $1,
            id_utente = $2,
            ident = $3, descr = $4,
            mec = $5, meo = $6,
            bibli = $7, note = $8,
            topon = $9, esist = $10, timestamp_utc = $timestamp_utc_txt,
            comun = $11, geom = $geomTxt, status = $12";
    return runPreparedQuery($conn, $stmtID, $query, [$id, $user, $ident,
        $descr, $mec, $meo, $bibl, $note, $topon, $esist, $comun, $status]);
}

function upsertIntoFunzioniGeoTmp($conn, $stmtID, $id, $idbene, $idbener, $denom, $denomr,
        $data, $data_ante, $data_poste, $tipodata, $funzione, $bibl, $note, $id_utente,
        $id_utente_bene, $id_utente_bener, $status) {
    $tablename = 'tmp_db.funzionigeo';
    $query = "INSERT INTO $tablename(id_bene, denominazione, data, data_ante, data_poste, 
            tipodata, funzione, id_bener, denominazioner,
            bibliografia, note, id, id_utente, id_utente_bene, id_utente_bener, status)
            VALUES($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12,$13,$14,$15,$16)
            ON CONFLICT (id,id_utente) DO UPDATE SET id_bene=$1,
            denominazione=$2, data=$3, data_ante=$4, data_poste=$5, tipodata=$6, funzione=$7,
            id_bener=$8, denominazioner=$9, bibliografia=$10, note=$11,
            id=$12, id_utente=$13, id_utente_bene=$14, id_utente_bener=$15, status=$16
            RETURNING id";
    return runPreparedQuery($conn, $stmtID, $query,
            [$idbene, $denom, $data, $data_ante, $data_poste, $tipodata, $funzione,
                $idbener, $denomr, $bibl, $note, $id, $id_utente, $id_utente_bene,
                $id_utente_bener, $status]);
}

function insertIntoManipolaBene($conn, $stmtID, $userID, $beneID) {
    $query = "INSERT INTO public.manipola_bene(id_utente, id_bene) " .
            "VALUES($1,$2)";
    return runPreparedQuery($conn, $stmtID, $query, array($userID, $beneID));
}

function insertIntoManipolaFunzione($conn, $stmtID, $userID, $funzioneID) {
    $query = "INSERT INTO public.manipola_funzione(id_utente, id_funzione) " .
            "VALUES($1,$2)";
    return runPreparedQuery($conn, $stmtID, $query, array($userID, $funzioneID));
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
 * Copia una funzione temporanea nell'archivio definitivo.
 */

function upsertFunzioneTmpToFunzioniGeo($conn, $stmtID, $id, $id_utente) {
    $query = "WITH tmp_funzione AS (
                SELECT * from tmp_db.funzionigeo WHERE id=$1 and id_utente=$2
            )
            INSERT INTO public.funzionigeo(id_bene, lotto, denominazione, data, data_ante, data_poste,
            tipodata, funzione, id_bener, denominazioner, bibliografia, note, id)
            SELECT id_bene, lotto, denominazione, data, data_ante, data_poste,
            tipodata, funzione, id_bener, denominazioner, bibliografia, note, id FROM tmp_funzione
            ON CONFLICT (id) DO UPDATE SET id_bene=(SELECT id_bene FROM tmp_funzione),
            lotto=(SELECT lotto FROM tmp_funzione), denominazione=(SELECT denominazione FROM tmp_funzione),
            data=(SELECT data FROM tmp_funzione), data_ante=(SELECT data_ante FROM tmp_funzione),
            data_poste=(SELECT data_poste FROM tmp_funzione), tipodata=(SELECT tipodata FROM tmp_funzione),
            funzione=(SELECT funzione FROM tmp_funzione), id_bener=(SELECT id_bener FROM tmp_funzione),
            denominazioner=(SELECT id_bene FROM tmp_funzione), bibliografia=(SELECT id_bene FROM tmp_funzione),
            note=(SELECT denominazioner FROM tmp_funzione), id=(SELECT id FROM tmp_funzione)";
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

/* /
 * controlla che le query preparate eseguite siano andate a buon fine. I null sono ignorati
 */

function checkAllPreparedQuery($pQueryArr) {
    $ok = true;
    foreach ($pQueryArr as $value) {
        if (isset($value))
            $ok = $ok && $value['ok'];
    }
    return $ok;
}

/* /
 * da una lista di query preparate eseguite ottiene la prima con un errore
 */

function getFirstFailedQuery($pQueryArr) {
    $query = null;
    foreach ($pQueryArr as $value) {
        if (isset($value) && !$value['ok']) {
            $query = $value;
            return $query;
        }
    }
}

/* /
 * Restituisce l'id di una funzione (se presente nella query)
 */

function getIdFunzione($query) {
    $idFunzione = null;
    if ($query['ok']) {
        $row = pg_fetch_assoc($query['data']);
        $idFunzione = $row ? $row['id'] : null;
    }
    return $idFunzione;
}
