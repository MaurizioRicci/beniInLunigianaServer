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
    case "id_bene":
        $columnToOrder = "id_bene";
        break;
    case "id_bener":
        $columnToOrder = "id_bener";
        break;
    case "denominazione":
        $columnToOrder = "denominazione";
        break;
    case "denominazioner":
        $columnToOrder = "denominazioner";
        break;
    case "data_ante":
        $columnToOrder = "data_ante";
        break;
    case "data_post":
        $columnToOrder = "data_post";
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
$data_ante = trim(getOrDefault($query, 'data_ante', ''));
$data_post = trim(getOrDefault($query, 'data_post', ''));
$tipodata = trim(getOrDefault($query, 'tipodata', ''));
$funzione = trim(getOrDefault($query, 'funzione', ''));
$note = trim(getOrDefault($query, 'note', ''));
$ruolo = trim(getOrDefault($query, 'ruolo', ''));
$ruolor = trim(getOrDefault($query, 'ruolor', ''));
$schedatori_iniziali = trim(getOrDefault($query, 'schedatori_iniziali', ''));

// Ottengo tutti i beni inseriti
$query_funzioni_aggiunte_tutte_select = "SELECT * FROM funzionigeo_ruoli_schedatori ";
$query_funzioni_aggiunte_tutte_where = "";

// indice del parametro nella query preparata
$paramIdx = 1;
$params = [];

// costruisco la clausola WHERE della query
if ($id !== '') {
    // se è un numero
    if (is_numeric($id)) {
        $query_funzioni_aggiunte_tutte_where .= "id=$$paramIdx AND ";
        $paramIdx += 1;
        array_push($params, $id);
    } else { // se è un intervallo della forma X-Y. Se Y<X nessun record viene restituito
        $id = str_replace(' ', '', $id);
        $lowerUpperBounds = explode('-', $id, 2);
        $lower = count($lowerUpperBounds) > 0 && is_numeric($lowerUpperBounds[0]) ?
                $lowerUpperBounds[0] : '';
        $upper = count($lowerUpperBounds) > 1 && is_numeric($lowerUpperBounds[1]) ?
                $lowerUpperBounds[1] : '';
        if ($lower !== '') {
            $query_funzioni_aggiunte_tutte_where .= "id>=$$paramIdx AND ";
            $paramIdx += 1;
            array_push($params, $lower);
        }
        if ($upper !== '') {
            $query_funzioni_aggiunte_tutte_where .= "id<=$$paramIdx AND ";
            $paramIdx += 1;
            array_push($params, $upper);
        }
    }
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
if ($data_ante !== '') {
    $data_ante = '%' . $data_ante . '%';
    $query_funzioni_aggiunte_tutte_where .= "data_ante ilike $$paramIdx AND ";
    $paramIdx++;
    array_push($params, $data_ante);
}
if ($data_post !== '') {
    $data_post = '%' . $data_post . '%';
    $query_funzioni_aggiunte_tutte_where .= "data_post ilike $$paramIdx AND ";
    $paramIdx++;
    array_push($params, $data_post);
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
    $total_rows = pg_num_rows($query['data']);
    while ($row = pg_fetch_assoc($query['data'])) {
        array_push($res['data'], funzioniPostgres2JS($row));
        $res['count'] = $total_rows;
    }
} else {
    http_response_code(500);
    $error = true;
    $res['msg'] = pg_result_error($query['data']);
}

echo json_encode($res);
