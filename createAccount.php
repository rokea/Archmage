<?php
/*
if (!isset($_SERVER['PHP_AUTH_USER'])) {
    header('WWW-Authenticate: Basic realm="My Realm"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Text to send if user hits Cancel button';
    exit;
} else {
    if ($_SERVER['PHP_AUTH_USER']=="SecretUsername" && $_SERVER['PHP_AUTH_PW']=="SecretPassword") {
    	echo "";
    } else {
    	die('Denied!');
    }
}
*/

$returnMsg="";

if (isset($_POST['username']) && isset($_POST['password']) && is_numeric($_POST['magicschool'])) {

	// Database credentials
   $host = 'localhost'; 
   $db = 'Archmage';    
   $uid = 'root';
   $pwd = 'ben0828';
   
   $app_username = htmlspecialchars($_POST['username'], ENT_QUOTES);
   $app_password = htmlspecialchars($_POST['password'], ENT_QUOTES);  
   $magic_school_id = $_POST['magicschool'];
 
	//connect to the database server   
    $link = mysql_connect($host, $uid, $pwd) or die("Could not connect");
   
   //select the json database
   mysql_select_db($db) or die("Could not select database");
   
   //Check if username is already taken
   $sql="select count(id) as id from mage where lower(app_username)=lower('{$uid}')";
   
   $rs = mysql_query($sql, $link);
   
   $row = mysql_fetch_row($rs);
   
   if ($row[0] > 0) {
   		$returnMsg="Username already exists";
   } else {
	   //create user in database
	   $sql="insert into mage (magic_school_id, protected_until, app_username, app_password) values ('{$magic_school_id}', date_add(CURRENT_TIMESTAMP(), interval 3 day), '{$app_username}',md5('{$app_password}'));";  
	   
	   mysql_query($sql, $link);
	   	
	   $returnMsg=mysql_insert_id();
   }
} else {	
	$returnMsg = "Missing username, password or magic school";
}

echo $returnMsg;
