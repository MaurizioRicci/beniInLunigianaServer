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
    case "identificazione":
        $columnToOrder = "ident";
        break;
    case "descrizione":
        $columnToOrder = "descr";
        break;
    case "comune":
        $columnToOrder = "comun";
        break;
    case "macroEpocaCar":
        $columnToOrder = "mec";
        break;
    case "macroEpocaOrig":
        $columnToOrder = "meo";
        break;
    case "toponimo":
        $columnToOrder = "topon";
        break;
    default:
        $columnToOrder = "";
        break;
};
$offset = $limit * ($page - 1);

// imposto i filtri sui campi
// true serve a creare un array come in php
$query = json_decode($_GET['query'], true);
$id = trim($query['id']);
$ident = trim($query['identificazione']);
$descr = trim($query['descrizione']);
$comun = trim($query['comune']);
$mec = trim($query['macroEpocaCar']);
$meo = trim($query['macroEpocaOrig']);
$bibli = trim($query['bibliografia']);
$note = trim($query['note']);
$topon = trim($query['toponimo']);
$schedatori_iniziali = trim($query['schedatori_iniziali']);

// Ottengo tutti i beni inseriti
$query_beni_aggiunti_tutti_select = "SELECT *, count(*) over() as total_rows
     FROM benigeo_e_schedatori ";
$query_beni_aggiunti_tutti_where = "";

// costruisco la clausola WHERE della query
if (is_numeric($id)) {
    $query_beni_aggiunti_tutti_where = $query_beni_aggiunti_tutti_where
            . "id='$id' AND ";
}
if ($ident !== '') {
    $query_beni_aggiunti_tutti_where = $query_beni_aggiunti_tutti_where
            . "ident ilike'%$ident%' AND ";
}
if ($descr !== '') {
    $query_beni_aggiunti_tutti_where = $query_beni_aggiunti_tutti_where
            . "descr ilike'%$descr%' AND ";
}
if ($comun !== '') {
    $query_beni_aggiunti_tutti_where = $query_beni_aggiunti_tutti_where
            . "comun ilike'%$comun%' AND ";
}
if ($meo !== '') {
    $query_beni_aggiunti_tutti_where = $query_beni_aggiunti_tutti_where
            . "meo ilike'%$meo%' AND ";
}
if ($mec !== '') {
    $query_beni_aggiunti_tutti_where = $query_beni_aggiunti_tutti_where
            . "mec ilike'%$mec%' AND ";
}
if ($bibli !== '') {
    $query_beni_aggiunti_tutti_where = $query_beni_aggiunti_tutti_where
            . "bibli ilike'%$bibli%' AND ";
}
if ($note !== '') {
    $query_beni_aggiunti_tutti_where = $query_beni_aggiunti_tutti_where
            . "note ilike'%$note%' AND ";
}
if ($topon !== '') {
    $query_beni_aggiunti_tutti_where = $query_beni_aggiunti_tutti_where
            . "topon ilike'%$topon%' AND ";
}
if ($schedatori_iniziali !== '') {
    $query_beni_aggiunti_tutti_where = $query_beni_aggiunti_tutti_where
            . "schedatori_iniziali ilike'%$schedatori_iniziali%' AND ";
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
        array_push($res['data'], beniPostgres2JS($row));
        $res['count'] = $row['total_rows'];
    }
} else {
    http_response_code(500);
    $error = true;
    $res['msg'] = pg_result_error($query['data']);
}

echo json_encode($res);
