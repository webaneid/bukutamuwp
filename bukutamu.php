<?php
/**
 * Plugin Name:       Buku Tamu
 * Plugin URI:        https://webane.com
 * Description:       Buku tamu digital untuk WordPress — form kunjungan publik dengan tanda tangan & galeri foto, moderasi admin, dan tampilan testimoni. Dikembangkan oleh Webane Indonesia.
 * Version:           0.2.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Requires Plugins:  advanced-custom-fields-pro
 * Author:            Webane Indonesia
 * Author URI:        https://webane.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bukutamu
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'BUKUTAMU_VERSION', '0.2.1' );
define( 'BUKUTAMU_FILE', __FILE__ );
define( 'BUKUTAMU_PATH', plugin_dir_path( __FILE__ ) );
define( 'BUKUTAMU_URL', plugin_dir_url( __FILE__ ) );
define( 'BUKUTAMU_BASENAME', plugin_basename( __FILE__ ) );

require_once BUKUTAMU_PATH . 'includes/class-bukutamu.php';

register_activation_hook( __FILE__, [ 'Bukutamu', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'Bukutamu', 'deactivate' ] );

Bukutamu::instance();
