<?php
/* ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL); */
$hostname_sandycat = "localhost";
$database_sandycat = "ventassc";
$username_sandycat = "root";
$password_sandycat = "";
$sandycat = new mysqli($hostname_sandycat, $username_sandycat, $password_sandycat, $database_sandycat);
if ($sandycat -> connect_errno) {
die( "Fallo la conexión a MySQL: (" . $mysqli -> mysqli_connect_errno() 
. ") " . $mysqli -> mysqli_connect_error());
}
?>