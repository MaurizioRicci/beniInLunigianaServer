<?php

function myShutDownFunction($connection) {
    pg_query($connection, 'ROLLBACK');
}