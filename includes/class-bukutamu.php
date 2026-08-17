<?php
/**
 * Bootstrap utama plugin. Memuat semua modul dan menghubungkan lifecycle hooks.
 */

defined( 'ABSPATH' ) || exit;

final class Bukutamu {

	private static ?Bukutamu $instance = null;

	public static function instance(): Bukutamu {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->includes();
		add_action( 'plugins_loaded', [ $this, 'boot' ] );
	}

	private function includes(): void {
		require_once BUKUTAMU_PATH . 'includes/helpers.php';
		require_once BUKUTAMU_PATH . 'includes/class-acf-fields.php';
		require_once BUKUTAMU_PATH . 'includes/class-cpt.php';
		require_once BUKUTAMU_PATH . 'includes/class-cpt-templates.php';
		require_once BUKUTAMU_PATH . 'includes/class-security.php';
		require_once BUKUTAMU_PATH . 'includes/class-uploads.php';
		require_once BUKUTAMU_PATH . 'includes/class-signature.php';
		require_once BUKUTAMU_PATH . 'includes/class-rest-api.php';
		require_once BUKUTAMU_PATH . 'includes/class-shortcode.php';
		require_once BUKUTAMU_PATH . 'includes/class-assets.php';
		require_once BUKUTAMU_PATH . 'includes/class-page-template.php';
	}

	public function boot(): void {
		load_plugin_textdomain( 'bukutamu', false, dirname( BUKUTAMU_BASENAME ) . '/languages' );

		Bukutamu_ACF_Fields::instance();
		Bukutamu_CPT::instance();
		Bukutamu_Cpt_Templates::instance();
		Bukutamu_Rest_Api::instance();
		Bukutamu_Shortcode::instance();
		Bukutamu_Assets::instance();
		Bukutamu_Page_Template::instance();
	}

	/**
	 * @see register_activation_hook()
	 */
	public static function activate(): void {
		require_once BUKUTAMU_PATH . 'includes/class-cpt.php';
		Bukutamu_CPT::register();
		flush_rewrite_rules();
	}

	/**
	 * @see register_deactivation_hook()
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}
