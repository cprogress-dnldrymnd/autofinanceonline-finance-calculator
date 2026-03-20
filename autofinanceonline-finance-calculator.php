<?php

/**
 * Plugin Name: Auto Finance Online Custom Calculator
 * Plugin URI:  https://digitallydisruptive.co.uk/
 * Description: Custom API-driven finance calculator replicating the AFO embed with customizable parameters.
 * Version:     1.2.0
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
	 * Includes new text mapping and button color fields.
	 *
	 * @return void
	 */
	public function register_settings()
	{
		// General Settings
		register_setting('afo_general_settings', 'afo_api_key');
		register_setting('afo_general_settings', 'afo_api_url');
		
		// Text Settings (New)
		register_setting('afo_text_settings', 'afo_heading_text');
		register_setting('afo_text_settings', 'afo_deposit_label');
		register_setting('afo_text_settings', 'afo_term_label');
		register_setting('afo_text_settings', 'afo_warning_text');
		register_setting('afo_text_settings', 'afo_rep_example');

		// Style Settings
		register_setting('afo_style_settings', 'afo_primary_color');
		register_setting('afo_style_settings', 'afo_secondary_color');
		register_setting('afo_style_settings', 'afo_bg_color');
		register_setting('afo_style_settings', 'afo_btn_text_color');
	}

	/**
	 * Renders the admin settings page with a tabbed interface.
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
				<a href="?page=afo-calculator&tab=text" class="nav-tab <?php echo $active_tab === 'text' ? 'nav-tab-active' : ''; ?>">Text Settings</a>
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
							<td><input type="url" name="afo_api_url" value="<?php echo esc_attr(get_option('afo_api_url', 'https://www.autofinanceonline.co.uk/wp-json/finance/v1/calculate')); ?>" class="regular-text" /></td>
						</tr>
					</table>
				<?php
				} elseif ($active_tab === 'text') {
					settings_fields('afo_text_settings');
					do_settings_sections('afo_text_settings');
				?>
					<table class="form-table">
						<tr>
							<th scope="row">Main Heading</th>
							<td><input type="text" name="afo_heading_text" value="<?php echo esc_attr(get_option('afo_heading_text', 'Finance Options')); ?>" class="regular-text" /></td>
						</tr>
						<tr>
							<th scope="row">Deposit Slider Label</th>
							<td><input type="text" name="afo_deposit_label" value="<?php echo esc_attr(get_option('afo_deposit_label', 'How much do you want to deposit?')); ?>" class="regular-text" /></td>
						</tr>
						<tr>
							<th scope="row">Term Slider Label</th>
							<td><input type="text" name="afo_term_label" value="<?php echo esc_attr(get_option('afo_term_label', 'Over how many years do you want to repay?')); ?>" class="regular-text" /></td>
						</tr>
						<tr>
							<th scope="row">Warning Text (Under Term)</th>
							<td><input type="text" name="afo_warning_text" value="<?php echo esc_attr(get_option('afo_warning_text', 'If the caravan is over 7 years old the repayment period may be restricted.')); ?>" class="large-text" /></td>
						</tr>
						<tr>
							<th scope="row">Representative Example (Static)</th>
							<td>
								<textarea name="afo_rep_example" rows="4" class="large-text"><?php echo esc_textarea(get_option('afo_rep_example', 'Cash price £15,000, deposit £0, total amount of credit £15,000, term 7 years, 84 monthly payments of £235.18, on a hire purchase agreement, option to purchase fee £1, total amount payable £19,755.46, representative APR 8.5%, rate of interest 8.5% fixed. Rates may differ as they are dependent on individual circumstances. Subject to status.')); ?></textarea>
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
							<th scope="row">Primary Color (Right Panel)</th>
							<td><input type="color" name="afo_primary_color" value="<?php echo esc_attr(get_option('afo_primary_color', '#e3342f')); ?>" /></td>
						</tr>
						<tr>
							<th scope="row">Secondary Color (Circles, Tooltips)</th>
							<td><input type="color" name="afo_secondary_color" value="<?php echo esc_attr(get_option('afo_secondary_color', '#2d3748')); ?>" /></td>
						</tr>
						<tr>
							<th scope="row">Base Background Color</th>
							<td><input type="color" name="afo_bg_color" value="<?php echo esc_attr(get_option('afo_bg_color', '#e2e8f0')); ?>" /></td>
						</tr>
						<tr>
							<th scope="row">Button Text Color</th>
							<td><input type="color" name="afo_btn_text_color" value="<?php echo esc_attr(get_option('afo_btn_text_color', '#1a202c')); ?>" /></td>
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
		$btn_text        = get_option('afo_btn_text_color', '#1a202c');

		$custom_css = "
			:root {
				--afo-primary: {$primary_color};
				--afo-secondary: {$secondary_color};
				--afo-bg: {$bg_color};
				--afo-btn-text: {$btn_text};
			}
		";
		wp_add_inline_style('afo-styles', $custom_css);
	}

	/**
	 * Renders the shortcode HTML structure.
	 * Incorporates dynamic user text mapping and static Representative Example block.
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

		// Fetch text inputs
		$heading_text  = get_option('afo_heading_text', 'Finance Options');
		$deposit_label = get_option('afo_deposit_label', 'How much do you want to deposit?');
		$term_label    = get_option('afo_term_label', 'Over how many years do you want to repay?');
		$warning_text  = get_option('afo_warning_text', 'If the caravan is over 7 years old the repayment period may be restricted.');
		$rep_example   = get_option('afo_rep_example', 'Cash price £15,000, deposit £0, total amount of credit £15,000...');

		ob_start();
	?>
		<div class="afo-calculator-wrapper">
			<div class="afo-left-col">
				<h2 class="afo-title"><?php echo esc_html($heading_text); ?></h2>
				
				<div class="afo-ui-group">
					<label><?php echo esc_html($deposit_label); ?></label>
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
					<label><?php echo esc_html($term_label); ?></label>
					<?php if (!empty($warning_text)) : ?>
						<p class="afo-warning-text"><?php echo esc_html($warning_text); ?></p>
					<?php endif; ?>
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
								<span class="afo-stat-lbl">Total amount payable</span>
								<strong class="afo-stat-val" id="afo-res-total">£--</strong>
							</div>
							<div class="afo-stat">
								<span class="afo-stat-lbl">APR</span>
								<strong class="afo-stat-val" id="afo-res-rate">--%</strong>
							</div>
						</div>
					</div>
					
					<div class="afo-card-mid">
						<p>Requested borrowing value: <strong id="afo-res-borrowing">£--</strong></p>
						<p>Amount based on <strong id="afo-res-plan-months">--</strong> month repayment plan.</p>
						<button class="afo-quote-btn-solid" id="afo-quote-btn">Quote me</button>
					</div>
				</div>

				<div class="afo-rep-example">
                    <p><strong>Representative example</strong></p>
                    <p class="afo-rep-text-content"><?php echo wp_kses_post($rep_example); ?></p>
				</div>
			</div>
		</div>
<?php
		return ob_get_clean();
	}
}

new AFO_Calculator();