<?php
/*
Plugin Name: SEO Master
Description: Schedule/manual SEO posts with AI (Gemini/AI21/OpenAI), Pixabay→Pexels images, Rank Math metadata, and Google Indexing (auto + manual) with logs.
Author: Tim Barksdale
Version: 1.2.1
*/

if (!defined('ABSPATH')) exit;

define('SEO_MASTER_VER', '1.2.1');
define('SEO_MASTER_DIR', plugin_dir_path(__FILE__));
define('SEO_MASTER_URL', plugin_dir_url(__FILE__));

/** Allow .json uploads for Google Indexing service accounts */
add_filter('upload_mimes', function($m){
    $m['json'] = 'application/json';
    return $m;
});

/** Add weekly cron schedule (classic arrays for max PHP compatibility) */
add_filter('cron_schedules', function($s){
    if (!isset($s['weekly'])) {
        $s['weekly'] = array(
            'interval' => 604800, // 7 * 24 * 60 * 60
            'display'  => __('Once Weekly')
        );
    }
    return $s;
});

/** Includes */
require_once SEO_MASTER_DIR . 'includes/logger.php';
require_once SEO_MASTER_DIR . 'includes/admin.php';
require_once SEO_MASTER_DIR . 'includes/generator.php';
require_once SEO_MASTER_DIR . 'includes/images.php';
require_once SEO_MASTER_DIR . 'includes/ai.php';
require_once SEO_MASTER_DIR . 'includes/indexing.php';
require_once SEO_MASTER_DIR . 'includes/schema.php';

/**
 * Activation: set up cron if scheduler is enabled in options.
 * (Generator file defines the handler for 'seo_master_cron_generate')
 */
register_activation_hook(__FILE__, function () {
    // If already scheduled, do nothing
    if (wp_next_scheduled('seo_master_cron_generate')) return;

    // Choose interval from option (hourly/daily/weekly)
    $freq = get_option('seo_master_frequency', 'daily');
    $interval = 'daily';
    if ($freq === 'hourly') $interval = 'hourly';
    if ($freq === 'weekly') $interval = 'weekly';

    // Schedule the event to start shortly
    wp_schedule_event(time() + 60, $interval, 'seo_master_cron_generate');
});

/** Deactivation: remove our cron hook */
register_deactivation_hook(__FILE__, function () {
    $ts = wp_next_scheduled('seo_master_cron_generate');
    if ($ts) wp_unschedule_event($ts, 'seo_master_cron_generate');
});

/**
 * If user changes frequency or enables/disables scheduler,
 * reschedule cleanly.
 */
add_action('updated_option', function($option, $old, $new){
    if ($option !== 'seo_master_frequency' && $option !== 'seo_master_schedule_enabled') return;

    // Clear any existing schedule
    $ts = wp_next_scheduled('seo_master_cron_generate');
    if ($ts) wp_unschedule_event($ts, 'seo_master_cron_generate');

    // Only schedule if enabled
    if (get_option('seo_master_schedule_enabled', 0)) {
        $freq = get_option('seo_master_frequency', 'daily');
        $interval = 'daily';
        if ($freq === 'hourly') $interval = 'hourly';
        if ($freq === 'weekly') $interval = 'weekly';
        wp_schedule_event(time() + 60, $interval, 'seo_master_cron_generate');
    }
}, 10, 3);
