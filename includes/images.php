<?php
if (!defined('ABSPATH')) exit;

function seo_master_get_used_image_urls(){
    $u = get_option('seo_master_used_image_urls', array());
    return is_array($u) ? $u : array();
}
function seo_master_push_used_image_url($url){
    $u = seo_master_get_used_image_urls();
    $u[] = $url;
    $u = array_slice(array_values(array_unique($u)), -200);
    update_option('seo_master_used_image_urls', $u, false);
}

function seo_master_fetch_images($query, $count){
    $out = array();
    $q   = rawurlencode($query);
    $pixabay = trim(get_option('seo_master_pixabay_key',''));
    $pexels  = trim(get_option('seo_master_pexels_key',''));

    $already = seo_master_get_used_image_urls(); // GLOBAL de-dupe

    // Helper: add candidate if unique globally AND unique in this batch
    $add_unique = function($url,$alt) use (&$out,&$already,$count){
        if(!$url) return;
        if(in_array($url,$already,true)) return;
        foreach($out as $o){ if($o['url']===$url) return; }
        $out[] = array('url'=>$url,'alt'=>$alt);
        if(count($out) >= $count) return;
    };

    // Pixabay
    if($pixabay && count($out) < $count){
        $url = "https://pixabay.com/api/?key={$pixabay}&q={$q}&image_type=photo&orientation=horizontal&lang=en&safesearch=true&order=popular&per_page=".intval(max(10, $count*5));
        $res = wp_remote_get($url, array('timeout'=>20));
        $code= is_wp_error($res) ? 0 : intval(wp_remote_retrieve_response_code($res));
        if(!is_wp_error($res) && $code==200){
            $data = json_decode(wp_remote_retrieve_body($res), true);
            if(!empty($data['hits'])){
                foreach($data['hits'] as $h){
                    $src = !empty($h['largeImageURL']) ? $h['largeImageURL'] : '';
                    $alt = !empty($h['tags']) ? $h['tags'] : $query;
                    if($src){ $add_unique($src, sanitize_text_field($alt)); if(count($out) >= $count) break; }
                }
                seo_master_log('IMAGES_PIXABAY','ok',200);
            } else {
                seo_master_log('IMAGES_PIXABAY','no hits',200);
            }
        } else {
            $msg = is_wp_error($res)?$res->get_error_message():('HTTP '.$code);
            seo_master_log('IMAGES_PIXABAY',$msg,$code);
        }
    }

    // Pexels fallback
    if($pexels && count($out) < $count){
        $url = "https://api.pexels.com/v1/search?query={$q}&per_page=".intval(max(10, $count*5));
        $res = wp_remote_get($url, array('timeout'=>20, 'headers'=>array('Authorization'=>$pexels)));
        $code= is_wp_error($res) ? 0 : intval(wp_remote_retrieve_response_code($res));
        if(!is_wp_error($res) && $code==200){
            $data = json_decode(wp_remote_retrieve_body($res), true);
            if(!empty($data['photos'])){
                foreach($data['photos'] as $p){
                    if(empty($p['src'])) continue;
                    $src = '';
                    if(!empty($p['src']['large2x'])) $src = $p['src']['large2x'];
                    elseif(!empty($p['src']['large'])) $src = $p['src']['large'];
                    elseif(!empty($p['src']['original'])) $src = $p['src']['original'];
                    if($src){
                        $alt = isset($p['alt']) && $p['alt'] ? $p['alt'] : $query;
                        $add_unique($src, sanitize_text_field($alt));
                        if(count($out) >= $count) break;
                    }
                }
                seo_master_log('IMAGES_PEXELS','ok',200);
            } else {
                seo_master_log('IMAGES_PEXELS','no photos',$code);
            }
        } else {
            $msg = is_wp_error($res)?$res->get_error_message():('HTTP '.$code);
            seo_master_log('IMAGES_PEXELS',$msg,$code);
        }
    }

    return $out;
}

function seo_master_attach_image_to_post($image_url,$post_id,$alt=''){
    require_once ABSPATH.'wp-admin/includes/file.php';
    require_once ABSPATH.'wp-admin/includes/media.php';
    require_once ABSPATH.'wp-admin/includes/image.php';

    $tmp = download_url($image_url);
    if(is_wp_error($tmp)){
        seo_master_log('IMAGE_DL',$tmp->get_error_message(),0);
        return 0;
    }
    $filename = basename(parse_url($image_url, PHP_URL_PATH));
    if(!$filename) $filename = 'image.jpg';
    $file = array('name'=>$filename, 'tmp_name'=>$tmp);
    $id   = media_handle_sideload($file, $post_id);

    if(is_wp_error($id)){
        @unlink($tmp);
        seo_master_log('IMAGE_ATTACH',$id->get_error_message(),0);
        return 0;
    }

    if($alt) update_post_meta($id, '_wp_attachment_image_alt', wp_strip_all_tags($alt));

    // Track globally to prevent reuse
    seo_master_push_used_image_url($image_url);

    return $id;
}
