<?php
if (!defined('ABSPATH')) exit;

function seo_master_fetch_images($query, $count){
    $out = array();
    $q   = rawurlencode($query);
    $pixabay = trim(get_option('seo_master_pixabay_key',''));
    $pexels  = trim(get_option('seo_master_pexels_key',''));

    // Pixabay first
    if($pixabay){
        $url = "https://pixabay.com/api/?key={$pixabay}&q={$q}&image_type=photo&orientation=horizontal&lang=en&safesearch=true&order=popular&per_page=".intval(max(3, min(50, $count*3)));
        $res = wp_remote_get($url, array('timeout'=>20));
        $code= is_wp_error($res) ? 0 : intval(wp_remote_retrieve_response_code($res));
        if(!is_wp_error($res) && $code==200){
            $data = json_decode(wp_remote_retrieve_body($res), true);
            if(!empty($data['hits'])){
                foreach($data['hits'] as $h){
                    if(empty($h['largeImageURL'])) continue;
                    $out[] = array('url'=>$h['largeImageURL'], 'alt'=>sanitize_text_field($h['tags']));
                    if(count($out) >= $count) break;
                }
                seo_master_log('IMAGES_PIXABAY','ok',200);
                if(count($out) >= $count) return $out;
            } else {
                seo_master_log('IMAGES_PIXABAY','no hits',200);
            }
        } else {
            $msg = is_wp_error($res)?$res->get_error_message():('HTTP '.$code);
            seo_master_log('IMAGES_PIXABAY',$msg,$code);
        }
    }

    // Pexels fallback
    if($pexels){
        $url = "https://api.pexels.com/v1/search?query={$q}&per_page=".intval(max(3, min(50, $count*3)));
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
                        $out[] = array('url'=>$src, 'alt'=>sanitize_text_field($alt));
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
    return $id;
}
