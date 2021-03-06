<?php

include 'connectionString.php';
// -------------------------COSTANTI PER IL PROGRAMMA
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

    // Se il file è occupato aspetto un numero casuale ma esponenziale di secondi max 3 tentativi
    // Al massimo aspetto 2^0+2^1+2^2 secondi, 7 secondi max
    // Faccio una sorta di esponential back-off con N=2
    $esponente = 0;
    while (!flock($file_lock, LOCK_EX | LOCK_NB)) {
        $rand_int = rand(1, pow(2, $esponente));
        sleep($rand_int);
        $esponente += 1;
        if ($esponente > 2) {
            die("Troppi accessi concorrenti, riprovare più tardi");
        }
    }
} else {
    error_log("failed to create lock \n", 0);
    die("failed to create lock \n");
}


//----------------------------ESPORTO GLI SHAPE FILE DAL DB IN DEI FILE
// creo gli shape con pgsql2shp
// le funzioni occorre risistemare le colonne. La query deve essere su una riga sennò fallisce
$query_funzioni = "SELECT id_bene,denominazione, data_ante,data_post,tipodata,ruolo,funzione,id_bener,denominazioner,ruolor,bibliografia,note FROM funzionigeo_ruoli_schedatori";
$output1 = shell_exec("pgsql2shp -f ${workingDir}benigeo -P $password -u $username $db_name benigeo");
$output2 = shell_exec("pgsql2shp -f ${workingDir}funzionigeo -P $password -u $username $db_name \"${query_funzioni}\"");

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
	//remove_path dalla documentazione: "Prefix to remove from matching file paths before adding to the archive."
    $options = array('remove_path' => $directory);
    // matcha tutti i file incluso lo zip stesso
    $zip->addPattern('/\.(?:shp|cpg|dbf|prj|shx)$/', $directory, $options);
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