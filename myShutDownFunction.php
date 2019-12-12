<?php

function myShutDownFunction($connection) {
    // ogni volta che il programma termina manda un rollback
    // serve a chiudere eventuali transazioni ancora aperte (nel caso di errori)
    $stat = pg_transaction_status($connection);
    if ($stat !== PGSQL_TRANSACTION_UNKNOWN && $stat !== PGSQL_TRANSACTION_IDLE) {
        // Connection is in a transaction state
        pg_query($connection, 'ROLLBACK');
    }
}
