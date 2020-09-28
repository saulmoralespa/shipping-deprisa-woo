(function($){
    $('button.tracking-deprisa').click(function(e){
        e.preventDefault();

        $.ajax({
            data: {
                action: 'deprisa_tracking',
                nonce: $(this).data("nonce"),
                shipping_number: $(this).data("guide")
            },
            type: 'POST',
            url: wc_add_to_cart_params.ajax_url,
            dataType: "json",
            beforeSend : () => {
                Swal.fire({
                    title: 'Consultando...',
                    onOpen: () => {
                        Swal.showLoading()
                    },
                    allowOutsideClick: false
                });
            },
            success: (r) => {
                if (r.ESTADOS && r.ESTADOS.ESTADO[0]){
                    //["DESCRIPCION"]=>
                    //["FECHA_EVENTO"]=>
                    let status = r.ESTADOS.ESTADO[0].DESCRIPCION
                    Swal.fire({
                        icon: 'info',
                        title: status,
                        allowOutsideClick: false,
                        showCloseButton: true,
                        showConfirmButton: false
                    })
                }else{
                    Swal.fire(
                        'Error',
                        'No se puede consultar el estado de esta guía',
                        'error'
                    );
                }
            }
        });
    })
})(jQuery);