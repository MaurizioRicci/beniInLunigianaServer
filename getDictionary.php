<?php
include('connection.php');

$res = array();
http_response_code(500);

if (isset($_GET['dict_name']) ) {
	
	$param = $_GET['dict_name'];
	
	$result = pg_prepare($conn,'', "SELECT * FROM vocabolari.\"$param\" ORDER BY nome ASC");
	if($result){
		$result = pg_execute($conn,'', array());
		if (!$result) {
		  echo "An error occurred.\n";
		  exit;
		}
		
		while ($row = pg_fetch_row($result)) {
			$temp = array(
			  'id' => $row[0],
			  'value' => $row[0]);
		  array_push($res, $temp);
		}
		http_response_code(200);
	}
}
header('Content-type: application/json');
echo json_encode($res);
?>