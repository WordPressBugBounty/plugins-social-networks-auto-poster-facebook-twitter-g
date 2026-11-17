<?php

if (!defined('ABSPATH')) exit;

/** Official Meta OAuth and publishing used by direct Instagram and Threads accounts. */
final class NXS_Meta_Direct
{
    const TRANSIENT_PREFIX = 'nxs_meta_oauth_';

    public static function register()
    {
        add_action('admin_post_nxs_meta_oauth_start', array(__CLASS__, 'start'));
        add_action('admin_post_nxs_meta_oauth_callback', array(__CLASS__, 'callback'));
        add_action('admin_notices', array(__CLASS__, 'notice'));
    }

    public static function callback_url()
    {
        return admin_url('admin-post.php?action=nxs_meta_oauth_callback');
    }

    public static function start()
    {
        if (!nxs_snap_user_can_access()) wp_die(esc_html__('You are not allowed to manage SNAP accounts.', 'social-networks-auto-poster-facebook-twitter-g'), 403);
        $network = isset($_POST['network']) ? sanitize_key(wp_unslash($_POST['network'])) : '';
        $index = isset($_POST['account_index']) ? absint($_POST['account_index']) : 0;
        if (!in_array($network, array('ig', 'th'), true)) wp_die(esc_html__('Unsupported Meta network.', 'social-networks-auto-poster-facebook-twitter-g'), 400);
        check_admin_referer('nxs_meta_oauth_' . $network . '_' . $index);
        $app_id = isset($_POST['app_id']) ? trim(sanitize_text_field(wp_unslash($_POST['app_id']))) : '';
        $app_secret = isset($_POST['app_secret']) ? trim((string) wp_unslash($_POST['app_secret'])) : '';
        if ($app_id === '' || $app_secret === '') self::redirect_error($network, __('Enter the App ID and App Secret before authorizing.', 'social-networks-auto-poster-facebook-twitter-g'));

        $settings = array();
        if (isset($_POST['message_format'])) {
            $settings['msgFormat'] = sanitize_textarea_field(wp_unslash($_POST['message_format']));
            $settings['nName'] = isset($_POST['account_nickname']) ? sanitize_text_field(wp_unslash($_POST['account_nickname'])) : '';
            $settings['defImg'] = isset($_POST['default_image']) ? esc_url_raw(wp_unslash($_POST['default_image'])) : '';
            $settings['attchImg'] = $network === 'ig' || !empty($_POST['attach_image']) ? 1 : 0;
        }

        try { $state = nxs_b64url_encode(random_bytes(32)); } catch (Exception $e) { self::redirect_error($network, __('Could not start a secure authorization request.', 'social-networks-auto-poster-facebook-twitter-g')); }
        $pending = array(
            'user_id' => get_current_user_id(), 'network' => $network, 'index' => $index,
            'app_id' => $app_id, 'app_secret' => nxs_secure_encrypt($app_secret), 'created' => time(),
            'settings' => $settings,
        );
        set_transient(self::TRANSIENT_PREFIX . hash('sha256', $state), $pending, 15 * MINUTE_IN_SECONDS);
        $args = array('client_id' => $app_id, 'redirect_uri' => self::callback_url(), 'response_type' => 'code', 'state' => $state);
        if ($network === 'ig') {
            $args['scope'] = 'instagram_business_basic,instagram_business_content_publish';
            $url = add_query_arg($args, 'https://www.instagram.com/oauth/authorize');
        } else {
            $args['scope'] = 'threads_basic,threads_content_publish';
            $url = add_query_arg($args, 'https://threads.com/oauth/authorize');
        }
        wp_redirect($url);
        exit;
    }

    public static function callback()
    {
        if (!is_user_logged_in()) auth_redirect();
        $state = isset($_GET['state']) ? sanitize_text_field(wp_unslash($_GET['state'])) : '';
        $key = self::TRANSIENT_PREFIX . hash('sha256', $state);
        $pending = $state !== '' ? get_transient($key) : false;
        delete_transient($key);
        if (!is_array($pending) || empty($pending['user_id']) || (int) $pending['user_id'] !== get_current_user_id() || time() - (int) $pending['created'] > 900) self::redirect_error('', __('The authorization request expired or could not be verified. Please try again.', 'social-networks-auto-poster-facebook-twitter-g'));
        $network = $pending['network'];
        if (isset($_GET['error'])) {
            $detail = isset($_GET['error_description']) ? sanitize_text_field(wp_unslash($_GET['error_description'])) : sanitize_text_field(wp_unslash($_GET['error']));
            self::redirect_error($network, sprintf(__('Authorization was not completed: %s', 'social-networks-auto-poster-facebook-twitter-g'), $detail));
        }
        $code = isset($_GET['code']) ? trim(sanitize_text_field(wp_unslash($_GET['code']))) : '';
        if ($code === '') self::redirect_error($network, __('The provider did not return an authorization code.', 'social-networks-auto-poster-facebook-twitter-g'));
        $secret = nxs_secure_decrypt($pending['app_secret']);
        $token = self::exchange_code($network, $pending['app_id'], $secret, $code);
        if (!$token['ok']) self::redirect_error($network, $token['error']);
        $profile = self::profile($network, $token['access_token']);
        if (!$profile['ok']) self::redirect_error($network, $profile['error']);
        if (empty($profile['data']['id']) && empty($profile['data']['user_id'])) self::redirect_error($network, __('Meta did not return an account ID. Check the app permissions and try again.', 'social-networks-auto-poster-facebook-twitter-g'));
        self::save_account($pending, $token, $profile['data']);
        self::redirect_notice(sprintf(__('%s account connected successfully.', 'social-networks-auto-poster-facebook-twitter-g'), $network === 'ig' ? 'Instagram' : 'Threads'));
    }

    private static function exchange_code($network, $app_id, $secret, $code)
    {
        $url = $network === 'ig' ? 'https://api.instagram.com/oauth/access_token' : 'https://graph.threads.com/oauth/access_token';
        $short = self::request('POST', $url, array('client_id' => $app_id, 'client_secret' => $secret, 'grant_type' => 'authorization_code', 'redirect_uri' => self::callback_url(), 'code' => $code));
        if (!$short['ok'] || empty($short['data']['access_token'])) return array('ok' => false, 'error' => !empty($short['error']) ? $short['error'] : 'Meta did not return an access token.');
        $long_url = $network === 'ig' ? 'https://graph.instagram.com/access_token' : 'https://graph.threads.com/access_token';
        $long = self::request('GET', $long_url, array('grant_type' => $network === 'ig' ? 'ig_exchange_token' : 'th_exchange_token', 'client_secret' => $secret, 'access_token' => $short['data']['access_token']));
        if (!$long['ok'] || empty($long['data']['access_token'])) return array('ok' => false, 'error' => !empty($long['error']) ? $long['error'] : 'Meta did not return a long-lived access token.');
        $data = $long['data'];
        return array('ok' => true, 'access_token' => $data['access_token'], 'expires_in' => !empty($data['expires_in']) ? absint($data['expires_in']) : 0);
    }

    private static function profile($network, $token)
    {
        $url = $network === 'ig' ? 'https://graph.instagram.com/me' : 'https://graph.threads.com/v1.0/me';
        $fields = $network === 'ig' ? 'id,user_id,username,account_type,profile_picture_url' : 'id,username,threads_profile_picture_url,threads_biography';
        return self::request('GET', $url, array('fields' => $fields, 'access_token' => $token));
    }

    private static function save_account($pending, $token, $profile)
    {
        global $nxs_SNAP;
        if (!isset($nxs_SNAP) || !is_object($nxs_SNAP)) $nxs_SNAP = new nxs_SNAP();
        $network = $pending['network']; $index = (int) $pending['index'];
        $networks = current_user_can('manage_options') ? $nxs_SNAP->nxs_accts : $nxs_SNAP->nxs_acctsU;
        if (!is_array($networks)) $networks = array();
        $old = !empty($networks[$network][$index]) && is_array($networks[$network][$index]) ? $networks[$network][$index] : array();
        $username = !empty($profile['username']) ? sanitize_text_field($profile['username']) : '';
        $defaults = $network === 'ig' ? array('msgFormat' => "%TITLE%\n%HTAGS%", 'attchImg' => 1, 'defImg' => '') : array('msgFormat' => 'New post (%TITLE%) has been published on %SITENAME% - %URL%', 'attchImg' => 1, 'defImg' => '');
        $account = array_merge($defaults, $old, array(
            'do' => 1, 'metaDirect' => $network, 'appKey' => 'x5g9a' . nsx_doEncode($pending['app_id']),
            'appSec' => 'd3h0a' . nsx_doEncode(nxs_secure_decrypt($pending['app_secret'])), 'accessToken' => $token['access_token'],
            'accessTokenExp' => !empty($token['expires_in']) ? time() + (int) $token['expires_in'] : 0,
            'metaUserID' => !empty($profile['user_id']) ? sanitize_text_field($profile['user_id']) : sanitize_text_field($profile['id']),
            'uName' => $username, 'nName' => !empty($old['nName']) ? $old['nName'] : ($username !== '' ? $username : ($network === 'ig' ? 'Instagram' : 'Threads')),
            'profileURL' => $username !== '' ? ($network === 'ig' ? 'https://www.instagram.com/' . rawurlencode($username) . '/' : 'https://www.threads.com/@' . rawurlencode($username)) : '',
            'v' => defined('NXS_SETV') ? NXS_SETV : 5,
        ));
        if (!empty($pending['settings']) && is_array($pending['settings'])) {
            $settings = $pending['settings'];
            if (!empty($settings['nName'])) $account['nName'] = $settings['nName'];
            if (!empty($settings['msgFormat'])) $account['msgFormat'] = $settings['msgFormat'];
            if (array_key_exists('defImg', $settings)) $account['defImg'] = $settings['defImg'];
            if (array_key_exists('attchImg', $settings)) $account['attchImg'] = $network === 'ig' ? 1 : (int) $settings['attchImg'];
        }
        $networks[$network][$index] = $account;
        $nxs_SNAP->saveNetworksOptions($networks);
    }

    public static function publish_instagram($options, $message)
    {
        $bad = self::bad_result();
        if (empty($options['accessToken']) || empty($options['metaUserID'])) { $bad['Error'] = 'Instagram is not authorized.'; return $bad; }
        $image = self::image_url($options, $message);
        if ($image === '') { $bad['Error'] = 'Instagram requires a publicly accessible HTTPS image. Add a featured image or a default image URL.'; return $bad; }
        $caption = !empty($message['pText']) ? $message['pText'] : nxs_doFormatMsg($options['msgFormat'], $message);
        $caption = nsTrnc(html_entity_decode((string) $caption, ENT_QUOTES, 'UTF-8'), 2200, ' ', '');
        $created = self::request('POST', 'https://graph.instagram.com/' . rawurlencode($options['metaUserID']) . '/media', array('image_url' => $image, 'caption' => $caption, 'access_token' => $options['accessToken']));
        if (!$created['ok'] || empty($created['data']['id'])) { $bad['Error'] = $created['error']; return $bad; }
        $ready = self::wait_for_container('https://graph.instagram.com/' . rawurlencode($created['data']['id']), $options['accessToken'], 'status_code,error_message');
        if (!$ready['ok']) { $bad['Error'] = $ready['error']; return $bad; }
        $published = self::request('POST', 'https://graph.instagram.com/' . rawurlencode($options['metaUserID']) . '/media_publish', array('creation_id' => $created['data']['id'], 'access_token' => $options['accessToken']));
        return self::published_result($published, 'https://graph.instagram.com/', $options['accessToken']);
    }

    public static function publish_threads($options, $message)
    {
        $bad = self::bad_result();
        if (empty($options['accessToken']) || empty($options['metaUserID'])) { $bad['Error'] = 'Threads is not authorized.'; return $bad; }
        $text = !empty($message['pText']) ? $message['pText'] : nxs_doFormatMsg($options['msgFormat'], $message);
        $body = array('media_type' => 'TEXT', 'text' => nsTrnc(html_entity_decode((string) $text, ENT_QUOTES, 'UTF-8'), 500, ' ', ''), 'access_token' => $options['accessToken']);
        $image = !empty($options['attchImg']) ? self::image_url($options, $message) : '';
        if ($image !== '') { $body['media_type'] = 'IMAGE'; $body['image_url'] = $image; }
        $base = 'https://graph.threads.com/v1.0/';
        $created = self::request('POST', $base . rawurlencode($options['metaUserID']) . '/threads', $body);
        if (!$created['ok'] || empty($created['data']['id'])) { $bad['Error'] = $created['error']; return $bad; }
        if ($image !== '') {
            $ready = self::wait_for_container($base . rawurlencode($created['data']['id']), $options['accessToken'], 'status,error_message');
            if (!$ready['ok']) { $bad['Error'] = $ready['error']; return $bad; }
        }
        $published = self::request('POST', $base . rawurlencode($options['metaUserID']) . '/threads_publish', array('creation_id' => $created['data']['id'], 'access_token' => $options['accessToken']));
        return self::published_result($published, $base, $options['accessToken']);
    }

    private static function image_url($options, $message)
    {
        $url = !empty($message['imageURL']) ? trim((string) $message['imageURL']) : (!empty($options['defImg']) ? trim((string) $options['defImg']) : '');
        return nxs_validate_remote_url($url, true) ? esc_url_raw($url) : '';
    }

    private static function wait_for_container($url, $token, $fields)
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $status = self::request('GET', $url, array('fields' => $fields, 'access_token' => $token));
            if (!$status['ok']) return $status;
            $raw_status = !empty($status['data']['status_code']) ? $status['data']['status_code'] : (!empty($status['data']['status']) ? $status['data']['status'] : '');
            $code = strtoupper(sanitize_key($raw_status));
            if (in_array($code, array('FINISHED', 'PUBLISHED'), true)) return array('ok' => true, 'data' => $status['data'], 'error' => '');
            if ($code === '') return array('ok' => false, 'data' => $status['data'], 'error' => 'Meta did not return a media processing status.');
            if (in_array($code, array('ERROR', 'EXPIRED'), true)) return array('ok' => false, 'data' => $status['data'], 'error' => !empty($status['data']['error_message']) ? sanitize_text_field($status['data']['error_message']) : 'Meta could not process the media container.');
            if ($attempt < 4) sleep(2);
        }
        return array('ok' => false, 'data' => array(), 'error' => 'Meta is still processing the media. Please try again in a moment.');
    }

    private static function published_result($published, $base, $token)
    {
        $bad = self::bad_result();
        if (!$published['ok'] || empty($published['data']['id'])) { $bad['Error'] = $published['error']; return $bad; }
        $id = sanitize_text_field($published['data']['id']);
        $detail = self::request('GET', $base . rawurlencode($id), array('fields' => 'id,permalink', 'access_token' => $token));
        return array('pgID' => $id, 'postID' => $id, 'isPosted' => 1, 'pDate' => date('Y-m-d H:i:s'), 'postURL' => $detail['ok'] && !empty($detail['data']['permalink']) ? esc_url_raw($detail['data']['permalink']) : '', 'Error' => '');
    }

    private static function request($method, $url, $fields)
    {
        $args = array('timeout' => 45, 'redirection' => 3, 'headers' => array('Accept' => 'application/json'));
        if (!empty($fields['access_token']) && strpos((string) wp_parse_url($url, PHP_URL_PATH), '/access_token') === false) {
            $args['headers']['Authorization'] = 'Bearer ' . $fields['access_token'];
            unset($fields['access_token']);
        }
        if ($method === 'GET') $url = add_query_arg($fields, $url); else $args['body'] = $fields;
        $response = wp_remote_request($url, array_merge($args, array('method' => $method)));
        if (is_wp_error($response)) return array('ok' => false, 'data' => array(), 'error' => $response->get_error_message());
        $status = (int) wp_remote_retrieve_response_code($response); $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($data)) $data = array();
        if ($status < 200 || $status >= 300 || isset($data['error'])) {
            $error = !empty($data['error']['message']) ? $data['error']['message'] : (!empty($data['error_message']) ? $data['error_message'] : sprintf('Meta API returned HTTP %d.', $status));
            return array('ok' => false, 'data' => $data, 'error' => sanitize_text_field($error));
        }
        return array('ok' => true, 'data' => $data, 'error' => '');
    }

    private static function bad_result() { return array('pgID' => '', 'postID' => '', 'isPosted' => 0, 'pDate' => date('Y-m-d H:i:s'), 'postURL' => '', 'Error' => ''); }
    private static function redirect_error($network, $message) { self::redirect_notice($message, true); }
    private static function redirect_notice($message, $error = false) { wp_safe_redirect(add_query_arg(array('page' => 'nxssnap', 'nxs_meta_notice' => rawurlencode($message), 'nxs_meta_error' => $error ? 1 : 0), admin_url('admin.php'))); exit; }

    public static function notice()
    {
        if (empty($_GET['nxs_meta_notice']) || !nxs_snap_user_can_access()) return;
        $message = sanitize_text_field(rawurldecode(wp_unslash($_GET['nxs_meta_notice']))); $error = !empty($_GET['nxs_meta_error']);
        echo '<div class="notice ' . ($error ? 'notice-error' : 'notice-success') . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }
}

NXS_Meta_Direct::register();
