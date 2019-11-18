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
$offset = $limit * $page;

// imposto i filtri sui campi
$query = json_decode($_GET['query'], true);
$id = $query['id'];
$ident = $query['identificazione'];
$descr = $query['descrizione'];
$comun = $query['comune'];
$mec = $query['macroEpocaCar'];
$meo = $query['macroEpocaOrig'];
$bibli = $query['bibliografia'];
$note = $query['note'];
$topon = $query['toponimo'];
$schedatori_iniziali = $query['schedatori_iniziali'];

// Ottengo tutti i beni inseriti
$query_beni_aggiunti_tutti = "SELECT *, count(*) over() as total_rows
     FROM benigeo_e_schedatori 
     WHERE ";
if (is_numeric($id)) {
    $query_beni_aggiunti_tutti = $query_beni_aggiunti_tutti . "id='$id' AND ";
}
$query_beni_aggiunti_tutti = $query_beni_aggiunti_tutti .
        "(ident ilike '%$ident' OR ident IS NULL) AND (descr ilike '%$descr%' OR descr IS NULL)"
        . " AND (comun ilike '%$comun%' OR comun IS NULL)"
        . " AND (mec ilike '%$mec%' OR mec IS NULL) AND (meo ilike '%$meo%' OR meo IS NULL)"
        . " AND (bibli ilike '%$bibli%' OR bibli IS NULL) AND (note ilike '%$note%' OR note IS NULL)"
        . " AND (topon ilike '%$topon%' OR descr IS NULL)"
        . " AND (schedatori_iniziali ilike '%$schedatori_iniziali%' OR schedatori_iniziali IS NULL)"
        . " LIMIT $1"
        . " OFFSET $2";

$params = [$limit, $offset];

// eseguo la query
$query = runPreparedQuery($conn, $c++, $query_beni_aggiunti_tutti, $params);
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
