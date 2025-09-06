<?php
if (!defined('ABSPATH')) exit;

/**
 * Get images from Pixabay (default) with fallback to Pexels
 */
function seo_master_fetch_images($query,$count=3){
  $out=[]; $pixabay=get_option('seo_master_pixabay_key'); $pexels=get_option('seo_master_pexels_key');
  if($pixabay){
    $url="https://pixabay.com/api/?key=".urlencode($pixabay)."&q=".urlencode($query)."&image_type=photo&per_page=$count&safesearch=true";
    $res=wp_remote_get($url);
    if(!is_wp_error($res) && wp_remote_retrieve_response_code($res)==200){
      $j=json_decode(wp_remote_retrieve_body($res),true);
      if(!empty($j['hits'])) foreach($j['hits'] as $h) $out[]=$h['largeImageURL'];
    }
  }
  if(empty($out) && $pexels){
    $res=wp_remote_get("https://api.pexels.com/v1/search?query=".urlencode($query)."&per_page=$count",
      ['headers'=>['Authorization'=>$pexels]]);
    if(!is_wp_error($res) && wp_remote_retrieve_response_code($res)==200){
      $j=json_decode(wp_remote_retrieve_body($res),true);
      if(!empty($j['photos'])) foreach($j['photos'] as $p) $out[]=$p['src']['large'];
    }
  }
  return $out;
}
