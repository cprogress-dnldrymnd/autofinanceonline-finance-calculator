<?php

/**
 * Plugin Name: Auto Finance Online Custom Calculator
 * Plugin URI:  https://digitallydisruptive.co.uk/
 * Description: Custom API-driven finance calculator replicating the AFO embed with customizable parameters.
 * Version:     1.1.1
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
	 * Includes descriptive comments for architecture reference.
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
	 * Includes secondary color parameters for the modern card layout.
	 *
	 * @return void
	 */
	public function register_settings()
	{
		register_setting('afo_general_settings', 'afo_api_key');
		register_setting('afo_general_settings', 'afo_api_url');
		register_setting('afo_style_settings', 'afo_primary_color');
		register_setting('afo_style_settings', 'afo_secondary_color');
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
								<p class="description">Input the specific POST/GET endpoint URL defined in the API documentation.</p>
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
							<th scope="row">Primary Color (Right Panel Background)</th>
							<td>
                                <input type="color" name="afo_primary_color" value="<?php echo esc_attr(get_option('afo_primary_color', '#e3342f')); ?>" />
                                <p class="description">The vibrant background color for the "Your personal finance example" card (e.g. Red).</p>
                            </td>
						</tr>
						<tr>
							<th scope="row">Secondary Color (Buttons, Circles, Tooltips)</th>
							<td>
                                <input type="color" name="afo_secondary_color" value="<?php echo esc_attr(get_option('afo_secondary_color', '#2d3748')); ?>" />
                                <p class="description">The dark accent color for the monthly payment circle and slider thumbs (e.g. Dark Grey).</p>
                            </td>
						</tr>
						<tr>
							<th scope="row">Base Background Color</th>
							<td><input type="color" name="afo_bg_color" value="<?php echo esc_attr(get_option('afo_bg_color', '#e2e8f0')); ?>" /></td>
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
		$version = time(); // Dynamic cache busting during development

		wp_enqueue_style('afo-styles', plugin_dir_url(__FILE__) . 'assets/css/style.css', [], $version);
		wp_enqueue_script('afo-script', plugin_dir_url(__FILE__) . 'assets/js/script.js', [], $version, true);

		// Extract settings
		$api_key = get_option('afo_api_key', '091ecf3e-ad04-47d9-94b1-043539780f16');
		$api_url = get_option('afo_api_url', 'https://www.autofinanceonline.co.uk/wp-json/finance/v1/calculate');

		$raw_price = get_post_meta(get_the_ID(), 'price', true);
		if (empty($raw_price)) {
			$raw_price = get_post_meta(get_the_ID(), '_price', true);
		}
		$clean_price = preg_replace('/[^0-9.]/', '', $raw_price);
		$price = ! empty($clean_price) ? floatval($clean_price) : 24995;

		wp_localize_script('afo-script', 'afoConfig', [
			'apiKey'  => $api_key,
			'apiUrl'  => $api_url,
			'price'   => $price,
			'nonce'   => wp_create_nonce('afo_calc_nonce')
		]);

		$primary_color   = get_option('afo_primary_color', '#e3342f');
		$secondary_color = get_option('afo_secondary_color', '#2d3748');
		$bg_color        = get_option('afo_bg_color', '#e2e8f0');

		$custom_css = "
			:root {
				--afo-primary: {$primary_color};
				--afo-secondary: {$secondary_color};
				--afo-bg: {$bg_color};
			}
		";
		wp_add_inline_style('afo-styles', $custom_css);
	}

	/**
	 * Renders the shortcode HTML structure for the 2-column card layout.
	 * Strictly limited to display only the original data points requested.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output for the calculator.
	 */
	public function render_shortcode($atts)
	{
		$raw_price = get_post_meta(get_the_ID(), 'price', true);
		if (empty($raw_price)) {
			$raw_price = get_post_meta(get_the_ID(), '_price', true);
		}
		$clean_price = preg_replace('/[^0-9.]/', '', $raw_price);
		$price = ! empty($clean_price) ? floatval($clean_price) : 24995;

		ob_start();
	?>
		<div class="afo-calculator-wrapper">
			<div class="afo-left-col">
				<h2 class="afo-title">Finance Options</h2>
				
				<div class="afo-ui-group">
					<label>How much do you want to deposit?</label>
					<div class="afo-slider-ui">
						<button type="button" class="afo-step-btn" data-target="afo-deposit" data-dir="-1">&lt;</button>
						<div class="afo-range-container">
							<div class="afo-tooltip" id="afo-tooltip-deposit">£0</div>
							<input type="range" id="afo-deposit" min="0" max="<?php echo esc_attr($price); ?>" value="8000" step="1">
							<div class="afo-range-labels">
								<span>£0</span>
								<span>£<?php echo number_format($price, 0); ?></span>
							</div>
						</div>
						<button type="button" class="afo-step-btn" data-target="afo-deposit" data-dir="1">&gt;</button>
					</div>
				</div>

				<div class="afo-ui-group">
					<label>Over how many years do you want to repay?</label>
					<p class="afo-warning-text">If the caravan is over 7 years old the repayment period may be restricted.</p>
					<div class="afo-slider-ui">
						<button type="button" class="afo-step-btn" data-target="afo-term" data-dir="-1">&lt;</button>
						<div class="afo-range-container">
							<div class="afo-tooltip" id="afo-tooltip-term">3.5 years</div>
							<input type="range" id="afo-term" min="0.5" max="10" value="3.5" step="0.5">
							<div class="afo-range-labels">
								<span>0.5 years</span>
								<span>10 years</span>
							</div>
						</div>
						<button type="button" class="afo-step-btn" data-target="afo-term" data-dir="1">&gt;</button>
					</div>
				</div>

                <div style="display:none;">
					<input type="range" id="afo-borrow" min="0" max="<?php echo esc_attr($price); ?>" value="<?php echo esc_attr($price - 8000); ?>" step="1">
				</div>
			</div>

			<div class="afo-right-col">
				<div class="afo-card-header">Your personal finance example</div>
				
				<div class="afo-inner-card">
					<div class="afo-card-top">
						<div class="afo-monthly-circle">
							<span class="afo-circle-val" id="afo-res-monthly">£--</span>
							<span class="afo-circle-lbl">per month*</span>
						</div>
						<div class="afo-card-stats">
							<div class="afo-stat">
								<span class="afo-stat-lbl">Total amount repayable</span>
								<strong class="afo-stat-val" id="afo-res-total">£--</strong>
							</div>
							<div class="afo-stat">
								<span class="afo-stat-lbl">Best available rate</span>
								<strong class="afo-stat-val" id="afo-res-rate">--% APR</strong>
							</div>
						</div>
					</div>
					
					<div class="afo-card-mid">
						<p>Requested borrowing value: <strong id="afo-res-borrowing">£--</strong></p>
						<p>Amount based on <strong id="afo-res-plan-months">--</strong> month repayment plan.</p>
						<button class="afo-quote-btn-solid" id="afo-quote-btn">Get a quote</button>
					</div>
				</div>

				<div class="afo-rep-example">
					<div class="afo-rep-grid">
						<div><span>Vehicle Price</span> <strong id="rep-price">£--</strong></div>
						<div><span>Deposit</span> <strong id="rep-deposit">£--</strong></div>
						<div><span>Total cost of credit</span> <strong id="afo-res-credit">£--</strong></div>
					</div>
                    <p class="afo-disclaimer" style="margin-top: 1.5rem; font-size: 0.8rem; text-align: left; opacity: 0.8;">Representative Example: Subject to status.</p>
				</div>
			</div>
		</div>
<?php
		return ob_get_clean();
	}
}

new AFO_Calculator();