<?php
if (!defined('ABSPATH')) exit;

/**
 * Admin menus + settings UI for SEO Master
 */

add_action('admin_menu', function () {
  add_menu_page(
    'SEO Master',
    'SEO Master',
    'manage_options',
    'seo-master',
    'seo_master_settings_page',
    'dashicons-analytics',
    58
  );

  add_submenu_page(
    'seo-master',
    'Manual Generator',
    'Manual Generator',
    'manage_options',
    'seo-master-generator',
    'seo_master_manual_generator_page'
  );

  add_submenu_page(
    'seo-master',
    'Google Indexing',
    'Google Indexing',
    'manage_options',
    'seo-master-indexing',
    'seo_master_indexing_page'
  );

  add_submenu_page(
    'seo-master',
    'Logs',
    'Logs',
    'manage_options',
    'seo-master-logs',
    'seo_master_logs_page'
  );
});

add_action('admin_init', function () {
  // Register options
  $opts = array(
    'seo_master_pixabay_key',
    'seo_master_pexels_key',
    'seo_master_ai_provider',
    'seo_master_ai_model',
    'seo_master_gemini_key',
    'seo_master_ai21_key',
    'seo_master_openai_key',
    'seo_master_default_length',
    'seo_master_image_count',
    'seo_master_default_category',
    'seo_master_default_tags',
    'seo_master_schedule_enabled',
    'seo_master_frequency',
    'seo_master_keywords_list',
    'seo_master_google_json_path'
  );
  foreach ($opts as $o) register_setting('seo_master', $o);

  // Sections
  add_settings_section('seo_master_ai',     'AI Provider',   null, 'seo-master');
  add_settings_section('seo_master_images', 'Images',        null, 'seo-master');
  add_settings_section('seo_master_sched',  'Scheduling',    null, 'seo-master');
  add_settings_section('seo_master_index',  'Google Indexing', null, 'seo-master');

  // Fields — AI
  add_settings_field('seo_master_ai_provider','Provider','seo_master_field_ai_provider','seo-master','seo_master_ai');
  add_settings_field('seo_master_ai_model','Model','seo_master_field_ai_model','seo-master','seo_master_ai');
  add_settings_field('seo_master_gemini_key','Gemini API Key','seo_master_field_text','seo-master','seo_master_ai',array('name'=>'seo_master_gemini_key'));
  add_settings_field('seo_master_ai21_key','AI21 API Key','seo_master_field_text','seo-master','seo_master_ai',array('name'=>'seo_master_ai21_key'));
  add_settings_field('seo_master_openai_key','OpenAI API Key','seo_master_field_text','seo-master','seo_master_ai',array('name'=>'seo_master_openai_key'));

  // Fields — Images
  add_settings_field('seo_master_pixabay_key','Pixabay API Key','seo_master_field_text','seo-master','seo_master_images',array('name'=>'seo_master_pixabay_key'));
  add_settings_field('seo_master_pexels_key','Pexels API Key (fallback)','seo_master_field_text','seo-master','seo_master_images',array('name'=>'seo_master_pexels_key'));

  // Fields — Scheduling
  add_settings_field('seo_master_schedule_enabled','Enable Scheduler','seo_master_field_checkbox','seo-master','seo_master_sched',array('name'=>'seo_master_schedule_enabled'));
  add_settings_field('seo_master_frequency','Frequency','seo_master_field_freq','seo-master','seo_master_sched');
  add_settings_field('seo_master_keywords_list','Keywords List (one per line)','seo_master_field_textarea','seo-master','seo_master_sched',array('name'=>'seo_master_keywords_list'));
  add_settings_field('seo_master_default_length','Default Length (words)','seo_master_field_number','seo-master','seo_master_sched',array('name'=>'seo_master_default_length','default'=>1200));
  add_settings_field('seo_master_image_count','Default Image Count','seo_master_field_number','seo-master','seo_master_sched',array('name'=>'seo_master_image_count','default'=>3));
  add_settings_field('seo_master_default_category','Default Category','seo_master_field_category','seo-master','seo_master_sched');
  add_settings_field('seo_master_default_tags','Default Tags (comma)','seo_master_field_text','seo-master','seo_master_sched',array('name'=>'seo_master_default_tags'));

  // Fields — Indexing
  add_settings_field('seo_master_google_json_path','Service JSON URL','seo_master_field_text','seo-master','seo_master_index',array('name'=>'seo_master_google_json_path'));
});

/** Reusable field renderers */
function seo_master_field_text($args){
  $n=$args['name']; $v=get_option($n,'');
  echo '<input type="text" class="regular-text code" name="'.esc_attr($n).'" value="'.esc_attr($v).'">';
}
function seo_master_field_number($args){
  $n=$args['name']; $d=isset($args['default'])?intval($args['default']):0; $v=get_option($n,$d);
  echo '<input type="number" name="'.esc_attr($n).'" value="'.esc_attr($v).'">';
}
function seo_master_field_checkbox($args){
  $n=$args['name']; $v=intval(get_option($n,0));
  echo '<label><input type="checkbox" name="'.esc_attr($n).'" value="1" '.checked($v,1,false).'> Enable</label>';
}
function seo_master_field_textarea($args){
  $n=$args['name']; $v=get_option($n,'');
  echo '<textarea name="'.esc_attr($n).'" rows="6" class="large-text code">'.esc_textarea($v).'</textarea>';
}
function seo_master_field_category(){
  wp_dropdown_categories(array(
    'name'=>'seo_master_default_category',
    'hide_empty'=>0,
    'selected'=>intval(get_option('seo_master_default_category',0))
  ));
}
function seo_master_field_freq(){
  $v=get_option('seo_master_frequency','daily');
  echo '<select name="seo_master_frequency">'
      .'<option value="hourly" '.selected($v,'hourly',false).'>Hourly</option>'
      .'<option value="daily" '.selected($v,'daily',false).'>Daily</option>'
      .'<option value="weekly" '.selected($v,'weekly',false).'>Weekly</option>'
      .'</select>';
}
function seo_master_field_ai_provider(){
  $v=get_option('seo_master_ai_provider','none');
  echo '<select name="seo_master_ai_provider" id="seo_master_ai_provider">'
      .'<option value="none" '.selected($v,'none',false).'>None (template fallback)</option>'
      .'<option value="gemini" '.selected($v,'gemini',false).'>Google Gemini</option>'
      .'<option value="ai21" '.selected($v,'ai21',false).'>AI21 Studio</option>'
      .'<option value="openai" '.selected($v,'openai',false).'>OpenAI</option>'
      .'</select>';
}
function seo_master_field_ai_model(){
  $prov=get_option('seo_master_ai_provider','none'); $cur=get_option('seo_master_ai_model','');
  $opts=array(''=>'— Select Model —');
  if($prov==='gemini') $opts=array('gemini-1.5-flash'=>'gemini-1.5-flash','gemini-1.5-flash-8b'=>'gemini-1.5-flash-8b');
  if($prov==='ai21')   $opts=array('j2-lite'=>'j2-lite','j2-light'=>'j2-light');
  if($prov==='openai') $opts=array('gpt-3.5-turbo'=>'gpt-3.5-turbo');
  echo '<select name="seo_master_ai_model">';
  foreach($opts as $k=>$l){
    echo '<option value="'.esc_attr($k).'" '.selected($cur,$k,false).'>'.esc_html($l).'</option>';
  }
  echo '</select>';
}

/** Settings page (top-level) */
function seo_master_settings_page(){ ?>
  <div class="wrap">
    <h1>SEO Master — Settings</h1>
    <form method="post" action="options.php">
      <?php settings_fields('seo_master'); do_settings_sections('seo-master'); submit_button('Save Settings'); ?>
    </form>

    <hr><h2>Upload Google Indexing Service JSON</h2>
    <form method="post" enctype="multipart/form-data">
      <?php wp_nonce_field('seo_master_upload_json'); ?>
      <input type="file" name="seo_master_google_json" accept=".json,application/json">
      <input type="submit" class="button button-primary" name="seo_master_upload_json" value="Upload JSON">
      <p>Current JSON URL: <code><?php echo esc_html(get_option('seo_master_google_json_path','(none)')); ?></code></p>
    </form>

    <form method="post" style="margin-top:8px;">
      <?php wp_nonce_field('seo_master_delete_json'); ?>
      <input type="submit" class="button" name="seo_master_delete_json" value="Remove stored JSON URL">
    </form>

    <hr><h2>Test Connections</h2>
    <form method="post">
      <?php wp_nonce_field('seo_master_test_conns'); ?>
      <input type="submit" class="button" name="seo_master_test_btn" value="Run Tests">
    </form>

    <?php
      if (isset($_POST['seo_master_test_btn']) && check_admin_referer('seo_master_test_conns')) {
        echo '<div style="margin-top:10px;">'.seo_master_run_connection_tests().'</div>';
      }
    ?>
  </div>
<?php }

/** Handle JSON upload/delete */
add_action('admin_init', function(){
  if(!current_user_can('manage_options')) return;

  if(isset($_POST['seo_master_upload_json'])){
    check_admin_referer('seo_master_upload_json');
    if(!empty($_FILES['seo_master_google_json']['name'])){
      $u = wp_handle_upload($_FILES['seo_master_google_json'], array('test_form'=>false,'test_type'=>false));
      if(!isset($u['error'])){
        $raw = @file_get_contents($u['file']); $j = json_decode($raw,true);
        if(json_last_error()===JSON_ERROR_NONE && !empty($j['client_email']) && !empty($j['private_key'])){
          update_option('seo_master_google_json_path', esc_url_raw($u['url']));
          add_action('admin_notices', function(){ echo '<div class="updated"><p>Service JSON uploaded & validated.</p></div>'; });
        } else {
          @unlink($u['file']);
          add_action('admin_notices', function(){ echo '<div class="error"><p>Invalid JSON file (missing client_email/private_key).</p></div>'; });
        }
      } else {
        $e = esc_html($u['error']);
        add_action('admin_notices', function() use($e){ echo '<div class="error"><p>Upload error: '.$e.'</p></div>'; });
      }
    }
  }

  if(isset($_POST['seo_master_delete_json'])){
    check_admin_referer('seo_master_delete_json');
    update_option('seo_master_google_json_path','');
    add_action('admin_notices', function(){ echo '<div class="updated"><p>Service JSON entry removed.</p></div>'; });
  }
});

/** Manual Generator page shell (content comes from generator.php) */
function seo_master_manual_generator_page(){ /* implemented in generator.php */ }

/** Indexing & Logs pages (rendered in generator/indexing files) */
function seo_master_indexing_page(){ /* implemented in admin.php bottom or indexing.php */ }

function seo_master_logs_page(){ /* implemented in admin (render logs table) */ }
