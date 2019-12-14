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
$id = trim($query['id']);
$id_bene = trim($query['id_bene']);
$id_bener = trim($query['id_bener']);
$denom = trim($query['denominazione']);
$denomr = trim($query['denominazioner']);
$bibli = trim($query['bibliografia']);
$data = trim($query['data']);
$tipodata = trim($query['tipodata']);
$funzione = trim($query['funzione']);
$note = trim($query['note']);
$ruolo = trim($query['ruolo']);
$ruolor = trim($query['ruolor']);
$schedatori_iniziali = trim($query['schedatori_iniziali']);

// Ottengo tutti i beni inseriti
$query_beni_aggiunti_tutti_select = "SELECT *, count(*) over() as total_rows
     FROM funzionigeo_ruoli_schedatori ";
$query_beni_aggiunti_tutti_where = "";

// costruisco la clausola WHERE della query
if (is_numeric($id)) {
    $query_beni_aggiunti_tutti_where .= "id='$id' AND ";
}
if (is_numeric($id_bene)) {
    $query_beni_aggiunti_tutti_where .= "id_bene='$id_bene' AND ";
}
if (is_numeric($id_bener)) {
    $query_beni_aggiunti_tutti_where .= "id='$id_bener' AND ";
}
if ($denom !== '') {
    $query_beni_aggiunti_tutti_where .= "denominazione ilike'%$denom%' AND ";
}
if ($denomr !== '') {
    $query_beni_aggiunti_tutti_where .= "denominazioner ilike'%$denomr%' AND ";
}
if ($bibli !== '') {
    $query_beni_aggiunti_tutti_where .= "bibli ilike'%$bibli%' AND ";
}
if ($data !== '') {
    $query_beni_aggiunti_tutti_where .= "data ilike'%$data%' AND ";
}
if ($tipodata !== '') {
    $query_beni_aggiunti_tutti_where .= "tipodata ilike'%$tipodata%' AND ";
}
if ($funzione !== '') {
    $query_beni_aggiunti_tutti_where .= "funzione ilike'%$funzione%' AND ";
}
if ($ruolo !== '') {
    $query_beni_aggiunti_tutti_where .= "ruolo::text ilike'%$ruolo%' AND ";
}
if ($ruolor !== '') {
    $query_beni_aggiunti_tutti_where .= "ruolor::text ilike'%$ruolor%' AND ";
}
if ($note !== '') {
    $query_beni_aggiunti_tutti_where .= "note ilike'%$note%' AND ";
}
if ($schedatori_iniziali !== '') {
    $query_beni_aggiunti_tutti_where .= "schedatori_iniziali ilike'%$schedatori_iniziali%' AND ";
}

if ($query_beni_aggiunti_tutti_where !== '') {
    // sarà qualcosa del tipo:
    // WHERE campo1 ilike 'xx' and campo2 ilike 'yyy' and true
    // true è un escamotage per far quadrare l'ultimo AND aggiunto
    $query_beni_aggiunti_tutti_where = ' WHERE ' . $query_beni_aggiunti_tutti_where . 'TRUE ';
    // aggiungo il where alla select
    $query_beni_aggiunti_tutti_select = $query_beni_aggiunti_tutti_select
            . $query_beni_aggiunti_tutti_where;
}

if (isset($columnToOrder) && $columnToOrder !== '') {
    $query_beni_aggiunti_tutti_select = $query_beni_aggiunti_tutti_select
            . "ORDER BY $columnToOrder $sortDirection";
}

$query_beni_aggiunti_tutti_select = $query_beni_aggiunti_tutti_select
        . " LIMIT $1"
        . " OFFSET $2";

$params = [$limit, $offset];

// eseguo la query
$query = runPreparedQuery($conn, $c++, $query_beni_aggiunti_tutti_select, $params);
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
