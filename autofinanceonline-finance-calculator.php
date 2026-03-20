<?php

/**
 * Plugin Name: Auto Finance Online Custom Calculator
 * Plugin URI:  https://digitallydisruptive.co.uk/
 * Description: Custom API-driven finance calculator with a 2-column layout and static representative example.
 * Version:     1.2.1
 * Author:      Digitally Disruptive - Donald Raymundo
 * Author URI:  https://digitallydisruptive.co.uk/
 * Text Domain: afo-calculator
 */

if (! defined('ABSPATH')) {
	exit;
}

class AFO_Calculator
{
	public function __construct()
	{
		add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
		add_action('admin_menu', [$this, 'add_settings_page']);
		add_action('admin_init', [$this, 'register_settings']);
		add_shortcode('finance_calculator', [$this, 'render_shortcode']);
	}

	public function add_settings_page()
	{
		add_options_page('Finance Calculator Settings', 'Finance Calculator', 'manage_options', 'afo-calculator', [$this, 'render_settings_page']);
	}

	public function register_settings()
	{
		register_setting('afo_general_settings', 'afo_api_key');
		register_setting('afo_general_settings', 'afo_api_url');
		register_setting('afo_text_settings', 'afo_heading_text');
		register_setting('afo_text_settings', 'afo_deposit_label');
		register_setting('afo_text_settings', 'afo_term_label');
		register_setting('afo_text_settings', 'afo_warning_text');
		register_setting('afo_text_settings', 'afo_rep_example');
		register_setting('afo_style_settings', 'afo_primary_color');
		register_setting('afo_style_settings', 'afo_secondary_color');
		register_setting('afo_style_settings', 'afo_bg_color');
		register_setting('afo_style_settings', 'afo_btn_text_color');
	}

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
							<th scope="row">API URL</th>
							<td><input type="url" name="afo_api_url" value="<?php echo esc_attr(get_option('afo_api_url', 'https://www.autofinanceonline.co.uk/wp-json/finance/v1/calculate')); ?>" class="regular-text" /></td>
						</tr>
					</table>
				<?php
				} elseif ($active_tab === 'text') {
					settings_fields('afo_text_settings');
				?>
					<table class="form-table">
						<tr>
							<th scope="row">Heading</th>
							<td><input type="text" name="afo_heading_text" value="<?php echo esc_attr(get_option('afo_heading_text', 'Finance Options')); ?>" class="regular-text" /></td>
						</tr>
						<tr>
							<th scope="row">Deposit Label</th>
							<td><input type="text" name="afo_deposit_label" value="<?php echo esc_attr(get_option('afo_deposit_label', 'How much do you want to deposit?')); ?>" class="regular-text" /></td>
						</tr>
						<tr>
							<th scope="row">Term Label</th>
							<td><input type="text" name="afo_term_label" value="<?php echo esc_attr(get_option('afo_term_label', 'Over how many years do you want to repay?')); ?>" class="regular-text" /></td>
						</tr>
						<tr>
							<th scope="row">Warning Text</th>
							<td><input type="text" name="afo_warning_text" value="<?php echo esc_attr(get_option('afo_warning_text', 'If the caravan is over 7 years old the repayment period may be restricted.')); ?>" class="large-text" /></td>
						</tr>
						<tr>
							<th scope="row">Representative Example</th>
							<td><textarea name="afo_rep_example" rows="5" class="large-text"><?php echo esc_textarea(get_option('afo_rep_example', 'Cash price £15,000, deposit £0, total amount of credit £15,000, term 7 years, 84 monthly payments of £235.18...')); ?></textarea></td>
						</tr>
					</table>
				<?php
				} else {
					settings_fields('afo_style_settings');
				?>
					<table class="form-table">
						<tr>
							<th scope="row">Primary Color</th>
							<td><input type="color" name="afo_primary_color" value="<?php echo esc_attr(get_option('afo_primary_color', '#e3342f')); ?>" /></td>
						</tr>
						<tr>
							<th scope="row">Secondary Color</th>
							<td><input type="color" name="afo_secondary_color" value="<?php echo esc_attr(get_option('afo_secondary_color', '#2d3748')); ?>" /></td>
						</tr>
						<tr>
							<th scope="row">Background Color</th>
							<td><input type="color" name="afo_bg_color" value="<?php echo esc_attr(get_option('afo_bg_color', '#e2e8f0')); ?>" /></td>
						</tr>
						<tr>
							<th scope="row">Button Text Color</th>
							<td><input type="color" name="afo_btn_text_color" value="<?php echo esc_attr(get_option('afo_btn_text_color', '#ffffff')); ?>" /></td>
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

	public function enqueue_scripts()
	{
		$version = time();
		wp_enqueue_style('afo-styles', plugin_dir_url(__FILE__) . 'assets/css/style.css', [], $version);
		wp_enqueue_script('afo-script', plugin_dir_url(__FILE__) . 'assets/js/script.js', [], $version, true);

		$raw_price = get_post_meta(get_the_ID(), 'price', true) ?: get_post_meta(get_the_ID(), '_price', true);
		$price = !empty($raw_price) ? floatval(preg_replace('/[^0-9.]/', '', $raw_price)) : 24995;

		wp_localize_script('afo-script', 'afoConfig', [
			'apiKey' => get_option('afo_api_key', '091ecf3e-ad04-47d9-94b1-043539780f16'),
			'apiUrl' => get_option('afo_api_url', 'https://www.autofinanceonline.co.uk/wp-json/finance/v1/calculate'),
			'price'  => $price
		]);

		$custom_css = ":root { 
			--afo-primary: " . get_option('afo_primary_color', '#e3342f') . "; 
			--afo-secondary: " . get_option('afo_secondary_color', '#2d3748') . "; 
			--afo-bg: " . get_option('afo_bg_color', '#e2e8f0') . "; 
			--afo-btn-text: " . get_option('afo_btn_text_color', '#ffffff') . "; 
		}";
		wp_add_inline_style('afo-styles', $custom_css);
	}

	public function render_shortcode($atts)
	{
		$raw_price = get_post_meta(get_the_ID(), 'price', true) ?: get_post_meta(get_the_ID(), '_price', true);
		$price = !empty($raw_price) ? floatval(preg_replace('/[^0-9.]/', '', $raw_price)) : 24995;

		ob_start();
	?>
		<div class="afo-calculator-wrapper">
			<div class="afo-left-col">
				<h2 class="afo-title"><?php echo esc_html(get_option('afo_heading_text', 'Finance Options')); ?></h2>
				<div class="afo-ui-group">
					<label><?php echo esc_html(get_option('afo_deposit_label', 'How much do you want to deposit?')); ?></label>
					<div class="afo-slider-ui">
						<button type="button" class="afo-step-btn" data-target="afo-deposit" data-dir="-1">&lt;</button>
						<div class="afo-range-container">
							<div class="afo-tooltip" id="afo-tooltip-deposit">£0</div>
							<input type="range" id="afo-deposit" min="0" max="<?php echo esc_attr($price); ?>" value="8000" step="1">
						</div>
						<button type="button" class="afo-step-btn" data-target="afo-deposit" data-dir="1">&gt;</button>
					</div>
				</div>
				<div class="afo-ui-group">
					<label><?php echo esc_html(get_option('afo_term_label', 'Over how many years do you want to repay?')); ?></label>
					<p class="afo-warning-text"><?php echo esc_html(get_option('afo_warning_text', 'If the caravan is over 7 years old the repayment period may be restricted.')); ?></p>
					<div class="afo-slider-ui">
						<button type="button" class="afo-step-btn" data-target="afo-term" data-dir="-1">&lt;</button>
						<div class="afo-range-container">
							<div class="afo-tooltip" id="afo-tooltip-term">5 years</div>
							<input type="range" id="afo-term" min="0.5" max="15" value="5" step="0.5">
						</div>
						<button type="button" class="afo-step-btn" data-target="afo-term" data-dir="1">&gt;</button>
					</div>
				</div>
				<input type="hidden" id="afo-borrow" value="<?php echo esc_attr($price - 8000); ?>">
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
								<span class="afo-stat-lbl">Total cost of credit</span>
								<strong class="afo-stat-val" id="afo-res-credit">£--</strong>
							</div>
							<div class="afo-stat">
								<span class="afo-stat-lbl">Total amount repayable</span>
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
						<button class="afo-quote-btn-solid" id="afo-quote-btn">Get a quote</button>
					</div>
				</div>
				<div class="afo-rep-example">
					<p><strong>Representative example</strong></p>
					<p><?php echo wp_kses_post(get_option('afo_rep_example')); ?></p>
				</div>
			</div>
		</div>
<?php
		return ob_get_clean();
	}
}
new AFO_Calculator();
