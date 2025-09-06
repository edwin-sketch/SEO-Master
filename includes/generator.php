<?php
if (!defined('ABSPATH')) exit;

/**
 * Manual + Scheduled Post Generator
 */

add_action('seo_master_cron_generate','seo_master_run_scheduler');

function seo_master_manual_generator_page(){
  if(isset($_POST['seo_master_generate'])){
    check_admin_referer('seo_master_manual_gen');
    $kw=sanitize_text_field($_POST['seo_master_kw']);
    $sec=array_map('sanitize_text_field',explode(',',$_POST['seo_master_sec']??''));
    $len=intval($_POST['seo_master_len']);
    $images=seo_master_fetch_images($kw,get_option('seo_master_image_count',3));
    $txt=seo_master_generate_text($kw,$sec,$len);

    $id=wp_insert_post([
      'post_title'=>ucwords($kw),
      'post_content'=>$txt,
      'post_status'=>'draft',
      'post_category'=>[intval(get_option('seo_master_default_category',1))],
      'tags_input'=>explode(',',get_option('seo_master_default_tags',''))
    ]);

    if($id && !empty($images)){
      $img_id=seo_master_set_featured_image($images[0],$id);
      if($img_id) set_post_thumbnail($id,$img_id);
    }

    seo_master_submit_to_google(get_permalink($id),'URL_UPDATED');
    echo '<div class="updated"><p>Draft generated for "'.esc_html($kw).'".</p></div>';
  }

  ?>
  <div class="wrap">
    <h1>Manual Generator</h1>
    <form method="post">
      <?php wp_nonce_field('seo_master_manual_gen'); ?>
      <p><input type="text" name="seo_master_kw" placeholder="Keyword" class="regular-text"></p>
      <p><input type="text" name="seo_master_sec" placeholder="Secondary keywords (comma)" class="regular-text"></p>
      <p><input type="number" name="seo_master_len" value="1000"> words</p>
      <p><input type="submit" name="seo_master_generate" class="button button-primary" value="Generate Draft"></p>
    </form>
  </div>
  <?php
}

function seo_master_run_scheduler(){
  $enabled=get_option('seo_master_schedule_enabled',0);
  if(!$enabled) return;
  $list=explode("\n",get_option('seo_master_keywords_list',''));
  $list=array_filter(array_map('trim',$list));
  if(empty($list)) return;
  $kw=$list[array_rand($list)];
  $len=intval(get_option('seo_master_default_length',1200));
  $sec=[]; // could extend later
  $images=seo_master_fetch_images($kw,get_option('seo_master_image_count',3));
  $txt=seo_master_generate_text($kw,$sec,$len);

  $id=wp_insert_post([
    'post_title'=>ucwords($kw),
    'post_content'=>$txt,
    'post_status'=>get_option('seo_master_post_status','publish'),
    'post_category'=>[intval(get_option('seo_master_default_category',1))],
    'tags_input'=>explode(',',get_option('seo_master_default_tags',''))
  ]);

  if($id && !empty($images)){
    $img_id=seo_master_set_featured_image($images[0],$id);
    if($img_id) set_post_thumbnail($id,$img_id);
  }

  seo_master_submit_to_google(get_permalink($id),'URL_UPDATED');
}
