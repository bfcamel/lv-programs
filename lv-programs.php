<?php
/**
 * Plugin Name: Люди и Верблюды — Программы фонда
 * Plugin URI:  https://bfcamel.ru/
 * Description: Управление программами фонда, двумя вариантами вывода, шапками и независимым порядком карточек.
 * Version:     1.0.9
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Author:      Благотворительный фонд «Люди и Верблюды»
 * Text Domain: lv-programs
 * License:     GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LVFP_VERSION', '1.0.9' );
define( 'LVFP_FILE', __FILE__ );
define( 'LVFP_DIR', plugin_dir_path( __FILE__ ) );
define( 'LVFP_URL', plugin_dir_url( __FILE__ ) );

require_once LVFP_DIR . 'includes/class-lvfp-plugin.php';
require_once LVFP_DIR . 'includes/class-lvfp-admin.php';

register_activation_hook( LVFP_FILE, array( 'LVFP_Plugin', 'activate' ) );

add_action(
	'plugins_loaded',
	static function () {
		LVFP_Plugin::instance();
		if ( is_admin() ) {
			LVFP_Admin::instance();
		}
	}
);
