<?php
global $wp;
if (!session_id()) {
	session_start();
}
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://www.webmaxy.co
 * @since      1.0.0
 *
 * @package    WebMaxyCDP
 * @subpackage WebMaxyCDP/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    WebMaxyCDP
 * @subpackage WebMaxyCDP/includes
 * @author     WebMaxy
 */

class WebMaxyCDP
{

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      WebMaxyCDP_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;
	protected $cookie;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct()
	{
		if (defined('WebMaxyCDP_VERSION')) {
			$this->version = WebMaxyCDP_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'webmaxycdp';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->enqueue_script();
	}

	public function is_chained_product($cart_item_properties)
	{

		if (class_exists('WC_Chained_Products') &&  !empty($cart_item_properties['chained_item_of'])) {
			return true;
		}
		return false;
	}

	public function wbmxa_build_add_to_cart_data($added_product, $quantity, $cart)
	{
		$added_product_id = $added_product->get_id();

		return array(
			'$value' => (float) $cart->total,
			// 'AddedItemDescription' => (string) $added_product->get_description(),
			'AddedItemImageURL' => (string) wp_get_attachment_url(get_post_thumbnail_id($added_product_id)),
			'AddedItemPrice' => (float) $added_product->get_price(),
			'price' => (float) $added_product->get_sale_price(),
			'AddedItemQuantity' => (int) $quantity,
			'id' => (int) $added_product_id,
			'title' => (string) $added_product->get_name(),
			'AddedItemSKU' => (string) $added_product->get_sku(),
			'AddedItemURL' => (string) $added_product->get_permalink(),
		);
	}


	function wbmxa_track_request($customer_identify, $data, $event, $action)
	{

		$client_id =	get_option('webmaxy_client_id');
		$secret_id =	get_option('webmaxy_secret_id');
		if (empty($client_id) && empty($secret_id)) {
			return;
		}
		$code = base64_encode($client_id . ":" . $secret_id);
		if (!$code) {
			return;
		}
		$atc_data = array(
			'token' => $code,
			'event_type' => $event,
			'customer_properties' => $customer_identify,
			'properties' => $data
		);
		$base64_encoded = base64_encode(json_encode($atc_data));
		$url = "https://performanceapi.webmaxy.co/api/woocommerce/$action?data=" . $base64_encoded . '&admin=' . is_admin();
		$response = wp_remote_get($url);
		if ($event == 'SESSION' || $event == 'LOGIN'  || $event == 'PLACE_ORDER' || $event == 'USER_REGISTER') {
			$response = wp_remote_retrieve_body($response);
			$response = json_decode($response);
			if (!empty($response)) {
				if (isset($response->us)) {
					setcookie('__wbmxa_ckid', $response->ck, time() + 31556926, "/");
					unset($_SESSION['__wbmxa_usid']);
					$_SESSION['__wbmxa_usid'] = sanitize_text_field($response->us);
				}
			}
			return true;
		}
		return true;
	}

	function customer_list($request)
	{
		$code = $request['code'];
		$code = base64_decode($code);
		$arr = explode(':', $code);
		if (count($arr) < 2) {
			return false;
		}
		$client_id = $arr[0];
		$secret_id = $arr[1];
		if ($client_id != get_option('webmaxy_client_id') || $secret_id != get_option('webmaxy_secret_id')) {
			return false;
		}
		$DBRecord = array();
		$args = array(
			'paged'   => $request['page'],
			'number' => 1000
		);
		$users = get_users($args);
		$i = 0;
		foreach ($users as $user) {
			$DBRecord[$i]['WPId'] = $user->ID;
			$DBRecord[$i]['first_name'] = $user->first_name;
			$DBRecord[$i]['last_name']  = $user->last_name;
			$DBRecord[$i]['email'] = $user->user_email;
			$i++;
		}
		$data = array(
			"count" => count_users(),
			"users" => $DBRecord
		);
		$response = new WP_REST_Response($data);
		$response->set_status(200);
		return $response;
	}

	private function enqueue_script()
	{
		add_action('wp_head', array($this, 'my_getcookie'));
		add_action('template_redirect', array($this, 'pageLoaded'));
		add_action('wp_login', array($this, 'wmxA_login_event'), 10, 2);
		add_action('user_register', array($this, 'user_register'), 10, 2);
		add_action('wp_logout', array($this, 'wmxA_logout_event'), 10, 1);
		add_action('woocommerce_add_to_cart', array($this, 'wmxA_added_to_cart_event'), 25, 3);

		add_action('woocommerce_checkout_order_processed', array($this, 'wmxA_checkout_order_event'), 10, 1);
		add_action('woocommerce_order_status_cancelled', array($this, 'wmxA_cancel_order_event'),  21, 1);
		// add_action('woocommerce_order_status_changed', 'wmxA_order_status_event', 10, 1);
		add_action('woocommerce_order_status_changed', array($this, 'wmxA_order_status_event'), 10, 1);
		add_action('woocommerce_update_product', array($this, 'wmxA_price_change_event'), 10, 1);
		add_action('updated_post_meta', array($this, 'mp_sync_on_product_save'), 10, 4);
		add_action('wp_footer', array($this, 'wpb_hook_javascript_footer'), 10, 1);

		add_action('rest_api_init', function () {
			register_rest_route('api/v1', 'users', array(
				'methods'  => 'GET',
				'callback' =>  array($this, 'customer_list'),
			));

			register_rest_route('api/v1', 'orders', array(
				'methods'  => 'GET',
				'callback' =>  array($this, 'getAllOrders'),
			));
			register_rest_route('api/v1', 'categories', array(
				'methods'  => 'GET',
				'callback' =>  array($this, 'getALLCategory'),
			));
			register_rest_route('api/v1', 'order-details', array(
				'methods'  => 'GET',
				'callback' =>  array($this, 'getOrderDetails'),
			));

			register_rest_route('api/v1', 'login-status', array(
				'methods'  => 'GET',
				'callback' =>  array($this, 'checkUserStatus'),
			));
		});
	}

	public function user_register($user_id, $user_data)
	{

		$data = array(
			"ck" => sanitize_text_field((isset($_COOKIE['__wbmxa_ckid']) ? $_COOKIE['__wbmxa_ckid'] : "")),
			"us" => sanitize_text_field((isset($_SESSION['__wbmxa_usid']) ? $_SESSION['__wbmxa_usid'] : "")),
			"email" => $user_data['user_email'],
		);

		$this->wbmxa_track_request(1, $data, "USER_REGISTER", 'actions');
	}

	public function checkUserStatus()
	{
		$isUserLogged = false;
		if (isset($_SESSION['__wbmxa_loginid'])) {
			$isUserLogged = true;
		}
		$data = [
			'loggedin' => $isUserLogged,
		];
		wp_send_json_success($data);
	}

	public function getALLCategory($request)
	{
		$code = $request['code'];
		$code = base64_decode($code);
		$arr = explode(':', $code);
		if (count($arr) < 2) {
			return false;
		}
		$client_id = $arr[0];
		$secret_id = $arr[1];
		if ($client_id != get_option('webmaxy_client_id') || $secret_id != get_option('webmaxy_secret_id')) {
			return array(get_option('webmaxy_client_id'), $client_id, get_option('webmaxy_secret_id'), $secret_id);
		}
		$categories = [];
		$args = array(
			'hierarchical' => 1,
			'show_option_none' => '',
			'hide_empty' => 0,
			'taxonomy' => 'product_cat',
			"orderby" => 'parent',
			'order' => "ASC"
		);
		$categories = get_categories($args);
		foreach ($categories as $key => $sc) {
			$sc->link = get_term_link($sc->slug, $sc->taxonomy);
			$arr =	explode("/", $sc->link);
			$cat_name = "";
			if (count($arr) > 0) {
				$is_p_cate_pass = false;
				foreach ($arr as $key => $value) {
					if ($is_p_cate_pass) {
						if (!empty($value)) {
							if ($cat_name == "") {
								$cat_name = $value;
							} else {
								$cat_name .= " > " . $value;
							}
						}
					}
					if ($value == "product-category") {
						$is_p_cate_pass = true;
					}
				}
			}
			$sc->cat_name = $cat_name;
		}
		return $categories;
	}


	public  function wmxA_order_status_event($order_id)
	{
		$is_admin = is_admin();
		if ($is_admin) {
			$order = wc_get_order($order_id);
			if (empty($order)) {
				return;
			}
			$items = $order->get_items();
			$dataset = [];
			foreach ($items as $key => $item) {
				$product = $item->get_product();
				if ($product) {
					$obj = array(
						'AddedItemImageURL' => (string) wp_get_attachment_url(get_post_thumbnail_id($product->get_id())),
						'AddedItemPrice' => (float) $product->get_price(),
						'AddedItemQuantity' => $item->get_quantity(),
						'subtotal' => $item->get_subtotal(),
						'total' => $item->get_total(),
						'id' => (int)$product->get_id(),
						'title' => (string) $product->get_name(),
						'AddedItemSKU' => (string) $product->get_sku(),
						'AddedItemURL' => (string) $product->get_permalink()
					);
					$dataset[] = $obj;
				}
			}
			$data['ck'] = sanitize_text_field($_COOKIE['__wbmxa_ckid']);
			$data['us'] = sanitize_text_field($_SESSION['__wbmxa_usid']);
			$data['order_id'] = $order_id;
			$data['items'] = $dataset;
			$data['status'] = $order->get_status();
			$data['currency'] = $order->get_currency();
			$data['total'] = $order->get_total();
			$this->wbmxa_track_request(1, $data, 'ORDER_STATUS_CHANGED', 'actions');
		}
	}


	public function getAllOrders($request)
	{

		$code = $request['code'];
		$code = base64_decode($code);
		$arr = explode(':', $code);
		if (count($arr) < 2) {
			return false;
		}
		$client_id = $arr[0];
		$secret_id = $arr[1];
		if ($client_id != get_option('webmaxy_client_id') || $secret_id != get_option('webmaxy_secret_id')) {
			return array(get_option('webmaxy_client_id'), $client_id, get_option('webmaxy_secret_id'), $secret_id);
		}
		$DBRecord = array();
		$args = array(
			'paged' => $request['page'],
			'numberposts' => 1000
		);
		$orders = wc_get_orders($args);
		$i = 0;
		foreach ($orders as $order) {
			$items = [];
			$order = wc_get_order($order->id);
			foreach ($order->get_items() as  $item_key => $item_values) {
				$product = $item_values->get_product();
				if ($product) {
					$product_data = $product->get_data();
					$obj = array(
						'AddedItemImageURL' => (string) wp_get_attachment_url(get_post_thumbnail_id($product->get_id())),
						'AddedItemPrice' => (float) $product->get_price(),
						'AddedItemQuantity' => $item_values->get_quantity(),
						'subtotal' => $item_values->get_subtotal(),
						'total' => $item_values->get_total(),
						'id' => (int)$product->get_id(),
						'title' => (string) $product->get_name(),
						'AddedItemSKU' => (string) $product->get_sku(),
						'AddedItemURL' => (string) $product->get_permalink(),
						'categories' => $product_data['category_ids'],
						'attributes' => $product_data['attributes']
					);
					$items[] = $obj;
				}
			}
			$DBRecord[$i]['coupons'] = $order->get_coupon_codes();
			$DBRecord[$i]['order'] = $order->get_data();
			$DBRecord[$i]['items'] = $items;
			$i++;
		}
		$statuss = ["completed", "processing", "on-hold", "pending", "cancelled", "refunded", "failed"];
		$count = 0;
		foreach ($statuss as $key => $value) {
			$count = $count + wc_orders_count($value);
		}
		$data = array("count" => $count, "orders" => $DBRecord);
		$response = new WP_REST_Response($data);
		$response->set_status(200);
		return $response;
	}



	function mp_sync_on_product_save($meta_id, $post_id, $meta_key, $meta_value)
	{
		if ($meta_key == '_edit_lock') { // we've been editing the post
			if (get_post_type($post_id) == 'product') {
				$product = wc_get_product($post_id);
				if (!empty($product)) {
					$_SESSION['__wbmxa_price'] = $product->get_sale_price();
				}
			}
		}
	}


	function wmxA_price_change_event($product_id)
	{
		$product = wc_get_product($product_id);
		if (!empty($product)) {

			if ($_SESSION['__wbmxa_price'] > $product->get_sale_price()) {
				$data = array(
					"id" => $product->get_id(),
					"price" => $product->get_sale_price(),
					"title" => $product->get_title(),
				);

				$this->wbmxa_track_request(1, $data, "PRICE_DROPPED", 'actions');
			}
		}
	}

	public function my_getcookie()
	{
		$client_id =	get_option('webmaxy_client_id');
		$secret_id =	get_option('webmaxy_secret_id');
		if (empty($client_id) && empty($secret_id)) {
			return;
		}
		$code = base64_encode($client_id . ":" . $secret_id);
		$is_admin = is_admin();

		if ($is_admin) {
			return;
		}
		$now = time();
		if (isset($_SESSION['discard_after']) && $now > $_SESSION['discard_after']) {
			unset($_SESSION['__wbmxa_usid']);
			unset($_SESSION['__wbmxa_price']);
		}
		$_SESSION['discard_after'] = $now + 900;
	}

	function wpb_hook_javascript_footer()
	{
		if (isset($_SESSION['__wbmxa_usid'])) {
			$client_id =	get_option('webmaxy_client_id');
			$secret_id =	get_option('webmaxy_secret_id');
			if (empty($client_id) && empty($secret_id)) {
				return;
			}
			$code = base64_encode($client_id . ":" . $secret_id);
			$is_admin = is_admin();

			if ($is_admin) {
				return;
			}
			$us = $_SESSION['__wbmxa_usid'];
?>
			<script>
				var ifrm = document.createElement("iframe");
				ifrm.id = "webmaxyloyaltyId"
				ifrm.setAttribute("src", "https://loyalty.webmaxy.ai/loyalty/rewards?code=<?php echo $code; ?>&us=<?php echo $us; ?>");
				ifrm.style = "z-index:9999999;position: fixed;border: none;border-radius: 20px;bottom: 20px;right: 11px;min-width: 450px; min-height: 600px;";
				document.body.appendChild(ifrm);
				document.getElementById('webmaxyloyaltyId').onload = function() {
					window.addEventListener("message", (event) => {
						if (event.origin !== "https://loyalty.webmaxy.ai")
							return;
						if (event.data === "DO_SIGN_UP_OR_LOGIN") {
							window.location.assign("my-account")
						}
						if (event.data === "DO_REFRESH_WEBMAXY") {
							var iframe = document.getElementById('webmaxyloyaltyId');
							iframe.src = iframe.src;
						}
						if (event.data === "CLOSE_LOYALTY_PANEL") {
							var iframe = document.getElementById('webmaxyloyaltyId');
							iframe.style = "z-index:9999999;position: fixed;border: none;border-radius: 20px;bottom: 20px;right: 11px;min-width: 100px; min-height: 100px;display:block;"
						}
						if (event.data === "OPEN_LOYALTY_PANEL") {
							var iframe = document.getElementById('webmaxyloyaltyId');
							iframe.style = "z-index:9999999;position: fixed;border: none;border-radius: 20px;bottom: 20px;right: 11px;min-width: 450px; min-height: 600px;display:block;";
						}


					}, false);
				};
			</script>
<?php
		}
	}

	function get_client_ip()
	{
		$ipaddress = '';
		if (getenv('HTTP_CLIENT_IP'))
			$ipaddress = getenv('HTTP_CLIENT_IP');
		else if (getenv('HTTP_X_FORWARDED_FOR'))
			$ipaddress = getenv('HTTP_X_FORWARDED_FOR');
		else if (getenv('HTTP_X_FORWARDED'))
			$ipaddress = getenv('HTTP_X_FORWARDED');
		else if (getenv('HTTP_FORWARDED_FOR'))
			$ipaddress = getenv('HTTP_FORWARDED_FOR');
		else if (getenv('HTTP_FORWARDED'))
			$ipaddress = getenv('HTTP_FORWARDED');
		else if (getenv('REMOTE_ADDR'))
			$ipaddress = getenv('REMOTE_ADDR');
		else
			$ipaddress = 'UNKNOWN';
		return $ipaddress;
	}
	// referral	device	landing_page
	public	function pageLoaded()
	{

		$is_admin = is_admin();

		if ($is_admin) {
			return false;
		}
		if (!session_id()) {
			session_start();
		}
		$device = "DESKTOP";
		if (wp_is_mobile()) {
			$device = "MOBILE";
		}
		if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
			$url = "https://";
		} else {
			$url = "http://";
		}
		$url .= sanitize_text_field($_SERVER['HTTP_HOST']);
		$url .= sanitize_text_field($_SERVER['REQUEST_URI']);



		if (is_wc_endpoint_url('order-received') && !empty($_GET['key'])) {
			$order_id = wc_get_order_id_by_order_key($_GET['key']);
			$order = wc_get_order($order_id);
			// echo ("<script>setTimeout(function(){wmxattr.conversion(".$order->get_total().",'".$order->get_currency()."');},1000);</script>");
		}
		$referra_url = sanitize_text_field(isset($_GET['referer']) ? $_GET['referer'] : ($_SERVER['HTTP_REFERER'] != null ? $_SERVER['HTTP_REFERER'] : null));
		$reff = $this->getDomain($referra_url);
		$utm_medium = sanitize_text_field((isset($_GET['utm_medium']) ? $_GET['utm_medium'] : ""));
		$utm_source = sanitize_text_field((isset($_GET['utm_source']) ? $_GET['utm_source'] : ""));
		$data = array(
			"ck" => sanitize_text_field((isset($_COOKIE['__wbmxa_ckid']) ? $_COOKIE['__wbmxa_ckid'] : "")),
			"us" => sanitize_text_field((isset($_SESSION['__wbmxa_usid']) ? $_SESSION['__wbmxa_usid'] : "")),
			"referral" => $reff,
			"utm_source" => $utm_source,
			"utm_medium" => $utm_medium,
			"referra_url" => $referra_url,
			"device" => $device,
			"landing_page" => $url,
			"ip" => $this->get_client_ip()
		);
		$this->wbmxa_track_request(1, $data, "SESSION", 'session');


		if (
			!is_singular() &&
			!is_page() &&
			!is_single() &&
			!is_archive() &&
			!is_home() &&
			!is_front_page()
		) {
			return false;
		}

		if (isset($_COOKIE['__wbmxa_ckid']) && isset($_SESSION['__wbmxa_usid'])) {
			if (is_checkout()) {
				if (count(WC()->cart->get_cart()) > 0) {
					$dataset = [];
					foreach (WC()->cart->get_cart() as $cart_item) {
						$product = sanitize_text_field($cart_item['data']);
						$quantity = sanitize_text_field($cart_item['quantity']);
						if (!empty($product)) {
							$dataset[] = $this->wbmxa_build_add_to_cart_data($product, $quantity, WC()->cart);
						}
					}
					$data = array(
						"ck" => sanitize_text_field((isset($_COOKIE['__wbmxa_ckid']) ? $_COOKIE['__wbmxa_ckid'] : "")),
						"us" => sanitize_text_field((isset($_SESSION['__wbmxa_usid']) ? $_SESSION['__wbmxa_usid'] : "")),
						"data" => $dataset
					);
					$this->wbmxa_track_request(1, $data, "REACHED_CHECKOUT", 'actions');
				}
			} else if (is_product()) {
				$data = array(
					"ck" => sanitize_text_field((isset($_COOKIE['__wbmxa_ckid']) ? $_COOKIE['__wbmxa_ckid'] : "")),
					"us" => sanitize_text_field((isset($_SESSION['__wbmxa_usid']) ? $_SESSION['__wbmxa_usid'] : "")),
					"url" => $url,
					"is_product" => 1,
					"id" => wc_get_product()->get_id(),
					"title" => wc_get_product()->get_title(),
					"price" => wc_get_product()->get_sale_price(),
				);
				$this->wbmxa_track_request(1, $data, "VIEW", 'actions');
			} else {
				$data = array(
					"ck" => sanitize_text_field((isset($_COOKIE['__wbmxa_ckid']) ? $_COOKIE['__wbmxa_ckid'] : "")),
					"us" => sanitize_text_field((isset($_SESSION['__wbmxa_usid']) ? $_SESSION['__wbmxa_usid'] : "")),
					"url" => $url,
					"is_product" => 0
				);
				$this->wbmxa_track_request(1, $data, "VIEW", 'actions');
			}

			return false;
		}
	}

	private  function getHostName($url)
	{
		$pieces = parse_url($url);
		$domain = isset($pieces['host']) ? $pieces['host'] : '';
		if (preg_match('/(?P<domain>[a-z0-9][a-z0-9\-]{1,63}\.[a-z\.]{2,6})$/i', $domain, $regs)) {
			$dos = explode('.', $regs['domain']);
			return strtoupper($dos[0]);
		} else {
			return "DIRECT";
		}
	}
	private function getDomain($path)
	{
		if ($path == null) {
			return 'DIRECT';
		}
		return $this->getHostName($path);
	}

	public function wmxA_login_event($user_login, $user)
	{
		$data = array(
			"email" => $user->user_email,
			"name" => $user->user_login,
			"ck" => sanitize_text_field((!isset($_COOKIE['__wbmxa_ckid']) ? "" : $_COOKIE['__wbmxa_ckid'])),
			"us" => sanitize_text_field((!isset($_SESSION['__wbmxa_usid']) ? "" : $_SESSION['__wbmxa_usid'])),
		);
		$this->wbmxa_track_request(1, $data, "LOGIN", 'login');
	}

	// REACHED_CHECKOUT
	public static function wmxA_logout_event($user_logout_id)
	{
		// your code
		unset($_SESSION['__wbmxa_loginid']);
	}

	public  function wmxA_added_to_cart_event($cart_item_key, $product_id, $quantity)
	{

		if (isset($_COOKIE['__wbmxa_ckid']) && isset($_SESSION['__wbmxa_usid'])) {
			$added_product = wc_get_product($product_id);
			if (!$added_product instanceof WC_Product) {
				return;
			}
			$data = $this->wbmxa_build_add_to_cart_data($added_product, $quantity, WC()->cart);
			$data['ck'] = sanitize_text_field($_COOKIE['__wbmxa_ckid']);
			$data['us'] = sanitize_text_field($_SESSION['__wbmxa_usid']);
			$this->wbmxa_track_request(1, $data, 'ATC', 'actions');
		}
	}

	public  function wmxA_checkout_order_event($order_id)
	{

		if (isset($_COOKIE['__wbmxa_ckid']) && isset($_SESSION['__wbmxa_usid'])) {
			$order = wc_get_order($order_id);
			if (empty($order)) {
				return;
			}

			// $order = new WC_Order( $order_id );
			$items = $order->get_items();
			$dataset = [];
			foreach ($items as $key => $item) {
				$product = $item->get_product();
				if ($product) {
					$product_data = $product->get_data();
					$obj = array(
						'AddedItemImageURL' => (string) wp_get_attachment_url(get_post_thumbnail_id($product->get_id())),
						'AddedItemPrice' => (float) $product->get_price(),
						'AddedItemQuantity' => $item->get_quantity(),
						'subtotal' => $item->get_subtotal(),
						'total' => $item->get_total(),
						'id' => (int)$product->get_id(),
						'title' => (string) $product->get_name(),
						'AddedItemSKU' => (string) $product->get_sku(),
						'AddedItemURL' => (string) $product->get_permalink(),
						'categories' => $product_data['category_ids'],
						'attributes' => $product_data['attributes']
					);
					$dataset[] = $obj;
				}
			}
			$data['ck'] = sanitize_text_field($_COOKIE['__wbmxa_ckid']);
			$data['us'] = sanitize_text_field($_SESSION['__wbmxa_usid']);
			$data['order_id'] = $order_id;
			$data['items'] = $dataset;
			$data['currency'] = $order->get_currency();
			$data['total'] = $order->get_total();
			$order_data = $order->get_data();
			$data['user'] = $order_data['billing'];
			$data['coupons'] = $order->get_coupon_codes();
			$this->wbmxa_track_request(1, $data, 'PLACE_ORDER', 'actions');

			// sleep(2);
		}
	}


	public  function wmxA_cancel_order_event($order_id)
	{

		if (isset($_COOKIE['__wbmxa_ckid']) && isset($_SESSION['__wbmxa_usid'])) {
			$order = wc_get_order($order_id);
			if (empty($order)) {
				return;
			}

			// $order = new WC_Order( $order_id );
			$items = $order->get_items();
			$dataset = [];
			foreach ($items as $key => $item) {
				$product = $item->get_product();
				if ($product) {
					$obj = array(
						'AddedItemImageURL' => (string) wp_get_attachment_url(get_post_thumbnail_id($product->get_id())),
						'AddedItemPrice' => (float) $product->get_price(),
						'AddedItemQuantity' => $item->get_quantity(),
						'subtotal' => $item->get_subtotal(),
						'total' => $item->get_total(),
						'id' => (int)$product->get_id(),
						'title' => (string) $product->get_name(),
						'AddedItemSKU' => (string) $product->get_sku(),
						'AddedItemURL' => (string) $product->get_permalink()
					);
					$dataset[] = $obj;
				}
			}
			$data['ck'] = sanitize_text_field($_COOKIE['__wbmxa_ckid']);
			$data['us'] = sanitize_text_field($_SESSION['__wbmxa_usid']);
			$data['order_id'] = $order_id;
			$data['items'] = $dataset;
			$data['currency'] = $order->get_currency();
			$data['total'] = $order->get_total();
			$this->wbmxa_track_request(1, $data, 'CANCEL_ORDER', 'actions');
		}
	}





	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - WebMaxyCDP_Loader. Orchestrates the hooks of the plugin.
	 * - WebMaxyCDP_i18n. Defines internationalization functionality.
	 * - WebMaxyCDP_Admin. Defines all hooks for the admin area.
	 * - WebMaxyCDP_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies()
	{

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-webmaxycdp-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-webmaxycdp-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-webmaxycdp-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path(dirname(__FILE__)) . 'public/class-webmaxycdp-public.php';

		$this->loader = new WebMaxyCDP_Loader();
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the WebMaxyCDP_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale()
	{

		$plugin_i18n = new WebMaxyCDP_i18n();

		$this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks()
	{

		$plugin_admin = new WebMaxyCDP_Admin($this->get_plugin_name(), $this->get_version());

		// $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		// $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks()
	{

		$plugin_public = new WebMaxyCDP_Public($this->get_plugin_name(), $this->get_version());

		// $this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		// $this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */

	public function run()
	{
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */

	public function get_plugin_name()
	{
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    WebMaxyCDP_Loader    Orchestrates the hooks of the plugin.
	 */

	public function get_loader()
	{
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */

	public function get_version()
	{
		return $this->version;
	}
}
