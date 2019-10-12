<?php
include('connection.php');

$res = array();
http_response_code(500);

if (isset($_GET['identificazione']) ) {
	
	$identificazione = $_GET['identificazione'];
	$query = "SELECT id, ident FROM benigeo WHERE ident ILIKE '$identificazione%' LIMIT 100";
	$result = pg_prepare($conn,'', $query);
	if($result){
		$result = pg_execute($conn,'', array());
		if (!$result) {
		  echo "An error occurred.\n";
		  exit;
		}
		
		while ($row = pg_fetch_assoc($result)) {
		  $temp = array(
		  'id' => $row['id'],
		  'value' => $row['ident']);
		  array_push($res, $temp);
		}
		http_response_code(200);
	}
}
header('Content-type: application/json');
echo json_encode($res);
?>