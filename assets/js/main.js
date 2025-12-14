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

})(jQuery);
