
<?php						 
do {
	if($row_lista['meta_key']=='_shipping_first_name') {
		$_shipping_first_name = $row_lista['meta_value'];
	}
	if($row_lista['meta_key']=='_shipping_last_name') {
		$_shipping_last_name = $row_lista['meta_value'];
	}
	if($row_lista['meta_key']=='billing_id') {
		$billing_id = $row_lista['meta_value'];
	}
	if($row_lista['meta_key']=='_shipping_city') {
		$_shipping_city = $row_lista['meta_value'];
	}
	if($row_lista['meta_key']=='_shipping_state') {
		$_shipping_state = $row_lista['meta_value'];
	}
	if($row_lista['meta_key']=='_shipping_address_1') {
		$_shipping_address_1 = $row_lista['meta_value'];
	}
	if($row_lista['meta_key']=='_shipping_address_2') {
		$_shipping_address_2 = $row_lista['meta_value'];
	}
	if($row_lista['meta_key']=='_billing_neighborhood') {
		$_billing_neighborhood = $row_lista['meta_value'];
	}
	if($row_lista['meta_key']=='_billing_phone') {
		$_billing_phone = $row_lista['meta_value'];
	}
	if($row_lista['meta_key']=='_billing_email') {
		$_billing_email = $row_lista['meta_value'];
	}
	if($row_lista['meta_key']=='_paid_date') {
		$_paid_date = date("d m Y", strtotime($row_lista['meta_value']));
	}
	if($row_lista['meta_key']=='_payment_method_title') {
		$metodo = $row_lista['meta_value'];
	}
	if($row_lista['meta_key']=='_cart_discount') {
		$_cart_discount = $row_lista['meta_value'];
	}
	if($row_lista['meta_key']=='_order_total') {
		$vtotal = $row_lista['meta_value'];
	}
	if($row_lista['meta_key']=='_order_shipping') {
		$_order_shipping = $row_lista['meta_value'];
	}
		
		 } while ($row_lista = mysqli_fetch_assoc($lista));
?>