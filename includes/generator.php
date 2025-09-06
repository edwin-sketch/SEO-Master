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
