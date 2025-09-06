<?php
if (!defined('ABSPATH')) exit;

/**
 * Google Indexing API
 */
function seo_master_submit_to_google($url,$type='URL_UPDATED'){
  $json_url=get_option('seo_master_google_json_path');
  if(!$json_url) return false;
  $raw=@file_get_contents($json_url); $key=json_decode($raw,true);
  if(!$key || empty($key['private_key'])||empty($key['client_email'])) return false;

  $jwt=seo_master_build_jwt($key['client_email'],$key['private_key']);
  $res=wp_remote_post('https://oauth2.googleapis.com/token',[
    'body'=>[
      'grant_type'=>'urn:ietf:params:
