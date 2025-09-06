<?php
if (!defined('ABSPATH')) exit;

/**
 * Admin menus + settings UI for SEO Master
 * - Classic PHP syntax (array(), named functions) for max compatibility
 * - Top-level menu + submenus (Manual Generator, Google Indexing, Logs)
 * - Settings sections/fields
 * - JSON upload/remove handlers
 * - Indexing page (manual submit + logs table)
 * - Logs page
 */

/* -------------------------
 * Menus
 * ------------------------- */
add_action('admin_menu', 'seo_master_add_menus');
function seo_master_add_menus(){
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
        'seo_master_manual_generator_page' // implemented in includes/generator.php
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
}

/* -------------------------
 * Settings
 * ------------------------- */
add_action('admin_init', 'seo_master_register_settings');
function seo_master_register_settings(){
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
    foreach($opts as $o){
        register_setting('seo_master', $o);
    }

    // Sections
    add_settings_section('seo_master_ai',     'AI Provider',     'seo_master_section_ai',     'seo-master');
    add_settings_section('seo_master_images', 'Images',          'seo_master_section_images', 'seo-master');
    add_settings_section('seo_master_sched',  'Scheduling',      'seo_master_section_sched',  'seo-master');
    add_settings_section('seo_master_index',  'Google Indexing', 'seo_master_section_index',  'seo-master');

    // Fields — AI
    add_settings_field('seo_master_ai_provider', 'Provider', 'seo_master_field_ai_provider', 'seo-master', 'seo_master_ai');
    add_settings_field('seo_master_ai_model',    'Model',    'seo_master_field_ai_model',    'seo-master', 'seo_master_ai');
    add_settings_field('seo_master_gemini_key',  'Gemini API Key', 'seo_master_field_text',  'seo-master', 'seo_master_ai', array('name'=>'seo_master_gemini_key'));
    add_settings_field('seo_master_ai21_key',    'AI21 API Key',   'seo_master_field_text',  'seo-master', 'seo_master_ai', array('name'=>'seo_master_ai21_key'));
    add_settings_field('seo_master_openai_key',  'OpenAI API Key',  'seo_master_field_text', 'seo-master', 'seo_master_ai', array('name'=>'seo_master_openai_key'));

    // Fields — Images
    add_settings_field('seo_master_pixabay_key','Pixabay API Key',          'seo_master_field_text', 'seo-master', 'seo_master_images', array('name'=>'seo_master_pixabay_key'));
    add_settings_field('seo_master_pexels_key', 'Pexels API Key (fallback)','seo_master_field_text', 'seo-master', 'seo_master_images', array('name'=>'seo_master_pexels_key'));

    // Fields — Scheduling
    add_settings_field('seo_master_schedule_enabled','Enable Scheduler',        'seo_master_field_checkbox','seo-master','seo_master_sched', array('name'=>'seo_master_schedule_enabled'));
    add_settings_field('seo_master_frequency',       'Frequency',               'seo_master_field_freq',    'seo-master','seo_master_sched');
    add_settings_field('seo_master_keywords_list',   'Keywords (one per line)', 'seo_master_field_textarea','seo-master','seo_master_sched', array('name'=>'seo_master_keywords_list'));
    add_settings_field('seo_master_default_length',  'Default Length (words)',  'seo_master_field_number',  'seo-master','seo_master_sched', array('name'=>'seo_master_default_length','default'=>1200));
    add_settings_field('seo_master_image_count',     'Default Image Count',     'seo_master_field_number',  'seo-master','seo_master_sched', array('name'=>'seo_master_image_count','default'=>3));
    add_settings_field('seo_master_default_category','Default Category',        'seo_master_field_category','seo-master','seo_master_sched');
    add_settings_field('seo_master_default_tags',    'Default Tags (comma)',    'seo_master_field_text',    'seo-master','seo_master_sched', array('name'=>'seo_master_default_tags'));

    // Fields — Indexing
    add_settings_field('seo_master_google_json_path','Service JSON URL','seo_master_field_text','seo-master','seo_master_index', array('name'=>'seo_master_google_json_path'));
}

// Section descriptions (optional; keep minimal)
function seo_master_section_ai(){ echo '<p>Select provider & model. Leave provider as "None" to use the built-in template writer.</p>'; }
function seo_master_section_images(){ echo '<p>Pixabay is primary, Pexels is fallback.</p>'; }
function seo_master_section_sched(){ echo '<p>Enable and choose frequency to auto-generate posts from your keyword list.</p>'; }
function seo_master_section_index(){ echo '<p>Upload your Google Indexing API service JSON (or paste its Media URL) to auto/manuel submit URLs.</p>'; }

/* -------------------------
 * Field renderers
 * ------------------------- */
function seo_master_field_text($args){
    $n = isset($args['name']) ? $args['name'] : '';
    $v = get_option($n, '');
    echo '<input type="text" class="regular-text code" name="'.esc_attr($n).'" value="'.esc_attr($v).'" />';
}
function seo_master_field_number($args){
    $n = isset($args['name']) ? $args['name'] : '';
    $d = isset($args['default']) ? intval($args['default']) : 0;
    $v = get_option($n, $d);
    echo '<input type="number" name="'.esc_attr($n).'" value="'.esc_attr($v).'" />';
}
function seo_master_field_checkbox($args){
    $n = isset($args['name']) ? $args['name'] : '';
    $v = intval(get_option($n, 0));
    echo '<label><input type="checkbox" name="'.esc_attr($n).'" value="1" '.checked($v,1,false).' /> Enable</label>';
}
function seo_master_field_textarea($args){
    $n = isset($args['name']) ? $args['name'] : '';
    $v = get_option($n, '');
    echo '<textarea name="'.esc_attr($n).'" rows="6" class="large-text code">'.esc_textarea($v).'</textarea>';
}
function seo_master_field_category(){
    wp_dropdown_categories(array(
        'name' => 'seo_master_default_category',
        'hide_empty' => 0,
        'selected' => intval(get_option('seo_master_default_category', 0))
    ));
}
function seo_master_field_freq(){
    $v = get_option('seo_master_frequency', 'daily');
    echo '<select name="seo_master_frequency">';
    echo '<option value="hourly" '.selected($v,'hourly',false).'>Hourly</option>';
    echo '<option value="daily" '.selected($v,'daily',false).'>Daily</option>';
    echo '<option value="weekly" '.selected($v,'weekly',false).'>Weekly</option>';
    echo '</select>';
}
function seo_master_field_ai_provider(){
    $v = get_option('seo_master_ai_provider','none');
    echo '<select name="seo_master_ai_provider" id="seo_master_ai_provider">';
    echo '<option value="none"   '.selected($v,'none',false).'>None (template fallback)</option>';
    echo '<option value="gemini" '.selected($v,'gemini',false).'>Google Gemini</option>';
    echo '<option value="ai21"   '.selected($v,'ai21',false).'>AI21 Studio</option>';
    echo '<option value="openai" '.selected($v,'openai',false).'>OpenAI</option>';
    echo '</select>';
}
function seo_master_field_ai_model(){
    $prov = get_option('seo_master_ai_provider','none');
    $cur  = get_option('seo_master_ai_model','');
    $opts = array('' => '— Select Model —');
    if($prov === 'gemini') $opts = array('gemini-1.5-flash'=>'gemini-1.5-flash','gemini-1.5-flash-8b'=>'gemini-1.5-flash-8b');
    if($prov === 'ai21')   $opts = array('j2-lite'=>'j2-lite','j2-light'=>'j2-light');
    if($prov === 'openai') $opts = array('gpt-3.5-turbo'=>'gpt-3.5-turbo');

    echo '<select name="seo_master_ai_model">';
    foreach($opts as $k=>$l){
        echo '<option value="'.esc_attr($k).'" '.selected($cur,$k,false).'>'.esc_html($l).'</option>';
    }
    echo '</select>';
}

/* -------------------------
 * Settings Page (top-level)
 * ------------------------- */
function seo_master_settings_page(){ ?>
    <div class="wrap">
        <h1>SEO Master — Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields('seo_master'); do_settings_sections('seo-master'); submit_button('Save Settings'); ?>
        </form>

        <hr><h2>Upload Google Indexing Service JSON</h2>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('seo_master_upload_json'); ?>
            <input type="file" name="seo_master_google_json" accept=".json,application/json" />
            <input type="submit" class="button button-primary" name="seo_master_upload_json" value="Upload JSON" />
            <p>Current JSON URL: <code><?php echo esc_html(get_option('seo_master_google_json_path','(none)')); ?></code></p>
        </form>

        <form method="post" style="margin-top:8px;">
            <?php wp_nonce_field('seo_master_delete_json'); ?>
            <input type="submit" class="button" name="seo_master_delete_json" value="Remove stored JSON URL" />
        </form>

        <hr><h2>Test Connections</h2>
        <form method="post">
            <?php wp_nonce_field('seo_master_test_conns'); ?>
            <input type="submit" class="button" name="seo_master_test_btn" value="Run Tests" />
        </form>

        <?php
        if (isset($_POST['seo_master_test_btn']) && check_admin_referer('seo_master_test_conns')) {
            echo '<div style="margin-top:10px;">'.seo_master_run_connection_tests().'</div>';
        }
        ?>
    </div>
<?php }

/* -------------------------
 * Upload/Delete JSON handlers
 * ------------------------- */
add_action('admin_init', 'seo_master_handle_json_forms');
function seo_master_handle_json_forms(){
    if(!current_user_can('manage_options')) return;

    // Upload JSON
    if(isset($_POST['seo_master_upload_json'])){
        check_admin_referer('seo_master_upload_json');
        if(!empty($_FILES['seo_master_google_json']['name'])){
            $u = wp_handle_upload($_FILES['seo_master_google_json'], array('test_form'=>false, 'test_type'=>false));
            if(!isset($u['error'])){
                $raw = @file_get_contents($u['file']);
                $j   = json_decode($raw, true);
                if(json_last_error()===JSON_ERROR_NONE && !empty($j['client_email']) && !empty($j['private_key'])){
                    update_option('seo_master_google_json_path', esc_url_raw($u['url']));
                    add_action('admin_notices', create_function('', 'echo \'<div class="updated"><p>Service JSON uploaded & validated.</p></div>\';'));
                } else {
                    @unlink($u['file']);
                    add_action('admin_notices', create_function('', 'echo \'<div class="error"><p>Invalid JSON file (missing client_email/private_key).</p></div>\';'));
                }
            } else {
                $e = esc_html($u['error']);
                add_action('admin_notices', create_function('', 'echo \'<div class="error"><p>Upload error: '. $e .'</p></div>\';'));
            }
        }
    }

    // Delete stored URL
    if(isset($_POST['seo_master_delete_json'])){
        check_admin_referer('seo_master_delete_json');
        update_option('seo_master_google_json_path', '');
        add_action('admin_notices', create_function('', 'echo \'<div class="updated"><p>Service JSON entry removed.</p></div>\';'));
    }
}

/* -------------------------
 * Indexing Page
 * ------------------------- */
function seo_master_indexing_page(){
    // Handle manual submit on POST
    if(isset($_POST['seo_master_submit_url']) && check_admin_referer('seo_master_manual_index')){
        $url  = esc_url_raw(isset($_POST['index_url']) ? $_POST['index_url'] : '');
        $type = sanitize_text_field(isset($_POST['index_type']) ? $_POST['index_type'] : 'URL_UPDATED');
        if($url){
            if(function_exists('seo_master_submit_to_google')){
                $resp = seo_master_submit_to_google($url, $type);
                echo '<div class="notice notice-info"><p>Submitted: '.esc_html($url).'</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Indexing function not found.</p></div>';
            }
        }
    }

    echo '<div class="wrap"><h1>Google Indexing</h1>';
    echo '<p>Auto-submit on publish/update for posts, pages & attachments when a service JSON is set.</p>';

    echo '<form method="post" style="margin-bottom:12px;">';
    wp_nonce_field('seo_master_manual_index');
    echo '<input type="hidden" name="index_url" value="'.esc_attr(home_url('/')).'" />';
    echo '<input type="hidden" name="index_type" value="URL_UPDATED" />';
    echo '<input type="submit" name="seo_master_submit_url" class="button" value="Submit Home URL (Test)" />';
    echo '</form>';

    echo '<form method="post">';
    wp_nonce_field('seo_master_manual_index');
    echo '<p><label>URL: <input type="url" name="index_url" class="regular-text" placeholder="'.esc_attr(home_url('/sample-post/')).'"></label> ';
    echo '<select name="index_type"><option value="URL_UPDATED">URL_UPDATED</option><option value="URL_DELETED">URL_DELETED</option></select> ';
    echo '<input type="submit" class="button button-primary" name="seo_master_submit_url" value="Submit to Google"></p>';
    echo '</form>';

    echo '<h2>Logs</h2>';
    seo_master_render_logs_table();

    echo '</div>';
}

/* -------------------------
 * Logs Page + table
 * ------------------------- */
function seo_master_logs_page(){
    echo '<div class="wrap"><h1>SEO Master — Logs</h1>';
    seo_master_render_logs_table();
    echo '</div>';
}

function seo_master_render_logs_table(){
    $logs = get_option('seo_master_logs', array());
    echo '<table class="widefat striped"><thead><tr>';
    echo '<th>Time</th><th>Service</th><th>HTTP</th><th>Message</th>';
    echo '</tr></thead><tbody>';

    if(empty($logs)){
        echo '<tr><td colspan="4">No log entries.</td></tr>';
    } else {
        $logs = array_reverse($logs); // newest first
        foreach($logs as $row){
            $time = isset($row['time']) ? $row['time'] : '';
            $svc  = isset($row['service']) ? $row['service'] : '';
            $code = isset($row['code']) ? intval($row['code']) : 0;
            $msg  = isset($row['message']) ? $row['message'] : '';
            echo '<tr>';
            echo '<td>'.esc_html($time).'</td>';
            echo '<td>'.esc_html($svc).'</td>';
            echo '<td>'.esc_html($code).'</td>';
            echo '<td><code style="white-space:pre-wrap">'.esc_html(mb_substr($msg,0,1000)).'</code></td>';
            echo '</tr>';
        }
    }
    echo '</tbody></table>';
}

/* -------------------------
 * Connection tests (simple)
 * ------------------------- */
function seo_master_run_connection_tests(){
    $out = '<ul style="list-style:disc;margin-left:20px;">';

    // Pixabay
    $pix = trim(get_option('seo_master_pixabay_key',''));
    $out .= '<li>Pixabay key: '.($pix ? '✅ set' : '❌ missing').'</li>';

    // Pexels
    $pex = trim(get_option('seo_master_pexels_key',''));
    $out .= '<li>Pexels key (fallback): '.($pex ? '✅ set' : '⚠️ not set').'</li>';

    // AI Providers
    $prov = get_option('seo_master_ai_provider','none');
    $model= get_option('seo_master_ai_model','');
    $out .= '<li>AI Provider: <strong>'.esc_html($prov).'</strong> — Model: <code>'.esc_html($model).'</code></li>';

    if($prov==='gemini'){
        $gk = trim(get_option('seo_master_gemini_key',''));
        $out .= '<li>Gemini key: '.($gk?'✅ set':'❌ missing').'</li>';
    } elseif($prov==='ai21'){
        $a21 = trim(get_option('seo_master_ai21_key',''));
        $out .= '<li>AI21 key: '.($a21?'✅ set':'❌ missing').'</li>';
    } elseif($prov==='openai'){
        $ok = trim(get_option('seo_master_openai_key',''));
        $out .= '<li>OpenAI key: '.($ok?'✅ set':'❌ missing').'</li>';
    } else {
        $out .= '<li>Using template fallback (no external AI needed)</li>';
    }

    // Google Indexing JSON
    $json = trim(get_option('seo_master_google_json_path',''));
    $out .= '<li>Google service JSON URL: '.($json && $json!=='(none)' ? '✅ set' : '❌ missing').'</li>';

    $out .= '</ul>';
    return $out;
}
