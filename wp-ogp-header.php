<?php
/*
Plugin Name: WP OGP header
Plugin URI:
Description:
Tags: OGP, Open Graph Protocol, Open Graph, Dublin Core, Dublin Core Metadata Element Set (DCMES), DCMES, ISO15836
Version: 0.2
Author: Kris
Author URI:
License: 
*/

/*
DISCLAIMER:
This plugin is inspired by the plugin 'WP Open Graph Meta'
by Sven Haselb�ck and I have added several more tags, regarding
the use of the Open Graph Protocol (see http://ogp.me) and the meta-tags,
which for now (mid-2012) are officialy recognized for use.


SUPPORTED:
The tags i have added support for, are the following

General
- og:title
- og:description
- og:url
- og:site_name
- og:type
- og:local

Author bio
- profile:first_name
- profile:middle_name	(!!! a custom tweake I have made to the wp-core in my own installation !!!)
- profile:last_name
- profile:username

Single post/page
- article:author
- article:section		(outputs all sections the post/page are in)
- article:tag			(outputs all tags the post/page has attached)
- article:published_time
- article:modified_time
- og:image
- og:image:type
- og:image:width
- og:image:height

TO-DO:
- Determine if og:locale:alternative is nessesary. If proved, implement it in the plugin.
- Add support for the 'fb:admins' and 'fb:app_id' tags.
- Add support for the twitter tags listed (to many to list here) in the 'twitter_ogp()' and 'twitter_video_ogp()' functions.
- Add support for street address (somehow) on the author bio page, if adress is provided.


DOCUMENTATION SCHEMA, OPEN GRAPH PROTOCOL AND DUBLIN CORE:
Schema
- http://schema.org/
Open Graph Protocol
- http://ogp.me/
Dublin Core
- http://bublincore.org/
- http://dublincore.org/documents/dc-html/
*/

require('validate-mime-type.php');
require('allowed-scheme-values.php');
/**
 * Checks for other plugin activated.
 */
/*function plugins_active() {
	// Get options array.
	$options = get_option('add_meta_tags_opts');
	
	// Get specific options.
	$auto_dublincore = $options['auto_dublincore'];
	$auto_opengraph = $options['auto_opengraph'];
	
	// Check if the specific options is enabled.
	$do_auto_dublincore = ($options['auto_dublincore'] == '1') ? true : false;
	$do_auto_opengraph = ($options['auto_opengraph'] == '1') ? true : false;
	
	// Outputs value true/false to function return array for every option checked.
	$array = array();
	if ($do_auto_dublincore == true) $array[] = true; else $array[] = false;
	if ($do_auto_opengraph == true) $array[] = true; else $array[] = false;
	return $array;
}*/

class wp_ogp_header
{
	/**
	 * Nimmt die Daten der verschiedenen Meta-Elemente auf, um diese geb�ndelt
	 * in einer Funktion ausgeben zu k�nnen.
	 *
	 * @var array
	 */
	protected $_meta = array();	// <meta property="" content="" />
	protected $_meta2 = array(); // <meta name="" value="" />
	protected $_metadc = array(); // <meta name="" content="" />
	
	protected $_html_xmlns = array(); // <html xmlns="">
	protected $_head_prefix = array(); // <head prefix="">
	
	/**
	 * Constructer
	 */
	public function __construct()
	{
		if (!is_admin()) {
			add_action('wp_head', array($this, 'add_elements'));
		} else {
			// Konfiguration via WP-Admin folgt
		}
	}
	
	/**
	 * Add elements for articles / posts / pages / etc.
	 * 
	 * Which basically are every page available for
	 * public view on the current wordpress install.
	 */
	public function add_elements()
	{
		$this->_get_title(); // og:title + dc.title
		$this->_get_description(); // og:description + dc.description
		
		$this->_meta['og:url'] = $this->_add_url_of_the_current_page();
		$this->_meta['og:site_name'] = is_home() || is_front_page() ? get_bloginfo('name') .' - '. get_bloginfo('url').'/' : get_bloginfo('name')  .' - '. get_bloginfo('url').'/' ;
		
		if (is_single() && !is_attachment()) {
			if(in_array(get_post_format(), array('aside','image','video','quote','link'))) {
				$this->_meta['og:type'] = get_post_format();
			} else {
				$this->_meta['og:type'] = 'article';
				$this->_metadc['type']['DCMIType'] = 'text';
				$this->_metadc['format']['IMT'] = 'text/html';
			}
		}
		elseif (is_home() || is_front_page()) {
			$this->_meta['og:type'] = 'blog';
			$this->_metadc['format']['IMT'] = 'text/html';
		}
		elseif (is_404() || is_search()) {
			$this->_meta['og:type'] = 'website';
		}
		elseif (is_author()) {
			$this->_meta['og:type'] = 'Profile page';
			$this->_metadc['type'] = 'Profile page';
		}
		elseif (is_attachment()) {
			$attachment_id = get_post_thumbnail_id();
			$attachment_mime_type = get_post_mime_type($attachment_id);
			$attachment_type = get_attachment_mime_type($attachment_mime_type);
			
			$this->_meta['og:type'] = $attachment_type;
			$this->_metadc['type'] = $attachment_type;
			$this->_metadc['format'] = $attachment_mime_type;
		}
		
		$this->_meta['og:locale'] = str_replace('-', '_', get_bloginfo('language'));
		//$this->_meta['og:locale:alternative']  = str_replace('-', '_', get_bloginfo('language'));
		$lang_code = explode('-', get_bloginfo('language'));
		$this->_metadc['language']['RFC1766'] = strtolower($lang_code[0]);
		
		if (is_author()) {
			$this->_add_author_bio();	
			//$this->_add_street_address();
			//$this->_add_contact_information();
		}
		
		if (is_single() || is_page()) {
			global $post;
			$this->_add_arcticle_author_information($post->post_author);
			
			//$this->_add_twitter_ogp($id);
		}
		
		if (is_single()) {
			global $post; $id = $post->ID;
			$this->_add_post_categories($id);
			$this->_add_post_tags($id);
		}
		if (is_attachment() || is_single()) {
			global $post; $id = $post->ID;
			$this->_add_image($id);
		}
		
		if (is_single()) {
			$this->_video_posts();
			rewind_posts();
		}
		
		$this->_output();
	}
	
	/**
     * Gibt den Title f�r das Meta-Element zur�ck. Wenn der Title via wpSEO
     * oder All in One SEO Pack gesetzt worden ist, wird dieser bevorzugt
     *
     * @return string
     */
    protected function _get_title()
    {
			$title = null;

			if (class_exists('wpSEO_Base')) {
					$title = trim(get_post_meta(get_the_ID($id), '_wpseo_edit_title', true));
			} else if (function_exists('aiosp_meta')) {
					$title = trim(get_post_meta(get_the_ID($id), '_aioseop_title', true));
			}

			if (!isset($title) && empty($title)) {				
				global $post;
				$id = $post->ID;
				
				if (is_tag()) {
					$title .= 'Tag Archive for '. ucfirst(single_tag_title("", false));
					$title .= ' - ';
				}
				elseif (is_archive()) {
					$title .= get_the_title($id);
					$title .= ' Archive - ';
				}
				elseif (is_search()) {
					$title .= 'Search for &quot;'.wp_specialchars($s).'&quot; - ';
				}
				elseif (!(is_404()) && (is_single()) || (is_page())) {
					$title .= get_the_title($id);
					$title .= ' - ';
				}
				elseif (is_404()) {
					$title .= 'Not Found - ';
				}
				if (is_home()) {
					$title .= get_bloginfo('name');
					$title .= ' - ';
					$title .= get_bloginfo('description');
				}
				else {
					$title .= get_bloginfo('name');
				}
				if ($paged > 1) {
					$title .= ' - page '. $paged;
				}
			}
			$this->_meta['og:title'] = $title;
			$this->_metadc['title'] = $title;
    }

    /**
     * Gibt die Description f�r das Meta-Element zur�ck. Wenn die Description
     * via wpSEO oder All in One SEO Pack gesetzt worden ist, wird diese
     * bevorzugt
     *
     * @return mixed|string
     */
		protected function _get_description_wpSEO_Base_or_aiosp_meta($id)
		{
			if (class_exists('wpSEO_Base')) {
				return trim(get_post_meta(get_the_ID($id), '_wpseo_edit_description', true));
			}
			elseif (function_exists('aiosp_meta')) {
				return trim(get_post_meta(get_the_ID($id), '_aioseop_description', true));
			}
			
			elseif (is_single() && has_excerpt()) {
				return strip_tags(get_the_excerpt($id));
			}
			elseif (is_tag()) {
				global $wpdb;
				$tag_name = single_tag_title("", false);
				$term_id = $wpdb->get_var("SELECT * FROM ".$wpdb->terms." WHERE  `name` =  '".$tag_name."'");
				$term = get_term($term_id, 'post_tag');
				return $term->description;
			}
			elseif (is_category()) {
				$cat_id = get_query_var('cat');
				$cat = get_term($cat_id, 'category');
				return $cat->description;
			}
			elseif (is_attachment()) {
				$args = array(
					'post_type' => 'attachment',
					'orderby' => 'menu_order',
					'order' => 'ASC',
					'post_mime_type' => 'image',
					'post_status' => null,
					'numberposts' => null,
					'post_parent' => $id);
				$alt = get_post_meta($attachment->ID, '_wp_attachment_image_alt', true);
				$image_title = $attachment->post_title;
				$caption = $attachment->post_excerpt;
				$description = $image->post_content;
				$attachments = get_posts($args);
				if ($attachments) {
					foreach ( $attachments as $attachment ) {
						if ($description) { return $description; }
						elseif ($caption) { return $caption; }
						elseif ($alt) { return $alt; }
					}
				}
			}
			else {
				return get_bloginfo('description');
			}
		}		
    protected function _get_description()
    {
			global $post;
			
			$id = $post->ID;
			$description = $this->_get_description_wpSEO_Base_or_aiosp_meta($id);
			
			if ($description) {
				$this->_meta['og:description'] = $description;
				$this->_metadc['description'] = $description;
			}
			
    }
	
	/**
	 * Get the url of the current page
	 */
	protected function _add_url_of_the_current_page()
	{
		global $post;
		
		$id = $post->ID;
		
		if (is_home() || is_front_page()) {
			return get_bloginfo('url') .'/';
		}
		elseif (is_singular()) {
			return get_permalink($id);
		}
		elseif (is_category()) {
			$cat = get_the_category();
			return get_category_link($cat[0]->term_id);
		}
		elseif (is_tag()) {
			global $wpdb;
			
			$tag_name = single_tag_title("", false);
			$tag_ID = $wpdb->get_var("SELECT * FROM ".$wpdb->terms." WHERE  `name` =  '".$tag_name."'");
			return get_tag_link($tag_ID);
		}
		elseif (is_author()) {
			return get_author_posts_url($id);
		}
		else {
			//return get_permalink($id);
		}
	}
	
	/**
	 * Gets information about the author of the post/page
	 */
	protected function _add_arcticle_author_information($id)
	{
		$display_name   = get_the_author_meta('display_name', $id);
		$nickname       = get_the_author_meta('nickname', $id);
		$user_firstname = get_the_author_meta('user_firstname', $id);
		$user_lastname  = get_the_author_meta('user_lastname', $id);
		
		$authorinfos = array();
		$authorinfos[] = (isset($nickname) && !empty($nickname) && ($nickname !== $display_name)) ? $user_firstname.' '.$user_lastname.' (aka. '.$display_name.' / '.$nickname .')' : $user_firstname.' '.$user_lastname.' (aka. '.$display_name.')';
		
		if( is_array($authorinfos) && count($authorinfos) > 0 ) {
			foreach( $authorinfos as $authorinfo ) {
				$this->_meta['article:author'][] = $authorinfo;
				$this->_metadc['creator'][] = $authorinfo;
			}
		}
	}
	
	/**
	 * Catch all images related to the current post/page and display them via
	 * the og:image tag
	 *
	 * NB:
	 * 1) The function captures if the post has a featured image set via the
	 * wordpress default method. 2) And all other images, wordpress regards as
	 * directly attached to the post. Directly attached images are, if the
	 * images are inserted in the posts via the default method, which outputs
	 * "ugly" html-code directly in the post.
	 */
	protected function _add_image($id)
	{
		// use featured image if one exists
		if (has_post_thumbnail()) {
			$get_post_thumbnail_id = get_post_thumbnail_id();
			$feat = wp_get_attachment_image_src($get_post_thumbnail_id, 'large');
			
			if (esc_attr($feat[1]) >= 768 || 768 <= esc_attr($feat[2])) {
				$feat = wp_get_attachment_image_src($get_post_thumbnail_id, 'medium');
			}
			
			$this->_meta['og:image'][]        = esc_attr($feat[0]);
			$this->_meta['og:image:type'][]   = get_post_mime_type($get_post_thumbnail_id);
			$this->_meta['og:image:width'][]  = esc_attr($feat[1]);
			$this->_meta['og:image:height'][] = esc_attr($feat[2]);
		}
		
		// Checks if there are post attachements for the post
		$post_attachments = get_posts(array(
			'post_type' => 'attachment',
			'post_mime_type' => 'image',
			'orderby' => 'menu_order',
			'order' => 'ASC',
			'post_per_page' => -1,
			'post_parent' => $id ));
		
		if (is_array($post_attachments) && count(post_attachments) > 0) {
			foreach ($post_attachments as $post_attachment) {
				$post_attachment_meta = wp_get_attachment_image_src($post_attachment->ID, 'large');
				
				if ($post_attachment_meta[1] > 768 || 768 < $post_attachment_meta[2]) {
					$post_attachment_meta = wp_get_attachment_image_src($post_attachment->ID, 'medium');
				}
				
				// og:image - og:image:type - og:image:width - og:image:height
				$this->_meta['og:image'][]        = $post_attachment_meta[0];
				$this->_meta['og:image:type'][]   = $post_attachment->post_mime_type;
				$this->_meta['og:image:width'][]  = $post_attachment_meta[1];
				$this->_meta['og:image:height'][] = $post_attachment_meta[2];
			}
		}
			
		// The function checks for an attachement and if the attachment is
		// an image. It only runs if both conditions are true.
		if (is_attachment()) {
			$get_post_thumbnail_id = get_post_thumbnail_id();
			$attachment_mime_type = get_post_mime_type($get_post_thumbnail_id);
			
			if (get_attachment_mime_type($attachment_mime_type, 'image')) {
				$feat = wp_get_attachment_image_src($get_post_thumbnail_id, 'large');
				
				if (esc_attr($feat[1]) >= 768 || 768 <= esc_attr($feat[2])) {
					$feat = wp_get_attachment_image_src($get_post_thumbnail_id, 'medium');
				}
				
				$this->_meta['og:image']        = esc_attr($feat[0]);
				$this->_meta['og:image:type']   = $attachment_mime_type;
				$this->_meta['og:image:width']  = esc_attr($feat[1]);
				$this->_meta['og:image:height'] = esc_attr($feat[2]);
			}
		}
	}
	
	/**
	 * Get all post categories, if current page is single.php
	 */
	protected function _add_post_categories($id)
	{
		$categories = get_the_category($id);
		
		if (is_array($categories)) {
			foreach ($categories as $category) {
				$cat_name = $category->cat_name;
				$this->_meta['article:section'][] = $cat_name;
				$this->_metadc['type'][] = $cat_name;
			}
		}
	}

	/**
	 * Get all tags from post, if page is single.php
	 */
	protected function _add_post_tags($id)
	{
		$tags = get_the_tags($id);

		if (is_array($tags) && count($tags) > 0) {
			foreach ($tags as $tag) {
				$this->_meta['article:tag'][] = $tag->name;
				$this->_metadc['subject'][] = $tag->name;
			}
		}
		
		// The article information about time of publishing, last modified
		// and article expiration, Have a necessity of being written
		// in the format of ISO8601 !!!
		// For more information about the ISO8601 format, see the
		// the wikipedia page @ http://en.wikipedia.org/wiki/ISO_8601
		
		$this->_meta['article:published_time'] = get_the_date('Y-m-d');
		$this->_metadc['date']['ISO8601'] = get_the_date('Y-m-d');
		
		// if the last modified date is equal to the published date, then the modified date will not be printet
		if (get_the_date('Y-m-d') !== get_the_modified_date('Y-m-d')) {
			$this->_meta['article:modified_time']['ISO8601'] = get_the_modified_date('Y-m-d');
			$this->_metadc['date.x-metadatalastmodifed']['ISO8601'] = get_the_modified_date('Y-m-d');
		}
		// $this->_meta['article:expiration_time'] = get_the_modified_date('c');
	}
	
	/**
	 * Adds the first-, middle- (required modification of core files if
	 * wp-v3.4.2 or below!) and lastname, plus the profile username.
	 *
	 * NB:
	 * The Gender-tag, which is commented out for now, is something I
	 * have plans to introduce, if wordpress anytime in the future
	 * chooses to implement the field in the core as (an optional)
	 * field on the 'User Profile Page'.
	 */
	protected function _add_author_bio()
	{
		// Imports the the current author_ID
		$author = (get_query_var('author_name')) ? get_user_by('slug', get_query_var('author_name')) : get_userdata(get_query_var('author'));
		$author_ID = $author->ID;
		
		// Profile information from author
		$user_firstname = get_the_author_meta('user_firstname', $author_ID);
		//$user_middlename = get_the_author_meta('user_middlename', $author_ID);
		$user_lastname = get_the_author_meta('user_lastname', $author_ID);
		$nickname = get_the_author_meta('nickname', $author_ID);
		//$user_gender = get_the_author_meta('gender', $author_ID); //set via a custom field
		$description = sanitize_text_field(htmlspecialchars(get_the_author_meta('description', $author_ID)));
		$website = sanitize_text_field(htmlspecialchars(get_the_author_meta('user_url', $author_ID)));
		
		// Social contact methods
		$facebook   = get_the_author_meta( 'facebook',   $author_ID );
		$twitter    = get_the_author_meta( 'twitter',    $author_ID );
		$googleplus = get_the_author_meta( 'googleplus', $author_ID );
		$linkedin   = get_the_author_meta( 'linkedin',   $author_ID );
		//'path' = get_the_author_meta( 'path', $author_ID );
		//$flick = get_the_author_meta( 'flickr', $author_ID );
		//'stumbleupon' = get_the_author_meta( 'stumbleupon', $author_ID );
		//'digg' = get_the_author_meta( 'digg', $author_ID );
		
		if (isset($user_firstname) && !empty($user_firstname)) {
			$this->_meta['profile:first_name'] = $user_firstname;
		}
		
		/*if (isset($user_middlename) || !empty($user_middlename)) {
			$this->_meta['profile:middle_name'] = $user_middlename;
		}*/
		
		if (isset($user_lastname) && !empty($user_lastname)) {
			$this->_meta['profile:last_name'] = $user_lastname;
		}
		
		if (isset($nickname) && !empty($nickname)) {
			$this->_meta['profile:username'] = $nickname;
		}
		
		//if (isset($user_gender) && !empty($user_gender)) {
		//	$this->_meta['profile:gender'] = $user_gender;
		//}
		
		
		if (isset($description) && !empty($description)) {
			$description_prefix = ' | Author Biographical Info : ';
			$this->_meta['og:description'] .= $description_prefix . $description;
			$this->_metadc['description'] .= $description_prefix . $description;
		}

		if (isset($facebook) && !empty($facebook)) {
			$facebook = preg_replace(
				"#((http|https)\:\/\/)?(www\.)?facebook\.com\/([a-zA-Z0-9-_\.]+)(\/)?#",
				"$4",
				$facebook );
			
			$facebook = 'http'.is_ssl('s').'://www.facebook.com/' . $facebook;
			$this->_meta['profile:facebook'] = $facebook;
			$this->_metadc['relation'][] = $facebook;
		}
		
		if (isset($twitter) && !empty($twitter)) {
			$twitter = preg_replace(
				"#((http|https)\:\/\/)?(www\.)?twitter\.com\/([a-zA-Z0-9-_\.]+)#",
				"$4#",
				$twitter );
			
			$twitter = 'http'.is_ssl('s').'://www.twitter.com/' . $twitter;
			$this->_meta['profile:twitter'] = $twitter;
			$this->_metadc['relation'][] = $twitter;
		}
		
		if (isset($googleplus) && !empty($googleplus)) {
			$googleplus = preg_replace(
				"#((http|https)\:\/\/)?\plus\.google\.com\/([0-9]+)(\/posts)?#",
				"$3",
				$googleplus );
			
			$googleplus = 'http'.is_ssl('s').'://plus.google.com/' . $googleplus;
			$this->_meta['profile:googleplus'] = $googleplus;
			$this->_metadc['relation'][] = $googleplus;
		}
		
		if (isset($linkedin) && !empty($linkedin)) {
			$linkedin = preg_replace(
				"#((http|https)\:\/\/)?([a-z]{2,3})\.linkedin\.com\/pub\/([a-zA-Z0-9-]+)\/([a-z0-9]+)\/([a-z0-9]+)\/([a-z0-9]+)\/#",
				"http".is_ssl("s")."://$3.linkedin.com/pub/$4/$5/$6/$7/",
				$linkedin );
			
			$this->_meta['profile:linkedin'] = $linkedin;
			$this->_metadc['relation'][] = $linkedin;
		}
		
		//if (isset($path) && !empty($path)) {
		//	$this->_meta['profile:path'] = $path;
		//}
		
		//if (isset($flickr) && !empty($flickr)) {
		//	$this->_meta['profile:flickr'] = $flickr;
		//}
		
		//if (isset($stumbleupon) && !empty($stumbleupon)) {
		//	$this->_meta['profile:stumbleupon'] = $stumbleupon;
		//}
		
		//if (isset($digg) && !empty($digg)) {
		//	$this->_meta['profile:digg'] = $digg;
		//}
		
		if (isset($website) && !empty($website)) {
			$this->_metadc['relation'][] = $website;
		}
	}
	
	
	/**
	 * Can be used to add street address to the wordpress
	 * author page, if the required fields are filled in.
	 *
	 * For more information, see Facebooks dev-docs at:
	 * http://developers.facebook.com/docs/opengraphprotocol/#location
	 */
	/*protected function _add_street_address()
	{
		$this->_meta['og:latitude']			= '';
		$this->_meta['og:longitude']		= '';
		$this->_meta['og:street-address']	= '';
		$this->_meta['og:locality']			= '';
		$this->_meta['og:region']			= '';
		$this->_meta['og:postal-code']		= '';
		$this->_meta['og:country-name']		= '';
	}*/
	
	
	/**
	 * Can be used to present contact information with
	 * the meta tags the OGP protoccol provides
	 *
	 * For more info, see the section right below
	 * 'Location' at:
	 * http://developers.facebook.com/docs/opengraphprotocol/#location
	 */
	/*protected function _add_contact_information()
	{
		$this->_meta['og:email']		= '';
		$this->_meta['og:phone_number']	= '';
		$this->_meta['og:fax_number']	= '';
	}*/
	
	
	/**
	 * Facebook
	 *
	 * Example(s):
	 *  Single:  <meta property="fb:admins" content="USER_ID" />
	 *  Several: <meta proterty="fb:admins" content="USER_ID1,USER_ID2" />
	 * <meta property="fb:app_id" content="APP_ID" />
	 * <meta property="fb:profile_id content="THIRD_PARTY_FB_ID" />
	 *
	 * For more information about the FB ogp tags, see:
	 * http://developers.facebook.com/docs/opengraph/objects/builtin/
	 * http://developers.facebook.com/docs/opengraphprotocol/
	 */
	/*protected function _add_facebook_ogp()
	{
		$this->_meta['fb_admins'] = ''
		$this->_meta['fb:app_id'] = '';	
	}*/
	
	/**
	 * Twitter
	 *
	 * Best to only let it run, when current view is
	 * the 'single.php'-file or viewing a page.
	 * 
	 * Example:
	 * <meta name="twitter:card" value="summary|photo|player">
	 * <meta name="twitter:site" value="@yoast|@youtube">
	 * <meta name="twitter:creator" value="@michielheijmans">
	 * <meta name="twitter:player" value="https://www.youtube.com/embed/P5XGvNpWXqA">
     * <meta property="twitter:player:width" content="640">
     * <meta property="twitter:player:height" content="360">
	 *
	 * For more information about how to use this,
	 * see: https://dev.twitter.com/docs/cards
	 */
	/*protected function _add_twitter_ogp($id)
	{
		// Format: <meta name="" value"" />
		
		if (get_post_type($id) == 'post') {
			$this->_meta2['twitter:card']	= 'summary';
			$this->_meta2['twitter:url']	= get_permalink();
			$this->_meta2['twitter:title']	= get_the_title($id).' - '.get_bloginfo('name');
			
			$description = null;
			$this->_get_description_wpSEO_Base_or_aiosp_meta($id);
			if (!empty($description)) {
				if (strlen(utf8_decode($description)) <= 200) {
					$this->_meta2['twitter:description'] = $description;
				}
			}
			
			// Use featured image if one exists for the 'twitter:image'-tag.
			if (has_post_thumbnail($id)) {
				$get_post_thumbnail_id = get_post_thumbnail_id();
				$feat = wp_get_attachment_image_src($get_post_thumbnail_id, 'medium');
				$this->_meta2['twitter:image'] = esc_attr($feat[0]);
			}
		}
		
		// The twitter id for the website the content is published on
		$this->_meta2['twitter:site']			= '';
		$this->_meta2['twitter:site:id']		= '';
		
		// The twitter id of the author of the current content / post
		$this->_meta2['twitter:creator']		= '',
		$this->_meta2['twitter:creator:id']		= '',
		
	}*/
	
	/**
	 * Video tags inteded for twitter, seed used by youtube
	 */
	/*protected function _add_twitter_ogp_video()
	{
		// Format: <meta name="" value"" />
		
		$this->_meta2['twitter:player']			= '';
		
		// Format: <meta property="" content"" />
		
		$this->_meta['twitter:player:width']	= '';
		$this->_meta['twitter:player:height']	= '';
	}
	
	/**
	 * Type of business, the current wp install is promoting
	 */
	/*protected function _add_business($business)
	{
		$business_options = array(
			'bar',
			'company',
			'cafe',
			'hotel',
			'restaurant');
		
		if (in_array($business)) {
			$chosen_business = array_keys($business_options, $business);
		} else {
			goto $end_add_business;
		}
		$this->_meta['business'] = $chosen_business;
		
		end_add_business:
		return;
	}*/
	
	/**
	 * Type of organization, which runs the current wp install
	 */
	/*protected function _add_organization()
	{
		$organization_options = array(
			'band',
			'government',
			'non_profit',
			'school',
			'university');
		
		$this->_meta['organization'] = $organization_options;
	}*/
	
	/**
	 *
	 */
	/*protected function google_news_keywords()
	{
		add_action( 'wp_head', '<meta name="news_keywords" content="' . $keywords . '">' );
	}*/
	
	/**
	 * If video post and video is loaded from youtube
	 * 
	 * @since _site-code 0.1 
	 */
	protected function _video_posts() {
		global $post;
		if(is_single()) :
			$format = get_post_format();
			
			if($format === 'video') :
				// Get list of videoes provided to the field.
				$_wp_format_video = get_post_custom_values( '_wp_format_video' );
				if( empty($_wp_format_video[0]) ) $_wp_format_video = get_post_custom_values( '_format_video_embed' );
				if( !is_ssl() ) $_wp_format_video[0] = str_replace( 'https:', 'http:', $_wp_format_video[0] );
				if( is_ssl() )  $_wp_format_video[0] = str_replace( 'http:', 'https:', $_wp_format_video[0] );
				
				// Get video_id
				if( preg_match( '%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $_wp_format_video[0], $match ) ) :
					$video_id = $match[1];
				
					// Get JSONc data
					$json = json_decode( file_get_contents( "http".is_ssl('s')."://gdata.youtube.com/feeds/api/videos/$video_id?v=2&alt=jsonc" ) );
					
					function remove_querystring_var($url, $key) { 
						$url = preg_replace('/(.*)(?|&)' . $key . '=[^&]+?(&)(.*)/i', '$1$2$4', $url . '&'); 
						$url = substr($url, 0, -1); 
						return $url; 
					}
					$og_video_url = remove_querystring_var($json->data->player->default, 'feature');
					
					$this->_meta['og:video'] = preg_replace('^https:^', 'http:', $og_video_url);
					$this->_meta['og:video:secure_url'] = preg_replace('^http:^', 'https:', $og_video_url);
					$this->_meta['og:video:type'] = 'application/x-shockwave-flash';
					$this->_meta['og:video:width'] = '640';
					$this->_meta['og:video:height'] = '480';
					
					// Returns the total lenght of the video in seconds. 
					$this->_meta['music:duration'] = $json->data->duration;
				endif;
			endif;
		endif;
	}
	
	/**
	 *
	 */
	protected function _output()
	{		
		// Output values assigned to '$_meta'
		foreach ($this->_meta as $property => $content) {
			$content = is_array($content) ? $content : array($content);

			foreach ($content as $content_single) {
				$output[] = '<meta property="' . $property . '" content="' . esc_attr(trim($content_single)) . '" />' . "\n";
			}
		}
		
		// Output values assigned to $_meta2
		foreach ($this->_meta2 as $name => $value) {
			$value = is_array($value) ? $value : array($value);

			foreach ($value as $value_single) {
				$output[] = '<meta name="' . $name . '" value="' . esc_attr(trim($value_single)) . '" />' . "\n";
			}
		}
		
		// Outputs values assigned to $_metadc
		foreach ($this->_metadc as $name => $value) {
			$value = is_array($value) ? $value : array($value);
			
			foreach ($value as $key => $value_single) {
				//in_array($key, $allowed_scheme_values, true) ? $schema = ' schema="' . esc_attr(trim($key)) . '"' : $schema = null;
				
				$output[] = '<meta name="dc.' . $name . '"' . is_dc_schema_allowed(esc_attr(trim($key))) . 'content="' . esc_attr(trim($value_single)) . '" />' . "\n";
			}
		}
		
		// All outputs are gathered in a string
		$wp_ogp_header_string = "\n" . '<!-- START WP OGP header -->' . "\n";
		
		// Outputs the Dublin Core meta values
		$wp_ogp_header_string .= '<link rel="schema.dc" href="http://purl.org/dc/elements/1.1/" />' . "\n";
		$wp_ogp_header_string .= '<link rel="schema.dcterms" href="http://purl.org/dc/terms/" >' . "\n";
		//$wp_ogp_header_string .= '<link rel="schema.xsd" href="http://www.w3.org/2001/XMLSchema#" >' . "\n";
		
		// All the assigned values are added to the output
		foreach ($output as $string) $wp_ogp_header_string .= $string;
		
		$wp_ogp_header_string .= '<!-- END WP OGP header -->' . "\n\n";
		
		// When there is nothing more to assign to the
		// string, the whole string is outputted
		echo $wp_ogp_header_string;
	}
}
/*if(($plugins_active[0] && $plugins_active[1]) == false)	$wp_ogp_header = new wp_ogp_header();
elseif($plugins_active[0] == true || $plugins_active[1] == true)*/ $wp_ogp_header = new wp_ogp_header();