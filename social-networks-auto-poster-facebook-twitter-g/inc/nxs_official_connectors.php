<?php
/** Official OAuth and HTTP helpers for Pinterest, Reddit, Scoop.it, and Google Business Profile. */
if (!defined('ABSPATH')) exit;

if (!class_exists('NXS_Official_Connectors')) { class NXS_Official_Connectors {
    public static function callback_url() { return nxs_get_admin_url('admin.php?page=nxssnap'); }

    public static function handle_oauth($network, $accounts) {
        if (!in_array($network, array('pn', 'rd', 'gmb'), true) || !nxs_snap_user_can_access()) return;
        $start = isset($_GET['nxs_official_start']) ? sanitize_key(wp_unslash($_GET['nxs_official_start'])) : '';
        if ($start === $network) {
            $index = isset($_GET['acc']) ? absint($_GET['acc']) : -1;
            if (!isset($accounts[$index]) || empty($accounts[$index]['appKey']) || empty($accounts[$index]['appSec'])) wp_die(esc_html__('Save your app credentials before connecting.', 'social-networks-auto-poster-facebook-twitter-g'));
            $client = nxs_gak($accounts[$index]['appKey']);
            $state = nxs_oauth_state_create($network, $index);
            if ($network === 'pn') $query = array('client_id'=>$client, 'redirect_uri'=>self::callback_url(), 'response_type'=>'code', 'scope'=>'boards:read,boards:write,pins:read,pins:write', 'state'=>$state);
            elseif ($network === 'gmb') $query = array('client_id'=>$client, 'redirect_uri'=>self::callback_url(), 'response_type'=>'code', 'scope'=>'https://www.googleapis.com/auth/business.manage', 'state'=>$state, 'access_type'=>'offline', 'prompt'=>'consent');
            else $query = array('client_id'=>$client, 'response_type'=>'code', 'state'=>$state, 'redirect_uri'=>self::callback_url(), 'duration'=>'permanent', 'scope'=>'identity submit');
            $base = $network === 'pn' ? 'https://www.pinterest.com/oauth/' : ($network === 'gmb' ? 'https://accounts.google.com/o/oauth2/v2/auth' : 'https://www.reddit.com/api/v1/authorize');
            self::redirect($base.'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986), true);
        }
        $state = isset($_GET['state']) ? wp_unslash($_GET['state']) : '';
        if (!is_string($state) || strpos($state, 'nxs-'.$network.'-') !== 0) return;
        if (!nxs_oauth_state_validate($state, $network, $index) || !isset($accounts[$index])) wp_die(esc_html__('Invalid or expired authorization state.', 'social-networks-auto-poster-facebook-twitter-g'), '', array('response'=>403));
        if (!empty($_GET['error'])) wp_die(esc_html(sanitize_text_field(wp_unslash($_GET['error']))));
        $code = isset($_GET['code']) ? sanitize_text_field(wp_unslash($_GET['code'])) : '';
        if ($code === '') wp_die(esc_html__('Authorization code is missing.', 'social-networks-auto-poster-facebook-twitter-g'));
        $account = $accounts[$index];
        $body = array('grant_type'=>'authorization_code', 'code'=>$code, 'redirect_uri'=>self::callback_url());
        $token = self::token_request($network, $account, $body);
        if (empty($token['access_token']) || empty($token['refresh_token'])) wp_die(esc_html(!empty($token['message']) ? $token['message'] : __('Authorization did not return a renewable token.', 'social-networks-auto-poster-facebook-twitter-g')));
        $account['accessToken'] = $token['access_token'];
        $account['refreshToken'] = $token['refresh_token'];
        $account['accessTokenExp'] = time() + max(0, (int)($token['expires_in'] ?? 3600));
        $account['officialApi'] = $network;
        if ($network === 'rd') {
            $me = self::request('https://oauth.reddit.com/api/v1/me', $account['accessToken'], 'GET', null, 'rd');
            if (!empty($me['name'])) $account['uName'] = sanitize_user($me['name']);
        }
        nxs_save_glbNtwrks($network, $index, $account, '*');
        self::redirect(self::callback_url(), false);
    }

    public static function token_request($network, $account, $body) {
        $url = $network === 'pn' ? 'https://api.pinterest.com/v5/oauth/token' : ($network === 'gmb' ? 'https://oauth2.googleapis.com/token' : 'https://www.reddit.com/api/v1/access_token');
        $client = nxs_gak($account['appKey']); $secret = nxs_gas($account['appSec']);
        if ($network === 'gmb') { $body['client_id']=$client; $body['client_secret']=$secret; }
        $headers = array('Content-Type'=>'application/x-www-form-urlencoded', 'Accept'=>'application/json');
        if ($network !== 'gmb') $headers['Authorization']='Basic '.base64_encode($client.':'.$secret);
        if ($network === 'rd') $headers['User-Agent'] = self::reddit_agent();
        $response = nxs_remote_post($url, array('headers'=>$headers, 'body'=>$body, 'timeout'=>30));
        return self::decode($response);
    }

    public static function access_token($network, &$account) {
        if (empty($account['officialApi']) || $account['officialApi'] !== $network || empty($account['refreshToken'])) return '';
        if (!empty($account['accessToken']) && !empty($account['accessTokenExp']) && (int)$account['accessTokenExp'] > time() + 300) return $account['accessToken'];
        $token = self::token_request($network, $account, array('grant_type'=>'refresh_token', 'refresh_token'=>$account['refreshToken']));
        if (empty($token['access_token'])) return '';
        $account['accessToken'] = $token['access_token'];
        if (!empty($token['refresh_token'])) $account['refreshToken'] = $token['refresh_token'];
        $account['accessTokenExp'] = time() + max(0, (int)($token['expires_in'] ?? 3600));
        if (isset($account['ii'])) nxs_save_glbNtwrks($network, $account['ii'], $account, '*');
        return $account['accessToken'];
    }

    public static function request($url, $token, $method='GET', $body=null, $network='') {
        $headers = array('Authorization'=>'Bearer '.$token, 'Accept'=>'application/json');
        if ($network === 'rd') $headers['User-Agent'] = self::reddit_agent();
        if ($method === 'POST') $headers['Content-Type'] = in_array($network,array('pn','gmb'),true) ? 'application/json' : 'application/x-www-form-urlencoded';
        $args = array('headers'=>$headers, 'timeout'=>30);
        if ($body !== null) {
            if (in_array($network,array('pn','gmb'),true)) $args['body'] = wp_json_encode($body);
            elseif ($network === 'sc' && is_array($body) && !empty($body['tag'])) {
                $tags = $body['tag']; unset($body['tag']);
                $args['body'] = http_build_query($body, '', '&', PHP_QUERY_RFC3986);
                foreach ($tags as $tag) $args['body'] .= '&tag='.rawurlencode($tag);
            } else $args['body'] = $body;
        }
        $response = $method === 'POST' ? nxs_remote_post($url, $args) : nxs_remote_get($url, $args);
        return self::decode($response);
    }

    public static function decode($response) {
        if (is_nxs_error($response)) return array('message'=>$response->get_error_message());
        if (!is_array($response)) return array('message'=>'Invalid API response');
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body)) return array('message'=>'Invalid JSON response', 'http_status'=>wp_remote_retrieve_response_code($response));
        $body['http_status'] = wp_remote_retrieve_response_code($response);
        return $body;
    }

    private static function redirect($url, $external) {
        if (!headers_sent()) {
            if ($external) wp_redirect($url); else wp_safe_redirect($url);
            exit;
        }
        echo '<script>window.location.replace('.wp_json_encode($url).');</script>';
        echo '<p><a href="'.esc_url($url).'">Continue authorization</a></p>';
        exit;
    }

    private static function reddit_agent() { return 'wordpress:nextscripts.snap:4.5.0.30 (contact: https://www.nextscripts.com/)'; }
}}
