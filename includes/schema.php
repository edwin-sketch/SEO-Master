<?php
if (!defined('ABSPATH')) exit;

/**
 * JSON-LD schema for posts
 * - Author = actual WP post author (name, URL, avatar)
 * - Uses featured image, title, description, dates, publisher logo
 * - Description priority: _seo_master_desc → Rank Math desc → excerpt → trimmed content
 */
add_action('wp_head', function () {
  if (!is_singular('post')) return;
  global $post;
  if (!$post) return;

  // Author
  $aid    = (int) $post->post_author;
  $author = array(
    '@type' => 'Person',
    'name'  => get_the_author_meta('display_name', $aid),
    'url'   => get_author_posts_url($aid),
  );
  $avatar = get_avatar_url($aid);
  if ($avatar) $author['image'] = $avatar;

  // Featured image
  $img_id = get_post_thumbnail_id($post->ID);
  $image  = $img_id ? wp_get_attachment_image_url($img_id, 'full') : '';

  // Description (fill if empty)
  $desc = get_post_meta($post->ID, '_seo_master_desc', true);
  if (!$desc) {
    $rm = get_post_meta($post->ID, 'rank_math_description', true);
    if ($rm) $desc = $rm;
  }
  if (!$desc) {
    $excerpt = has_excerpt($post) ? get_the_excerpt($post) : '';
    if ($excerpt) $desc = $excerpt;
  }
  if (!$desc) {
    $desc = wp_trim_words(wp_strip_all_tags($post->post_content), 45);
  }

  // Schema object
  $schema = array(
    '@context'         => 'https://schema.org',
    '@type'            => 'BlogPosting',
    'mainEntityOfPage' => get_permalink($post),
    'headline'         => get_the_title($post),
    'description'      => $desc,
    'image'            => $image,
    'datePublished'    => get_the_date('c', $post),
    'dateModified'     => get_the_modified_date('c', $post),
    'author'           => $author,
    'publisher'        => array(
      '@type' => 'Organization',
      'name'  => get_bloginfo('name'),
      'logo'  => array(
        '@type' => 'ImageObject',
        'url'   => get_site_icon_url()
      ),
    ),
  );

  echo '<script type="application/ld+json">' . wp_json_encode($schema) . '</script>';
}, 99);
```0
