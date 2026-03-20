<?php

/**
 * Plugin Name: Auto Finance Online Custom Calculator
 * Plugin URI:  https://digitallydisruptive.co.uk/
 * Description: Custom API-driven finance calculator replicating the AFO embed with customizable parameters.
 * Version:     1.0.0
 * Author:      Digitally Disruptive - Donald Raymundo
 * Author URI:  https://digitallydisruptive.co.uk/
 * Text Domain: afo-calculator
 */

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

/**
 * Core class for the Auto Finance Calculator Plugin.
 * Handles admin settings, shortcode registration, and asset enqueueing.
 */
class AFO_Calculator
{

	/**
	 * Initializes the plugin by registering core hooks.
	 *
	 * @return void
	 */
	public function __construct()
	{
		add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
		add_action('admin_menu', [$this, 'add_settings_page']);
		add_action('admin_init', [$this, 'register_settings']);
		add_shortcode('finance_calculator', [$this, 'render_shortcode']);
	}

	/**
	 * Registers the options page under the Settings menu.
	 *
	 * @return void
	 */
	public function add_settings_page()
	{
		add_options_page(
			'Finance Calculator Settings',
			'Finance Calculator',
			'manage_options',
			'afo-calculator',
			[$this, 'render_settings_page']
		);
	}

	/**
	 * Registers plugin settings securely utilizing the Settings API.
	 *
	 * @return void
	 */
	public function register_settings()
	{
		register_setting('afo_general_settings', 'afo_api_key');
		register_setting('afo_general_settings', 'afo_api_url');
		register_setting('afo_style_settings', 'afo_primary_color');
		register_setting('afo_style_settings', 'afo_bg_color');
	}

	/**
	 * Renders the admin settings page with a logically partitioned tabbed interface.
	 *
	 * @return void
	 */
	public function render_settings_page()
	{
		$active_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'general';
?>
		<div class="wrap">
			<h1>Finance Calculator Settings</h1>
			<h2 class="nav-tab-wrapper">
				<a href="?page=afo-calculator&tab=general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">General Settings</a>
				<a href="?page=afo-calculator&tab=styles" class="nav-tab <?php echo $active_tab === 'styles' ? 'nav-tab-active' : ''; ?>">Style Settings</a>
			</h2>
			<form method="post" action="options.php">
				<?php
				if ($active_tab === 'general') {
					settings_fields('afo_general_settings');
					do_settings_sections('afo_general_settings');
				?>
					<table class="form-table">
						<tr>
							<th scope="row">API Key</th>
							<td><input type="text" name="afo_api_key" value="<?php echo esc_attr(get_option('afo_api_key', '091ecf3e-ad04-47d9-94b1-043539780f16')); ?>" class="regular-text" /></td>
						</tr>
						<tr>
							<th scope="row">API Endpoint URL</th>
							<td>
								<input type="url" name="afo_api_url" value="<?php echo esc_attr(get_option('afo_api_url')); ?>" class="regular-text" />
								<p class="description">Input the specific POST/GET endpoint URL defined in the Gist documentation.</p>
							</td>
						</tr>
					</table>
				<?php
				} else {
					settings_fields('afo_style_settings');
					do_settings_sections('afo_style_settings');
				?>
					<table class="form-table">
						<tr>
							<th scope="row">Primary Color (Sliders & Buttons)</th>
							<td><input type="color" name="afo_primary_color" value="<?php echo esc_attr(get_option('afo_primary_color', '#f39c12')); ?>" /></td>
						</tr>
						<tr>
							<th scope="row">Panel Background Color</th>
							<td><input type="color" name="afo_bg_color" value="<?php echo esc_attr(get_option('afo_bg_color', '#f8f9fa')); ?>" /></td>
						</tr>
					</table>
				<?php
				}
				submit_button();
				?>
			</form>
		</div>
	<?php
	}

	/**
	 * Enqueues frontend scripts and stylesheets, passing dynamic PHP variables to JS.
	 *
	 * @return void
	 */
	public function enqueue_scripts()
	{
		wp_enqueue_style('afo-styles', plugin_dir_url(__FILE__) . 'assets/css/style.css', [], '1.0.0');
		wp_enqueue_script('afo-script', plugin_dir_url(__FILE__) . 'assets/js/script.js', [], '1.0.0', true);

		// Attempt standard meta key, fallback to WooCommerce default
		$raw_price = get_post_meta(get_the_ID(), 'price', true);
		if (empty($raw_price)) {
			$raw_price = get_post_meta(get_the_ID(), '_price', true);
		}

		// Sanitize price strictly to float format
		$clean_price = preg_replace('/[^0-9.]/', '', $raw_price);
		$price = ! empty($clean_price) ? floatval($clean_price) : 10000;

		// Extract settings with robust fallbacks to ensure JS payload integrity
		$api_key = get_option('afo_api_key');
		$api_url = get_option('afo_api_url');

		wp_localize_script('afo-script', 'afoConfig', [
			'apiKey'  => $api_key,
			'apiUrl'  => $api_url,
			'price'   => $price,
			'nonce'   => wp_create_nonce('afo_calc_nonce')
		]);

		// Inject dynamic CSS variables based on settings.
		$primary_color = get_option('afo_primary_color', '#f39c12');
		$bg_color      = get_option('afo_bg_color', '#f8f9fa');
		$custom_css    = "
			:root {
				--afo-primary: {$primary_color};
				--afo-bg: {$bg_color};
			}
		";
		wp_add_inline_style('afo-styles', $custom_css);
	}

	/**
	 * Renders the shortcode output and injects configuration data via HTML5 data attributes.
	 * Bypassing wp_localize_script prevents race conditions in deferred JS environments.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output for the calculator.
	 */
	public function render_shortcode($atts)
	{
		$post_id = get_the_ID();

		// Attempt standard meta key, fallback to WooCommerce default
		$raw_price = get_post_meta($post_id, 'price', true);
		if (empty($raw_price)) {
			$raw_price = get_post_meta($post_id, '_price', true);
		}

		// Sanitize price strictly to float format
		$clean_price = preg_replace('/[^0-9.]/', '', $raw_price);
		$price = ! empty($clean_price) ? floatval($clean_price) : 10000;

		// Extract settings with robust fallbacks
		$api_key = get_option('afo_api_key');
		$api_key = ! empty($api_key) ? $api_key : '091ecf3e-ad04-47d9-94b1-043539780f16';

		$api_url = get_option('afo_api_url');
		$api_url = ! empty($api_url) ? $api_url : 'https://www.autofinanceonline.co.uk/wp-json/finance/v1/calculate';

		ob_start();
	?>
		<div class="afo-calculator-container"
			data-api-key="<?php echo esc_attr($api_key); ?>"
			data-api-url="<?php echo esc_url($api_url); ?>"
			data-price="<?php echo esc_attr($price); ?>">

			<div class="afo-controls">
				<div class="afo-price-header">
					<h3>Vehicle price: <span id="afo-display-price">£<?php echo number_format($price, 2); ?></span></h3>
					<p>With a deposit of <span id="afo-display-deposit">£0.00</span>, balance to finance: <span id="afo-display-borrow">£<?php echo number_format($price, 2); ?></span></p>
				</div>

				<div class="afo-slider-group">
					<label for="afo-deposit">A deposit of</label>
					<input type="range" id="afo-deposit" min="0" max="<?php echo esc_attr($price); ?>" value="0" step="100">
				</div>

				<div class="afo-slider-group">
					<label for="afo-borrow">Amount to borrow</label>
					<input type="range" id="afo-borrow" min="0" max="<?php echo esc_attr($price); ?>" value="<?php echo esc_attr($price); ?>" step="100">
				</div>
				<div class="afo-slider-group">
					<label for="afo-term">Pay back over (Years)</label>
					<input type="range" id="afo-term" min="2" max="15" value="5" step="0.5">
					<span id="afo-display-term">5 years</span>
				</div>
			</div>

			<div class="afo-results">
				<div class="afo-result-row">
					<span>Best available rate</span>
					<strong id="afo-res-rate">--% APR</strong>
				</div>
				<div class="afo-result-row">
					<span>Total cost of credit</span>
					<strong id="afo-res-credit">£--</strong>
				</div>
				<div class="afo-result-row">
					<span>Total amount repayable</span>
					<strong id="afo-res-total">£--</strong>
				</div>
				<div class="afo-result-row afo-highlight">
					<span><span id="afo-res-months">60</span> monthly payments of</span>
					<strong id="afo-res-monthly">£--</strong>
				</div>
				<button class="afo-btn" id="afo-quote-btn">Get a quote</button>
				<p class="afo-disclaimer">Representative Example: Subject to status.</p>
			</div>
		</div>
<?php
		return ob_get_clean();
	}
}

new AFO_Calculator();
