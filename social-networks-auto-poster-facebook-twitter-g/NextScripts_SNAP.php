<?php
/*
Plugin Name: NextScripts: Social Networks Auto-Poster
Plugin URI: https://www.nextscripts.com/social-networks-auto-poster-for-wordpress
Description: This plugin automatically publishes posts from your blog to your social media accounts on X/Twitter, Facebook, Telegram, LinkedIn, and more networks.
Author: NextScripts
Version: 4.5.1
Author URI: https://www.nextscripts.com
Text Domain: social-networks-auto-poster-facebook-twitter-g
Copyright 2012-2026  NextScripts Corp
*/

const NextScripts_SNAP_Version = '4.5.1'; const NextScripts_SNAP_Version_Date = 'Nov 17, 2026';
require_once "inc/nxs_functions_wp.php"; if(!defined( 'NXSSNAP_BASENAME' ) ) define( 'NXSSNAP_BASENAME', plugin_basename( __FILE__ ) );

if (true===nxs_doSystemInitCheck()) {  // error_reporting(E_ALL); ini_set('display_errors', '1');
    require_once "inc/nxs_functions.php"; require_once "inc/nxs_functions_adv.php"; require_once "inc/nxs_functions_ext.php"; require_once "inc/nxs_functions_engine.php"; require_once "inc/nxs_class_http.php"; require_once "inc/nxs_class_addns.php";
    require_once "inc/nxs_class_snap.php"; require_once "inc/nxs_class_flt.php"; require_once "inc/nxs_class_mgmt.php"; require_once "inc/nxs_class_ntlist.php"; require_once "inc/nxs_class_oauth.php";
    add_filter('get_post_metadata', 'nxs_safe_post_metadata', 10, 5);
    add_filter('is_protected_meta', function($protected,$meta_key,$meta_type){return $meta_type==='post'&&preg_match('/^(?:snap|_snap|nxs_|_nxs_)/i',(string)$meta_key)?true:$protected;},10,3);
    //## Some Globals and Constants
    global $nxs_snapAvNts, $nxs_SNAP, $nxs_snapSetPgURL, $nxs_snapThisPageUrl; $nxs_snapSetPgURL = nxs_get_admin_url('admin.php?page=nxssnap');
    $nxs_current_page=isset($_GET['page'])?sanitize_key(wp_unslash($_GET['page'])):'nxssnap'; $nxs_snapThisPageUrl=esc_url_raw(add_query_arg('page',$nxs_current_page,nxs_get_admin_url('admin.php')));
    define( 'NXS_PLPATH', plugin_dir_path(__FILE__) );  define( 'NXS_PLURL', plugin_dir_url(__FILE__)); define( 'NXS_SETV', 350); global $nxs_plurl; $nxs_plurl =  NXS_PLURL; // Remove once networks upgraded
    require_once NXS_PLPATH . 'inc/nxs_postsocial.php';
    require_once NXS_PLPATH . 'inc/nxs_meta_direct.php';
    //## Register Autoloader for Dynamic Network Class Loading
    spl_autoload_register(function ($class_name) {
        if (preg_match('/^nxs_snapClass([A-Z0-9]+)$/', $class_name, $matches)) {
            $lcode = strtolower($matches[1]);
            $file = NXS_PLPATH . 'inc-cl/' . $lcode . '.php';
            if (file_exists($file)) { require_once $file; }
        } elseif (preg_match('/^nxs_class_SNAP_([A-Z0-9]+)$/', $class_name, $matches)) {
            $lcode = strtolower($matches[1]);
            $file = NXS_PLPATH . 'inc-cl/' . $lcode . '.api.php';
            if (file_exists($file)) { require_once $file; }
        } elseif (preg_match('/^nxsAPI_([A-Z0-9]+)_Native$/', $class_name, $matches)) {
            $lcode = strtolower($matches[1]);
            $file = NXS_PLPATH . 'inc-cl/' . $lcode . '.php';
            if (file_exists($file)) { require_once $file; }
        } elseif (preg_match('/^nxsAPI_([A-Z0-9]+)$/', $class_name, $matches)) {
            $lcode = strtolower($matches[1]);
            $file = NXS_PLPATH . 'inc-cl/' . $lcode . '.api.php';
            if (file_exists($file)) { require_once $file; }
        }
    });

    //## Get Available Networks (Dynamically Registered instead of filesystem globbing)
    do_action('nxs_actBeforeGetAvNetworks');
    $nxs_snapAvNts = array(
        array('code'=>'BS', 'lcode'=>'bs', 'name'=>'Bluesky', 'type'=>'Social Networks', 'ptype'=>'F', 'status'=>'A', 'desc'=>'Publish text and images to Bluesky directly with an App Password or through Post.Social'),
        array('code'=>'BG', 'lcode'=>'bg', 'name'=>'Blogger', 'type'=>'Blogs/Publishing Platforms', 'ptype'=>'B', 'status'=>'A', 'desc'=>'Autopost to your blog. HTML is supported'),
        array('code'=>'DA', 'lcode'=>'da', 'name'=>'deviantART (!)', 'type'=>'Image Sharing', 'ptype'=>'F', 'status'=>'B', 'desc'=>'Autopost to your blog. HTML is supported'),
        array('code'=>'DI', 'lcode'=>'di', 'name'=>'Diigo', 'type'=>'Link Sharing/Boormarks', 'ptype'=>'F', 'status'=>'A', 'desc'=>'Auto-submit bookmark to your account'),
        array('code'=>'FB', 'lcode'=>'fb', 'name'=>'Facebook', 'type'=>'Social Networks', 'ptype'=>'F', 'status'=>'A', 'desc'=>'Publish to a Facebook Page using your own app or Post.Social'),
        array('code'=>'FL', 'lcode'=>'fl', 'name'=>'Flickr', 'type'=>'Image Sharing', 'ptype'=>'F', 'status'=>'A', 'desc'=>'Autopost images to your photostream and/or sets. Tags are supported'),
        array('code'=>'GMB', 'lcode'=>'gmb', 'name'=>'Google Business Profile', 'type'=>'Blogs/Publishing Platforms', 'ptype'=>'P', 'status'=>'A', 'desc'=>'Create a Google Business Profile post'),
        array('code'=>'IG', 'lcode'=>'ig', 'name'=>'Instagram', 'type'=>'Social Networks', 'ptype'=>'P', 'status'=>'A', 'desc'=>"Upload your blogpost's image to your Instagram account."),
        array('code'=>'IP', 'lcode'=>'ip', 'name'=>'Instapaper', 'type'=>'Blogs/Publishing Platforms', 'ptype'=>'F', 'status'=>'A', 'desc'=>'Autopost to your account'),
        array('code'=>'LI', 'lcode'=>'li', 'name'=>'LinkedIn', 'type'=>'Social Networks', 'ptype'=>'B', 'status'=>'A', 'desc'=>'Publish to your profile or company page using your own app or Post.Social'),
        array('code'=>'LJ', 'lcode'=>'lj', 'name'=>'LiveJournal', 'type'=>'Blogs/Publishing Platforms', 'ptype'=>'F', 'status'=>'A', 'desc'=>'Auto-submit your blogpost to LiveJournal blog or community. LiveJournal engine based website DreamWidth.org is also supported'),
        array('code'=>'LN', 'lcode'=>'ln', 'name'=>'Line', 'type'=>'Messengers', 'ptype'=>'F', 'status'=>'A', 'desc'=>'Autopost texts, images, or links to your channel, group or chat'),
        array('code'=>'MC', 'lcode'=>'mc', 'name'=>'MailChimp', 'type'=>'Email Marketing', 'ptype'=>'F', 'status'=>'A', 'desc'=>'One of the most popular email marketing tools. You can send your blogposts as email campaigns to specific subscribers'),
        array('code'=>'MD', 'lcode'=>'md', 'name'=>'Medium', 'type'=>'Blogs/Publishing Platforms', 'ptype'=>'F', 'status'=>'A', 'desc'=>'Autopost to your profile or publications'),
        array('code'=>'OK', 'lcode'=>'ok', 'name'=>'ok.ru', 'type'=>'Social Networks', 'ptype'=>'F', 'status'=>'A', 'desc'=>'Autopost text, image or share a link to your profile or group'),
        array('code'=>'PN', 'lcode'=>'pn', 'name'=>'Pinterest', 'type'=>'Social Networks', 'ptype'=>'P', 'status'=>'A', 'desc'=>"Post your blogpost's image to your Pinterest board."),
        array('code'=>'RD', 'lcode'=>'rd', 'name'=>'Reddit', 'type'=>'Link Sharing/Boormarks', 'ptype'=>'P', 'status'=>'A', 'desc'=>'Autopost to your subreddits'),
        array('code'=>'SC', 'lcode'=>'sc', 'name'=>'Scoop.It', 'type'=>'Blogs/Publishing Platforms', 'ptype'=>'F', 'status'=>'A', 'desc'=>'Autopost to your "Topics". Ability to attach your blogpost to scoop. Ability to make "Image" posts'),
        array('code'=>'TG', 'lcode'=>'tg', 'name'=>'Telegram', 'type'=>'Messengers', 'ptype'=>'F', 'status'=>'A', 'desc'=>'Autopost texts, images, or links to your channel, group or chat'),
        array('code'=>'TH', 'lcode'=>'th', 'name'=>'Threads', 'type'=>'Social Networks', 'ptype'=>'F', 'status'=>'A', 'desc'=>'Publish text and image posts to Threads through Post.Social or your own Threads app.'),
        array('code'=>'TR', 'lcode'=>'tr', 'name'=>'Tumblr', 'type'=>'Blogs/Publishing Platforms', 'ptype'=>'F', 'status'=>'A', 'desc'=>'Create text, image, audio-link or video-link posts on your Tumblr blog.'),
        array('code'=>'TW', 'lcode'=>'tw', 'name'=>'X/Twitter', 'type'=>'Social Networks', 'ptype'=>'F', 'status'=>'A', 'desc'=>'Autopost to your account. Ability to attach images'),
        array('code'=>'VB', 'lcode'=>'vb', 'name'=>'vBulletin', 'type'=>'Forums', 'ptype'=>'F', 'status'=>'A', 'desc'=>'Auto-submit your blogpost to vBulletin forums. Could create new threads or new posts'),
        array('code'=>'VK', 'lcode'=>'vk', 'name'=>'VK.Com', 'type'=>'Social Networks', 'ptype'=>'F', 'status'=>'A', 'desc'=>'Post text, image or share a link to your profile or group page'),
        array('code'=>'WB', 'lcode'=>'wb', 'name'=>'Weibo', 'type'=>'Social Networks', 'ptype'=>'F', 'status'=>'A', 'desc'=>'Biggest Chinese Microblogging Service. You can post your messages and images'),
        array('code'=>'WP', 'lcode'=>'wp', 'name'=>'WP Based Blog', 'type'=>'Blogs/Publishing Platforms', 'ptype'=>'F', 'status'=>'A', 'desc'=>'Auto-submit your blogpost to another WordPress based site. Support for any standalone WordPress and WordPress.com, Blog.com, etc..')
    );
    $nxs_snapAPINts = array(
        array('code'=>'BS', 'lcode'=>'bs', 'name'=>'Bluesky'),
        array('code'=>'BG', 'lcode'=>'bg', 'name'=>'Blogger'),
        array('code'=>'DA', 'lcode'=>'da', 'name'=>'deviantART'),
        array('code'=>'DI', 'lcode'=>'di', 'name'=>'Diigo'),
        array('code'=>'FB', 'lcode'=>'fb', 'name'=>'Facebook'),
        array('code'=>'FL', 'lcode'=>'fl', 'name'=>'Flickr'),
        array('code'=>'GMB', 'lcode'=>'gmb', 'name'=>'Google Business Profile'),
        array('code'=>'IG', 'lcode'=>'ig', 'name'=>'Instagram'),
        array('code'=>'IP', 'lcode'=>'ip', 'name'=>'Instapaper'),
        array('code'=>'LI', 'lcode'=>'li', 'name'=>'LinkedIn'),
        array('code'=>'LJ', 'lcode'=>'lj', 'name'=>'LiveJournal'),
        array('code'=>'LN', 'lcode'=>'ln', 'name'=>'Line'),
        array('code'=>'MC', 'lcode'=>'mc', 'name'=>'MailChimp'),
        array('code'=>'MD', 'lcode'=>'md', 'name'=>'Medium'),
        array('code'=>'OK', 'lcode'=>'ok', 'name'=>'ok.ru'),
        array('code'=>'PN', 'lcode'=>'pn', 'name'=>'Pinterest'),
        array('code'=>'RD', 'lcode'=>'rd', 'name'=>'Reddit'),
        array('code'=>'SC', 'lcode'=>'sc', 'name'=>'Scoop.It'),
        array('code'=>'TG', 'lcode'=>'tg', 'name'=>'Telegram'),
        array('code'=>'TH', 'lcode'=>'th', 'name'=>'Threads'),
        array('code'=>'TR', 'lcode'=>'tr', 'name'=>'Tumblr'),
        array('code'=>'TW', 'lcode'=>'tw', 'name'=>'X/Twitter'),
        array('code'=>'VB', 'lcode'=>'vb', 'name'=>'vBulletin'),
        array('code'=>'VK', 'lcode'=>'vk', 'name'=>'VK.Com'),
        array('code'=>'WB', 'lcode'=>'wb', 'name'=>'Weibo'),
        array('code'=>'WP', 'lcode'=>'wp', 'name'=>'WP Based Blog')
    );
    $nxs_avlblNTList = array();
    do_action('nxs_actAfterGetAvNetworks');
    //## MAIN
    add_action( 'init', 'nxs_initSNAP', 0 );
    function nxs_initSNAP() { global $nxs_SNAP;
        $nxs_SNAP = new nxs_SNAP(); nxs_checkAddQueryTable(); nxs_checkAddLogTable(); new nxs_Filters; $role = get_role('administrator'); if (!empty($role)) $role->add_cap('haveown_snap_accss'); //prr($nxs_SNAP->sMode); prr($nxs_SNAP->nxs_options);
        //##### Save Meta for posts / Add/Change meta on Save
        add_action('edit_post', array($nxs_SNAP, 'NS_SNAP_SavePostMetaTags'));
        add_action('publish_post', array($nxs_SNAP, 'NS_SNAP_SavePostMetaTags'));
        add_action('save_post', array($nxs_SNAP, 'NS_SNAP_SavePostMetaTags'));
        //## OG Tags
        if (!empty($nxs_SNAP->nxs_options['nxsOG']) && ( $nxs_SNAP->nxs_options['nxsOG'] == 'A' || $nxs_SNAP->nxs_options['nxsOG'] == 'N' ) ) {  // nxs_LogIt('BG','','','',$_SERVER["HTTP_USER_AGENT"]);
            if ( !empty($_SERVER["HTTP_USER_AGENT"]) && ( strpos($_SERVER["HTTP_USER_AGENT"], "facebookexternalhit") !== false || strpos($_SERVER["HTTP_USER_AGENT"], "Facebot") !== false || strpos($_SERVER["HTTP_USER_AGENT"], "ChXrome") !== false || strpos($_SERVER["HTTP_USER_AGENT"], "Google (") !== false || strpos($_SERVER["HTTP_USER_AGENT"], "LinkedInBot") !== false || strpos($_SERVER["HTTP_USER_AGENT"], "XING-contenttabreceiver") !== false || strpos($_SERVER["HTTP_USER_AGENT"], "Java/1.7.0_45") !== false )) {
                if (!is_admin()) @ob_start( 'nxs_ogtgCallback' );
            }
            add_action('wp_head', 'nxs_addOGTagsPreHolder', 150);
            add_action('shutdown', 'nxs_end_flush_ob', 1000);
        }
        if ($nxs_SNAP->sMode['l']=='M'){
            if (function_exists('nxssnapmu_columns_head')) add_filter('wpmu_blogs_columns', 'nxssnapmu_columns_head');
            if (function_exists('nxssnapmu_columns_content')) add_action('manage_blogs_custom_column', 'nxssnapmu_columns_content', 10, 2);
            if (function_exists('nxssnapmu_columns_content')) add_action('manage_sites_custom_column', 'nxssnapmu_columns_content', 10, 2);
            if (function_exists('nxs_add_style')) add_action( 'admin_footer', 'nxs_add_style' );
            if (function_exists('nxs_saveSiteSets_ajax')) add_action('wp_ajax_nxs_saveSiteSets', 'nxs_saveSiteSets_ajax');
        }
        //## Watch for New Posts
        if ($nxs_SNAP->sMode['r']) add_action('transition_post_status', 'nxs_snapLogPublishTo', 100, 3);
    }
    //## Actions
    add_action('admin_head', 'nxs_adminCSS');
    add_action('admin_enqueue_scripts', 'nxssnap_enqueue_scripts' );
    add_action('admin_init', 'nxs_adminInitFunc');
    add_action('in_admin_header', 'nxs_admin_header');
    add_action('wp_ajax_nxs_getExpSettings', 'nxs_getExpSettings_ajax'); //## Export Settings
    //## Logs
    add_action('wp_ajax_nxs_clLgo', 'nxs_clLgo_ajax');
    add_action('wp_ajax_nxs_rfLgo', 'nxs_rfLgo_ajax');
    //## Ajax
    add_action('wp_ajax_nxs_snap_aj', 'nxs_snapAjax');
    //## Cron and Schedulle Actions
    add_filter('cron_schedules', 'cron_add_nxsAutPoster'); //## Adds NXS Autoposter(nxsquery) to WP Schedules List
    add_action('nxs_querypost_event', 'nxs_checkQuery'); //## Main NXS Cron function - nxs_querypost_event to run nxs_checkQuery() function.
    add_action('nxs_hourly_event', 'nxs_do_this_hourly'); //## Adds Hourly Event
    add_action('wp_loaded', 'nxs_activation'); //## Adds nxs_querypost_event to wp_schedule_event as nxsquery
    //## Cron reposter action functions
    function cron_add_nxsAutPoster( $schedules ) {
        global $nxs_SNAP; if (is_object($nxs_SNAP)) { $o = $nxs_SNAP->nxs_options; $i = !empty($o['queryInterval'])?$o['queryInterval']:60; } else $i = 60; $schedules['nxsquery'] = array( 'interval' => $i, 'display' => __( 'NextScripts AutoPoster' )); return $schedules;
    }
    function nxs_activation(){
        if (!wp_next_scheduled('nxs_hourly_event')){wp_schedule_event(time(), 'hourly', 'nxs_hourly_event');}
        if (!wp_next_scheduled('nxs_querypost_event')){wp_schedule_event(time(), 'nxsquery', 'nxs_querypost_event');}
    }
    if (function_exists("add_shortcode")) add_shortcode( 'nxs_links', 'nxs_links_func' );
}
