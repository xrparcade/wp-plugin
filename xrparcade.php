<?php
declare(strict_types=1);

/*
Plugin Name: XRPArcade
Description: Essentials for XRPArcade
Author: Stefanos Demetriou
Author URI: https://www.github.com/mougias
*/

if (!function_exists('add_action')) {
	header('Status: 403 Forbidden');
	header('HTTP/1.1 403 Forbidden');
	exit();
}

require_once('inc/xumm.class.php');

require_once('inc/xumm.widget.class.php');
add_action('widgets_init', function () {
	register_widget('XummWidget');
});

require_once('inc/xrparcade_newsletter_manager.class.php');
$manager = new XRPArcadeNewsletterManager();
$manager->init_hooks();

require_once('inc/cron.php');
$cron = new XRPArcadeCron();
$cron->init_hooks();

register_activation_hook(__FILE__,'xrparcade_cron_activation');
function xrparcade_cron_activation()
{
	if (!wp_next_scheduled('xrparcade_cron_payments')) {
		wp_schedule_event(time(), 'daily', 'xrparcade_cron_payments');
	}

	if (!wp_next_scheduled('xrparcade_cron_newsletter_checkbox')) {
		wp_schedule_event(time(), 'twicedaily', 'xrparcade_cron_newsletter_checkbox');
	}
}

register_deactivation_hook(__FILE__,'xrparcade_cron_deactivation');
function xrparcade_cron_deactivation()
{
	wp_clear_scheduled_hook('xrparcade_cron_payments');
	wp_clear_scheduled_hook('xrparcade_cron_newsletter_checkbox');
}
