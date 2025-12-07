$(document).ready(function(){
    $('#search').focus()
    
    
     $('#search').on('keyup', function(){
      var search = $('#search').val()
      var _order_id = $('#_order_id').val()
   var dataString = '_order_id=' + _order_id + '&search='+ search
      $.ajax({
        type: 'POST',
        url: 'search.php',
        data: dataString,
        beforeSend: function(){
          $('#result').html('ESPERANDO')
        }
      })
      .done(function(resultado){
        $('#result').html(resultado)
      })
      .fail(function(){
        alert('Hubo un error :(')
      })
    })
  }) 
