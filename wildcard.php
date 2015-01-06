<?php
/**
 * Plugin Name: Wildcard
 * Plugin URI: http://www.trywildcard.com/docs
 * Description: This plugin quickly formats your Wordpress site for inclusion into the Wildcard mobile application.
 * Version: 1.1
 * Author: Connor McEwen
 * Author URI: http://cmcewen.com
 * License: GPL2
 */
defined('ABSPATH') or die("No script kiddies please!");

class Wildcard_API {
  public function __construct(){
    add_filter('query_vars', array($this, 'add_query_vars'), 0);
    add_action('parse_request', array($this, 'sniff_requests'), 0);
  }

  public function add_query_vars($vars){
    $vars[] = '__wildcard';
    $vars[] = 'url';
    return $vars;
  }

  public static function add_endpoint(){
    add_rewrite_rule('^wildcard-api(?:\?(.*))?/?$','index.php?__wildcard=1&url=$matches[1]','top');
    flush_rewrite_rules();
  }

  public function sniff_requests(){
    global $wp;
    if(isset($wp->query_vars['__wildcard'])){
      $this->handle_request();
      exit;
    }
  }

  protected function handle_request(){
    global $wp;
    $wildcard_url = $wp->query_vars['url'];
    $wildcard_json = $this->get_article_card( $wildcard_url );
    header('content-type: application/json; charset=utf-8');
    if($wildcard_json)
      echo json_encode($wildcard_json);
    else
      echo 'Could not find post from that URL';
  }

  public static function plugin_activated() {
    $notice=true;
    update_option('wildcard_show_activation_notice', $notice);
  }

  public static function plugin_deactivated() {
    flush_rewrite_rules();
  }

  public static function initialize_plugin() {
    if ($notice=get_option('wildcard_show_activation_notice')) {
      add_action( 'admin_notices', array( 'Wildcard_API','activation_success'));
      $notice=false;
      update_option('wildcard_show_activation_notice', $notice);
    }
  }

  public static function activation_success() {
    ?>
    <div class="updated">
    <p><a target="_blank" href="http://pair.trywildcard.com/wordpress">The Wildcard plugin was activated! You must contact us to finish adding your site to the Wildcard app - click this message to complete your installation.</a></p>
    </div>
    <?php
  }

  public function get_article_card( $url ) {
    global $post;
    $postid = url_to_postid( $url );
    if ($postid == 0) {
      return false;
    }
    $current_post = get_post($postid);
    $post = $current_post;
    setup_postdata($post);
    $data = array(
      'card_type' => 'article',
      'pair_version' => '0.3.1',
      'web_url' => $url,
      'article' => array(
        'title' => html_entity_decode(get_the_title($current_post),ENT_NOQUOTES,'UTF-8'),
        'html_content' => apply_filters('the_content', $current_post->post_content),
        'abstract_content' => html_entity_decode(strip_tags(get_the_excerpt()),ENT_NOQUOTES,'UTF-8'),
        'publication_date' => get_the_time('c', $current_post),
        'updated_date' => get_post_modified_time('c', null, $current_post->ID, true),
        'author' => get_the_author_meta('display_name', $current_post->post_author),
        'source' => get_bloginfo('show', $current_post)
      )
    );
    $tag_objects = get_the_tags($current_post);
    if ($tag_objects) {
      $tags = array_map(create_function('$o', 'return $o->name;'), $tag_objects);
      $data['keywords'] = array_values($tags);
    }
    $test_for_image = true;
    if (preg_match('/\[embed(.*)](.*)\[\/embed]/', $current_post->post_content, $matches)) {
      $plain_url = str_replace('[/embed]', '', str_replace('[embed]', '', $matches[0]));
      $iframe = wp_oembed_get($plain_url);
      if (preg_match('/<iframe(.*)src(.*)=(.*)"(.*)"/U', $iframe, $result)) {
        $defaults = wp_embed_defaults();
        $data['article']['media'] = array(
          'type' => 'video',
          'title' => html_entity_decode(get_the_title($current_post),ENT_NOQUOTES,'UTF-8'),
          "embedded_url_width" => $defaults["width"],
          "embedded_url_height" => round($defaults["width"] * 9.0 / 16.0),
          "embedded_url" => array_pop($result)
          );
        $test_for_image = false;
      }
    }
    else if ($test_for_image) {
      if (has_post_thumbnail($postid)) {
        $data['article']['media'] = array(
          'type' => 'image',
          'image_url' =>  wp_get_attachment_url(get_post_thumbnail_id($postid))
        );
      }
      else {
        $attachments = get_children( array('post_parent' => $postid, 'post_type' => 'attachment', 'post_mime_type' => 'image') );
        if ( $attachments ) {
          $attachment = array_shift($attachments);
          $image = wp_get_attachment_image_src($attachment->ID);
          $data['article']['media'] = array(
            'type' => 'image',
            'image_url' => $image[0]
          );
        }
        else {
          $content = $current_post->post_content;
          $searchimages = '~<img [^>]* />~';
          preg_match_all( $searchimages, $content, $pics );
          $iNumberOfPics = count($pics[0]);
          if ( $iNumberOfPics > 0 ) {
            preg_match('/<img(.*)src(.*)=(.*)"(.*)"/U', $pics[0][0], $img_src);
            $img = array_pop($img_src);
            $data['article']['media'] = array(
            'type' => 'image',
            'image_url' => $img
            );
          }
        }
      }
    }
    return $data;
  }
}
add_action( 'admin_init', array('Wildcard_API','initialize_plugin' ));
add_action( 'admin_init', array('Wildcard_API','add_endpoint' ));
register_activation_hook(__FILE__, array('Wildcard_API', 'plugin_activated' ));
register_deactivation_hook(__FILE__, array('Wildcard_API', 'plugin_deactivated' ));
new Wildcard_API();
?>