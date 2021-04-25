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

$active_plugins = apply_filters('active_plugins', get_option('active_plugins'));
if (
	!in_array('ultimate-member/ultimate-member.php', $active_plugins)
	|| !in_array('newsletter/plugin.php', $active_plugins)
) {
	return;
}

if (!class_exists('Xumm')) {
	require_once('inc/xumm.class.php');
}

if (!class_exists('XummWidget')) {
	require_once('inc/xumm.widget.class.php');

	add_action('widgets_init', function () {
		register_widget('XummWidget');
	});
}

if (!class_exists('XRPArcadeNewsletterManager')) {
	require_once('inc/xrparcade_newsletter_manager.class.php');
	$manager = new XRPArcadeNewsletterManager();
	$manager->init_hooks();
}

if (!class_exists('XRPArcadeCron')) {
	require_once('inc/cron.php');
	$cron = new XRPArcadeCron();
	$cron->init_hooks();
}

register_activation_hook(__FILE__,'xrparcade_cron_activation');
register_deactivation_hook(__FILE__,'xrparcade_cron_deactivation');

function xrparcade_cron_activation()
{
	if (!wp_next_scheduled('xrparcade_cron_payments')) {
		wp_schedule_event(time(), 'daily', 'xrparcade_cron_payments');
	}
}

function xrparcade_cron_deactivation()
{
	wp_clear_scheduled_hook('xrparcade_cron_payments');
}
