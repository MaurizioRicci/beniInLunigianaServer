<?php

// -------------------------COSTANTI PER IL PROGRAMMA
$user = "postgres";
$password = "postgres";
$workingDir = "./shapefile/";
$zipName = "benigeo_funzioni.zip";

function makeDir($path) {
    return is_dir($path) || mkdir($path);
}

// ---------------------------CREO AMBIENTE DI LAVORO RICHIESTO
makeDir($workingDir);

// Codice per lock file preso da https://www.gianlucadivincenzo.it/php/lock-file-php/
// Creo un file .lock che serve come semaforo per sincronizzare gli accessi concorrenti
// Create lock file, with write permission.
$file_lock = fopen($workingDir . ".lock", "w+");
if (isset($file_lock) && !empty($file_lock)) {
    /* Test locking functionality: <- flock -x ./.lock sleep 20 -> */
    /* LOCK_NB, don't block the script execute. */
    // Volendo si può fare $obtain_lock = flock($file_lock, LOCK_EX | LOCK_NB);
    // LOCK_NB non blocca lo script e se trova che altri hanno il lock da errore e muore
    // Mettere solo LOCK_EX invece blocca lo script fino a che gli altri hanno fatto il loro lavoro
    
    /* /$obtain_lock = flock($file_lock, LOCK_EX | LOCK_NB);
      if (!$obtain_lock || true) {
      // error_log("Not obtain lock-> $file_lock , insert operation inside queue! \n", 0);
      die("Accesso concorrente, riprovare più tardi");
      }/ */
    
    // Se il file è occupato aspetto un numero esponenziale di secondi max 3 tentativi
    // Al massimo aspetto 2^0+2^1+2^2 secondi, 7 secondi max
    $esponente = 0;
    while (!flock($file_lock, LOCK_EX | LOCK_NB)) {
        sleep(pow(2, $esponente));
        $esponente += 1;
        if ($esponente > 3) {
            die("Troppi accessi concorrenti, riprovare più tardi");
        }
    }
} else {
    error_log("failed to create lock \n", 0);
    die("failed to create lock \n");
}


//----------------------------ESPORTO GLI SHAPE FILE DAL DB IN DEI FILE
// creo gli shape con pgsql2shp
$output1 = shell_exec("pgsql2shp -f ${workingDir}benigeo -P $password -u $user postgis_db benigeo");
$output2 = shell_exec("pgsql2shp -f ${workingDir}funzionigeo -P $password -u $user postgis_db funzionigeo_ruoli_schedatori");

printf($output1);
printf($output2);


//----------------------------ZIPPO GLI SHAPEFILE
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


//-----------------------------FACCIO SCARICARE LO ZIP
$zipPath = $workingDir . $zipName;
// faccio redirect allo zip
header('Location: ' . $zipPath, true, 302);


//-----------------------------CHIUDO E RILASCIO IL LOCK
if (isset($file_lock) && !empty($file_lock)) {
    error_log("Close lock file: $file_lock .\n", 0);
    flock($file_lock, LOCK_UN);
    fclose($file_lock);
} 