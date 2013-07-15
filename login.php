<?php

if (!isset($_SERVER['PHP_AUTH_USER'])) {
    header('WWW-Authenticate: Basic realm="My Realm"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Text to send if user hits Cancel button';
    exit;
} else {
    if ($_SERVER['PHP_AUTH_USER']=="rokea" && $_SERVER['PHP_AUTH_PW']=="empty!") {
    	echo "Logged!";
    } else {
    	die('Denied!');
    }
}


