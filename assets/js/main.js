(function($) {

	"use strict";

	/**
	 * Función centralizada para limpiar localStorage del sistema de ventas
	 * @param {string} type - Tipo de limpieza: 'C' = Completa, 'O' = Nueva Orden
	 */
	window.cleanVentasLocalStorage = function(type) {
		const cleanupTypes = {
			// Limpieza completa - cuando se crea un pedido exitosamente
			'C': [
				'cartbounty_custom_email',
				'ventas_customer_data',
				'ventas_form_data',
				'ventas_wizard_form_data',
				'ventas_cart_products',
				'ventas_order_summary'
			],
			// Nueva orden - cuando se inicia un nuevo pedido (excluye ventas_cart_products)
			'O': [
				'cartbounty_custom_email',
				'ventas_customer_data',
				'ventas_form_data',
				'ventas_wizard_form_data'
			]
		};

		const keysToClean = cleanupTypes[type];
		
		if (!keysToClean) {
			console.warn('Tipo de limpieza no válido:', type);
			return false;
		}

		let cleanedCount = 0;
		keysToClean.forEach(key => {
			if (localStorage.getItem(key) !== null) {
				localStorage.removeItem(key);
				cleanedCount++;
			}
		});

		const typeNames = {
			'C': 'Completa (pedido creado)',
			'O': 'Nueva orden'
		};

		console.log(`localStorage limpiado - Tipo: ${typeNames[type]}, Keys eliminadas: ${cleanedCount}/${keysToClean.length}`);
		return true;
	};

	// Función para reconstruir cache completa desde datos de orden
	window.buildOrderCache = function(orderData) {
		try {
			// 1. Limpiar cache existente
			window.cleanVentasLocalStorage('C');
			
			// 2. Construir ventas_cart_products desde items de la orden
			const cartProducts = {};
			if (orderData.items && orderData.items.length > 0) {
				orderData.items.forEach(item => {
					const cartKey = `${item.product_id}:a:${orderData.order_id}`;
					
					// Calcular precios con descuentos
					const lineTotal = parseFloat(item.line_total) || 0;
					const quantity = parseInt(item.quantity) || 1;
					const unitPrice = quantity > 0 ? lineTotal / quantity : lineTotal;
					
					// Determinar precio regular y precio de oferta
					let regularPrice = unitPrice;
					let salePrice = null;
					
					// Si hay metadatos de precio regular, usarlos
					if (item.regular_price && parseFloat(item.regular_price) > unitPrice) {
						regularPrice = parseFloat(item.regular_price);
						salePrice = unitPrice;
					}
					
					cartProducts[cartKey] = {
						cart_key: cartKey,
						product_id: parseInt(item.product_id),
						variation_id: item.variation_id || null,
						title: item.order_item_name || 'Producto',
						variation_label: '',
						variation_attributes: {},
						price: unitPrice,
						regular_price: regularPrice,
						sale_price: salePrice,
						stock: 999,
						sku: item.sku || '',
						image_url: item.image_url || '',
						permalink: item.permalink || '',
						quantity: quantity,
						available: true
					};
				});
			}
			localStorage.setItem('ventas_cart_products', JSON.stringify(cartProducts));
			
			// 3. Construir ventas_form_data
			const formData = {
				_order_id: orderData.order_id.toString(),
				nombre1: orderData.billing_first_name || '',
				nombre2: orderData.billing_last_name || '',
				billing_id: orderData.dni_cliente || orderData.billing_id || '',
				_billing_email: orderData.billing_email || '',
				_billing_phone: orderData.billing_phone || '',
				_shipping_address_1: orderData.billing_address_1 || '',
				_shipping_address_2: orderData.billing_address_2 || '',
				_billing_neighborhood: orderData.billing_barrio || '',
				_shipping_state: orderData.billing_state || '',
				_order_shipping: orderData.shipping_cost ? parseFloat(orderData.shipping_cost).toLocaleString() : '10.000',
				_order_shipping_value: orderData.shipping_cost || '10000',
				_payment_method_title: orderData.payment_method_title || 'Pago Contra Entrega Aplica solo para Bogotá',
				_cart_discount: '0',
				_timestamp: Date.now(),
				_step: 1
			};
			localStorage.setItem('ventas_form_data', JSON.stringify(formData));
			
			// 4. Construir ventas_order_summary
			const products = Object.values(cartProducts);
			const totalItems = products.reduce((sum, product) => sum + product.quantity, 0);
			const totalPrice = products.reduce((sum, product) => sum + (product.price * product.quantity), 0);
			
			const orderSummary = {
				products: products,
				total_items: totalItems,
				total_price: totalPrice,
				_order_shipping: formData._order_shipping,
				_cart_discount: formData._cart_discount,
				_payment_method_title: formData._payment_method_title,
				post_expcerpt: '',
				timestamp: new Date().toISOString()
			};
			localStorage.setItem('ventas_order_summary', JSON.stringify(orderSummary));
			
			// 5. Construir ventas_wizard_form_data (copia de form_data)
			localStorage.setItem('ventas_wizard_form_data', JSON.stringify(formData));
			
			console.log('Cache de orden reconstruida exitosamente:', {
				order_id: orderData.order_id,
				products_count: products.length,
				total_items: totalItems,
				total_price: totalPrice
			});
			
			return true;
			
		} catch (error) {
			console.error('Error reconstruyendo cache de orden:', error);
			return false;
		}
	};

})(jQuery);
