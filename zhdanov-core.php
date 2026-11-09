<?php
/**
 * Plugin Name: Жданов: ядро
 * Plugin URI: https://zhdanovsites.com/
 * Description: Компаньон «Темы Жданов»: Афиша, контакты и маршрут, Яндекс Метрика с согласием на cookie (152-ФЗ), нижняя панель на телефоне, хлебные крошки, разметка Schema.org. Контент живёт в плагине и не пропадает при смене темы.
 * Version: 0.8.9
 * Requires at least: 6.7
 * Requires PHP: 7.4
 * Author: Zhdanov Sites
 * Author URI: https://zhdanovsites.com/
 * License: GPLv2 or later
 * Text Domain: zhdanov-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ZHDANOV_CORE_VERSION', '0.8.9' );
define( 'ZHDANOV_CORE_FILE', __FILE__ );
define( 'ZHDANOV_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'ZHDANOV_CORE_URL', plugin_dir_url( __FILE__ ) );

require_once ZHDANOV_CORE_DIR . 'inc/icons.php';
require_once ZHDANOV_CORE_DIR . 'inc/settings.php';
require_once ZHDANOV_CORE_DIR . 'inc/helpers.php';
require_once ZHDANOV_CORE_DIR . 'inc/translit.php';
require_once ZHDANOV_CORE_DIR . 'inc/i18n.php';
require_once ZHDANOV_CORE_DIR . 'inc/events.php';
require_once ZHDANOV_CORE_DIR . 'inc/types.php';
require_once ZHDANOV_CORE_DIR . 'inc/schedule.php';
require_once ZHDANOV_CORE_DIR . 'inc/lists.php';
require_once ZHDANOV_CORE_DIR . 'inc/legal.php';
require_once ZHDANOV_CORE_DIR . 'inc/lists3.php';
require_once ZHDANOV_CORE_DIR . 'inc/form.php';
require_once ZHDANOV_CORE_DIR . 'inc/blocks.php';
require_once ZHDANOV_CORE_DIR . 'inc/seo.php';
require_once ZHDANOV_CORE_DIR . 'inc/front.php';
require_once ZHDANOV_CORE_DIR . 'inc/recipe.php';
require_once ZHDANOV_CORE_DIR . 'inc/presets.php';
require_once ZHDANOV_CORE_DIR . 'inc/health.php';
require_once ZHDANOV_CORE_DIR . 'inc/design-import.php';
require_once ZHDANOV_CORE_DIR . 'inc/migrate.php';
require_once ZHDANOV_CORE_DIR . 'inc/pay.php';
require_once ZHDANOV_CORE_DIR . 'inc/part3.php';
require_once ZHDANOV_CORE_DIR . 'inc/booking.php';
require_once ZHDANOV_CORE_DIR . 'inc/access.php';
require_once ZHDANOV_CORE_DIR . 'inc/samples.php';
require_once ZHDANOV_CORE_DIR . 'inc/export.php';
require_once ZHDANOV_CORE_DIR . 'inc/concept.php';
require_once ZHDANOV_CORE_DIR . 'inc/concept-safe.php';
require_once ZHDANOV_CORE_DIR . 'inc/concept-data.php';
require_once ZHDANOV_CORE_DIR . 'inc/update.php';
require_once ZHDANOV_CORE_DIR . 'inc/whatsnew.php';
require_once ZHDANOV_CORE_DIR . 'inc/report.php';
require_once ZHDANOV_CORE_DIR . 'inc/mail.php';
require_once ZHDANOV_CORE_DIR . 'inc/gdpr.php';
require_once ZHDANOV_CORE_DIR . 'inc/client.php';
require_once ZHDANOV_CORE_DIR . 'inc/activity.php';
require_once ZHDANOV_CORE_DIR . 'inc/maint.php';
require_once ZHDANOV_CORE_DIR . 'inc/indexnow.php';
require_once ZHDANOV_CORE_DIR . 'inc/autoredirect.php';
require_once ZHDANOV_CORE_DIR . 'inc/duplicate.php';
require_once ZHDANOV_CORE_DIR . 'inc/twofa.php';
require_once ZHDANOV_CORE_DIR . 'inc/selfupdate.php';
require_once ZHDANOV_CORE_DIR . 'inc/feedback.php';
require_once ZHDANOV_CORE_DIR . 'inc/intake.php';
require_once ZHDANOV_CORE_DIR . 'inc/postcheck.php';
require_once ZHDANOV_CORE_DIR . 'inc/clientreport.php';
require_once ZHDANOV_CORE_DIR . 'inc/security.php';

register_activation_hook( __FILE__, function () {
	zhdanov_core_register_events();
	flush_rewrite_rules();
} );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
