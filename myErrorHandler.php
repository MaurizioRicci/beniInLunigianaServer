<?php

// error handler function
function myErrorHandler($errno, $errstr, $errfile, $errline) {
    if ($errno == E_NOTICE) {
        // appena rilevo un accesso a una variabile che non esiste il programma deve terminare
        // significa che che qualcosa è andato storto e non deve proseguire in nessun modo
        if (strpos(strtolower($errstr), 'undefined index') !== false) {
            exit("$errstr - file: $errfile - line: $errline");
        }
    }
    // This error code is not included in error_reporting, so let it fall
    // through to the standard PHP error handler
    return false;
}