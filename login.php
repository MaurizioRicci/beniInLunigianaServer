<?php
include('connection.php');
http_response_code(401);
$res = array(
	"ok" => false,
	"role" => '',
	"msg" => 'Username/Password invalid'
);
	
if (isset($_POST['username']) && isset($_POST['password']) ) {
	$username = $_POST['username'];
	$password = $_POST['password'];
	
	$result = pg_prepare($conn,'', "SELECT * FROM utenti WHERE username=$1 and password=$2");
	if($result){
		$result = pg_execute($conn,'', array($username, $password));
		if (!$result) {
		  http_response_code(500);
		  echo "An error occurred.\n";
		  exit;
		}
		
		if ($row = pg_fetch_assoc($result)) {
		  if ($row['username'] == $username) {
			if ($row['password'] == $password){
				$res["ok"] = true;
				$res["role"] = $row['role'];
				$res["msg"] = '';
				http_response_code(200);
			}
		  }
		}
	}
}
header('Content-type: application/json');
echo json_encode($res);
?>