<?php

/**
 * Plugin Name: Auto Finance Online Custom Calculator
 * Plugin URI:  https://digitallydisruptive.co.uk/
 * Description: Custom API-driven finance calculator replicating the AFO embed with customizable parameters.
 * Version:     2.1.0
 * Author:      Digitally Disruptive - Donald Raymundo
 * Author URI:  https://digitallydisruptive.co.uk/
 * Text Domain: afo-calculator
 */

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

/**
 * Core class for the Auto Finance Calculator Plugin.
 * Handles admin settings (4 tabs), shortcode registration, and asset enqueueing.
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
		add_action('admin_menu',         [$this, 'add_settings_page']);
		add_action('admin_init',         [$this, 'register_settings']);
		add_shortcode('finance_calculator', [$this, 'render_shortcode']);
	}

	// ─── Admin ────────────────────────────────────────────────────────────────

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
	 * Registers plugin settings using the Settings API.
	 * Groups: general, style, labels, representative example.
	 *
	 * @return void
	 */
	public function register_settings()
	{
		// ── General ──────────────────────────────────────────────────────────
		register_setting('afo_general_settings', 'afo_api_key');
		register_setting('afo_general_settings', 'afo_api_url');

		// ── Styles — Left Panel ───────────────────────────────────────────────
		register_setting('afo_style_settings', 'afo_primary_color');
		register_setting('afo_style_settings', 'afo_bg_color');
		register_setting('afo_style_settings', 'afo_left_heading_color');
		register_setting('afo_style_settings', 'afo_left_label_color');
		register_setting('afo_style_settings', 'afo_left_sublabel_color');
		register_setting('afo_style_settings', 'afo_left_muted_color');
		register_setting('afo_style_settings', 'afo_slider_track_color');
		register_setting('afo_style_settings', 'afo_bubble_bg_color');
		register_setting('afo_style_settings', 'afo_bubble_text_color');
		register_setting('afo_style_settings', 'afo_arrow_bg_color');
		register_setting('afo_style_settings', 'afo_arrow_text_color');

		// ── Styles — Right Panel ──────────────────────────────────────────────
		register_setting('afo_style_settings', 'afo_right_bg_color');
		register_setting('afo_style_settings', 'afo_right_text_color');
		register_setting('afo_style_settings', 'afo_right_muted_color');
		register_setting('afo_style_settings', 'afo_circle_bg_color');
		register_setting('afo_style_settings', 'afo_btn_bg_color');
		register_setting('afo_style_settings', 'afo_btn_text_color');

		// ── Styles — Inner Card ───────────────────────────────────────────────
		register_setting('afo_style_settings', 'afo_card_bg_color');
		register_setting('afo_style_settings', 'afo_card_text_color');
		register_setting('afo_style_settings', 'afo_card_muted_color');
		register_setting('afo_style_settings', 'afo_card_circle_bg_color');

		// ── Styles — Representative Example ──────────────────────────────────
		register_setting('afo_style_settings', 'afo_rep_text_color');
		register_setting('afo_style_settings', 'afo_rep_muted_color');
		register_setting('afo_style_settings', 'afo_rep_border_color');

		// ── Labels ───────────────────────────────────────────────────────────
		register_setting('afo_label_settings', 'afo_left_heading');
		register_setting('afo_label_settings', 'afo_deposit_label');
		register_setting('afo_label_settings', 'afo_deposit_sublabel');
		register_setting('afo_label_settings', 'afo_borrow_label');
		register_setting('afo_label_settings', 'afo_borrow_sublabel');
		register_setting('afo_label_settings', 'afo_term_label');
		register_setting('afo_label_settings', 'afo_term_sublabel');
		register_setting('afo_label_settings', 'afo_right_heading');
		register_setting('afo_label_settings', 'afo_btn_text');
		register_setting('afo_label_settings', 'afo_monthly_sublabel');

		// ── Representative Example ────────────────────────────────────────────
		register_setting('afo_rep_settings', 'afo_rep_cash_price');
		register_setting('afo_rep_settings', 'afo_rep_deposit');
		register_setting('afo_rep_settings', 'afo_rep_credit');
		register_setting('afo_rep_settings', 'afo_rep_term');
		register_setting('afo_rep_settings', 'afo_rep_monthly');
		register_setting('afo_rep_settings', 'afo_rep_agreement');
		register_setting('afo_rep_settings', 'afo_rep_purchase_fee');
		register_setting('afo_rep_settings', 'afo_rep_total_payable');
		register_setting('afo_rep_settings', 'afo_rep_apr');
		register_setting('afo_rep_settings', 'afo_rep_interest');
		register_setting('afo_rep_settings', 'afo_rep_footer');
	}

	/**
	 * Renders the admin settings page with four logically partitioned tabs:
	 * General, Styles, Labels, and Representative Example.
	 *
	 * @return void
	 */
	public function render_settings_page()
	{
		$active_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'general';
		$tabs = [
			'general' => 'General Settings',
			'styles'  => 'Style Settings',
			'labels'  => 'Label Settings',
			'rep'     => 'Representative Example',
		];
?>
		<div class="wrap">
			<h1>Finance Calculator Settings</h1>
			<h2 class="nav-tab-wrapper">
				<?php foreach ($tabs as $key => $label) : ?>
					<a href="?page=afo-calculator&tab=<?php echo esc_attr($key); ?>"
						class="nav-tab <?php echo $active_tab === $key ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html($label); ?>
					</a>
				<?php endforeach; ?>
			</h2>

			<form method="post" action="options.php">
				<?php
				// ── GENERAL ───────────────────────────────────────────────────
				if ($active_tab === 'general') {
					settings_fields('afo_general_settings');
				?>
					<table class="form-table">
						<tr>
							<th scope="row">API Key</th>
							<td>
								<input type="text" name="afo_api_key"
									value="<?php echo esc_attr(get_option('afo_api_key', '091ecf3e-ad04-47d9-94b1-043539780f16')); ?>"
									class="regular-text" />
							</td>
						</tr>
						<tr>
							<th scope="row">API Endpoint URL</th>
							<td>
								<input type="url" name="afo_api_url"
									value="<?php echo esc_attr(get_option('afo_api_url', 'https://www.autofinanceonline.co.uk/wp-json/finance/v1/calculate')); ?>"
									class="regular-text" />
								<p class="description">POST/GET endpoint URL as defined in the Gist documentation.</p>
							</td>
						</tr>
					</table>

				<?php } elseif ($active_tab === 'styles') {
					settings_fields('afo_style_settings');

					// Helper: renders a colour row with an optional description.
					// Using an inline closure keeps the table markup clean.
					$colour_row = function (string $label, string $option_name, string $default, string $desc = '') {
						$value = get_option($option_name, $default);
						echo '<tr>';
						echo '<th scope="row">' . esc_html($label) . '</th>';
						echo '<td>';
						echo '<input type="color" name="' . esc_attr($option_name) . '" value="' . esc_attr($value) . '" />';
						echo '<code style="margin-left:10px;vertical-align:middle;">' . esc_html($value) . '</code>';
						if ($desc) {
							echo '<p class="description">' . esc_html($desc) . '</p>';
						}
						echo '</td>';
						echo '</tr>';
					};
				?>

					<!-- ── LEFT PANEL ─────────────────────────────────────────── -->
					<h2 style="margin-top:1.5rem;">Left Panel</h2>
					<table class="form-table">
						<?php
						$colour_row('Slider Accent / Thumb',        'afo_primary_color',      '#cc2020', 'Slider thumb, focused track fill.');
						$colour_row('Panel Background',             'afo_bg_color',            '#f0f2f5');
						$colour_row('Main Heading Text',            'afo_left_heading_color',  '#111111');
						$colour_row('Slider Label Text',           'afo_left_label_color',    '#222222');
						$colour_row('Slider Sub-label Text',       'afo_left_sublabel_color', '#888888');
						$colour_row('Secondary / Hint Text',       'afo_left_muted_color',    '#666666', 'Price header paragraph, range min/max labels.');
						$colour_row('Slider Track (unfilled)',     'afo_slider_track_color',  '#d0d3d8');
						$colour_row('Bubble Background',           'afo_bubble_bg_color',     '#1a1a1a', 'Floating value tooltip above each slider.');
						$colour_row('Bubble Text',                 'afo_bubble_text_color',   '#ffffff');
						$colour_row('Arrow Button Background',     'afo_arrow_bg_color',      '#2a2a2a');
						$colour_row('Arrow Button Icon',           'afo_arrow_text_color',    '#ffffff');
						?>
					</table>

					<!-- ── RIGHT PANEL ────────────────────────────────────────── -->
					<h2 style="margin-top:2rem;">Right Panel</h2>
					<table class="form-table">
						<?php
						$colour_row('Panel Background',            'afo_right_bg_color',      '#cc2020', 'Typically matches the accent colour but can differ.');
						$colour_row('Primary Text',                'afo_right_text_color',    '#ffffff', 'Heading, stat values, monthly amount.');
						$colour_row('Secondary / Muted Text',      'afo_right_muted_color',   '#ffcccc', 'Stat labels, sub-labels, hero info paragraph.');
						$colour_row('Monthly Circle Background',   'afo_circle_bg_color',     '#7a0000', 'The dark circle behind the monthly payment amount.');
						$colour_row('Quote Button Background',     'afo_btn_bg_color',        '#ffffff');
						$colour_row('Quote Button Text',           'afo_btn_text_color',      '#cc2020');
						?>
					</table>

					<!-- ── INNER CARD ─────────────────────────────────────────── -->
					<h2 style="margin-top:2rem;">Inner Card <small>(hero stats area)</small></h2>
					<table class="form-table">
						<?php
						$colour_row('Card Background',             'afo_card_bg_color',       '#ffffff');
						$colour_row('Card Primary Text',           'afo_card_text_color',     '#111111', 'Monthly amount, stat values.');
						$colour_row('Card Muted / Label Text',    'afo_card_muted_color',    '#555555', 'Stat labels, sub-labels, info paragraph.');
						$colour_row('Card Circle Background',     'afo_card_circle_bg_color', '#1a1a1a', 'The circle behind the monthly payment amount when using the card.');
						?>
					</table>

					<!-- ── REPRESENTATIVE EXAMPLE BOX ─────────────────────────── -->
					<h2 style="margin-top:2rem;">Representative Example</h2>
					<table class="form-table">
						<?php
						$colour_row('Row Value Text',              'afo_rep_text_color',      '#ffffff');
						$colour_row('Row Label / Footer Text',    'afo_rep_muted_color',     '#ffcccc');
						$colour_row('Row Divider / Border',       'afo_rep_border_color',    '#ffffff33', 'Supports 8-digit hex for transparency, e.g. #ffffff33.');
						?>
					</table>

				<?php } elseif ($active_tab === 'labels') {
					settings_fields('afo_label_settings');
				?>
					<h2>Left Panel</h2>
					<table class="form-table">
						<tr>
							<th scope="row">Main Heading</th>
							<td>
								<input type="text" name="afo_left_heading"
									value="<?php echo esc_attr(get_option('afo_left_heading', 'Finance Options')); ?>"
									class="regular-text" />
							</td>
						</tr>
						<tr>
							<th scope="row">Deposit Slider Label</th>
							<td>
								<input type="text" name="afo_deposit_label"
									value="<?php echo esc_attr(get_option('afo_deposit_label', 'How much do you want to deposit?')); ?>"
									class="regular-text" />
							</td>
						</tr>
						<tr>
							<th scope="row">Deposit Slider Sub-label</th>
							<td>
								<input type="text" name="afo_deposit_sublabel"
									value="<?php echo esc_attr(get_option('afo_deposit_sublabel', '')); ?>"
									class="regular-text" />
								<p class="description">Optional hint shown below the deposit label.</p>
							</td>
						</tr>
						<tr>
							<th scope="row">Borrow Amount Label</th>
							<td>
								<input type="text" name="afo_borrow_label"
									value="<?php echo esc_attr(get_option('afo_borrow_label', 'Amount to borrow')); ?>"
									class="regular-text" />
							</td>
						</tr>
						<tr>
							<th scope="row">Borrow Amount Sub-label</th>
							<td>
								<input type="text" name="afo_borrow_sublabel"
									value="<?php echo esc_attr(get_option('afo_borrow_sublabel', '')); ?>"
									class="regular-text" />
							</td>
						</tr>
						<tr>
							<th scope="row">Term Slider Label</th>
							<td>
								<input type="text" name="afo_term_label"
									value="<?php echo esc_attr(get_option('afo_term_label', 'Pay back over (Years)')); ?>"
									class="regular-text" />
							</td>
						</tr>
						<tr>
							<th scope="row">Term Slider Sub-label</th>
							<td>
								<input type="text" name="afo_term_sublabel"
									value="<?php echo esc_attr(get_option('afo_term_sublabel', '')); ?>"
									class="regular-text" />
								<p class="description">E.g. "If the vehicle is over 7 years old the repayment period may be restricted."</p>
							</td>
						</tr>
					</table>

					<h2>Right Panel</h2>
					<table class="form-table">
						<tr>
							<th scope="row">Panel Heading</th>
							<td>
								<input type="text" name="afo_right_heading"
									value="<?php echo esc_attr(get_option('afo_right_heading', 'Your personal finance example')); ?>"
									class="regular-text" />
							</td>
						</tr>
						<tr>
							<th scope="row">Monthly Payment Sub-label</th>
							<td>
								<input type="text" name="afo_monthly_sublabel"
									value="<?php echo esc_attr(get_option('afo_monthly_sublabel', 'per month*')); ?>"
									class="regular-text" />
								<p class="description">Shown below the large monthly amount in the circle.</p>
							</td>
						</tr>
						<tr>
							<th scope="row">Quote Button Text</th>
							<td>
								<input type="text" name="afo_btn_text"
									value="<?php echo esc_attr(get_option('afo_btn_text', 'Get a quote')); ?>"
									class="regular-text" />
							</td>
						</tr>
					</table>

				<?php } elseif ($active_tab === 'rep') {
					settings_fields('afo_rep_settings');
				?>
					<p class="description" style="margin-bottom:1rem;">
						These static fields populate the Representative Example table shown on the right panel.
					</p>
					<table class="form-table">
						<tr>
							<th scope="row">Cash Price</th>
							<td><input type="text" name="afo_rep_cash_price"
									value="<?php echo esc_attr(get_option('afo_rep_cash_price', '£15,000')); ?>"
									class="regular-text" /></td>
						</tr>
						<tr>
							<th scope="row">Deposit</th>
							<td><input type="text" name="afo_rep_deposit"
									value="<?php echo esc_attr(get_option('afo_rep_deposit', '£0')); ?>"
									class="regular-text" /></td>
						</tr>
						<tr>
							<th scope="row">Total Amount of Credit</th>
							<td><input type="text" name="afo_rep_credit"
									value="<?php echo esc_attr(get_option('afo_rep_credit', '£15,000')); ?>"
									class="regular-text" /></td>
						</tr>
						<tr>
							<th scope="row">Finance Term</th>
							<td><input type="text" name="afo_rep_term"
									value="<?php echo esc_attr(get_option('afo_rep_term', '7 years')); ?>"
									class="regular-text" /></td>
						</tr>
						<tr>
							<th scope="row">Monthly Payments</th>
							<td>
								<input type="text" name="afo_rep_monthly"
									value="<?php echo esc_attr(get_option('afo_rep_monthly', '84 monthly payments of £235.18')); ?>"
									class="regular-text" />
								<p class="description">E.g. "84 monthly payments of £235.18"</p>
							</td>
						</tr>
						<tr>
							<th scope="row">Agreement Type</th>
							<td><input type="text" name="afo_rep_agreement"
									value="<?php echo esc_attr(get_option('afo_rep_agreement', 'Hire purchase agreement')); ?>"
									class="regular-text" /></td>
						</tr>
						<tr>
							<th scope="row">Option to Purchase Fee</th>
							<td><input type="text" name="afo_rep_purchase_fee"
									value="<?php echo esc_attr(get_option('afo_rep_purchase_fee', '£1')); ?>"
									class="regular-text" /></td>
						</tr>
						<tr>
							<th scope="row">Total Amount Payable</th>
							<td><input type="text" name="afo_rep_total_payable"
									value="<?php echo esc_attr(get_option('afo_rep_total_payable', '£19,755.46')); ?>"
									class="regular-text" /></td>
						</tr>
						<tr>
							<th scope="row">Representative APR</th>
							<td><input type="text" name="afo_rep_apr"
									value="<?php echo esc_attr(get_option('afo_rep_apr', '8.5%')); ?>"
									class="regular-text" /></td>
						</tr>
						<tr>
							<th scope="row">Rate of Interest</th>
							<td><input type="text" name="afo_rep_interest"
									value="<?php echo esc_attr(get_option('afo_rep_interest', '8.5% fixed')); ?>"
									class="regular-text" /></td>
						</tr>
						<tr>
							<th scope="row">Footer Notice</th>
							<td>
								<textarea name="afo_rep_footer" class="large-text" rows="3"><?php echo esc_textarea(get_option('afo_rep_footer', 'Rates may differ as they are dependent on individual circumstances. Subject to status.')); ?></textarea>
							</td>
						</tr>
					</table>
				<?php } ?>
				<?php submit_button(); ?>
			</form>
		</div>
	<?php
	}

	// ─── Frontend asset enqueueing ────────────────────────────────────────────

	/**
	 * Enqueues frontend scripts and stylesheets, passing dynamic PHP variables
	 * to JS and injecting all CSS custom properties for full colour control.
	 *
	 * @return void
	 */
	public function enqueue_scripts()
	{
		wp_enqueue_style('afo-styles', plugin_dir_url(__FILE__) . 'assets/css/style.css', [], '2.1.0');
		wp_enqueue_script('afo-script', plugin_dir_url(__FILE__) . 'assets/js/script.js', [], '2.1.0', true);

		// Attempt standard meta key, fallback to WooCommerce default
		$raw_price = get_post_meta(get_the_ID(), 'price', true);
		if (empty($raw_price)) {
			$raw_price = get_post_meta(get_the_ID(), '_price', true);
		}

		$clean_price = preg_replace('/[^0-9.]/', '', $raw_price);
		$price       = ! empty($clean_price) ? floatval($clean_price) : 10000;

		$api_key = get_option('afo_api_key', '091ecf3e-ad04-47d9-94b1-043539780f16');
		$api_url = get_option('afo_api_url', 'https://www.autofinanceonline.co.uk/wp-json/finance/v1/calculate');

		wp_localize_script('afo-script', 'afoConfig', [
			'apiKey' => $api_key,
			'apiUrl' => $api_url,
			'price'  => $price,
			'nonce'  => wp_create_nonce('afo_calc_nonce'),
		]);

		// ── Collect all colour options ────────────────────────────────────────
		$vars = [
			// Left panel
			'--afo-primary'        => get_option('afo_primary_color',      '#cc2020'),
			'--afo-left-bg'        => get_option('afo_bg_color',            '#f0f2f5'),
			'--afo-left-heading'   => get_option('afo_left_heading_color',  '#111111'),
			'--afo-left-label'     => get_option('afo_left_label_color',    '#222222'),
			'--afo-left-sublabel'  => get_option('afo_left_sublabel_color', '#888888'),
			'--afo-left-muted'     => get_option('afo_left_muted_color',    '#666666'),
			'--afo-slider-track'   => get_option('afo_slider_track_color',  '#d0d3d8'),
			'--afo-bubble-bg'      => get_option('afo_bubble_bg_color',     '#1a1a1a'),
			'--afo-bubble-text'    => get_option('afo_bubble_text_color',   '#ffffff'),
			'--afo-arrow-bg'       => get_option('afo_arrow_bg_color',      '#2a2a2a'),
			'--afo-arrow-text'     => get_option('afo_arrow_text_color',    '#ffffff'),
			// Right panel
			'--afo-right-bg'       => get_option('afo_right_bg_color',      '#cc2020'),
			'--afo-right-text'     => get_option('afo_right_text_color',    '#ffffff'),
			'--afo-right-muted'    => get_option('afo_right_muted_color',   '#ffcccc'),
			'--afo-circle-bg'      => get_option('afo_circle_bg_color',     '#7a0000'),
			'--afo-btn-bg'         => get_option('afo_btn_bg_color',        '#ffffff'),
			'--afo-btn-text'       => get_option('afo_btn_text_color',      '#cc2020'),
			// Inner card
			'--afo-card-bg'        => get_option('afo_card_bg_color',       '#ffffff'),
			'--afo-card-text'      => get_option('afo_card_text_color',     '#111111'),
			'--afo-card-muted'     => get_option('afo_card_muted_color',    '#555555'),
			'--afo-card-circle-bg' => get_option('afo_card_circle_bg_color', '#1a1a1a'),
			// Rep example
			'--afo-rep-text'       => get_option('afo_rep_text_color',      '#ffffff'),
			'--afo-rep-muted'      => get_option('afo_rep_muted_color',     '#ffcccc'),
			'--afo-rep-border'     => get_option('afo_rep_border_color',    '#ffffff33'),
		];

		// Build and inject the :root block
		$root_vars = implode(
			"\n\t\t\t\t",
			array_map(
				fn($prop, $val) => $prop . ': ' . $val . ';',
				array_keys($vars),
				$vars
			)
		);

		$custom_css = ":root {\n\t\t\t\t{$root_vars}\n\t\t\t}";
		wp_add_inline_style('afo-styles', $custom_css);
	}

	// ─── Shortcode ────────────────────────────────────────────────────────────

	/**
	 * Renders the calculator shortcode with the redesigned two-panel layout.
	 * Configuration is embedded via HTML5 data attributes to prevent race
	 * conditions in deferred JS environments.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_shortcode($atts)
	{
		$post_id = get_the_ID();

		// Price resolution: custom meta → WooCommerce meta → default
		$raw_price = get_post_meta($post_id, 'price', true);
		if (empty($raw_price)) {
			$raw_price = get_post_meta($post_id, '_price', true);
		}
		$clean_price = preg_replace('/[^0-9.]/', '', $raw_price);
		$price       = ! empty($clean_price) ? floatval($clean_price) : 10000;

		// API settings with robust fallbacks
		$api_key = get_option('afo_api_key', '091ecf3e-ad04-47d9-94b1-043539780f16');
		$api_url = get_option('afo_api_url', 'https://www.autofinanceonline.co.uk/wp-json/finance/v1/calculate');

		// ── Label settings ────────────────────────────────────────────────────
		$left_heading      = get_option('afo_left_heading',      'Finance Options');
		$deposit_label     = get_option('afo_deposit_label',     'How much do you want to deposit?');
		$deposit_sublabel  = get_option('afo_deposit_sublabel',  '');
		$borrow_label      = get_option('afo_borrow_label',      'Amount to borrow');
		$borrow_sublabel   = get_option('afo_borrow_sublabel',   '');
		$term_label        = get_option('afo_term_label',        'Pay back over (Years)');
		$term_sublabel     = get_option('afo_term_sublabel',     '');
		$right_heading     = get_option('afo_right_heading',     'Your personal finance example');
		$btn_text          = get_option('afo_btn_text',          'Get a quote');
		$monthly_sublabel  = get_option('afo_monthly_sublabel',  'per month*');

		// ── Rep example settings ──────────────────────────────────────────────
		$rep_cash_price   = get_option('afo_rep_cash_price',   '£15,000');
		$rep_deposit      = get_option('afo_rep_deposit',      '£0');
		$rep_credit       = get_option('afo_rep_credit',       '£15,000');
		$rep_term         = get_option('afo_rep_term',         '7 years');
		$rep_monthly      = get_option('afo_rep_monthly',      '84 monthly payments of £235.18');
		$rep_agreement    = get_option('afo_rep_agreement',    'Hire purchase agreement');
		$rep_purchase_fee = get_option('afo_rep_purchase_fee', '£1');
		$rep_total_payable = get_option('afo_rep_total_payable', '£19,755.46');
		$rep_apr          = get_option('afo_rep_apr',          '8.5%');
		$rep_interest     = get_option('afo_rep_interest',     '8.5% fixed');
		$rep_footer       = get_option('afo_rep_footer',       'Rates may differ as they are dependent on individual circumstances. Subject to status.');

		ob_start();
	?>
		<div class="afo-calculator-container"
			data-api-key="<?php echo esc_attr($api_key); ?>"
			data-api-url="<?php echo esc_url($api_url); ?>"
			data-price="<?php echo esc_attr($price); ?>">

			<!-- ═══════════════════════════════════════
			     LEFT PANEL — CONTROLS
			════════════════════════════════════════ -->
			<div class="afo-controls">

				<h2 class="afo-controls-heading"><?php echo esc_html($left_heading); ?></h2>

				<div class="afo-price-header">
					<h3>Vehicle price: <span id="afo-display-price">£<?php echo number_format($price, 2); ?></span></h3>
					<p>With a deposit of <span id="afo-display-deposit">£0.00</span>,
						balance to finance: <span id="afo-display-borrow">£<?php echo number_format($price, 2); ?></span></p>
				</div>

				<!-- DEPOSIT SLIDER -->
				<div class="afo-slider-group">
					<span class="afo-slider-label"><?php echo esc_html($deposit_label); ?></span>
					<?php if (! empty($deposit_sublabel)) : ?>
						<span class="afo-slider-sublabel"><?php echo esc_html($deposit_sublabel); ?></span>
					<?php endif; ?>
					<div class="afo-slider-track-wrap">
						<button class="afo-arrow-btn" type="button" data-target="afo-deposit" data-dir="-1"
							aria-label="Decrease deposit">&#8249;</button>
						<div class="afo-slider-inner">
							<div class="afo-bubble" id="afo-bubble-deposit">£0.00</div>
							<input type="range" id="afo-deposit"
								min="0"
								max="<?php echo esc_attr($price); ?>"
								value="0"
								step="100">
						</div>
						<button class="afo-arrow-btn" type="button" data-target="afo-deposit" data-dir="1"
							aria-label="Increase deposit">&#8250;</button>
					</div>
					<div class="afo-slider-range-labels">
						<span>£0</span>
						<span>£<?php echo number_format($price, 0); ?></span>
					</div>
				</div>

				<!-- BORROW SLIDER -->
				<div class="afo-slider-group">
					<span class="afo-slider-label"><?php echo esc_html($borrow_label); ?></span>
					<?php if (! empty($borrow_sublabel)) : ?>
						<span class="afo-slider-sublabel"><?php echo esc_html($borrow_sublabel); ?></span>
					<?php endif; ?>
					<div class="afo-slider-track-wrap">
						<button class="afo-arrow-btn" type="button" data-target="afo-borrow" data-dir="-1"
							aria-label="Decrease borrow amount">&#8249;</button>
						<div class="afo-slider-inner">
							<div class="afo-bubble" id="afo-bubble-borrow">£<?php echo number_format($price, 2); ?></div>
							<input type="range" id="afo-borrow"
								min="0"
								max="<?php echo esc_attr($price); ?>"
								value="<?php echo esc_attr($price); ?>"
								step="100">
						</div>
						<button class="afo-arrow-btn" type="button" data-target="afo-borrow" data-dir="1"
							aria-label="Increase borrow amount">&#8250;</button>
					</div>
					<div class="afo-slider-range-labels">
						<span>£0</span>
						<span>£<?php echo number_format($price, 0); ?></span>
					</div>
				</div>

				<!-- TERM SLIDER -->
				<div class="afo-slider-group">
					<span class="afo-slider-label"><?php echo esc_html($term_label); ?></span>
					<?php if (! empty($term_sublabel)) : ?>
						<span class="afo-slider-sublabel"><?php echo esc_html($term_sublabel); ?></span>
					<?php endif; ?>
					<div class="afo-slider-track-wrap">
						<button class="afo-arrow-btn" type="button" data-target="afo-term" data-dir="-1"
							aria-label="Decrease term">&#8249;</button>
						<div class="afo-slider-inner">
							<div class="afo-bubble" id="afo-bubble-term">5 years</div>
							<input type="range" id="afo-term"
								min="2"
								max="15"
								value="5"
								step="0.5">
						</div>
						<button class="afo-arrow-btn" type="button" data-target="afo-term" data-dir="1"
							aria-label="Increase term">&#8250;</button>
					</div>
					<div class="afo-slider-range-labels">
						<span>2 years</span>
						<span id="afo-display-term">5 years</span>
						<span>15 years</span>
					</div>
				</div>

			</div><!-- /afo-controls -->

			<!-- ═══════════════════════════════════════
			     RIGHT PANEL — RESULTS
			════════════════════════════════════════ -->
			<div class="afo-results">

				<h3 class="afo-results-heading"><?php echo esc_html($right_heading); ?></h3>

				<!-- Inner card: white background by default -->
				<div class="afo-results-card">

					<!-- ── Loading overlay ───────────────────────────────────── -->
					<div class="afo-loading-overlay" id="afo-loading-overlay" aria-hidden="true">
						<div class="afo-spinner"></div>
						<span class="afo-loading-label">Calculating&hellip;</span>
					</div>

					<!-- Hero: circle + key figures -->
					<div class="afo-hero-row">
						<div class="afo-monthly-circle">
							<span class="afo-monthly-amount" id="afo-res-monthly">£--</span>
							<span class="afo-monthly-sublabel"><?php echo esc_html($monthly_sublabel); ?></span>
						</div>
						<div class="afo-hero-stats">
							<!-- Total cost of credit is intentionally ABOVE total amount payable per spec -->
							<div class="afo-hero-stat-row">
								<div class="afo-hero-stat">
									<span class="stat-label">Total cost of credit</span>
									<span class="stat-value" id="afo-res-credit">£--</span>
								</div>
								<div class="afo-hero-stat">
									<span class="stat-label">APR</span>
									<span class="stat-value" id="afo-res-rate">--%</span>
								</div>
							</div>
							<div class="afo-hero-stat-row">
								<div class="afo-hero-stat">
									<span class="stat-label">Total amount payable</span>
									<span class="stat-value" id="afo-res-total">£--</span>
								</div>
							</div>
							<div class="afo-hero-info">
								Requested borrowing value: <strong><span id="afo-info-borrow">£<?php echo number_format($price, 2); ?></span></strong><br>
								Amount based on <strong><span id="afo-res-months">60</span> month</strong> repayment plan.
							</div>
						</div>
					</div>

					<button class="afo-btn" id="afo-quote-btn" type="button">
						<?php echo esc_html($btn_text); ?>
					</button>

					<!-- Representative Example table -->
					<div class="afo-rep-example">
						<div class="afo-rep-heading">Representative Example</div>
						<div class="afo-rep-grid">
							<div class="afo-rep-row">
								<span class="afo-rep-label">Vehicle Price</span>
								<span class="afo-rep-value"><?php echo esc_html($rep_cash_price); ?></span>
							</div>
							<div class="afo-rep-row">
								<span class="afo-rep-label">Monthy Payments</span>
								<span class="afo-rep-value"><?php echo esc_html($rep_monthly); ?></span>
							</div>
							<div class="afo-rep-row">
								<span class="afo-rep-label">Finance Term</span>
								<span class="afo-rep-value"><?php echo esc_html($rep_term); ?></span>
							</div>
							<div class="afo-rep-row">
								<span class="afo-rep-label">Total Amount Payable</span>
								<span class="afo-rep-value"><?php echo esc_html($rep_total_payable); ?></span>
							</div>
							<div class="afo-rep-row">
								<span class="afo-rep-label">APR</span>
								<span class="afo-rep-value"><?php echo esc_html($rep_apr); ?></span>
							</div>
							<div class="afo-rep-row">
								<span class="afo-rep-label">Option to Purchase Fee</span>
								<span class="afo-rep-value"><?php echo esc_html($rep_purchase_fee); ?></span>
							</div>
							<div class="afo-rep-row">
								<span class="afo-rep-label">Deposit</span>
								<span class="afo-rep-value"><?php echo esc_html($rep_deposit); ?></span>
							</div>
							<div class="afo-rep-row">
								<span class="afo-rep-label">Rate of Interest per Annum</span>
								<span class="afo-rep-value"><?php echo esc_html($rep_interest); ?></span>
							</div>
							<div class="afo-rep-row">
								<span class="afo-rep-label">Loan Amount</span>
								<span class="afo-rep-value"><?php echo esc_html($rep_credit); ?></span>
							</div>
							<div class="afo-rep-row">
								<span class="afo-rep-label">Agreement Type</span>
								<span class="afo-rep-value"><?php echo esc_html($rep_agreement); ?></span>
							</div>
						</div>
						<?php if (! empty($rep_footer)) : ?>
							<p class="afo-rep-footer"><?php echo esc_html($rep_footer); ?></p>
						<?php endif; ?>
					</div>

				</div><!-- /afo-results-card -->

			</div><!-- /afo-results -->

		</div><!-- /afo-calculator-container -->
<?php
		return ob_get_clean();
	}
}

new AFO_Calculator();