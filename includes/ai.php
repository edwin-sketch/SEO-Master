<?php
if (!defined('ABSPATH')) exit;

/**
 * AI text generation (Gemini, AI21, OpenAI) with safe fallbacks.
 * Returns a plain text/markdown-ish article string styled to read like a human.
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
    $res   = null;

    // Build a natural, human style prompt
    $secondary = is_array($secondary) ? array_filter(array_map('sanitize_text_field', $secondary)) : array();
    $sec_str   = empty($secondary) ? '' : (' Secondary keywords to naturally incorporate: ' . implode(', ', $secondary) . '.');

    $base_prompt =
        'Write an SEO-friendly blog post that reads like a human wrote it (not robotic). ' .
        'Target length about ' . intval($length) . ' words on the topic: "' . sanitize_text_field($keyword) . '".' .
        $sec_str .
        ' Style guidelines: ' .
        '1) conversational tone with varied sentence length; ' .
        '2) short hook intro; ' .
        '3) clear H2/H3 headings without labels like "H2:"; ' .
        '4) concise paragraphs (2–4 sentences each); ' .
        '5) use bullets sparingly; ' .
        '6) concrete examples where helpful; ' .
        '7) actionable conclusion; ' .
        '8) do NOT include meta description blocks or JSON/schema in the body; ' .
        '9) do NOT include code fences or triple backticks; ' .
        '10) avoid repetitive phrasing. ' .
        'Return only the article content.';

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
            $body = array(
                'prompt'      => $base_prompt,
                'numResults'  => 1,
                'maxTokens'   => max(512, min(2048, $length * 4)),
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
                if (isset($j['completions'][0]['data']['text'])){
                    $out = (string) $j['completions'][0]['data']['text'];
                } elseif (isset($j['completions'][0]['text'])){
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

    // -------- Fallback writer when AI fails or is disabled --------
    if (!$out || strlen(trim($out)) < 200){
        $out = seo_master_template_writer($keyword, is_array($secondary) ? implode(', ', $secondary) : (string)$secondary, $length);
    }

    // -------- Cleanups to avoid "robotic" signals --------
    // Strip "H2:" or "H3:" labels after heading hashes
    $out = preg_replace('/^(#{2,3})\s*H[23]:\s*/m', '$1 ', $out);
    // Remove any accidental triple-backtick fences
    $out = preg_replace('/^\s*```.*$/m', '', $out);
    // Normalize blank lines (max two)
    $out = preg_replace("/\n{3,}/", "\n\n", $out);

    return trim($out);
}
