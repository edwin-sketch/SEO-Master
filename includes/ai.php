<?php
if (!defined('ABSPATH')) exit;

/**
 * AI text generation (Gemini, AI21, OpenAI) with safe fallbacks.
 * Returns a plain text article string.
 *
 * @param string $keyword
 * @param array  $secondary
 * @param int    $length
 * @return string
 */
function seo_master_generate_text($keyword, $secondary = array(), $length = 1200){
    $prov  = get_option('seo_master_ai_provider', 'none');
    $model = get_option('seo_master_ai_model', '');
    $out   = '';
    $res   = null; // keep defined to avoid notices in logging

    // Build a consistent, human-friendly prompt
    $sec_str = '';
    if (is_array($secondary) && !empty($secondary)){
        $sec_str = ' Secondary keywords: ' . implode(', ', array_map('sanitize_text_field', $secondary)) . '.';
    }
    $base_prompt = 'Write a ' . intval($length) . ' word, human-like, SEO-optimized blog post about "' . sanitize_text_field($keyword) . '".'
                 . $sec_str
                 . ' Use a short hook intro, clear H2/H3 subheadings, concise paragraphs, scannable bullet lists where useful,'
                 . ' concrete examples, and a practical conclusion with next steps. Avoid meta description blocks; write natural content only.'
                 . ' Do not include any JSON or schema in the body.';

    // -------- Gemini --------
    if ($prov === 'gemini'){
        $key = trim(get_option('seo_master_gemini_key', ''));
        if ($key && $model){
            $url  = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($key);
            $body = array(
                'contents' => array(
                    array(
                        'parts' => array(
                            array('text' => $base_prompt)
                        )
                    )
                ),
                'generationConfig' => array('temperature' => 0.7)
            );
            $res  = wp_remote_post($url, array(
                'timeout' => 45,
                'headers' => array('Content-Type' => 'application/json'),
                'body'    => wp_json_encode($body)
            ));
            $code = is_wp_error($res) ? 0 : intval(wp_remote_retrieve_response_code($res));
            if (!is_wp_error($res) && $code === 200){
                $j = json_decode(wp_remote_retrieve_body($res), true);
                if (isset($j['candidates'][0]['content']['parts'][0]['text'])){
                    $out = (string) $j['candidates'][0]['content']['parts'][0]['text'];
                }
            }
            seo_master_log('AI_GEMINI', is_wp_error($res) ? $res->get_error_message() : wp_remote_retrieve_body($res), $code);
        }
    }

    // -------- AI21 --------
    if (!$out && $prov === 'ai21'){
        $key = trim(get_option('seo_master_ai21_key', ''));
        if ($key && $model){
            $url  = 'https://api.ai21.com/studio/v1/' . rawurlencode($model) . '/complete';
            // AI21 expects a "prompt" and returns completions
            $body = array(
                'prompt'     => $base_prompt,
                'numResults' => 1,
                'maxTokens'  => max(512, min(2048, $length * 4)),
                'temperature'=> 0.7
            );
            $res  = wp_remote_post($url, array(
                'timeout' => 45,
                'headers' => array(
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $key
                ),
                'body' => wp_json_encode($body)
            ));
            $code = is_wp_error($res) ? 0 : intval(wp_remote_retrieve_response_code($res));
            if (!is_wp_error($res) && $code === 200){
                $j = json_decode(wp_remote_retrieve_body($res), true);
                if (isset($j['completions'][0]['data']['text'])){
                    $out = (string) $j['completions'][0]['data']['text'];
                } elseif (isset($j['completions'][0]['text'])){
                    // some AI21 responses use 'text' directly
                    $out = (string) $j['completions'][0]['text'];
                }
            }
            seo_master_log('AI_AI21', is_wp_error($res) ? $res->get_error_message() : wp_remote_retrieve_body($res), $code);
        }
    }

    // -------- OpenAI --------
    if (!$out && $prov === 'openai'){
        $key = trim(get_option('seo_master_openai_key', ''));
        if ($key){
            $use_model = $model ? $model : 'gpt-3.5-turbo';
            $url  = 'https://api.openai.com/v1/chat/completions';
            $body = array(
                'model'    => $use_model,
                'messages' => array(
                    array('role' => 'user', 'content' => $base_prompt)
                ),
                'temperature' => 0.7
            );
            $res  = wp_remote_post($url, array(
                'timeout' => 45,
                'headers' => array(
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $key
                ),
                'body' => wp_json_encode($body)
            ));
            $code = is_wp_error($res) ? 0 : intval(wp_remote_retrieve_response_code($res));
            if (!is_wp_error($res) && $code === 200){
                $j = json_decode(wp_remote_retrieve_body($res), true);
                if (isset($j['choices'][0]['message']['content'])){
                    $out = (string) $j['choices'][0]['message']['content'];
                }
            }
            seo_master_log('AI_OPENAI', is_wp_error($res) ? $res->get_error_message() : wp_remote_retrieve_body($res), $code);
        }
    }

    // -------- Fallback (template writer) --------
    if (!$out || strlen(trim($out)) < 200){
        $out = seo_master_template_writer($keyword, is_array($secondary) ? implode(', ', $secondary) : (string)$secondary, $length);
    }

    // Basic cleanup to avoid stray JSON/markup from APIs
    $out = trim(preg_replace('/^\s*```(?:markdown|md)?\s*/i', '', $out));
    $out = trim(preg_replace('/```$/', '', $out));

    return $out;
}
