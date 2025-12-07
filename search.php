<?php

require_once 'conexion.php';
if (!isset($_SESSION)) {
  session_start();
}
$MM_authorizedUsers = "a,v";
$MM_donotCheckaccess = "false";

// *** Restrict Access To Page: Grant or deny access to this page
function isAuthorized($strUsers, $strGroups, $UserName, $UserGroup) { 
  // For security, start by assuming the visitor is NOT authorized. 
  $isValid = False; 

  // When a visitor has logged into this site, the Session variable MM_Username set equal to their username. 
  // Therefore, we know that a user is NOT logged in if that Session variable is blank. 
  if (!empty($UserName)) { 
    // Besides being logged in, you may restrict access to only certain users based on an ID established when they login. 
    // Parse the strings into arrays. 
    $arrUsers = Explode(",", $strUsers); 
    $arrGroups = Explode(",", $strGroups); 
    if (in_array($UserName, $arrUsers)) { 
      $isValid = true; 
    } 
    // Or, you may restrict access to only certain users based on their username. 
    if (in_array($UserGroup, $arrGroups)) { 
      $isValid = true; 
    } 
    if (($strUsers == "") && false) { 
      $isValid = true; 
    } 
  } 
  return $isValid; 
}



$MM_restrictGoTo = "http://localhost/ventas/facturacion.php";


if (!((isset($_SESSION['MM_Username'])))) { 
  $MM_qsChar = "?";
  $MM_referrer = $_SERVER['PHP_SELF'];
  if (strpos($MM_restrictGoTo, "?")) $MM_qsChar = "&";
  if (isset($QUERY_STRING) && strlen($QUERY_STRING) > 0) 
  $MM_referrer .= "?" . $QUERY_STRING;
  $MM_restrictGoTo = $MM_restrictGoTo. $MM_qsChar . "accesscheck=" . urlencode($MM_referrer);
  header("Location: ". $MM_restrictGoTo); 
  exit;
}

if (isset($_SESSION['MM_Username'])) {
$colname_usuario=mysqli_real_escape_string($sandycat,$_SESSION['MM_Username']);
}

if(!isset($_POST['_order_id'])) exit('No se recibió el valor a buscar');

function search()
{
  $mysqli = getConnexion();
  $venta = $_POST['venta'];
  $order_id = $_POST['_order_id'];
  // $search = $mysqli->real_escape_string($_POST['search']);
  $search = $_POST['search'];
  $query = "SELECT *, post_id, meta_key, meta_value FROM miau_posts LEFT JOIN miau_postmeta ON post_id = ID WHERE post_status = 'publish' AND meta_key = '_stock' AND post_title LIKE '%$search%' AND (post_type = 'product' OR post_type = 'product_variation') AND post_title != '[your-subject]' AND ID NOT IN (SELECT post_parent FROM miau_posts WHERE post_status = 'publish' AND post_type = 'product_variation') ORDER BY post_title ASC";
  /* $query = "SELECT * FROM miau_posts WHERE post_status = 'publish' AND post_title LIKE '%$search%' AND (post_type = 'product' OR post_type = 'product_variation') AND post_title != '[your-subject]' AND ID NOT IN (SELECT post_parent FROM miau_posts WHERE post_status = 'publish' AND post_type = 'product_variation') ORDER BY post_title ASC"; version 1 ok */
  $res = $mysqli->query($query);
  while ($row = $res->fetmiau_array(MYSQLI_ASSOC)) {
      if(strlen($search) > 2) {
    echo "<a href='#' data-toggle='modal' data-target='#nuevoprod' title='Vender' data-paquete-id='$row[ID]' data-order-idb='$row[post_title]' data-order-id='$order_id' data-nombre-id='$row[post_title]'>$row[post_title] ($row[meta_value])</a></br>";
    
    //"<a href='#' data-toggle='modal' data-target='#myModal' title='Vender' data-articulo-id='$row[ID]'>$row[post_title]</a>";
    //<form action='prosventa.php' method='post' >
      //   <input type='hidden' class='form-control' id='_order_id' name='_order_id' value='$order_id'>
        // <input type='hidden' class='form-control' id='ID' name='ID' value='$row[ID]'>
          // <button type='submit' class='btn btn-link'>$row[post_title]</button>
     //</form>"
    
    
    //<p><a href='$row[ID]' target='_blank'>$row[post_title] - $order_id +</a></p>";
      } else {
    echo "";
      }
  }
}

search();


?>