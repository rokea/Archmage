<?php

$link = mysql_connect('localhost', 'root', 'ben0828');
if (!$link) {
	echo mysql_errno($link) . ": " . mysql_error($link). "\n";
    die('Could not connect: ' . mysql_error());
}
mysql_select_db("nonexistentdb", $link);
echo mysql_errno($link) . ": " . mysql_error($link). "\n";

mysql_select_db("kossu", $link);
mysql_query("SELECT * FROM nonexistenttable", $link);
echo mysql_errno($link) . ": " . mysql_error($link) . "\n";
