<?php
/*
Plugin Name: SEO Master
Description: Schedule/manual SEO posts with AI (Gemini/AI21/OpenAI), Pixabay→Pexels images, Rank Math metadata, and Google Indexing (auto + manual) with logs.
Author: Tim Barksdale
Version: 1.2.0
*/

if (!defined('ABSPATH')) exit;
define('SEO_MASTER_VER','1.2.0');
define('SEO_MASTER_DIR', plugin_dir_path(__FILE__));
define('SEO_MASTER_URL', plugin_dir_url(__FILE__));

// Allow .json uploads for Google Indexing API key
add_filter('upload_mimes', function($m){ $m['json']='application/json'; return $m; });

// Add weekly cron schedule
add_filter('cron_schedules', function($s){
  if(!isset($s['weekly']))
    $s['weekly']=['interval'=>604800,'display'=>__('Once Weekly')];
  return $s;
});

// Load includes
require_once SEO_MASTER_DIR.'includes/logger.php';
require_once SEO_MASTER_DIR.'includes/admin.php';
require_once SEO_MASTER_DIR.'includes/generator.php';
require_once SEO_MASTER_DIR.'includes/images.php';
require_once SEO_MASTER_DIR.'includes/ai.php';
require_once SEO_MASTER_DIR.'includes/indexing.php';
require_once SEO_MASTER_DIR.'includes/schema.php';

// Activation/deactivation
register_activation_hook(__FILE__, function(){
  if(!wp_next_scheduled('seo_master_cron_generate'))
    wp_schedule_event(time(),'daily','seo_master_cron_generate');
});
register_deactivation_hook(__FILE__, function(){
  wp_clear_scheduled_hook('seo_master_cron
