<?php
// Cargar configuración desde archivo .env
require_once('class/config.php');
if (!isset($_SESSION)) {
  session_start();
}
$MM_authorizedUsers = "a,v";
$MM_donotCheckaccess = "false";

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

$colname_usuario = '';
if (isset($_SESSION['MM_Username'])) {
$colname_usuario=mysqli_real_escape_string($sandycat,$_SESSION['MM_Username']);
}

if(!isset($_POST['_order_id'])) exit('No se recibió el valor a buscar');

function search()
{
  global $miau; // Usar la conexión global de WooCommerce
  $venta = $_POST['venta'] ?? '';
  $order_id = $_POST['_order_id'] ?? '';
  $search = mysqli_real_escape_string($miau, $_POST['search'] ?? '');
  
  $query = "SELECT *, post_id, meta_key, meta_value FROM miau_posts LEFT JOIN miau_postmeta ON post_id = ID WHERE post_status = 'publish' AND meta_key = '_stock' AND post_title LIKE '%$search%' AND (post_type = 'product' OR post_type = 'product_variation') AND post_title != '[your-subject]' AND ID NOT IN (SELECT post_parent FROM miau_posts WHERE post_status = 'publish' AND post_type = 'product_variation') ORDER BY post_title ASC";
  
  $res = mysqli_query($miau, $query);
  if ($res) {
    while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
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
}

search();

?>