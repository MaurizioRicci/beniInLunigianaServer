<?php
include('connection.php');

$res = array();
http_response_code(500);
	
if (isset($_GET['comune']) ) {
	
	$comune = $_GET['comune'];
	$query = "SELECT DISTINCT comun FROM benigeo WHERE comun ILIKE '$comune%' ORDER BY comun ASC LIMIT 100";
	$result = pg_prepare($conn,'', $query);
	if($result){
		$result = pg_execute($conn,'', array());
		if (!$result) {
		  echo "An error occurred.\n";
		  exit;
		}
		
		while ($row = pg_fetch_assoc($result)) {
		  $temp = array(
		  'value' => $row['comun']);
		  array_push($res, $temp);
		}
		http_response_code(200);
	}
}
header('Content-type: application/json');
echo json_encode($res);
?>