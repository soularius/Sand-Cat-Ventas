$(document).ready(function(){
    // Focus on search input when page loads
    $('#search').focus();
    
    // Search functionality with improved UX
    $('#search').on('keyup', function(){
        var search = $(this).val().trim();
        var _order_id = $('#_order_id').val();
        
        // Only search if we have at least 3 characters
        if(search.length >= 3) {
            var dataString = '_order_id=' + _order_id + '&search=' + encodeURIComponent(search);
            
            $.ajax({
                type: 'POST',
                url: 'search.php',
                data: dataString,
                beforeSend: function(){
                    $('#result').html(`
                        <div class="loading-state">
                            <div class="spinner-border text-primary" role="status">
                                <span class="sr-only">Buscando...</span>
                            </div>
                            <h5 class="mt-3">Buscando productos...</h5>
                            <p>Por favor espera mientras buscamos "${search}"</p>
                        </div>
                    `);
                },
                timeout: 10000 // 10 second timeout
            })
            .done(function(resultado){
                $('#result').html(resultado);
            })
            .fail(function(xhr, status, error){
                console.error('Search error:', status, error);
                $('#result').html(`
                    <div class="loading-state">
                        <i class="fas fa-exclamation-triangle text-warning"></i>
                        <h5 class="mt-3">Error en la búsqueda</h5>
                        <p>No se pudo completar la búsqueda. Por favor intenta de nuevo.</p>
                    </div>
                `);
            });
        } else if(search.length === 0) {
            // Reset to initial state when search is empty
            $('#result').html(`
                <div class="loading-state">
                    <i class="fas fa-search"></i>
                    <h5>Busca productos</h5>
                    <p>Utiliza el campo de búsqueda para encontrar productos disponibles</p>
                </div>
            `);
        } else {
            // Show help message for short searches
            $('#result').html(`
                <div class="loading-state">
                    <i class="fas fa-keyboard text-warning"></i>
                    <h5>Continúa escribiendo...</h5>
                    <p>Necesitas al menos 3 caracteres para buscar</p>
                </div>
            `);
        }
    });
    
    // Function to be called by inline JavaScript if needed
    window.performSearch = function(searchTerm) {
        $('#search').val(searchTerm).trigger('keyup');
    };
});
