<?php
if (!defined('ABSPATH')) exit;


/**
 * Manual + Scheduled Post Generator
 * - Human-like formatting
 * - Choose image count (manual override; falls back to setting)
 * - Auto internal + external links
 * - Rank Math meta (title/description/focus keyword)
 */
/** Minimal Markdown → HTML converter for headings, lists, bold/italic, links, paragraphs */
function seo_master_markdown_to_html($md){
    if(!$md) return '';
    // Normalize newlines
    $md = str_replace("\r\n", "\n", $md);

    // Remove labels like "H2:" / "H3:" after hashes
    $md = preg_replace('/^(#{2,3})\s*H[23]:\s*/m', '$1 ', $md);

    // Strip code fences just in case
    $md = preg_replace('/^\s*```.*$/m', '', $md);

    // Headings
    $md = preg_replace('/^###\s+(.*)$/m', '<h3>$1</h3>', $md);
    $md = preg_replace('/^##\s+(.*)$/m',  '<h2>$1</h2>', $md);
    $md = preg_replace('/^#\s+(.*)$/m',   '<h1>$1</h1>', $md);

    // Bold/italic
    $md = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $md);
    $md = preg_replace('/\*(.+?)\*/s',     '<em>$1</em>', $md);

    // Links [text](url)
    $md = preg_replace_callback('/\[(.*?)\]\((https?:\/\/[^\s\)]+)\)/', function($m){
        return '<a href="'.esc_url($m[2]).'">'.esc_html($m[1]).'</a>';
    }, $md);

    // Lists: turn blocks of lines starting with * or - into <ul><li>...</li></ul>
    $md = preg_replace_callback('/(?:^|\n)((?:\s*[\*\-]\s+.+\n?)+)/m', function($m){
        $items = preg_split('/\n/', trim($m[1]));
        $lis = '';
        foreach($items as $it){
            $txt = preg_replace('/^\s*[\*\-]\s+/', '', $it);
            if($txt !== '') $lis .= '<li>'.esc_html($txt).'</li>';
        }
        return "\n<ul>".$lis."</ul>\n";
    }, $md);

    // Paragraphs: wrap loose lines that aren’t already HTML blocks
    $lines = preg_split('/\n{2,}/', trim($md));
    foreach($lines as &$blk){
        if(preg_match('/^\s*<(h1|h2|h3|ul|ol|li|p|img|blockquote|table|pre|code|figure|figcaption)\b/i', trim($blk))){
            // leave as-is
        } else {
            // wrap
            $blk = '<p>'.trim($blk).'</p>';
        }
    }
    return implode("\n\n", $lines);
}


add_action('seo_master_cron_generate','seo_master_run_scheduler');

/** Top-level page: Manual Generator (form + handler) */
function seo_master_manual_generator_page(){
    // Handle submission
    if (isset($_POST['seo_master_generate'])) {
        check_admin_referer('seo_master_manual_gen');

        $kw        = sanitize_text_field(isset($_POST['seo_master_kw']) ? $_POST['seo_master_kw'] : '');
        $sec_raw   = isset($_POST['seo_master_sec']) ? sanitize_text_field($_POST['seo_master_sec']) : '';
        $sec       = array_filter(array_map('trim', explode(',', $sec_raw)));
        $len       = max(400, intval(isset($_POST['seo_master_len']) ? $_POST['seo_master_len'] : 1000));
        $imgcount  = intval(isset($_POST['seo_master_imgcount']) ? $_POST['seo_master_imgcount'] : 0);
        $status    = (isset($_POST['seo_master_status']) && $_POST['seo_master_status']==='publish') ? 'publish' : 'draft';

        $data = array(
            'keyword'    => $kw,
            'secondary'  => implode(', ', $sec),
            'length'     => $len,
            'imgcount'   => $imgcount, // 0 means "use default"
            'categories' => array(intval(get_option('seo_master_default_category', 0))),
            'tags'       => get_option('seo_master_default_tags',''),
            'status'     => $status
        );

        $post_id = seo_master_generate_post($data);

        if ($post_id){
            echo '<div class="updated"><p>Draft created: <a href="'.esc_url(get_edit_post_link($post_id)).'">'.esc_html(get_the_title($post_id)).'</a></p></div>';
        } else {
            echo '<div class="error"><p>Generation failed. Check Logs for details.</p></div>';
        }
    }

    // Render form
    ?>
    <div class="wrap">
      <h1>Manual Generator</h1>
      <form method="post">
        <?php wp_nonce_field('seo_master_manual_gen'); ?>
        <table class="form-table">
          <tr>
            <th scope="row"><label for="seo_master_kw">Primary Keyword</label></th>
            <td><input type="text" id="seo_master_kw" name="seo_master_kw" class="regular-text" placeholder="e.g., Auxiliary Power Unit for Semi Truck" required></td>
          </tr>
          <tr>
            <th scope="row"><label for="seo_master_sec">Secondary Keywords</label></th>
            <td><input type="text" id="seo_master_sec" name="seo_master_sec" class="regular-text" placeholder="comma separated (optional)"></td>
          </tr>
          <tr>
            <th scope="row"><label for="seo_master_len">Target Length (words)</label></th>
            <td><input type="number" id="seo_master_len" name="seo_master_len" value="<?php echo intval(get_option('seo_master_default_length',1200)); ?>" min="400"></td>
          </tr>
          <tr>
            <th scope="row"><label for="seo_master_imgcount">Image Count</label></th>
            <td>
              <input type="number" id="seo_master_imgcount" name="seo_master_imgcount" value="<?php echo intval(get_option('seo_master_image_count',3)); ?>" min="0" max="10">
              <p class="description">Override for this post. Uses Settings → Default Image Count if left as-is.</p>
            </td>
          </tr>
          <tr>
            <th scope="row">Post Status</th>
            <td>
              <label><input type="radio" name="seo_master_status" value="draft" checked> Draft</label>
              &nbsp; <label><input type="radio" name="seo_master_status" value="publish"> Publish</label>
            </td>
          </tr>
        </table>
        <p><input type="submit" name="seo_master_generate" class="button button-primary" value="Generate Draft"></p>
      </form>
    </div>
    <?php
}

/**
 * Core generator used by manual + scheduler
 * $data:
 *  - keyword (string)
 *  - secondary (comma string)
 *  - length (int)
 *  - imgcount (int; 0 => use default)
 *  - categories (array of term IDs)
 *  - tags (comma string)
 *  - status (draft|publish)
 */
function seo_master_generate_post($data){
    $kw        = trim((string)$data['keyword']);
    $sec_str   = trim((string)$data['secondary']);
    $length    = max(600, intval($data['length']));
    $imgcount  = intval($data['imgcount']);
    if ($imgcount <= 0) $imgcount = max(0, intval(get_option('seo_master_image_count', 3)));

    // AI content (or template fallback)
    $sec_arr   = $sec_str ? array_map('trim', explode(',', $sec_str)) : array();
    $content   = seo_master_generate_text($kw, $sec_arr, $length);$content_raw = seo_master_generate_text($kw, $sec_arr, $length);

// Try to detect if it’s already HTML (has <h2>, <p>, etc.)
if (preg_match('/<h[1-6]\b|<p\b|<ul\b|<ol\b/i', $content_raw)) {
    $content = $content_raw;
} else {
    $content = seo_master_markdown_to_html($content_raw);
}


    // Title
    $title = ucwords(wp_strip_all_tags($kw));

    // Create post
    $post_id = wp_insert_post(array(
        'post_title'   => $title,
        'post_content' => wp_kses_post($content),
        'post_status'  => (isset($data['status']) ? $data['status'] : 'draft'),
        'post_type'    => 'post'
    ), true);

    if (is_wp_error($post_id)){
        seo_master_log('POST_CREATE','error: '.$post_id->get_error_message(), 500);
        return 0;
    }

    // Taxonomies
    if(!empty($data['categories'])){
        wp_set_post_terms($post_id, $data['categories'], 'category', false);
    }
    if(!empty($data['tags'])){
        $tags = array_filter(array_map('trim', explode(',', $data['tags'])));
        if ($tags) wp_set_post_terms($post_id, $tags, 'post_tag', false);
    }

    // Images (featured + inline)
    if ($imgcount > 0){
        $imgs = seo_master_fetch_images($kw, $imgcount);
        if (!empty($imgs)){
            $first = true;
            $content2 = get_post_field('post_content', $post_id);
            foreach ($imgs as $img){
                // $img can be string (url) or array('url'=>..., 'alt'=>...)
                $src = is_array($img) ? (isset($img['url'])?$img['url']:'') : $img;
                $alt = is_array($img) ? (isset($img['alt'])?$img['alt']:$kw) : $kw;
                if (!$src) continue;
                $att = seo_master_attach_image_to_post($src, $post_id, $alt);
                if ($att){
                    if ($first){ set_post_thumbnail($post_id, $att); $first=false; }
                    $content2 .= "\n\n" . wp_get_attachment_image($att, 'large');
                }
            }
            wp_update_post(array('ID'=>$post_id,'post_content'=>$content2));
        }
    }

    // Rank Math — title/description/focus keyword
    $desc = get_post_meta($post_id,'_seo_master_desc', true);
    if (!$desc) $desc = wp_trim_words(wp_strip_all_tags(get_post_field('post_content',$post_id)), 45);
    update_post_meta($post_id,'rank_math_title',        $title.' | '.get_bloginfo('name'));
    update_post_meta($post_id,'rank_math_description',  $desc);
    $focus = $kw . ($sec_str ? (', '.$sec_str) : '');
    update_post_meta($post_id,'rank_math_focus_keyword', $focus);
    update_post_meta($post_id,'_seo_master_desc',       $desc);

    // Add internal links (2 recent posts excluding this one)
    $recent = get_posts(array('numberposts'=>6,'post__not_in'=>array($post_id),'post_status'=>'publish'));
    $added = 0; $content3 = get_post_field('post_content',$post_id);
    foreach ($recent as $rp){
        if ($added >= 2) break;
        $content3 .= "\n<p>Related: <a href=\"".esc_url(get_permalink($rp->ID))."\">".esc_html(get_the_title($rp->ID))."</a></p>";
        $added++;
    }

    // Add 2 credible external links
    $q = urlencode($kw);
    $content3 .= "\n<p>Further reading: <a href=\"https://en.wikipedia.org/wiki/Special:Search?search={$q}\" rel=\"noopener nofollow\">Wikipedia</a> · <a href=\"https://www.britannica.com/search?query={$q}\" rel=\"noopener nofollow\">Britannica</a></p>";
    wp_update_post(array('ID'=>$post_id,'post_content'=>$content3));

    // Auto index on publish/update
    $url = get_permalink($post_id);
    if ($url) seo_master_google_index_now($url,'URL_UPDATED');

    return $post_id;
}

/** Scheduler worker: picks a random keyword and generates a post */
function seo_master_run_scheduler(){
    if (!get_option('seo_master_schedule_enabled', 0)) return;

    $list_raw = trim(get_option('seo_master_keywords_list',''));
    if (!$list_raw) return;
    $arr = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $list_raw)));
    if (empty($arr)) return;

    $kw = $arr[array_rand($arr)];
    $data = array(
        'keyword'    => $kw,
        'secondary'  => '',
        'length'     => intval(get_option('seo_master_default_length',1200)),
        'imgcount'   => intval(get_option('seo_master_image_count',3)),
        'categories' => array(intval(get_option('seo_master_default_category',0))),
        'tags'       => get_option('seo_master_default_tags',''),
        'status'     => 'publish'
    );
    seo_master_generate_post($data);
}

/**
 * Minimal fallback writer (used when external AI is disabled or fails).
 */
function seo_master_template_writer($kw, $sec_str, $length){
    $title = ucwords(wp_strip_all_tags($kw));
    $p     = max(4, intval($length/220)); // number of short sections

    $out  = '# ' . $title . "\n\n";
    $out .= 'When this topic comes up, most people want practical guidance rather than textbook definitions. Below is a concise, human-readable primer you can act on.' . "\n\n";

    $out .= "## What this covers\n\n";
    $out .= "- A quick overview\n- Practical tips\n- Things to avoid\n- What to do next\n\n";

    for ($i=1; $i<=$p; $i++){
        $out .= "## Tip " . $i . "\n\n";
        $out .= "Keep it simple: explain what to do, why it matters, and how to measure progress. Use one short example to make it concrete.\n\n";
        $out .= "- One quick win\n- One common mistake\n- One metric to track\n\n";
    }

    $out .= "## Wrapping up\n\n";
    $out .= "Pick one idea and try it this week. Small steps add up quickly.\n";
    return $out;
}
