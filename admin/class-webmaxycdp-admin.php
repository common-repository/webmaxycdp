<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://www.webmaxy.co
 * @since      1.0.0
 *
 * @package    WebMaxyCDP
 * @subpackage WebMaxyCDP/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    WebMaxyCDP
 * @subpackage WebMaxyCDP/admin
 * @author     WebMaxyCDP
 */
class WebMaxyCDP_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;
		add_action('admin_menu', array( $this, 'addPluginAdminMenu' ), 9);   
		add_action('admin_init', array( $this, 'registerAndBuildFields' )); 

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in WebMaxyCDP_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The WebMaxyCDP_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/webmaxycdp-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in WebMaxyCDP_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The WebMaxyCDP_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/webmaxycdp-admin.js', array( 'jquery' ), $this->version, false );

	}
	public function addPluginAdminMenu() {
		//add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $function, $icon_url, $position );
		add_menu_page(  $this->plugin_name, 'WebMaxyCDP', 'administrator', "\/web\/".$this->plugin_name, array( $this, 'displayPluginAdminSettings' ), 'dashicons-chart-area', 26 );
		
		//add_submenu_page( '$parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function );
		//add_submenu_page( $this->plugin_name, 'WebMaxyCDP Settings', 'Settings', 'administrator', $this->plugin_name.'-settings', array( $this, 'displayPluginAdminSettings' ));
	}
	public function displayPluginAdminDashboard() {
		require_once 'partials/webmaxycdp-admin-display.php';
  }
	public function displayPluginAdminSettings() {
		// set this var to be used in the settings-display view
		// $active_tab =   sanitize_text_field($_GET[ 'tab' ])  ?  sanitize_text_field($_GET[ 'tab' ]) : 'general';
		// if(sanitize_text_field($_GET['error_message'])!="" ){
		// 		add_action('admin_notices', array($this,'settingsPageSettingsMessages'));
		// 		do_action( 'admin_notices',  sanitize_text_field($_GET['error_message']) );
		// }
		require_once 'partials/webmaxycdp-admin-settings-display.php';
	}
	public function settingsPageSettingsMessages($error_message){
		switch ($error_message) {
				case '1':
						$message = __( 'There was an error adding this setting. Please try again.  If this persists, shoot us an email.', 'my-text-domain' );                 $err_code = esc_attr( 'webmaxy_site_id' );                 $setting_field = 'webmaxy_site_id';                 
						break;
		}
		$type = 'error';
		add_settings_error(
					$setting_field,
					$err_code,
					$message,
					$type
			);
	}
	public function registerAndBuildFields() {
			/**
		 * First, we add_settings_section. This is necessary since all future settings must belong to one.
		 * Second, add_settings_field
		 * Third, register_setting
		 */     
		add_settings_section(
			// ID used to identify this section and with which to register options
			'WebMaxyCDP_general_section', 
			// Title to be displayed on the administration page
			'',  
			// Callback used to render the description of the section
				array( $this, 'WebMaxyCDP_display_general_account' ),    
			// Page on which to add this section of options
			'WebMaxyCDP_general_settings'                   
		);
		unset($args);
		unset($args1);
		$args = array (
							'type'      => 'input',
							'subtype'   => 'text',
							'id'    => 'webmaxy_client_id',
							'name'      => 'webmaxy_client_id',
							'required' => 'true',
							'get_options_list' => '',
							'value_type'=>'normal',
							'wp_data' => 'option'
					);
					$args1 = array (
						'type'      => 'input',
						'subtype'   => 'text',
						'id'    => 'webmaxy_secret_id',
						'name'      => 'webmaxy_secret_id',
						'required' => 'true',
						'get_options_list' => '',
						'value_type'=>'normal',
						'wp_data' => 'option'
				);
		add_settings_field(
			'webmaxy_client_id',
			'Client Id',
			array( $this, 'WebMaxyCDP_render_settings_field' ),
			'WebMaxyCDP_general_settings',
			'WebMaxyCDP_general_section',
			$args
		);

		add_settings_field(
			'webmaxy_secret_id',
			'Secret Id',
			array( $this, 'WebMaxyCDP_render_settings_field' ),
			'WebMaxyCDP_general_settings',
			'WebMaxyCDP_general_section',
			$args1
		);
		register_setting(
			'WebMaxyCDP_general_settings',
			'webmaxy_client_id'
			);

		register_setting(
						'WebMaxyCDP_general_settings',
						'webmaxy_secret_id'
						);

	}
	public function WebMaxyCDP_display_general_account() {
		 echo '<p>Visit <a href="https://www.webmaxy.co">https://www.webmaxy.co</a> to get WebMaxyCDP ID</p>';
	} 
	public function WebMaxyCDP_render_settings_field($args) {
			/* EXAMPLE INPUT
								'type'      => 'input',
								'subtype'   => '',
								'id'    => $this->plugin_name.'_example_setting',
								'name'      => $this->plugin_name.'_example_setting',
								'required' => 'required="required"',
								'get_option_list' => "",
									'value_type' = serialized OR normal,
			'wp_data'=>(option or post_meta),
			'post_id' =>
			*/     
		if($args['wp_data'] == 'option'){
			$wp_data_value = get_option($args['name']);
		} elseif($args['wp_data'] == 'post_meta'){
			$wp_data_value = get_post_meta($args['post_id'], $args['name'], true );
		}

		switch ($args['type']) {

			case 'input':
					$value = ($args['value_type'] == 'serialized') ? serialize($wp_data_value) : $wp_data_value;
					if($args['subtype'] != 'checkbox'){
							$prependStart = (isset($args['prepend_value'])) ? '<div class="input-prepend"> <span class="add-on">'.$args['prepend_value'].'</span>' : '';
							$prependEnd = (isset($args['prepend_value'])) ? '</div>' : '';
							$step = (isset($args['step'])) ? 'step="'.$args['step'].'"' : '';
							$min = (isset($args['min'])) ? 'min="'.$args['min'].'"' : '';
							$max = (isset($args['max'])) ? 'max="'.$args['max'].'"' : '';
							if(isset($args['disabled'])){
									// hide the actual input bc if it was just a disabled input the info saved in the database would be wrong - bc it would pass empty values and wipe the actual information
									echo (esc_attr($prependStart).'<input type="'.esc_attr($args['subtype']).'" id="'.esc_attr($args['id']).'_disabled" '.esc_attr($step).' '.esc_attr($max).' '.esc_attr($min).' name="'.esc_attr($args['name']).'_disabled" size="40" disabled value="' . esc_attr($value) . '" /><input type="hidden" id="'.esc_attr($args['id']).'" '.esc_attr($step).' '.esc_attr($max).' '.esc_attr($min).' name="'.esc_attr($args['name']).'" size="40" value="' . esc_attr($value) . '" />'.$prependEnd);
							} else {
									echo (esc_attr($prependStart).'<input type="'.esc_attr($args['subtype']).'" id="'.esc_attr($args['id']).'" "'.esc_attr($args['required']).'" '.esc_attr($step).' '.esc_attr($max).' '.esc_attr($min).' name="'.esc_attr($args['name']).'" size="40" value="' . esc_attr($value) . '" />'.esc_attr($prependEnd));
							}
							/*<input required="required" '.$disabled.' type="number" step="any" id="'.$this->plugin_name.'_cost2" name="'.$this->plugin_name.'_cost2" value="' . esc_attr( $cost ) . '" size="25" /><input type="hidden" id="'.$this->plugin_name.'_cost" step="any" name="'.$this->plugin_name.'_cost" value="' . esc_attr( $cost ) . '" />*/

					} else {
							$checked = ($value) ? 'checked' : '';
							echo ('<input type="'.esc_attr($args['subtype']).'" id="'.esc_attr($args['id']).'" "'.esc_attr($args['required']).'" name="'.esc_attr($args['name']).'" size="40" value="1" '.esc_attr($checked).' />');
					}
					break;
			default:
					# code...
					break;
		}
	}
}
