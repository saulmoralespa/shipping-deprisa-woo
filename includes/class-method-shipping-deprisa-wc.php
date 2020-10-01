<?php


class WC_Shipping_Method_Shipping_Deprisa_WC extends WC_Shipping_Method
{
    public $debug;

    public $code_client;

    public $code_center;

    public $state_sender;

    public $city_sender;

    public $is_test;

    public function __construct($instance_id = 0)
    {
        parent::__construct($instance_id);

        $this->id                 = 'shipping_deprisa_wc';
        $this->instance_id        = absint( $instance_id );
        $this->method_title       = __( 'Deprisa' );
        $this->method_description = __( 'Deprisa empresa transportadora de Colombia' );
        $this->title = $this->get_option('title');

        $this->supports = array(
            'settings',
            'shipping-zones'
        );

        $this->init();

        $this->debug = $this->get_option( 'debug' );
        $this->is_test = (bool)$this->get_option( 'environment' );

        if($this->is_test){
            $this->code_client = $this->get_option( 'sandbox_code_client' );
            $this->code_center = $this->get_option( 'sandbox_code_center' );
        }else{
            $this->code_client = $this->get_option( 'code_client' );
            $this->code_center = $this->get_option( 'code_center' );
        }

        $this->state_sender = $this->get_option( 'state_sender' );
        $this->city_sender = $this->get_option( 'city_sender' );

    }

    public function is_available($package)
    {
        return parent::is_available($package) &&
            !empty($this->code_client) &&
            !empty($this->code_center);
    }

    public function init()
    {
        // Load the settings API.
        $this->init_form_fields(); // This is part of the settings API. Override the method to add your own settings.
        $this->init_settings(); // This is part of the settings API. Loads settings you previously init.
        // Save settings in admin if you have any defined.
        add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
    }

    public function init_form_fields()
    {
        $this->form_fields = include(dirname(__FILE__) . '/admin/settings.php');
    }

    public function admin_options()
    {
        ?>
        <h3><?php echo $this->title; ?></h3>
        <p><?php echo $this->method_description; ?></p>
        <table class="form-table">
            <?php
            if (!empty($this->code_client) && !empty($this->code_center) && $this->is_test)
                Shipping_Deprisa_WC::test_connection();
            $this->generate_settings_html();
            ?>
        </table>
        <?php
    }

    public function calculate_shipping($package = array())
    {
        global $woocommerce;
        $country = $package['destination']['country'];

        if($country !== 'CO')
            return apply_filters( 'woocommerce_shipping_' . $this->id . '_is_available', false, $package, $this );

        $city_sender = Shipping_Deprisa_WC::get_city($this->city_sender);
        $city_destination = Shipping_Deprisa_WC::get_city($package['destination']['city']);

        $params = [
            'TIPO_ENVIO' => 'N',
            'POBLACION_REMITENTE' => $city_sender,
            'PAIS_DESTINATARIO' => '057',
            'POBLACION_DESTINATARIO' => $city_destination,
            'INCOTERM' => '', //(SI para Inter)
            'CODIGO_SERVICIO'  => '3005',
            'TIPO_MERCANCIA' => '',
            'CONTENEDOR_MERCANCIA' => '', //(SI para Inter) S / C (Indica: sobre o caja)
            'TIPO_MONEDA' => 'COP'
        ];

        $items = $woocommerce->cart->get_cart();
        $data_products = Shipping_Deprisa_WC::data_products($items);

        $params = array_merge($params, $data_products);

        $data = Shipping_Deprisa_WC::liquidation($params);

        if (empty($data))
            return apply_filters( 'woocommerce_shipping_' . $this->id . '_is_available', false, $package, $this );

        $total = $data['TOTAL'] == 0 && $this->is_test ? 5000 : $data['TOTAL'];

        $rate = array(
            'id'      => $this->id,
            'label'   => $this->title,
            'cost'    => $total,
            'package' => $package,
        );

        return $this->add_rate( $rate );

    }
}