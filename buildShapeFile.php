<?php

$user = "postgres";
$password = "postgres";
$workingDir = "./shapefile/";
$zipName = "benigeo_funzioni.zip";

// creo gli shape con pgsql2shp
$output1 = shell_exec("pgsql2shp -f ${workingDir}benigeo -P $password -u $user postgis_db benigeo");
$output2 = shell_exec("pgsql2shp -f ${workingDir}funzionigeo -P $password -u $user postgis_db funzionigeo_ruoli_schedatori");

printf($output1);
printf($output2);

//zippo tutto
$zip = new ZipArchive();
$ret = $zip->open($workingDir . $zipName,
        ZipArchive::CREATE | ZipArchive::OVERWRITE);
if ($ret !== TRUE) {
    printf('Failed with code %d', $ret);
} else {
    $directory = realpath($workingDir);
    $options = array('remove_path' => $directory);
    // matcha tutti i file incluso lo zip stesso
    $zip->addPattern('/.*/', $directory, $options);
    // tolgo lo zip da tutto
    $zip->deleteName($zipName);
    $zip->close();
    printf("OK");
}