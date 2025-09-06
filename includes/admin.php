<?php
if (!defined('ABSPATH')) exit;

add_action('admin_menu', function(){
  add_menu_page('SEO Master','SEO Master','manage_options','seo-master','seo_master_settings_page','dashicons-analytics',58);
  add_submenu_page('seo-master','Manual Generator','Manual Generator','manage_options','seo-master-generator','seo_master_manual_generator_page');
  add_submenu_page('seo-master','Google Indexing','Google Indexing','manage_options','seo-master-indexing','seo_master_indexing_page');
  add_submenu_page('seo-master','Logs','Logs','manage_options','seo-master-logs','seo_master_logs_page');
});

add_action('admin_init', function(){
  $opts = array(
    'seo_master_pixabay_key','seo_master_pexels_key','seo_master_ai_provider','seo_master_ai_model','seo_master_gemini_key',
    'seo_master_ai21_key','seo_master_openai_key','seo_master_default_length','seo_master_image_count','seo_master_default_category',
    'seo_master_default_tags','seo_master_schedule_enabled','seo_master_frequency','seo_master_keywords_list','seo_master_google_json_path'
  );
  foreach($opts as $o) register_setting('seo_master',$o);

  add_settings_section('seo_master_ai','AI Provider',null,'seo-master');
  add_settings_field('seo_master_ai_provider','Provider','seo_master_field_ai_provider','seo-master','seo_master_ai');
  add_settings_field('seo_master_ai_model','Model','seo_master_field_ai_model','seo-master','seo_master_ai');
  add_settings_field('seo_master_gemini_key','Gemini API Key','seo_master_field_text','seo-master','seo_master_ai',array('name'=>'seo_master_gemini_key'));
  add_settings_field('seo_master_ai21_key','AI21 API Key','seo_master_field_text','seo-master','seo_master_ai',array('name'=>'seo_master_ai21_key'));
  add_settings_field('seo_master_openai_key','OpenAI API Key','seo_master_field_text','seo-master','seo_master_ai',array('name'=>'seo_master_openai_key'));

  add_settings_section('seo_master_images','Images',null,'seo-master');
  add_settings_field('seo_master_pixabay_key','Pixabay API Key','seo_master_field_text','seo-master','seo_master_images',array('name'=>'seo_master_pixabay_key'));
  add_settings_field('seo_master_pexels_key','Pexels API Key (fallback)','seo_master_field_text','seo-master','seo_master_images',array('name'=>'seo_master_pexels_key'));

  add_settings_section('seo_master_sched','Scheduling',null,'seo-master');
  add_settings_field('seo_master_schedule_enabled','Enable Scheduler','seo_master_field_checkbox','seo-master','seo_master_sched',array('name'=>'seo_master_schedule_enabled'));
  add_settings_field('seo_master_frequency','Frequency','seo_master_field_freq','seo-master','seo_master_sched');
  add_settings_field
