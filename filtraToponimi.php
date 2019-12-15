<?php
include('connection.php');

$res = array();
http_response_code(500);
	
if (isset($_GET['toponimo']) ) {
	
	$toponimo = $_GET['toponimo']."%";
	$query = "SELECT DISTINCT topon FROM benigeo WHERE topon ILIKE $1 ORDER BY topon ASC LIMIT 100";
	$result = pg_prepare($conn,'', $query);
	if($result){
		$result = pg_execute($conn,'', [$toponimo]);
		if (!$result) {
		  echo "An error occurred.\n";
		  exit;
		}
		
		while ($row = pg_fetch_assoc($result)) {
		  $temp = array(
		  'value' => $row['topon']);
		  array_push($res, $temp);
		}
		http_response_code(200);
	}
}
header('Content-type: application/json');
echo json_encode($res);
?>