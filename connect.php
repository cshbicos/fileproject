<?php

$GLOBALS['db'] = mysql_connect(DB_SERVER, DB_USER, DB_PWD);

if (!$GLOBALS['db']) {
    die('Could not connect: ' . mysql_error());
}

mysql_select_db(DB_NAME, $GLOBALS['db']);

?>
