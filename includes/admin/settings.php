<?php

wc_enqueue_js( "
    jQuery( function( $ ) {
	
	let fields = '#woocommerce_shipping_deprisa_wc_code_client, #woocommerce_shipping_deprisa_wc_code_center';
	
	let sandbox_fields = '#woocommerce_shipping_deprisa_wc_sandbox_code_client, #woocommerce_shipping_deprisa_wc_sandbox_code_center';
	
	let city_saved = '".$this->get_option( 'city_sender' )."'

	$( '#woocommerce_shipping_deprisa_wc_environment' ).change(function(){
	
		$( sandbox_fields + ',' + fields ).closest( 'tr' ).hide();

		if ( '0' === $( this ).val() ) {
			$( fields ).closest( 'tr' ).show();
			
		}else{
		   $( sandbox_fields ).closest( 'tr' ).show();
		}
	}).change();
	
	$( '#woocommerce_shipping_deprisa_wc_state_sender' ).change(function(){
		let state = $( this ).val();
	
		$.ajax({
            method: 'GET',
            url: ajaxurl,
            data: {
            action: 'deprisa_get_cities',
            state
            },
            dataType: 'json',
            beforeSend: () => {
            },
            success: (res) => {
            let cities = res.map(function(city, index){
                    return city_saved === city ? '<option value='+city+' selected>'+city+'</option>' : '<option value='+city+'>'+city+'</option>';
                });
                $('#woocommerce_shipping_deprisa_wc_city_sender').html(cities);
            }
        });
		
	}).change();
	
});	
");

$states = WC_States_Places_Colombia::get_places();
$states = $states['CO'];

$states_arr = [];

foreach ($states as $key => $value){
    $states_arr[$key] = WC()->countries->get_states( 'CO' )[$key];
}

$docs_url = '<a target="_blank" href="https://shop.saulmoralespa.com/shipping-deprisa-woocommerce/">' . __( 'Ver documentación completa del plugin') . '</a>';
$docs = array(
    'docs'  => array(
        'title' => __( 'Documentación' ),
        'type'  => 'title',
        'description' => $docs_url
    )
);

return apply_filters(
    'deprisa_shipping_settings',
    array_merge(
        $docs,
        array(
            'enabled' => array(
                'title' => __('Activar/Desactivar'),
                'type' => 'checkbox',
                'label' => __('Activar Deprisa'),
                'default' => 'no'
            ),
            'title'        => array(
                'title'       => __( 'Título método de envío' ),
                'type'        => 'text',
                'description' => __( 'Esto controla el título que el usuario ve durante el pago' ),
                'default'     => __( 'Deprisa' ),
                'desc_tip'    => true
            ),
            'debug'        => array(
                'title'       => __( 'Depurador' ),
                'label'       => __( 'Habilitar el modo de desarrollador' ),
                'type'        => 'checkbox',
                'default'     => 'no',
                'description' => __( 'Registros de depuración, indice nombre archivo log: shipping-deprisa-' ),
                'desc_tip' => true
            ),
            'environment' => array(
                'title' => __('Entorno'),
                'type'        => 'select',
                'class'       => 'wc-enhanced-select',
                'description' => __('Entorno de pruebas o producción'),
                'desc_tip' => true,
                'default' => '1',
                'options'     => array(
                    '0'    => __( 'Producción'),
                    '1' => __( 'Pruebas')
                ),
            ),
            'state_sender' => array(
                'title' => __('Departamento del remitente'),
                'type'        => 'select',
                'class'       => 'wc-enhanced-select',
                'description' => __('Departamento del remitente (donde se encuentra ubicada la tienda)'),
                'desc_tip' => true,
                'default' => true,
                'options'     => $states_arr
            ),
            'city_sender' => array(
                'title' => __('Ciudad del remitente'),
                'type'        => 'select',
                'class'       => 'wc-enhanced-select',
                'description' => __('Ciudad del remitente (donde se encuentra ubicada la tienda)'),
                'desc_tip' => true,
                'default' => '',
                'options' => array(
                    ''    => __( 'Elige una opción...'),
                )
            ),
            'code_client' => array(
                'title' => __( 'Código cliente' ),
                'type'  => 'number',
                'description' => __( 'Código cliente proporcionado por Deprisa' ),
                'desc_tip' => true
            ),
            'code_center' => array(
                'title' => __( 'Código centro' ),
                'type'  => 'number',
                'description' => __( 'Código centro proporcionado por Deprisa' ),
                'desc_tip' => true
            ),
            'sandbox_code_client' => array(
                'title' => __( 'Código cliente (pruebas)' ),
                'type'  => 'number',
                'description' => __( 'Código cliente proporcionado por Deprisa' ),
                'desc_tip' => true
            ),
            'sandbox_code_center' => array(
                'title' => __( 'Código centro (pruebas)' ),
                'type'  => 'number',
                'description' => __( 'Código centro proporcionado por Deprisa' ),
                'desc_tip' => true
            ),
        )
    )
);