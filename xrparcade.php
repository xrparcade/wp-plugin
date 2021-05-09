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
require_once('inc/xrparcade_newsletter_manager.class.php');
require_once('inc/xrparcade_youtube_channels.php');
require_once('inc/cron.php');
require_once('inc/xumm_um_tab.class.php');
require_once('inc/xrparcade_newsletter_page.php');

(new XummWidget())->init_hooks();
(new XRPArcadeNewsletterManager())->init_hooks();
(new XRPArcadeYoutubeChannels())->init_hooks();
(new XRPArcadeCron())->init_hooks();
(new XummUMTab())->init_hooks();
(new XRPArcadeNewsletterPage())->init_hooks();

register_activation_hook(__FILE__,'xrparcade_cron_activation');
function xrparcade_cron_activation()
{
	wp_schedule_event(time(), 'daily', 'xrparcade_cron_payments');
	wp_schedule_event(time() + 3600, 'twicedaily', 'xrparcade_cron_newsletter_checkbox');
	wp_schedule_event(time() + 7200, 'daily', 'xrparcade_cron_youtubers');
}

register_deactivation_hook(__FILE__,'xrparcade_cron_deactivation');
function xrparcade_cron_deactivation()
{
	wp_clear_scheduled_hook('xrparcade_cron_payments');
	wp_clear_scheduled_hook('xrparcade_cron_newsletter_checkbox');
	wp_clear_scheduled_hook('xrparcade_cron_youtubers');
}

add_action('wp_head', 'xrparcade_head', 5);
function xrparcade_head()
{
	// preload um-gdpr
	echo '<link rel="preload" href="' . plugins_url('/ultimate-member/assets/js/um-gdpr.min.js') .'" as="script">';
}

add_filter('pre_get_posts', 'xrparcade_exclude_newsletter_category');
function xrparcade_exclude_newsletter_category(WP_Query $query)
{
	if (is_admin() && !defined('DOING_AJAX')) {
		return $query;
	}
	if (defined('XRPARCADE_NEWSLETTER_SHORTCODE') && XRPARCADE_NEWSLETTER_SHORTCODE) {
		return $query;
	}

	// only affect queries for posts
	$type = $query->get('post_type');
	if($type !== 'post') {
		return $query;
	}

	// exclude newsletter category (73)
	$query->set('cat', '-73');

	return $query;
}

add_action('save_post', 'xrparcade_newsletter_dont_send_push_notification', 0);
function xrparcade_newsletter_dont_send_push_notification()
{
	if (isset($_POST['post_category']) && is_array($_POST['post_category']) && in_array(73, $_POST['post_category'])) {
		unset($_POST['send_onesignal_notification']);
	}
}