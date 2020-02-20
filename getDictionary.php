<?php

include('connection.php');

$res = array();
http_response_code(500);

if (isset($_GET['dict_name'])) {

    $dict_name = $_GET['dict_name'];
    $dict_name_esc = pg_escape_identifier($dict_name);
    $result = pg_prepare($conn, '', "SELECT * FROM vocabolari.$dict_name_esc ORDER BY row_number ASC");
    if ($result) {
        $result = pg_execute($conn, '', []);
        if (!$result) {
            echo "An error occurred.\n";
            exit;
        }

        while ($row = pg_fetch_assoc($result)) {
            $temp = [
                'id' => $row['nome'],
                'value' => $row['nome'],
                'text' => array_key_exists('text', $row) && isset($row['text']) ? $row['text'] : $row['nome']
            ];
            array_push($res, $temp);
        }
        http_response_code(200);
    }
}
header('Content-type: application/json');
echo json_encode($res);
?>