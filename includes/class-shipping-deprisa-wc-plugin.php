<?php


class Shipping_Deprisa_WC_plugin
{
    /**
     * Filepath of main plugin file.
     *
     * @var string
     */
    public $file;
    /**
     * Plugin version.
     *
     * @var string
     */
    public $version;
    /**
     * Absolute plugin path.
     *
     * @var string
     */
    public $plugin_path;
    /**
     * Absolute plugin URL.
     *
     * @var string
     */
    public $plugin_url;
    /**
     * Absolute path to plugin includes dir.
     *
     * @var string
     */
    public $includes_path;
    /**
     * Absolute path to plugin lib dir
     *
     * @var string
     */
    public $lib_path;
    /**
     * @var bool
     */
    private $_bootstrapped = false;

    public function __construct($file, $version)
    {
        $this->file = $file;
        $this->version = $version;

        $this->plugin_path   = trailingslashit( plugin_dir_path( $this->file ) );
        $this->plugin_url    = trailingslashit( plugin_dir_url( $this->file ) );
        $this->includes_path = $this->plugin_path . trailingslashit( 'includes' );
        $this->lib_path = $this->plugin_path . trailingslashit( 'lib' );
    }

    public function run_deprisa_wc()
    {
        try{
            if ($this->_bootstrapped){
                throw new Exception( 'Deprisa shipping can only be called once');
            }
            $this->_run();
            $this->_bootstrapped = true;
        }catch (Exception $e){
            if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
                add_action('admin_notices', function() use($e) {
                    shipping_deprisa_wc_sd_notices($e->getMessage());
                });
            }
        }
    }

    protected  function _run()
    {
        if (!class_exists('\Saulmoralespa\Deprisa\Client'))
            require_once ($this->lib_path . 'vendor/autoload.php');
        require_once ($this->includes_path . 'class-method-shipping-deprisa-wc.php');
        require_once ($this->includes_path . 'class-shipping-deprisa-wc.php');

        add_filter( 'plugin_action_links_' . plugin_basename( $this->file), array( $this, 'plugin_action_links' ) );
        add_filter( 'woocommerce_shipping_methods', array( $this, 'shipping_deprisa_wc_add_method') );
        add_filter( 'woocommerce_checkout_fields', array($this, 'custom_woocommerce_fields'), 1000);
        add_filter( 'manage_edit-shop_order_columns', array($this, 'print_label'), 20 );
        add_filter( 'woocommerce_validate_postcode', array($this, 'filter_woocommerce_validate_postcode'), 10, 3 );

        add_action( 'wp_ajax_deprisa_get_cities', array($this, 'deprisa_get_cities'));
        add_action( 'woocommerce_checkout_process', array($this, 'check_post_code'));
        add_action( 'woocommerce_order_status_changed', array('Shipping_Deprisa_WC', 'generate_admision'), 20, 4 );
        add_action( 'woocommerce_process_product_meta', array($this, 'save_custom_shipping_option_to_products') );
        add_action( 'woocommerce_save_product_variation', array($this, 'save_variation_settings_fields'), 10, 2 );
        add_action( 'manage_shop_order_posts_custom_column', array($this, 'content_column_print_label'), 2 );
        add_action( 'admin_enqueue_scripts', array($this, 'enqueue_scripts_admin') );
        add_action( 'wp_ajax_deprisa_generate_label', array($this, 'deprisa_generate_label'));
        add_action( 'woocommerce_order_details_after_order_table', array($this, 'button_get_status_shipping'), 10, 1 );
        add_action( 'wp_enqueue_scripts', array($this, 'enqueue_scripts') );
        add_action( 'wp_ajax_deprisa_tracking', array($this, 'deprisa_tracking'));
        add_action( 'wp_ajax_nopriv_deprisa_tracking', array($this, 'deprisa_tracking'));

    }

    public function plugin_action_links($links)
    {
        $plugin_links = array();
        $plugin_links[] = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=shipping&section=shipping_deprisa_wc') . '">' . 'Configuraciones' . '</a>';
        $plugin_links[] = '<a target="_blank" href="https://shop.saulmoralespa.com/shipping-deprisa-woocommerce/">' . 'Documentación' . '</a>';
        return array_merge( $plugin_links, $links );
    }

    public function shipping_deprisa_wc_add_method( $methods ) {
        $methods['shipping_deprisa_wc'] = 'WC_Shipping_Method_Shipping_Deprisa_WC';
        return $methods;
    }

    public function log($message)
    {
        if (is_array($message) || is_object($message))
            $message = print_r($message, true);
        $logger = new WC_Logger();
        $logger->add('shipping-deprisa', $message);
    }

    public function deprisa_get_cities()
    {
        if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'shipping_deprisa_state_nonce' ) )
            return;

        $state = sanitize_text_field($_REQUEST['state']);

        $places = WC_States_Places_Colombia::get_places();

        wp_send_json($places['CO'][$state]);
    }

    public function custom_woocommerce_fields($fields)
    {
        $chosen_methods = WC()->session->get( 'chosen_shipping_methods' );
        $chosen_shipping = $chosen_methods[0];

        if ($chosen_shipping !== 'shipping_deprisa_wc')
            return $fields;

        $fields['billing']['billing_postcode']['required'] = true;
        $fields['shipping']['shipping_postcode']['required'] = true;

        $fields['billing']['billing_type_document'] = array(
            'label'       => __('Tipo de documento'),
            'placeholder' => _x('', 'placeholder'),
            'required'    => true,
            'clear'       => false,
            'type'        => 'select',
            'default' => 'CC',
            'options'     => array(
                'CC' => __('Cédula de ciudadanía' ),
                'CE' => __('Cédula de extranjería'),
                'NIT' => __('(NIT) Número de indentificación tributaria')
            )
        );

        $fields['billing']['billing_dni'] = array(
            'label' => __('Número de documento'),
            'placeholder' => _x('Su número de documento....', 'placeholder'),
            'required' => true,
            'clear' => false,
            'type' => 'number',
            'class' => array('my-css')
        );


        $fields['shipping']['shipping_type_document'] = array(
            'label'       => __('Tipo de documento'),
            'placeholder' => _x('', 'placeholder'),
            'required'    => true,
            'clear'       => false,
            'type'        => 'select',
            'default' => 'CC',
            'options'     => array(
                'CC' => __('Cédula de ciudadanía' ),
                'CE' => __('Cédula de extranjería'),
                'NIT' => __('(NIT) Número de indentificación tributaria')
            )
        );

        $fields['shipping']['shipping_dni'] = array(
            'label' => __('Número de documento'),
            'placeholder' => _x('Su número de documento....', 'placeholder'),
            'required' => true,
            'clear' => false,
            'type' => 'number',
            'class' => array('my-css')
        );

        return $fields;
    }

    public function check_post_code()
    {
        $post_code = sanitize_text_field($_POST['billing_postcode']);
        $state = sanitize_text_field($_POST['billing_state']);
        $city = sanitize_text_field($_POST['billing_city']);

        if(empty($post_code)) return;
        if(!is_numeric($post_code)) return;

        $state = Shipping_Deprisa_WC::clean_string($city) === 'Bogota D.C' ? 'BOG' : $state;

        if (strlen($post_code) !== 6 || !$this->is_acepted_post_code($state, $post_code))
            wc_add_notice( __( '<p>Consulte su código postal en <a target="_blank" href="http://visor.codigopostal.gov.co/472/visor/">Visor Codigo Postal 4-72 </a></p>' ), 'error' );
    }

    public function is_acepted_post_code($state, $post_code)
    {
        $states =  include dirname(__FILE__) . '/states.php';
        $key = array_search($state, $states);

        if (!$key)
            return false;

        return preg_match("/^($key)/", $post_code);
    }

    public static function add_custom_shipping_option_to_products()
    {
        global $post;

        woocommerce_wp_text_input( [
            'id'          => '_shipping_custom_price_product_smp',
            'label'       => __( 'Valor declarado del producto'),
            'placeholder' => 'Valor declarado del envío',
            'desc_tip'    => true,
            'description' => __( 'El valor que desea declarar para el envío'),
            'value'       => get_post_meta( $post->ID, '_shipping_custom_price_product_smp', true ),
        ] );
    }

    public function variation_settings_fields($loop, $variation_data, $variation)
    {
        woocommerce_wp_text_input(
            array(
                'id'          => '_shipping_custom_price_product_smp[' . $variation->ID . ']',
                'label'       => __( 'Valor declarado del producto'),
                'placeholder' => 'Valor declarado del envío',
                'desc_tip'    => true,
                'description' => __( 'El valor que desea declarar para el envío'),
                'value'       => get_post_meta( $variation->ID, '_shipping_custom_price_product_smp', true )
            )
        );
    }

    public function save_custom_shipping_option_to_products($post_id)
    {
        $custom_price_product = sanitize_text_field($_POST['_shipping_custom_price_product_smp']);
        if( isset( $custom_price_product ) )
            update_post_meta( $post_id, '_shipping_custom_price_product_smp', $custom_price_product );
    }

    public function save_variation_settings_fields($post_id)
    {
        $custom_variation_price_product = sanitize_text_field($_POST['_shipping_custom_price_product_smp'][ $post_id ]);
        if( ! empty( $custom_variation_price_product ) ) {
            update_post_meta( $post_id, '_shipping_custom_price_product_smp', $custom_variation_price_product );
        }
    }

    public function print_label($columns)
    {
        $columns['generate_labels_deprisa'] = 'Generar etiquetas Deprisa';
        return $columns;
    }

    public function content_column_print_label($column)
    {
        global $post;

        $order = new WC_Order($post->ID);

        $order_id_origin = $order->get_parent_id() > 0 ? $order->get_parent_id() : $order->get_id();
        $shipping_number = get_post_meta($order_id_origin, 'admision_deprisa', true);

        $upload_dir = wp_upload_dir();
        $label_file = $upload_dir['basedir'] . '/deprisa-labels/' . "$shipping_number.pdf";
        $label_url = $upload_dir['baseurl'] . '/deprisa-labels/' . "$shipping_number.pdf";

        if(!file_exists($label_file) && !empty($shipping_number) && $column == 'generate_labels_deprisa' ){
            echo "<button class='button-secondary generate_label_deprisa' data-guide='".$shipping_number."' data-nonce='".wp_create_nonce( "shipping_deprisa_generate_label") ."'>Generar Etiqueta</button>";
        }elseif (file_exists($label_file) && !empty($shipping_number) && $column == 'generate_labels_deprisa'){
            echo "<a target='_blank' class='button-primary' href='$label_url'>Ver rótulo</a>";
        }
    }

    public function filter_woocommerce_validate_postcode($valid, $postcode, $country)
    {

       $customer = WC()->customer;

       $city = $customer->get_billing_city();
       $state = $customer->get_billing_state();
       $state = Shipping_Deprisa_WC::clean_string($city) === 'Bogota D.C' ? 'BOG' : $state;

        if ($country === 'CO')
            $valid = (bool)  !empty($state) ? $this->is_acepted_post_code($state, $postcode) : preg_match( '/^([0-9]{6})$/i', $postcode );
        return $valid;
    }

    public function deprisa_generate_label()
    {
        if ( ! wp_verify_nonce(  $_REQUEST['nonce'], 'shipping_deprisa_generate_label' ) )
            return;

        $shipping_number = sanitize_text_field($_REQUEST['shipping_number']);

        if (!is_numeric($shipping_number)) return;

        $label_url = '';

        try{

            $labels['ETIQUETA'] = [
                'NUMERO_ENVIO' => $shipping_number,
                'TIPO_IMPRESORA' => 'T'
            ];

            $data = Shipping_Deprisa_WC::print_labels($labels);

            if(empty($data)) return;

            $label = $data['RESPUESTA_ETIQUETAS']["ETIQUETA"];

            $bin = base64_decode($label, true);
            if (strpos($bin, '%PDF') !== 0)
                throw new \Exception('Missing the PDF file signature');

            $upload_dir = wp_upload_dir();
            $dir = $upload_dir['basedir'] . '/deprisa-labels/';

            require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';

            $wp_filesystem = new WP_Filesystem_Direct(null);

            if (wp_mkdir_p($dir) && $wp_filesystem->put_contents("{$dir}$shipping_number.pdf", $bin))
                $label_url = $upload_dir['baseurl'] . '/deprisa-labels/' . "$shipping_number.pdf";

        }catch (\Exception $exception){
            $this->log($exception->getMessage());
        }

        wp_send_json(['url' => $label_url]);
    }

    public function button_get_status_shipping($order)
    {
        $order_id_origin = $order->get_parent_id() > 0 ? $order->get_parent_id() : $order->get_id();
        $shipping_number = get_post_meta($order_id_origin, 'admision_deprisa', true);

        if ($shipping_number){
            echo "<p>Envío delegado a Deprisa con código de seguimiento: $shipping_number</p>  <button class='button-secondary wp-caption tracking-deprisa' data-guide='".$shipping_number."' data-nonce='".wp_create_nonce( "shipping_deprisa_tracking") ."'>Seguimiento en línea</button>";
        }
    }

    public function deprisa_tracking()
    {
        if ( ! wp_verify_nonce(  $_REQUEST['nonce'], 'shipping_deprisa_tracking' ) )
            return;

        $shipping_number = sanitize_text_field($_REQUEST['shipping_number']);

        if (!is($shipping_number)) return;

        $data = new stdClass;

        try {
            $data = Shipping_Deprisa_WC::tracking($shipping_number);
        }catch (\Exception $exception){
        }

        wp_send_json($data);
    }

    public function enqueue_scripts_admin($hook)
    {
        if ($hook === 'edit.php'){
            wp_enqueue_script('sweetalert_shipping_deprisa_wc_sd', $this->plugin_url . 'assets/js/sweetalert2.js', array( 'jquery' ), $this->version, true );
            wp_enqueue_script( 'config_shipping_deprisa_wc_sd', $this->plugin_url . 'assets/js/config.js', array( 'jquery' ), $this->version, true );
        }
    }

    public function enqueue_scripts()
    {
        if(is_view_order_page()){
            wp_enqueue_script('sweetalert_shipping_deprisa_wc_sd', $this->plugin_url . 'assets/js/sweetalert2.js', array( 'jquery' ), $this->version, true );
            wp_enqueue_script( 'view_shipping_deprisa_wc_sd', $this->plugin_url . 'assets/js/view-order.js', array( 'jquery' ), $this->version, true );
        }
    }
}