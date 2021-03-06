<?php

// Restituisce tutti i beni approvati filtrati come richiesto dal client
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
    case "identificazione":
        $columnToOrder = "ident";
        break;
    case "descrizione":
        $columnToOrder = "descr";
        break;
    case "comune":
        $columnToOrder = "comun";
        break;
    case "esistenza":
        $columnToOrder = "esist";
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
    case "bibliografia":
        $columnToOrder = "bibli";
        break;
    case "note":
        $columnToOrder = "note";
        break;
    default:
        $columnToOrder = "";
        break;
};
$offset = $limit * ($page - 1);

// imposto i filtri sui campi
// true serve a creare un array come in php
$query = json_decode($_GET['query'], true);
$id = trim(getOrDefault($query, 'id', ''));
$ident = trim(getOrDefault($query, 'identificazione', ''));
$descr = trim(getOrDefault($query, 'descrizione', ''));
$comun = trim(getOrDefault($query, 'comune', ''));
$esist = trim(getOrDefault($query, 'esistenza', ''));
$mec = trim(getOrDefault($query, 'macroEpocaCar', ''));
$meo = trim(getOrDefault($query, 'macroEpocaOrig', ''));
$bibli = trim(getOrDefault($query, 'bibliografia', ''));
$note = trim(getOrDefault($query, 'note', ''));
$topon = trim(getOrDefault($query, 'toponimo', ''));
$schedatori_iniziali = trim(getOrDefault($query, 'schedatori_iniziali', ''));

// indice del parametro nella query preparata
$paramIdx = 1;
$params = [];

// Ottengo tutti i beni inseriti
$query_beni_aggiunti_tutti_select = "SELECT *, count(*) over() as total_rows FROM benigeo_e_schedatori ";
$query_beni_aggiunti_tutti_where = "";

// costruisco la clausola WHERE della query
if ($id !== '') {
    // se è un numero
    if (is_numeric($id)) {
        $query_beni_aggiunti_tutti_where .= "id=$$paramIdx AND ";
        $paramIdx += 1;
        array_push($params, $id);
    } else { // se è un intervallo della forma X-Y. Se Y<X nessun record viene restituito
        $id = str_replace(' ', '', $id);
        $lowerUpperBounds = explode('-', $id, 2);
        // controllo che esista X
        $lower = count($lowerUpperBounds) > 0 && is_numeric($lowerUpperBounds[0]) ?
                $lowerUpperBounds[0] : '';
        // controllo che esista Y
        $upper = count($lowerUpperBounds) > 1 && is_numeric($lowerUpperBounds[1]) ?
                $lowerUpperBounds[1] : '';
        if ($lower !== '') {
            // se X è dato filtro
            // paramIdx è l'indice da usare per quel dato parametro
            // va cercato dentro l'array params
            // es: Supponiamo di filtrare con id 5-10 => params=[5,10] => Scriverò la quey preparata come: id>=$1 and i<=$2
            $query_beni_aggiunti_tutti_where .= "id>=$$paramIdx AND ";
            $paramIdx += 1;
            array_push($params, $lower);
        }
        if ($upper !== '') {
            // se Y è dato filtro
            $query_beni_aggiunti_tutti_where .= "id<=$$paramIdx AND ";
            $paramIdx += 1;
            array_push($params, $upper);
        }
    }
}
if ($ident !== '') {
    $ident = '%' . $ident . '%';
    $query_beni_aggiunti_tutti_where .= "ident ilike $$paramIdx AND ";
    $paramIdx++;
    array_push($params, $ident);
}
if ($descr !== '') {
    $descr = '%' . $descr . '%';
    $query_beni_aggiunti_tutti_where .= "descr ilike $$paramIdx AND ";
    $paramIdx++;
    array_push($params, $descr);
}
if ($esist !== '') {
    $query_beni_aggiunti_tutti_where .= "esist = $$paramIdx AND ";
    $paramIdx++;
    array_push($params, $esist);
}
if ($comun !== '') {
    $comun = '%' . $comun . '%';
    $query_beni_aggiunti_tutti_where .= "comun ilike $$paramIdx AND ";
    $paramIdx++;
    array_push($params, $comun);
}
if ($meo !== '') {
    $meo = '%' . $meo . '%';
    $query_beni_aggiunti_tutti_where .= "meo ilike $$paramIdx AND ";
    $paramIdx++;
    array_push($params, $meo);
}
if ($mec !== '') {
    $mec = '%' . $mec . '%';
    $query_beni_aggiunti_tutti_where .= "mec ilike $$paramIdx AND ";
    $paramIdx++;
    array_push($params, $mec);
}
if ($bibli !== '') {
    $bibli = '%' . $bibli . '%';
    $query_beni_aggiunti_tutti_where .= "bibli ilike $$paramIdx AND ";
    $paramIdx++;
    array_push($params, $bibli);
}
if ($note !== '') {
    $note = '%' . $note . '%';
    $query_beni_aggiunti_tutti_where .= "note ilike $$paramIdx AND ";
    $paramIdx++;
    array_push($params, $note);
}
if ($topon !== '') {
    $topon = '%' . $topon . '%';
    $query_beni_aggiunti_tutti_where .= "topon ilike $$paramIdx AND ";
    $paramIdx++;
    array_push($params, $topon);
}
if ($schedatori_iniziali !== '') {
    $schedatori_iniziali = '%' . $schedatori_iniziali . '%';
    $query_beni_aggiunti_tutti_where .= "schedatori_iniziali ilike $$paramIdx AND ";
    $paramIdx++;
    array_push($params, $schedatori_iniziali);
}

if ($query_beni_aggiunti_tutti_where !== '') {
    // sarà qualcosa del tipo:
    // WHERE campo1 ilike 'xx' and campo2 ilike 'yyy' and true
    // true è un escamotage per far quadrare l'ultimo AND aggiunto
    $query_beni_aggiunti_tutti_where = ' WHERE ' . $query_beni_aggiunti_tutti_where . 'TRUE ';
    // aggiungo il where alla select
    $query_beni_aggiunti_tutti_select .= $query_beni_aggiunti_tutti_where;
}

if (isset($columnToOrder) && $columnToOrder !== '') {
    $query_beni_aggiunti_tutti_select = $query_beni_aggiunti_tutti_select
            . "ORDER BY $columnToOrder $sortDirection";
}

$query_beni_aggiunti_tutti_select .= " LIMIT $$paramIdx";
$paramIdx++;
$query_beni_aggiunti_tutti_select .= " OFFSET $$paramIdx";

array_push($params, $limit, $offset);

// eseguo la query
$query = runPreparedQuery($conn, $c++, $query_beni_aggiunti_tutti_select, $params);
if ($query['ok']) {
    $total_rows = pg_num_rows($query['data']);
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
