
// Add triple
$('.btn-add-triple').on('click', function(e){
    e.preventDefault();
    $(this).prop('disabled', true);

    // Get form variables
    var form = $('form.add-triple');

    // Loop through fields
    var data = new Object();
    $('.tab-pane.active', form).each(function(){
        var pane = $(this);

        data.type = pane.data('type');

        $('input, textarea, select', pane).each(function(){
            if($(this).attr('name')){
                // Regular fields
                if($(this).attr('type') == 'checkbox'){
                    data[$(this).attr('name')] = $(this).attr('checked') ? 1 : 0;
                }else{
                    data[$(this).attr('name')] = $(this).val();
                }
            }
        });
    });

    // Ajax call
    $.ajax({
        url: baseURL + 'api/triples',
        data: JSON.stringify(data),
        method: 'POST',
        headers: {
            'Accept' : 'application/json',
            'Authorization': authHeader
        },
        success: function(e){
            // Done, redirect to triples page
            window.location = baseURL + 'api/admin/triples';
        },
        error: function(e){
            $('.btn-add-triple').prop('disabled', false);
            if(e.status != 405){
                var error = JSON.parse(e.responseText);
                if(error.error && error.error.message){
                    $('.error .text').html(error.error.message)
                    $('.error').removeClass('hide').show().focus();
                }
            }else{
                // Ajax followed location header -> ignore
                window.location = baseURL + 'api/admin/triples';
            }
        }
    })

});