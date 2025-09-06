<?php
if (!defined('ABSPATH')) exit;

/**
 * AI text generation (Gemini, AI21, OpenAI)
 */
function seo_master_generate_text($keyword,$secondary=[],$length=1200){
  $prov=get_option('seo_master_ai_provider','none');
  $model=get_option('seo_master_ai_model','');
  $out="";

  if($prov==='gemini'){
    $key=get_option('seo_master_gemini_key');
    if($key){
      $res=wp_remote_post("https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent?key=$key",[
        'headers'=>['Content-Type'=>'application/json'],
        'body'=>json_encode(['contents'=>[['parts'=>[['text'=>"Write a $length word SEO blog post about $keyword. Use secondary keywords: ".implode(", ",$secondary).". Format naturally like a human."]]]]])
      ]);
      if(!is_wp_error($res)) $out=wp_remote_retrieve_body($res);
    }
  }

  if($prov==='ai21'){
    $key=get_option('seo_master_ai21_key');
    if($key){
      $res=wp_remote_post("https://api.ai21.com/studio/v1/$model/complete",[
        'headers'=>['Authorization'=>"Bearer $key",'Content-Type'=>'application/json'],
        'body'=>json_encode(['prompt'=>"Write a $length word SEO blog post about $keyword. Secondary keywords: ".implode(", ",$secondary),'numResults'=>1,'maxTokens'=>max(2000,$length*2),'temperature'=>0.7])
      ]);
      if(!is_wp_error($res)) $out=wp_remote_retrieve_body($res);
    }
  }

  if($prov==='openai'){
    $key=get_option('seo_master_openai
