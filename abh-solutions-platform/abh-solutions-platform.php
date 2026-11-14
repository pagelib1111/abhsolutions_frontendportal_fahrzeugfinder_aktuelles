<?php
/**
 * Plugin Name: ABH Solutions Plattform
 * Description: Fahrzeugfinder, Hersteller-Aktuelles und geschütztes Partnerportal für ABH Solutions.
 * Version: 0.1.1
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: MastMedia
 * Text Domain: abh-solutions-platform
 */
if (!defined('ABSPATH')) exit;

define('ABHSP_VERSION', '0.1.1');
define('ABHSP_FILE', __FILE__);
define('ABHSP_DIR', plugin_dir_path(__FILE__));
define('ABHSP_URL', plugin_dir_url(__FILE__));

require_once ABHSP_DIR . 'includes/class-abhsp-platform.php';
register_activation_hook(__FILE__, array('ABHSP_Platform', 'activate'));
add_action('plugins_loaded', array('ABHSP_Platform', 'boot'));
