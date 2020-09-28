(function($){
    $('button.generate_label_deprisa').click(function (e) {
        e.preventDefault();

        $.ajax({
            data: {
                action: 'deprisa_generate_label',
                nonce: $(this).data("nonce"),
                shipping_number: $(this).data("guide")
            },
            type: 'POST',
            url: ajaxurl,
            dataType: "json",
            beforeSend : () => {
                Swal.fire({
                    title: 'Generando etiqueta',
                    onOpen: () => {
                        Swal.showLoading()
                    },
                    allowOutsideClick: false
                });
            },
            success: (r) => {
                if (r.url){
                    Swal.fire({
                        icon: 'success',
                        html: `<a target="_blank" href="${r.url}">Ver etiqueta</a>`,
                        allowOutsideClick: false,
                        showCloseButton: true,
                        showConfirmButton: false
                    })
                }else{
                    Swal.fire(
                        'Error',
                        r.message,
                        'error'
                    );
                }
            }
        });
    });
})(jQuery);