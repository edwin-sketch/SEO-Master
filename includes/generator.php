<?php
if (!defined('ABSPATH')) exit;

/** Minimal Markdown → HTML converter (kept from your working build) */
function seo_master_markdown_to_html($md){
    if(!$md) return '';
    $md = str_replace("\r\n", "\n", $md);
    $md = preg_replace('/^(#{2,3})\s*H[23]:\s*/m', '$1 ', $md);
    $md = preg_replace('/^\s*```.*$/m', '', $md);
    $md = preg_replace('/^###\s+(.*)$/m', '<h3>$1</h3>', $md);
    $md = preg_replace('/^##\s+(.*)$/m',  '<h2>$1</h2>', $md);
    $md = preg_replace('/^#\s+(.*)$/m',   '<h1>$1</h1>', $md);
    $md = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $md);
    $md = preg_replace('/\*(.+?)\*/s',     '<em>$1</em>', $md);
    $md = preg_replace_callback('/(.*?)(https?:\/\/[^\s]+)\)/', function($m){
        return '<a href="'.esc_url($m[2]).'">'.esc_html($m[1]).'</a>';
    }, $md);
    $md = preg_replace_callback('/(?:^|\n)((?:\s*[\*\-]\s+.+\n?)+)/m', function($m){
        $items = preg_split('/\n/', trim($m[1])); $lis='';
        foreach($items as $it){ $txt=preg_replace('/^\s*[\*\-]\s+/', '', $it); if($txt!=='') $lis.='<li>'.esc_html($txt).'</li>'; }
        return "\n<ul>".$lis."</ul>\n";
    }, $md);
    $blocks = preg_split('/\n{2,}/', trim($md));
    foreach($blocks as &$blk){
        if(!preg_match('/^\s*<(h1|h2|h3|ul|ol|li|p|img|blockquote|table|pre|code|figure|figcaption)\b/i', trim($blk))){
            $blk = '<p>'.trim($blk).'</p>';
        }
    }
    return implode("\n\n", $blocks);
}

/** NEW: get style seed & angle to vary tone/structure each time */
function seo_master_style_seed(){
    $seeds = array(
        'practical-howto','case-led','myth-vs-fact','faq-style','checklist-led',
        'story-led','expert-tips','common-mistakes','data-led','comparison-led'
    );
    return $seeds[array_rand($seeds)];
}
function seo_master_angle_for($keyword){
    $angles = array(
        "Beginner’s overview with plain language and examples.",
        "Common mistakes to avoid, with fixes and quick wins.",
        "Actionable checklist with steps and simple metrics.",
        "Mini case study + what to copy and what to skip.",
        "FAQ format answering real questions concisely."
    );
    return $angles[array_rand($angles)];
}

/** NEW: avoid repeating keywords/titles recently */
function seo_master_recent_title_or_kw_exists($title, $keyword){
    $titles = get_option('seo_master_recent_titles', array());
    $kws    = get_option('seo_master_recent_kws', array());
    $title  = wp_strip_all_tags($title);
    if(in_array(mb_strtolower($title), $titles, true)) return true;
    if(in_array(mb_strtolower($keyword), $kws, true)) return true;
    return false;
}
function seo_master_push_recent_title_kw($title,$keyword){
    $titles = get_option('seo_master_recent_titles', array());
    $kws    = get_option('seo_master_recent_kws', array());
    array_push($titles, mb_strtolower(wp_strip_all_tags($title)));
    array_push($kws, mb_strtolower($keyword));
    $titles = array_slice(array_values(array_unique($titles)), -50);
    $kws    = array_slice(array_values(array_unique($kws)), -50);
    update_option('seo_master_recent_titles',$titles,false);
    update_option('seo_master_recent_kws',$kws,false);
}

/** NEW: similarity check vs. recent published posts */
function seo_master_is_too_similar($html){
    $recent = get_posts(array('numberposts'=>6,'post_status'=>'publish','post_type'=>'post'));
    $plain  = wp_strip_all_tags($html);
    foreach($recent as $p){
        $txt = wp_strip_all_tags($p->post_content);
        similar_text($plain, $txt, $percent);
        if($percent >= 70){ return true; } // threshold
    }
    return false;
}

add_action('seo_master_cron_generate','seo_master_run_scheduler');

function seo_master_manual_generator_page(){
    if (isset($_POST['seo_master_generate'])) {
        check_admin_referer('seo_master_manual_gen');

        $kw        = sanitize_text_field($_POST['seo_master_kw']);
        $sec_raw   = isset($_POST['seo_master_sec']) ? sanitize_text_field($_POST['seo_master_sec']) : '';
        $sec       = array_filter(array_map('trim', explode(',', $sec_raw)));
        $len       = max(400, intval($_POST['seo_master_len']));
        $imgcount  = intval($_POST['seo_master_imgcount']);
        $status    = (isset($_POST['seo_master_status']) && $_POST['seo_master_status']==='publish') ? 'publish' : 'draft';

        $data = array(
            'keyword'    => $kw,
            'secondary'  => implode(', ', $sec),
            'length'     => $len,
            'imgcount'   => $imgcount,
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

    ?>
    <div class="wrap">
      <h1>Manual Generator</h1>
      <form method="post">
        <?php wp_nonce_field('seo_master_manual_gen'); ?>
        <table class="form-table">
          <tr>
            <th><label for="seo_master_kw">Primary Keyword</label></th>
            <td><input type="text" id="seo_master_kw" name="seo_master_kw" class="regular-text" required></td>
          </tr>
          <tr>
            <th><label for="seo_master_sec">Secondary Keywords</label></th>
            <td><input type="text" id="seo_master_sec" name="seo_master_sec" class="regular-text" placeholder="comma separated (optional)"></td>
          </tr>
          <tr>
            <th><label for="seo_master_len">Target Length (words)</label></th>
            <td><input type="number" id="seo_master_len" name="seo_master_len" value="<?php echo intval(get_option('seo_master_default_length',1200)); ?>" min="400"></td>
          </tr>
          <tr>
            <th><label for="seo_master_imgcount">Image Count</label></th>
            <td><input type="number" id="seo_master_imgcount" name="seo_master_imgcount" value="<?php echo intval(get_option('seo_master_image_count',3)); ?>" min="0" max="10"></td>
          </tr>
          <tr>
            <th>Post Status</th>
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

/** Core generator used by manual + scheduler */
function seo_master_generate_post($data){
    $kw        = trim((string)$data['keyword']);
    $sec_str   = trim((string)$data['secondary']);
    $length    = max(600, intval($data['length']));
    $imgcount  = intval($data['imgcount']);
    if ($imgcount <= 0) $imgcount = max(0, intval(get_option('seo_master_image_count', 3)));

    // NEW: reject if keyword/title used recently
    $title = ucwords(wp_strip_all_tags($kw));
    if (seo_master_recent_title_or_kw_exists($title, $kw)) {
        seo_master_log('POST_SKIP','Duplicate keyword/title recently: '.$kw,409);
        return 0;
    }

    // Build varied prompt by seeding style + angle
    $seed  = seo_master_style_seed();       // NEW
    $angle = seo_master_angle_for($kw);     // NEW
    $sec_arr = $sec_str ? array_map('trim', explode(',', $sec_str)) : array();

    // First pass content
    $content_raw = seo_master_generate_text(
        $kw.' | style_seed: '.$seed.' | angle: '.$angle,
        $sec_arr,
        $length
    );
    $content = preg_match('/<h[1-6]\b|<p\b|<ul\b|<ol\b/i', $content_raw) ? $content_raw : seo_master_markdown_to_html($content_raw);

    // If too similar, try up to 2 different angles
    $tries = 0;
    while (seo_master_is_too_similar($content) && $tries < 2){
        $tries++;
        $seed  = seo_master_style_seed();
        $angle = seo_master_angle_for($kw);
        $content_raw = seo_master_generate_text(
            $kw.' | style_seed: '.$seed.' | angle: '.$angle,
            $sec_arr,
            $length
        );
        $content = preg_match('/<h[1-6]\b|<p\b|<ul\b|<ol\b/i', $content_raw) ? $content_raw : seo_master_markdown_to_html($content_raw);
    }

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
    if(!empty($data['categories'])) wp_set_post_terms($post_id, $data['categories'], 'category', false);
    if(!empty($data['tags'])){
        $tags = array_filter(array_map('trim', explode(',', $data['tags'])));
        if ($tags) wp_set_post_terms($post_id, $tags, 'post_tag', false);
    }

    // Images (featured + inline) — unique URLs per post & globally handled in images.php
    if ($imgcount > 0){
        $imgs = seo_master_fetch_images($kw, $imgcount);
        if (!empty($imgs)){
            $first = true;
            $content2 = get_post_field('post_content', $post_id);
            foreach ($imgs as $img){
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

    // Final content for Rank Math
    $final_content = get_post_field('post_content', $post_id);
    $desc = wp_trim_words(wp_strip_all_tags($final_content), 45);
    update_post_meta($post_id,'rank_math_title',         $title.' | '.get_bloginfo('name'));
    update_post_meta($post_id,'rank_math_description',   $desc);
    $focus = $kw . ($sec_str ? (', '.$sec_str) : '');
    update_post_meta($post_id,'rank_math_focus_keyword', $focus);
    update_post_meta($post_id,'_seo_master_desc',        $desc);

    // Internal links (2 recent) — avoid duplicates in content
    $recent = get_posts(array('numberposts'=>8,'post__not_in'=>array($post_id),'post_status'=>'publish'));
    $added = 0; $content3 = $final_content;
    $linked_ids = array();
    foreach ($recent as $rp){
        if ($added >= 2) break;
        if (in_array($rp->ID, $linked_ids, true)) continue;
        $link = '<p>Related: <a href="'.esc_url(get_permalink($rp->ID)).'">'.esc_html(get_the_title($rp->ID)).'</a></p>';
        if (strpos($content3, get_permalink($rp->ID)) === false){
            $content3 .= "\n".$link;
            $linked_ids[] = $rp->ID;
            $added++;
        }
    }
    // External links (stable, credible)
    $q = urlencode($kw);
    if (strpos($content3,'wikipedia.org')===false || strpos($content3,'britannica.com')===false){
        $ext = "\n<p>Further reading: <a href=\"https://en.wikipedia.org/wiki/Special:Search?search={$q}\" rel=\"noopener nofollow\">Wikipedia</a> · <a href=\"https://www.britannica.com/search?query={$q}\" rel=\"noopener nofollow\">Britannica</a></p>";
        $content3 .= $ext;
    }
    wp_update_post(array('ID'=>$post_id,'post_content'=>$content3));

    // Track recent title/keyword to avoid repeats
    seo_master_push_recent_title_kw($title,$kw);

    // Auto index
    $url = get_permalink($post_id);
    if ($url) seo_master_google_index_now($url,'URL_UPDATED');

    return $post_id;
}

function seo_master_run_scheduler(){
    if (!get_option('seo_master_schedule_enabled', 0)) return;
    $list_raw = trim(get_option('seo_master_keywords_list',''));
    if (!$list_raw) return;
    $arr = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $list_raw)));
    if (empty($arr)) return;

    // Pick a random keyword not used recently
    shuffle($arr);
    $kw = ''; $title = '';
    foreach ($arr as $cand){
        $title = ucwords(wp_strip_all_tags($cand));
        if (!seo_master_recent_title_or_kw_exists($title,$cand)){ $kw = $cand; break; }
    }
    if (!$kw){ seo_master_log('POST_SKIP','All scheduler keywords recently used.',409); return; }

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

/** Fallback writer (unchanged) */
function seo_master_template_writer($kw, $sec_str, $length){
    $title = ucwords(wp_strip_all_tags($kw));
    $p     = max(4, intval($length/220));
    $out  = '# ' . $title . "\n\n";
    $out .= "People want clear, practical guidance. Here's a concise primer you can act on.\n\n";
    $out .= "## What this covers\n\n- Quick overview\n- Practical tips\n- Pitfalls to avoid\n- Next steps\n\n";
    for ($i=1; $i<=$p; $i++){
        $out .= "## Tip " . $i . "\n\n";
        $out .= "Explain what to do, why it matters, and how to measure progress. Include one concrete example.\n\n";
        $out .= "- One quick win\n- One common mistake\n- One metric to track\n\n";
    }
    $out .= "## Wrapping up\n\nPick one idea and try it this week. Small steps add up quickly.\n";
    return $out;
}
