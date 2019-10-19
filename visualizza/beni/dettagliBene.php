<?php
include('../../connection.php');
include('../../utils.php');

header('Content-type: application/json');
$res = array();
http_response_code(500);
	
if (isset($_GET['id']) ) {
	
	$id = $_GET['id'];
	$query = "SELECT *, ST_AsGeoJSON(geom) as geojson, ST_AsGeoJSON(ST_Centroid(geom)) " .
	"as centroid_geojson FROM benigeo_e_schedatori WHERE id=$1";
	$result = pg_prepare($conn,'', $query);
	if($result){
		$result = pg_execute($conn,'', array($id));
		if ($result) {		
			while ($row = pg_fetch_assoc($result)) {
			  $res = beniPostgres2JS($row);
			}
			http_response_code(200);
		}
		else
			$res['msg'] = pg_result_error ($conn);
	}
}
echo json_encode($res);
?>