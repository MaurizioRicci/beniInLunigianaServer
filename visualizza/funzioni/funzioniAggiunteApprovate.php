<?php

include('../../connection.php');
include('../../utils.php');
include('../../queryUtils.php');

header('Content-type: application/json');
$res = ['data' => [], 'count' => 0];
$c = 0; // do un id progressivo alle query
$error = false;
http_response_code(200);

// imposto la query. Questa query è paginata e gestita dal server poichè può
// contenere troppi dati per caricarli tutti in memoria in un browser
$limit = filter_input(INPUT_GET, 'limit', FILTER_SANITIZE_NUMBER_INT);
$page = filter_input(INPUT_GET, 'page', FILTER_SANITIZE_NUMBER_INT);
$sortDirection = filter_input(INPUT_GET, 'ascending', FILTER_SANITIZE_NUMBER_INT) == 1 ? 'ASC' : 'DESC';
$columnToOrder = filter_input(INPUT_GET, 'orderBy', FILTER_SANITIZE_STRING);
$columnToOrder = $columnToOrder == '' ? 'id' : $columnToOrder;
switch ($columnToOrder) {
    case "id":
        $columnToOrder = "id";
        break;
    case "denominazione":
        $columnToOrder = "denominazione";
        break;
    case "denominazioner":
        $columnToOrder = "denominazioner";
        break;
    case "data":
        $columnToOrder = "data";
        break;
    case "tipodata":
        $columnToOrder = "tipodata";
        break;
    case "funzione":
        $columnToOrder = "funzione";
        break;
    case "bibliografia":
        $columnToOrder = "bibliografia";
        break;
    case "note":
        $columnToOrder = "note";
        break;
    case "ruolo":
        $columnToOrder = "ruolo";
        break;
    case "ruolor":
        $columnToOrder = "ruolor";
        break;
    default:
        $columnToOrder = "";
        break;
}
$offset = $limit * ($page - 1);

// imposto i filtri sui campi
// true serve a creare un array come in php
$query = json_decode($_GET['query'], true);
$id = trim(getOrDefault($query, 'id', ''));
$id_bene = trim(getOrDefault($query, 'id_bene', ''));
$id_bener = trim(getOrDefault($query, 'id_bener', ''));
$denom = trim(getOrDefault($query, 'denominazione', ''));
$denomr = trim(getOrDefault($query, 'denominazioner', ''));
$bibli = trim(getOrDefault($query, 'bibliografia', ''));
$data = trim(getOrDefault($query, 'data', ''));
$tipodata = trim(getOrDefault($query, 'tipodata', ''));
$funzione = trim(getOrDefault($query, 'funzione', ''));
$note = trim(getOrDefault($query, 'note', ''));
$ruolo = trim(getOrDefault($query, 'ruolo', ''));
$ruolor = trim(getOrDefault($query, 'ruolor', ''));
$schedatori_iniziali = trim(getOrDefault($query, 'schedatori_iniziali', ''));

// Ottengo tutti i beni inseriti
$query_funzioni_aggiunte_tutte_select = "SELECT *, count(*) over() as total_rows
     FROM funzionigeo_ruoli_schedatori ";
$query_funzioni_aggiunte_tutte_where = "";

// indice del parametro nella query preparata
$paramIdx = 1;
$params = [];

// costruisco la clausola WHERE della query
if (is_numeric($id)) {
    $query_funzioni_aggiunte_tutte_where .= "id=$$paramIdx AND ";
    $paramIdx++;
    array_push($params, $id);
}
if (is_numeric($id_bene)) {
    $query_funzioni_aggiunte_tutte_where .= "id_bene=$$paramIdx AND ";
    $paramIdx++;
    array_push($params, $id_bene);
}
if (is_numeric($id_bener)) {
    $query_funzioni_aggiunte_tutte_where .= "id_bener=$$paramIdx AND ";
    $paramIdx++;
    array_push($params, $id_bener);
}
if ($denom !== '') {
    $denom = '%' . $denom . '%';
    $query_funzioni_aggiunte_tutte_where .= "denominazione ilike $$paramIdx AND ";
    $paramIdx++;
    array_push($params, $denom);
}
if ($denomr !== '') {
    $denomr = '%' . $denomr . '%';
    $query_funzioni_aggiunte_tutte_where .= "denominazioner ilike $$paramIdx AND ";
    $paramIdx++;
    array_push($params, $denomr);
}
if ($bibli !== '') {
    $bibli = '%' . $bibli . '%';
    $query_funzioni_aggiunte_tutte_where .= "bibliografia ilike $$paramIdx AND ";
    $paramIdx++;
    array_push($params, $bibli);
}
if ($data !== '') {
    $data = '%' . $data . '%';
    $query_funzioni_aggiunte_tutte_where .= "data ilike $$paramIdx AND ";
    $paramIdx++;
    array_push($params, $data);
}
if ($tipodata !== '') {
    $tipodata = '%' . $tipodata . '%';
    $query_funzioni_aggiunte_tutte_where .= "tipodata ilike $$paramIdx AND ";
    $paramIdx++;
    array_push($params, $tipodata);
}
if ($funzione !== '') {
    $funzione = '%' . $funzione . '%';
    $query_funzioni_aggiunte_tutte_where .= "funzione ilike $$paramIdx AND ";
    $paramIdx++;
    array_push($params, $funzione);
}
if ($ruolor !== '') {
    $ruolor = '%' . $ruolor . '%';
    $query_funzioni_aggiunte_tutte_where .= "ruolo::text ilike $$paramIdx AND ";
    $paramIdx++;
    array_push($params, $ruolo);
}
if ($ruolor !== '') {
    $ruolor = '%' . $ruolor . '%';
    $query_funzioni_aggiunte_tutte_where .= "ruolor::text ilike $$paramIdx AND ";
    $paramIdx++;
    array_push($params, $ruolor);
}
if ($note !== '') {
    $note = '%' . $note . '%';
    $query_funzioni_aggiunte_tutte_where .= "note ilike $$paramIdx AND ";
    $paramIdx++;
    array_push($params, $note);
}
if ($schedatori_iniziali !== '') {
    $schedatori_iniziali = '%' . $schedatori_iniziali . '%';
    $query_funzioni_aggiunte_tutte_where .= "schedatori_iniziali ilike $$paramIdx AND ";
    $paramIdx++;
    array_push($params, $schedatori_iniziali);
}

if ($query_funzioni_aggiunte_tutte_where !== '') {
    // sarà qualcosa del tipo:
    // WHERE campo1 ilike 'xx' and campo2 ilike 'yyy' and true
    // true è un escamotage per far quadrare l'ultimo AND aggiunto
    $query_funzioni_aggiunte_tutte_where = ' WHERE ' . $query_funzioni_aggiunte_tutte_where . 'TRUE ';
    // aggiungo il where alla select
    $query_funzioni_aggiunte_tutte_select .= $query_funzioni_aggiunte_tutte_where;
}

if (isset($columnToOrder) && $columnToOrder !== '') {
    $query_funzioni_aggiunte_tutte_select = $query_funzioni_aggiunte_tutte_select
            . "ORDER BY $columnToOrder $sortDirection";
}

$query_funzioni_aggiunte_tutte_select .= " LIMIT $$paramIdx";
$paramIdx++;
$query_funzioni_aggiunte_tutte_select .= " OFFSET $$paramIdx";

array_push($params, $limit, $offset);

// eseguo la query
$query = runPreparedQuery($conn, $c++, $query_funzioni_aggiunte_tutte_select, $params);
if ($query['ok']) {
    while ($row = pg_fetch_assoc($query['data'])) {
        array_push($res['data'], funzioniPostgres2JS($row));
        $res['count'] = $row['total_rows'];
    }
} else {
    http_response_code(500);
    $error = true;
    $res['msg'] = pg_result_error($query['data']);
}

echo json_encode($res);
