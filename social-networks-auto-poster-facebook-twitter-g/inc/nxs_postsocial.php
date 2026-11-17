<?php
/**
 * Post.Social API integration.
 *
 * Kept separate from legacy network classes: Post.Social owns its remote
 * accounts and publication lifecycle; SNAP stores only site-scoped connection
 * state and durable submission correlation.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('NXS_POSTSOCIAL_API_URL')) {
    define('NXS_POSTSOCIAL_API_URL', 'https://api.post.social');
}
if (!defined('NXS_POSTSOCIAL_WEB_URL')) {
    define('NXS_POSTSOCIAL_WEB_URL', 'https://web.post.social');
}

class NXS_PostSocial_State
{
    const OPTION = 'nxs_postsocial_v1';
    const ACCOUNT_PENDING_OPTION = 'nxs_postsocial_account_pending_v1';

    public static function all()
    {
        $state = get_option(self::OPTION, array());
        return is_array($state) ? $state : array();
    }

    public static function update($values)
    {
        $state = array_merge(self::all(), is_array($values) ? $values : array());
        update_option(self::OPTION, $state, false);
        return $state;
    }

    public static function installation_uuid()
    {
        $state = self::all();
        if (!empty($state['bootstrap_site_uuid']) && wp_is_uuid($state['bootstrap_site_uuid'])) {
            return strtolower($state['bootstrap_site_uuid']);
        }
        if (empty($state['installation_uuid']) || !preg_match('/^[a-f0-9-]{36}$/i', $state['installation_uuid'])) {
            $state['installation_uuid'] = wp_generate_uuid4();
            update_option(self::OPTION, $state, false);
        }
        return strtolower($state['installation_uuid']);
    }

    public static function set_token($token)
    {
        $state = self::all();
        $state['token_encrypted'] = self::encrypt((string) $token);
        unset($state['token']);
        update_option(self::OPTION, $state, false);
    }

    public static function token()
    {
        $state = self::all();
        return empty($state['token_encrypted']) ? '' : self::decrypt($state['token_encrypted']);
    }

    public static function premium_key()
    {
        $state = self::all();
        return empty($state['premium_key_encrypted']) ? '' : self::decrypt($state['premium_key_encrypted']);
    }

    public static function set_premium_key($key)
    {
        $key = trim((string) $key);
        if ($key === '') return false;
        $state = self::all();
        $hash = hash_hmac('sha256', $key, wp_salt('auth'));
        if (!empty($state['premium_key_hash']) && hash_equals($state['premium_key_hash'], $hash) && self::premium_key() === $key) return true;
        $encrypted = self::encrypt($key);
        if ($encrypted === '' || self::decrypt($encrypted) !== $key) return false;
        $state['premium_key_encrypted'] = $encrypted;
        $state['premium_key_hash'] = $hash;
        unset($state['premium_subscription']);
        update_option(self::OPTION, $state, false);
        return true;
    }

    public static function site_key()
    {
        $state = self::all();
        if (!empty($state['site_key_encrypted'])) return self::decrypt($state['site_key_encrypted']);
        $key = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $encrypted = self::encrypt($key);
        if ($encrypted === '' || !hash_equals($key, self::decrypt($encrypted))) return '';
        self::update(array('site_key_encrypted' => $encrypted));
        return $key;
    }

    public static function set_refresh_token($token)
    {
        $state = self::all();
        if ((string) $token === '') {
            unset($state['refresh_token_encrypted']);
        } else {
            $state['refresh_token_encrypted'] = self::encrypt((string) $token);
        }
        update_option(self::OPTION, $state, false);
    }

    public static function refresh_token()
    {
        $state = self::all();
        return empty($state['refresh_token_encrypted']) ? '' : self::decrypt($state['refresh_token_encrypted']);
    }

    public static function set_pending($pending)
    {
        $state = self::all();
        $state['pending'] = is_array($pending) ? $pending : array();
        foreach (array('code_verifier', 'poll_token', 'proof_secret', 'legacy_secret') as $secret_key) {
            if (!empty($state['pending'][$secret_key])) {
                $state['pending'][$secret_key . '_encrypted'] = self::encrypt($state['pending'][$secret_key]);
                unset($state['pending'][$secret_key]);
            }
        }
        update_option(self::OPTION, $state, false);
    }

    public static function pending()
    {
        $state = self::all();
        $pending = !empty($state['pending']) && is_array($state['pending']) ? $state['pending'] : array();
        foreach (array('code_verifier', 'poll_token', 'proof_secret', 'legacy_secret') as $secret_key) {
            if (!empty($pending[$secret_key . '_encrypted'])) {
                $pending[$secret_key] = self::decrypt($pending[$secret_key . '_encrypted']);
            } elseif ($secret_key === 'proof_secret' && !empty($pending['proof_secret'])) {
                // Compatibility with pending claims created before 4.5.0.3.
                $pending['proof_secret'] = self::decrypt($pending['proof_secret']);
            }
            unset($pending[$secret_key . '_encrypted']);
        }
        return $pending;
    }

    public static function clear_pending()
    {
        $state = self::all();
        unset($state['pending']);
        update_option(self::OPTION, $state, false);
    }

    public static function connected()
    {
        $state = self::all();
        return !empty($state['connection_id']) && self::token() !== '';
    }

    public static function clear_connection()
    {
        $state = self::all();
        foreach (array('token', 'token_encrypted', 'refresh_token_encrypted', 'token_id', 'token_expires_at', 'pending_rotation_key', 'connection_id', 'project_id', 'project_name', 'project_url', 'manage_url', 'account_hint', 'accounts', 'billing', 'supported_networks', 'supported_networks_refreshed_at', 'supported_networks_plugin_version', 'initial_accounts_imported_for', 'pending', 'account_pending', 'account_settings_draft', 'bootstrap_site_uuid', 'account_type', 'account_type_checked_at', 'credential_type') as $key) {
            unset($state[$key]);
        }
        update_option(self::OPTION, $state, false);
        update_option(self::ACCOUNT_PENDING_OPTION, array(), false);
    }

    public static function set_account_pending($pending)
    {
        $pending = is_array($pending) ? $pending : array();
        foreach (array('poll_token', 'secret', 'code_verifier') as $secret_key) {
            if (!empty($pending[$secret_key])) {
                $pending[$secret_key . '_encrypted'] = self::encrypt($pending[$secret_key]);
                unset($pending[$secret_key]);
            }
        }
        // Keep the short-lived OAuth attempt apart from routine connection-state updates.
        update_option(self::ACCOUNT_PENDING_OPTION, $pending, false);
        $state = self::all();
        if (isset($state['account_pending'])) {
            unset($state['account_pending']);
            update_option(self::OPTION, $state, false);
        }
    }

    public static function account_pending()
    {
        $pending = get_option(self::ACCOUNT_PENDING_OPTION, null);
        if (!is_array($pending)) {
            $state = self::all();
            $pending = !empty($state['account_pending']) && is_array($state['account_pending']) ? $state['account_pending'] : array();
        }
        foreach (array('poll_token', 'secret', 'code_verifier') as $secret_key) {
            if (!empty($pending[$secret_key . '_encrypted'])) {
                $pending[$secret_key] = self::decrypt($pending[$secret_key . '_encrypted']);
            }
            unset($pending[$secret_key . '_encrypted']);
        }
        return $pending;
    }

    public static function clear_account_pending()
    {
        update_option(self::ACCOUNT_PENDING_OPTION, array(), false);
        $state = self::all();
        if (isset($state['account_pending'])) {
            unset($state['account_pending']);
            update_option(self::OPTION, $state, false);
        }
    }

    private static function key()
    {
        return hash('sha256', wp_salt('auth') . '|nxs-postsocial-v1', true);
    }

    private static function encrypt($plaintext)
    {
        if ($plaintext === '') {
            return '';
        }
        if (!function_exists('openssl_encrypt')) {
            return '';
        }
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($ciphertext === false) {
            return '';
        }
        return base64_encode($iv . $tag . $ciphertext);
    }

    private static function decrypt($encoded)
    {
        if (!function_exists('openssl_decrypt')) {
            return '';
        }
        $raw = base64_decode((string) $encoded, true);
        if ($raw === false || strlen($raw) < 29) {
            return '';
        }
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $ciphertext = substr($raw, 28);
        $plaintext = openssl_decrypt($ciphertext, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        return is_string($plaintext) ? $plaintext : '';
    }
}

class NXS_PostSocial_Premium
{
    public static function register()
    {
        // Observe the existing Helper activation request before its callback exits.
        add_action('wp_ajax_nxsDoLic', array(__CLASS__, 'key_entered'), 1);
        add_action('admin_init', array(__CLASS__, 'preload_for_admin_page'));
        add_action('admin_notices', array(__CLASS__, 'render_banner'));
        add_action('network_admin_notices', array(__CLASS__, 'render_banner'));
        add_action('admin_post_nxs_postsocial_dismiss_loyalty', array(__CLASS__, 'dismiss_loyalty'));
    }

    private static function is_snap_page()
    {
        $page = isset($_GET['page']) && is_scalar($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        return in_array($page, array('nxssnap', 'nxssnap-post', 'nxssnap-query', 'nxssnap-reposter', 'nxssnap-settings', 'nxs-log', 'nxs-help', 'nxssnap-postsocial', 'nxssnap-ntadmin'), true);
    }

    public static function preload_for_admin_page()
    {
        if (!function_exists('nxs_postsocial_check_snap_premium_subscription')) return;
        if (!self::is_snap_page() || !current_user_can('manage_options')) return;
        $page = sanitize_key(wp_unslash($_GET['page']));
        $state = NXS_PostSocial_State::all();
        if ($page === 'nxssnap-postsocial' || empty($state['premium_subscription']['checked_at'])) self::check_on_page_open();
    }

    public static function render_banner()
    {
        if (!self::is_snap_page() || !current_user_can('manage_options')) return;
        $state = NXS_PostSocial_State::all();
        $provider = function_exists('nxs_postsocial_check_snap_premium_subscription');
        $key = $provider ? NXS_PostSocial_State::premium_key() : '';
        $confirmed = $provider && !empty($state['premium_subscription']['active']) && $key !== '' && !empty($state['premium_key_hash']) && hash_equals($state['premium_key_hash'], hash_hmac('sha256', $key, wp_salt('auth')));
        if (!$confirmed) {
            $inactive = $provider && !empty($state['premium_subscription']['checked_at']) && empty($state['premium_subscription']['active']);
            if ($inactive || self::has_legacy_pro_trace()) {
                if (self::loyalty_dismissed()) self::register_loyalty_sidebar();
                else self::render_welcome_back_banner();
            } else {
                self::register_new_user_sidebar();
            }
            return;
        }
        if (sanitize_key(wp_unslash($_GET['page'])) === 'nxssnap-postsocial') return;
        ?>
        <div class="notice notice-info" style="border-left-color:#008a20"><p>
            <strong><?php esc_html_e('🚀 Meet Post.Social — Your New SNAP Publishing Cloud!', 'social-networks-auto-poster-facebook-twitter-g'); ?></strong>
            <?php esc_html_e('Replaces SNAP Premium API with 1-click social connections (no API keys needed).', 'social-networks-auto-poster-facebook-twitter-g'); ?>
            <strong><?php esc_html_e('Included for free as part of your SNAP Premium API access.', 'social-networks-auto-poster-facebook-twitter-g'); ?></strong>
            <a href="<?php echo esc_url(nxs_get_admin_url('admin.php?page=nxssnap-postsocial')); ?>"><?php esc_html_e('Activate Now →', 'social-networks-auto-poster-facebook-twitter-g'); ?></a>
        </p></div>
        <?php
    }

    public static function has_legacy_pro_trace()
    {
        $state = NXS_PostSocial_State::all();
        if (!empty($state['premium_key_encrypted']) || !empty($state['premium_key_hash'])) return true;

        foreach (array('nxsSNAPOptions', 'NS_SNAutoPoster') as $option_name) {
            $options = get_option($option_name, array());
            if (!is_array($options)) continue;
            foreach (array('lk', 'lku') as $field) {
                if (isset($options[$field]) && is_scalar($options[$field]) && !in_array(trim((string) $options[$field]), array('', '-', '0'), true)) return true;
            }
        }
        foreach (array('__plugins_cache_250', '__plugins_cache_244') as $option_name) {
            $cache = get_site_option($option_name);
            if (is_array($cache) && !empty($cache['lku']) && is_scalar($cache['lku']) && preg_match('/^[a-f0-9]{32}$/i', trim((string) $cache['lku']))) return true;
        }
        foreach (array('__plugins_cache_200', '__plugins_cache_201') as $option_name) {
            $cache = get_site_option($option_name);
            if (is_string($cache)) $cache = maybe_unserialize($cache);
            if (is_array($cache) && !empty($cache)) return true;
        }
        if ((int) get_site_option('__nxsuid') > 0) return true;
        foreach (array(242, 252, 253, 254, 255, 256, 257, 258, 259, 260, 261) as $suffix) {
            $code = get_site_option('__plugins_cache_' . $suffix);
            if (is_string($code) && $code !== '') return true;
        }
        return false;
    }

    public static function render_welcome_back_banner()
    {
        $url = 'https://web.post.social/register?snap=expro&site=' . rawurlencode(home_url('/'));
        ?>
        <?php self::loyalty_styles(); ?>
        <div class="notice notice-info nxs-ps-loyalty-banner">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="nxs-ps-loyalty-dismiss-form">
                <input type="hidden" name="action" value="nxs_postsocial_dismiss_loyalty">
                <?php wp_nonce_field('nxs_postsocial_dismiss_loyalty'); ?>
                <button type="submit" class="notice-dismiss" aria-label="<?php echo esc_attr__('Close offer', 'social-networks-auto-poster-facebook-twitter-g'); ?>"><span class="screen-reader-text"><?php esc_html_e('Close offer', 'social-networks-auto-poster-facebook-twitter-g'); ?></span></button>
            </form>
            <h2><?php esc_html_e('🎉 Welcome Back! A Special Thank You from SNAP', 'social-networks-auto-poster-facebook-twitter-g'); ?></h2>
            <p><?php echo wp_kses_post(__('Meet <strong>Post.Social</strong> — our new cloud publishing platform replacing SNAP Premium API.', 'social-networks-auto-poster-facebook-twitter-g')); ?></p>
            <p><?php esc_html_e('Connect Facebook, Instagram, X, LinkedIn, Threads, Bluesky, and more in just a few clicks — no developer apps, API keys, or complicated setup.', 'social-networks-auto-poster-facebook-twitter-g'); ?></p>
            <p><?php echo wp_kses_post(__("As a thank you for your past support, we've reserved a <strong>free trial and an exclusive loyalty discount</strong> just for you!", 'social-networks-auto-poster-facebook-twitter-g')); ?></p>
            <div class="nxs-ps-loyalty-actions"><a class="nxs-ps-loyalty-cta" href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener" referrerpolicy="origin"><?php esc_html_e('🚀 Claim Your Free Trial & Loyalty Discount', 'social-networks-auto-poster-facebook-twitter-g'); ?></a>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="nxs-ps-loyalty-close-form">
                    <input type="hidden" name="action" value="nxs_postsocial_dismiss_loyalty">
                    <?php wp_nonce_field('nxs_postsocial_dismiss_loyalty'); ?>
                    <button type="submit" class="button nxs-ps-loyalty-close"><?php esc_html_e('Close', 'social-networks-auto-poster-facebook-twitter-g'); ?></button>
                </form>
            </div>
        </div>
        <?php
    }

    private static function loyalty_styles()
    {
        ?>
        <style>
            .nxs-ps-loyalty-banner{position:relative;border-left-color:#008a20;padding:10px 42px 12px 16px;margin:12px 0 16px}
            .nxs-ps-loyalty-banner h2{font-size:18px!important;line-height:1.3!important;margin:2px 0 8px!important;font-weight:600}
            .nxs-ps-loyalty-banner p{margin:7px 0;font-size:13px;line-height:1.45}
            .nxs-ps-loyalty-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
            .nxs-ps-loyalty-close-form,.nxs-ps-loyalty-dismiss-form{margin:0}
            .nxs-ps-loyalty-cta,.nxs-ps-loyalty-banner .nxs-ps-loyalty-close{box-sizing:border-box!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;min-height:40px!important;height:40px!important;padding:0 14px!important;border-radius:4px!important;font-family:inherit!important;font-size:13px!important;font-weight:600!important;line-height:1.25!important;vertical-align:middle!important;white-space:normal!important;text-align:center!important;box-shadow:none!important}
            a.nxs-ps-loyalty-cta,a.nxs-ps-loyalty-cta:link,a.nxs-ps-loyalty-cta:visited,a.nxs-ps-loyalty-cta:hover,a.nxs-ps-loyalty-cta:focus,a.nxs-ps-loyalty-cta:active{max-width:100%;border:1px solid #006b18!important;background:#008a20!important;color:#fff!important;text-decoration:none!important;border-bottom:1px solid #006b18!important;box-shadow:none!important}
            a.nxs-ps-loyalty-cta:hover,a.nxs-ps-loyalty-cta:focus{background:#006b18!important}
            .nxs-ps-loyalty-banner .nxs-ps-loyalty-close{border:1px solid #3858ee!important;background:#fff!important;color:#3858ee!important;text-shadow:none!important;cursor:pointer}
            .nxs-ps-loyalty-sidebar p{margin:8px 0;line-height:1.45}
            #nxs_postsocial_new_user .inside,#nxs_postsocial_loyalty .inside{background:#eff8ef;margin:0!important;padding:14px 16px 16px!important}
            .nxs-ps-new-sidebar p{margin:8px 0;line-height:1.45}
            .nxs-ps-new-sidebar .nxs-ps-new-logo,.nxs-ps-loyalty-sidebar .nxs-ps-new-logo{text-align:center;margin:0 0 14px}
            .nxs-ps-new-sidebar .nxs-ps-new-logo img,.nxs-ps-loyalty-sidebar .nxs-ps-new-logo img{display:inline-block;max-width:190px;width:70%;height:auto}
            .nxs-ps-new-sidebar .nxs-ps-new-action,.nxs-ps-loyalty-sidebar .nxs-ps-new-action{text-align:center;margin:14px 0 0}
            .nxs-ps-new-sidebar .nxs-ps-loyalty-cta,.nxs-ps-loyalty-sidebar .nxs-ps-loyalty-cta{width:auto!important;height:auto!important;min-height:40px!important;padding:8px 12px!important;font-size:12px!important}
        </style>
        <?php
    }

    private static function loyalty_dismissed()
    {
        return (string) get_user_meta(get_current_user_id(), 'nxs_postsocial_loyalty_dismissed', true) === '1';
    }

    public static function dismiss_loyalty()
    {
        if (!current_user_can('manage_options')) wp_die(esc_html__('Not allowed.', 'social-networks-auto-poster-facebook-twitter-g'));
        check_admin_referer('nxs_postsocial_dismiss_loyalty');
        update_user_meta(get_current_user_id(), 'nxs_postsocial_loyalty_dismissed', '1');
        wp_safe_redirect(wp_get_referer() ?: nxs_get_admin_url('admin.php?page=nxssnap'));
        exit;
    }

    private static function register_loyalty_sidebar()
    {
        add_meta_box('nxs_postsocial_loyalty', __('Post.Social Loyalty Offer', 'social-networks-auto-poster-facebook-twitter-g'), array(__CLASS__, 'render_loyalty_sidebar'), 'toplevel_page_nxssnap', 'side', 'default');
    }

    private static function register_new_user_sidebar()
    {
        add_meta_box('nxs_postsocial_new_user', __('Post.Social Cloud Offer', 'social-networks-auto-poster-facebook-twitter-g'), array(__CLASS__, 'render_new_user_sidebar'), 'toplevel_page_nxssnap', 'side', 'default');
    }

    public static function render_new_user_sidebar()
    {
        $url = 'https://web.post.social/register?snap=new&site=' . rawurlencode(home_url('/'));
        self::loyalty_styles();
        ?>
        <div class="nxs-ps-new-sidebar">
            <p class="nxs-ps-new-logo"><img src="<?php echo esc_url(NXS_PLURL . 'img/postsocial-logo-horizontal.png'); ?>" alt="<?php echo esc_attr__('Post.Social', 'social-networks-auto-poster-facebook-twitter-g'); ?>"></p>
            <p><strong><?php esc_html_e('🎉 Meet the New SNAP Option — Cloud Auto-Posting!', 'social-networks-auto-poster-facebook-twitter-g'); ?></strong></p>
            <p><?php echo wp_kses_post(__('No more developer apps, approval requests, or complicated API setups! Connect Facebook, Instagram, X, LinkedIn, Threads, Bluesky, Pinterest, VK, and more in just a few clicks with <strong>Post.Social Cloud</strong>.', 'social-networks-auto-poster-facebook-twitter-g')); ?></p>
            <p><strong><?php esc_html_e('🎁 Special Release Offer for All SNAP Users!', 'social-networks-auto-poster-facebook-twitter-g'); ?></strong></p>
            <p><?php echo wp_kses_post(__('Celebrate the new SNAP with a <strong>free trial (no credit card required)</strong> and an <strong>exclusive discount</strong> on Post.Social plans.', 'social-networks-auto-poster-facebook-twitter-g')); ?></p>
            <p class="nxs-ps-new-action"><a class="nxs-ps-loyalty-cta" href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener" referrerpolicy="origin"><?php esc_html_e('🚀 Start Free Trial & Unlock Your Discount', 'social-networks-auto-poster-facebook-twitter-g'); ?></a></p>
        </div>
        <?php
    }

    public static function render_loyalty_sidebar()
    {
        $url = 'https://web.post.social/register?snap=expro&site=' . rawurlencode(home_url('/'));
        self::loyalty_styles();
        ?>
        <div class="nxs-ps-loyalty-sidebar">
            <p class="nxs-ps-new-logo"><img src="<?php echo esc_url(NXS_PLURL . 'img/postsocial-logo-horizontal.png'); ?>" alt="<?php echo esc_attr__('Post.Social', 'social-networks-auto-poster-facebook-twitter-g'); ?>"></p>
            <p><strong><?php esc_html_e('🎉 Welcome Back! A Special Thank You from SNAP', 'social-networks-auto-poster-facebook-twitter-g'); ?></strong></p>
            <p><?php echo wp_kses_post(__('Meet <strong>Post.Social</strong> — our new cloud publishing platform replacing SNAP Premium API.', 'social-networks-auto-poster-facebook-twitter-g')); ?></p>
            <p><?php esc_html_e('Connect your social accounts in just a few clicks — no developer apps or API keys.', 'social-networks-auto-poster-facebook-twitter-g'); ?></p>
            <p><?php echo wp_kses_post(__("Your past support qualifies you for a <strong>free trial and an exclusive loyalty discount</strong>.", 'social-networks-auto-poster-facebook-twitter-g')); ?></p>
            <p class="nxs-ps-new-action"><a class="nxs-ps-loyalty-cta" href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener" referrerpolicy="origin"><?php esc_html_e('Claim Free Trial & Discount →', 'social-networks-auto-poster-facebook-twitter-g'); ?></a></p>
        </div>
        <?php
    }

    public static function key_entered()
    {
        if (!function_exists('nxs_postsocial_check_snap_premium_subscription')) return;
        if (!current_user_can('manage_options')) return;
        check_ajax_referer('doLic');
        $key = isset($_POST['lk']) && is_scalar($_POST['lk']) ? trim((string) wp_unslash($_POST['lk'])) : '';
        if ($key === '' || !NXS_PostSocial_State::set_premium_key($key)) return;
        $state = NXS_PostSocial_State::all();
        if (empty($state['premium_subscription']['checked_at'])) self::check($key);
    }

    public static function check_on_page_open()
    {
        if (!function_exists('nxs_postsocial_check_snap_premium_subscription')) return;
        $key = NXS_PostSocial_State::premium_key();
        if ($key === '') {
            $options = get_option('nxsSNAPOptions', array());
            $key = is_array($options) && !empty($options['lk']) && is_scalar($options['lk']) ? trim((string) $options['lk']) : '';
            if ($key === '') {
                $helper = get_site_option('__plugins_cache_250');
                if (!is_array($helper) || empty($helper['lku'])) $helper = get_site_option('__plugins_cache_244');
                $hash = is_array($helper) && !empty($helper['lku']) && is_scalar($helper['lku']) ? trim((string) $helper['lku']) : '';
                if (preg_match('/^[a-f0-9]{32}$/i', $hash)) $key = strtolower($hash);
            }
            if ($key === '' || !NXS_PostSocial_State::set_premium_key($key)) return;
        }
        self::check($key);
    }

    public static function check($key)
    {
        $key = trim((string) $key);
        if ($key === '' || !function_exists('nxs_postsocial_check_snap_premium_subscription')) return;
        $state = NXS_PostSocial_State::all();
        $hash = hash_hmac('sha256', $key, wp_salt('auth'));
        if (empty($state['premium_key_hash']) || !hash_equals($state['premium_key_hash'], $hash)) return;
        $previous = !empty($state['premium_subscription']) && is_array($state['premium_subscription']) ? $state['premium_subscription'] : array();
        if (!empty($previous['retry_at']) && (int) $previous['retry_at'] > time()) return;
        $result = nxs_postsocial_check_snap_premium_subscription($key);
        if (!is_array($result) || empty($result['status'])) return;
        if ($result['status'] === 'rate_limited') {
            $previous['retry_at'] = time() + max(1, (int) ($result['retry_after'] ?? 60));
            NXS_PostSocial_State::update(array('premium_subscription' => $previous));
            return;
        }
        if ($result['status'] !== 'active' && $result['status'] !== 'invalid') return;
        $saved = array(
            'active' => $result['status'] === 'active',
            'email_hint' => !empty($result['email_hint']) ? sanitize_text_field($result['email_hint']) : '',
            'checked_at' => time(),
        );
        NXS_PostSocial_State::update(array('premium_subscription' => $saved));
    }

    public static function render_offer()
    {
        if (!function_exists('nxs_postsocial_check_snap_premium_subscription')) return;
        if (NXS_PostSocial_State::connected()) return;
        $state = NXS_PostSocial_State::all();
        $result = !empty($state['premium_subscription']) && is_array($state['premium_subscription']) ? $state['premium_subscription'] : array();
        $key = NXS_PostSocial_State::premium_key();
        if (empty($result['active']) || $key === '' || empty($state['premium_key_hash']) || !hash_equals($state['premium_key_hash'], hash_hmac('sha256', $key, wp_salt('auth')))) return;
        $url = 'https://web.post.social/register?snap=' . rawurlencode($key) . '&site=' . rawurlencode(home_url('/'));
        ?>
        <div class="notice notice-info" style="padding:16px 20px;margin:16px 0;max-width:720px;border-left-color:#008a20">
            <h2 style="margin:0 0 12px"><?php esc_html_e('🚀 Meet Post.Social — Your New SNAP Publishing Cloud!', 'social-networks-auto-poster-facebook-twitter-g'); ?></h2>
            <p><?php esc_html_e('Post.Social has replaced SNAP Premium API as our new cloud publishing service.', 'social-networks-auto-poster-facebook-twitter-g'); ?></p>
            <p><?php esc_html_e('Connect Facebook, Instagram, X, LinkedIn, Threads, Bluesky, Pinterest, and VK in just a few clicks — no developer apps or API keys required.', 'social-networks-auto-poster-facebook-twitter-g'); ?></p>
            <p><strong><?php esc_html_e('The Post.Social SNAP Premium plan is included with your SNAP Premium API access.', 'social-networks-auto-poster-facebook-twitter-g'); ?></strong></p>
            <?php if (!empty($result['email_hint'])): ?><p><?php esc_html_e('Original registration email:', 'social-networks-auto-poster-facebook-twitter-g'); ?> <strong><?php echo esc_html($result['email_hint']); ?></strong></p>
            <?php else: ?><p><?php esc_html_e('We could not identify the registration email. You will need to confirm ownership of your email during activation.', 'social-networks-auto-poster-facebook-twitter-g'); ?></p><?php endif; ?>
            <p><a class="button button-primary button-hero" href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('🚀 Activate Post.Social', 'social-networks-auto-poster-facebook-twitter-g'); ?></a></p>
        </div>
        <?php
    }
}

class NXS_PostSocial_Api
{
    private $base_url;

    public function __construct()
    {
        $this->base_url = untrailingslashit((string) NXS_POSTSOCIAL_API_URL);
    }

    public function request($method, $path, $body = null, $token = null, $headers = array())
    {
        $url = $this->base_url . '/api/v1/snap/' . ltrim($path, '/');
        if (!nxs_validate_remote_url($url, true)) {
            return array('ok' => false, 'status' => 0, 'error' => array(
                'code' => 'invalid_api_url',
                'category' => 'configuration',
                'message' => __('The configured Post.Social API URL is not a safe HTTPS endpoint.', 'social-networks-auto-poster-facebook-twitter-g'),
                'retryable' => false,
            ), 'request_id' => null);
        }
        $request_headers = array_merge(array(
            'Accept' => 'application/json',
            'User-Agent' => $this->user_agent(),
        ), $headers);
        if ($token === null) {
            $token = NXS_PostSocial_State::token();
        }
        if ($token !== '') {
            $request_headers['Authorization'] = 'Bearer ' . $token;
        }
        $args = array(
            'method' => strtoupper($method),
            'headers' => $request_headers,
            'timeout' => 25,
            'redirection' => 0,
            'sslverify' => true,
            'reject_unsafe_urls' => true,
            'limit_response_size' => 10 * MB_IN_BYTES,
        );
        if ($body !== null) {
            $args['headers']['Content-Type'] = 'application/json';
            $args['body'] = wp_json_encode($body);
        }
        $response = wp_safe_remote_request($url, $args);
        return $this->normalize($response);
    }

    public function bootstrap($site_key)
    {
        return $this->request('POST', 'bootstrap', array(
            'site_url' => home_url('/'),
            'site_name' => get_bloginfo('name'),
            'site_key' => $site_key,
        ), '');
    }

    public function start_managed_connection($project_id, $network, $return_url, $module_id = 0)
    {
        $body = array(
            'project_id' => $project_id,
            'network' => $network,
            'return_url' => $return_url,
        );
        if ($module_id > 0) $body['connection_module_id'] = (int) $module_id;
        return $this->request('POST', 'managed-connections', $body);
    }

    /** Return all Projects visible to this authenticated SNAP connection. */
    public function get_projects()
    {
        $capabilities = $this->request('GET', 'capabilities', null, '');
        $path = $capabilities['ok'] ? $this->discover_endpoint(array($capabilities['data']), array('projects', 'projects_url', 'list_projects')) : '';
        if ($path !== '') {
            return $this->request('GET', $path);
        }
        $projects = $this->request('GET', 'projects');
        if ($projects['ok'] || $projects['status'] !== 404) return $projects;
        $state = NXS_PostSocial_State::all();
        if (!empty($state['project_id'])) {
            return array(
                'ok' => true,
                'status' => 200,
                'data' => array('data' => array(array(
                    'id' => sanitize_text_field($state['project_id']),
                    'name' => !empty($state['project_name']) ? sanitize_text_field($state['project_name']) : __('Current Project', 'social-networks-auto-poster-facebook-twitter-g'),
                    'is_current' => true,
                    'accounts_path' => 'social-accounts',
                )), 'scoped_connection' => true),
                'error' => null,
                'request_id' => null,
                'retry_after' => 0,
            );
        }
        return $this->feature_unavailable(__('Post.Social did not return a Project for this connection.', 'social-networks-auto-poster-facebook-twitter-g'));
    }

    public function get_project_accounts($project_id, $project = array())
    {
        $project_id = sanitize_text_field((string) $project_id);
        $state = NXS_PostSocial_State::all();
        $path = $this->discover_endpoint(array($project), array('accounts', 'accounts_url', 'social_accounts', 'social_accounts_url', 'list_accounts'), array('project_id' => $project_id));
        if ($path === '') {
            $capabilities = $this->request('GET', 'capabilities', null, '');
            if ($capabilities['ok']) {
                $path = $this->discover_endpoint(array($capabilities['data']), array('project_accounts', 'project_social_accounts', 'social_accounts_by_project'), array('project_id' => $project_id));
            }
        }
        if ($path !== '') {
            return $this->request('GET', $path);
        }
        $project_accounts = $this->request('GET', 'projects/' . rawurlencode($project_id) . '/social-accounts');
        if ($project_accounts['ok'] || $project_accounts['status'] !== 404) return $project_accounts;
        if (!empty($state['project_id']) && hash_equals((string) $state['project_id'], $project_id)) {
            return $this->request('GET', 'social-accounts');
        }
        return $this->feature_unavailable(__('The Post.Social API does not currently expose accounts for this Project to SNAP.', 'social-networks-auto-poster-facebook-twitter-g'));
    }

    public function get_supported_networks()
    {
        $capabilities = $this->request('GET', 'capabilities', null, '');
        if (!$capabilities['ok']) {
            return $capabilities;
        }
        foreach (array('social_account_networks', 'supported_networks', 'networks') as $field) {
            if (!empty($capabilities['data'][$field]) && is_array($capabilities['data'][$field])) {
                return array('ok' => true, 'status' => 200, 'data' => array('data' => array_values($capabilities['data'][$field])), 'error' => null, 'request_id' => $capabilities['request_id'], 'retry_after' => 0);
            }
        }
        $path = $this->discover_endpoint(array($capabilities['data']), array('supported_networks', 'social_account_networks', 'connectable_networks'));
        if ($path !== '') {
            return $this->request('GET', $path);
        }
        $networks = $this->request('GET', 'networks');
        if ($networks['ok']) {
            $items = !empty($networks['data']['networks']) && is_array($networks['data']['networks']) ? $networks['data']['networks'] : array();
            return array('ok' => true, 'status' => 200, 'data' => array('data' => array_values($items), 'authorization_available' => !empty($items)), 'error' => null, 'request_id' => $networks['request_id'], 'retry_after' => 0);
        }
        if ($networks['status'] !== 404) return $networks;
        return array('ok' => true, 'status' => 200, 'data' => array('data' => array(), 'authorization_available' => false), 'error' => null, 'request_id' => $capabilities['request_id'], 'retry_after' => 0);
    }

    /** Public capabilities for the one-click network connection bootstrap. */
    public function get_network_connection_capabilities()
    {
        return $this->request('GET', 'capabilities', null, '');
    }

    /**
     * Start the complete SNAP -> Post.Social -> provider OAuth flow.
     *
     * The endpoint is deliberately usable before a site connection exists.
     * When SNAP already has a valid bearer token, sending it lets Post.Social
     * keep the user in the current Project without another sign-in step.
     */
    public function start_network_connection($body)
    {
        $capabilities = $this->get_network_connection_capabilities();
        if (!$capabilities['ok']) return $capabilities;
        $path = $this->discover_endpoint(array($capabilities['data']), array('start_network_connection'));
        if ($path === '' && !empty($capabilities['data']['features']['snap_network_connection_bootstrap'])) {
            $path = 'network-connections';
        }
        if ($path === '') {
            return $this->feature_unavailable(__('Post.Social has not enabled one-click account connections for SNAP yet.', 'social-networks-auto-poster-facebook-twitter-g'));
        }
        $token = NXS_PostSocial_State::connected() ? null : '';
        $headers = !empty($body['idempotency_key']) ? array('Idempotency-Key' => sanitize_text_field($body['idempotency_key'])) : array();
        $result = $this->request('POST', $path, $body, $token, $headers);
        if (!$result['ok'] && in_array($result['status'], array(401, 403, 404), true) && $token === null) {
            // A locally stored token can expire between page loads. Falling
            // back to the guest bridge keeps Add New Network self-contained;
            // Post.Social will authenticate the user inside the same window.
            unset($body['project_id']);
            $body['idempotency_key'] = wp_generate_uuid4();
            $headers['Idempotency-Key'] = $body['idempotency_key'];
            return $this->request('POST', $path, $body, '', $headers);
        }
        return $result;
    }

    public function get_network_connection_status($pending)
    {
        $attempt_id = !empty($pending['attempt_id']) ? sanitize_text_field((string) $pending['attempt_id']) : '';
        if ($attempt_id === '') return $this->feature_unavailable(__('The account connection attempt is missing.', 'social-networks-auto-poster-facebook-twitter-g'));
        $path = !empty($pending['status_path']) ? ltrim((string) $pending['status_path'], '/') : 'network-connections/' . rawurlencode($attempt_id);
        $headers = !empty($pending['poll_token']) ? array('X-SNAP-Poll-Token' => $pending['poll_token']) : array();
        return $this->request('GET', $path, null, !empty($pending['flow']) && $pending['flow'] === 'managed_connection' ? null : '', $headers);
    }

    public function cancel_network_connection($pending)
    {
        $attempt_id = !empty($pending['attempt_id']) ? sanitize_text_field((string) $pending['attempt_id']) : '';
        if ($attempt_id === '') return array('ok' => true, 'status' => 200, 'data' => array('status' => 'cancelled'), 'error' => null, 'request_id' => null, 'retry_after' => 0);
        $path = !empty($pending['cancel_path']) ? ltrim((string) $pending['cancel_path'], '/') : 'network-connections/' . rawurlencode($attempt_id);
        $headers = !empty($pending['poll_token']) ? array('X-SNAP-Poll-Token' => $pending['poll_token']) : array();
        return $this->request('DELETE', $path, null, !empty($pending['flow']) && $pending['flow'] === 'managed_connection' ? null : '', $headers);
    }

    public function start_social_account_connection($project_id, $network, $context = array(), $existing_account_id = '')
    {
        $capabilities = $this->request('GET', 'capabilities', null, '');
        if (!$capabilities['ok']) {
            return $capabilities;
        }
        $path = $this->discover_endpoint(array($context, $capabilities['data']), array('start_connection', 'start_url', 'account_connection', 'social_account_connection', 'authorize_account'), array(
            'project_id' => sanitize_text_field((string) $project_id),
            'network' => sanitize_key($network),
        ));
        if ($path === '' && !empty($capabilities['data']['features']['managed_connections'])) {
            $path = 'managed-connections';
        }
        if ($path === '') {
            return $this->feature_unavailable(__('Post.Social has not enabled social-account authorization through SNAP yet.', 'social-networks-auto-poster-facebook-twitter-g'));
        }
        $body = array(
            'project_id' => sanitize_text_field((string) $project_id),
            'network' => sanitize_key($network),
            'site_uuid' => NXS_PostSocial_State::installation_uuid(),
            'intent' => !empty($context['intent']) ? sanitize_key($context['intent']) : 'add_account',
        );
        if ($existing_account_id !== '') {
            $body['social_account_id'] = sanitize_text_field($existing_account_id);
            $body['reauthorize'] = true;
        }
        return $this->request('POST', $path, $body);
    }

    public function get_social_account_connection_status($pending)
    {
        $path = !empty($pending['status_path']) ? ltrim((string) $pending['status_path'], '/') : '';
        if ($path === '' && !empty($pending['status_url'])) {
            $path = $this->relative_path($pending['status_url']);
        }
        if ($path === '') {
            return $this->feature_unavailable(__('Post.Social did not provide an authorization status endpoint.', 'social-networks-auto-poster-facebook-twitter-g'));
        }
        $headers = array();
        if (!empty($pending['poll_token'])) {
            $headers['X-SNAP-Poll-Token'] = $pending['poll_token'];
        }
        if (!empty($pending['secret'])) {
            $headers['X-SNAP-Connection-Secret'] = $pending['secret'];
        }
        return $this->request('GET', $path, null, null, $headers);
    }

    private function discover_endpoint($documents, $names, $replacements = array())
    {
        foreach ((array) $documents as $document) {
            $candidate = $this->find_named_endpoint($document, array_map('strtolower', $names));
            if ($candidate === '') {
                continue;
            }
            foreach ($replacements as $name => $value) {
                $candidate = str_replace(array('{' . $name . '}', ':' . $name), rawurlencode((string) $value), $candidate);
            }
            $relative = $this->relative_path($candidate);
            if ($relative !== '') {
                return $relative;
            }
            if (preg_match('#^[a-z0-9/_{}:.-]+$#i', $candidate) && strpos($candidate, '..') === false && strpos($candidate, '{') === false) {
                return ltrim($candidate, '/');
            }
        }
        return '';
    }

    private function find_named_endpoint($value, $names, $depth = 0)
    {
        if (!is_array($value) || $depth > 6) {
            return '';
        }
        foreach ($value as $key => $item) {
            $normalized = strtolower(str_replace(array('-', ' '), '_', (string) $key));
            if (in_array($normalized, $names, true)) {
                if (is_string($item)) {
                    return $item;
                }
                if (is_array($item)) {
                    foreach (array('href', 'url', 'path', 'endpoint', 'start_url') as $field) {
                        if (!empty($item[$field]) && is_string($item[$field])) {
                            return $item[$field];
                        }
                    }
                }
            }
        }
        foreach ($value as $item) {
            if (is_array($item)) {
                $found = $this->find_named_endpoint($item, $names, $depth + 1);
                if ($found !== '') {
                    return $found;
                }
            }
        }
        return '';
    }

    private function feature_unavailable($message)
    {
        return array('ok' => false, 'status' => 501, 'data' => array(), 'error' => array(
            'code' => 'postsocial_feature_unavailable', 'category' => 'capability', 'message' => $message, 'retryable' => false,
        ), 'request_id' => null, 'retry_after' => 0);
    }

    public function relative_path($url)
    {
        $url = (string) $url;
        $prefix = '/api/v1/snap/';
        if (strpos($url, $prefix) === 0) {
            return ltrim(substr($url, strlen($prefix)), '/');
        }
        $url = esc_url_raw($url);
        if ($url === '' || !nxs_validate_remote_url($url, true)) {
            return '';
        }
        $base = wp_parse_url($this->base_url);
        $candidate = wp_parse_url($url);
        if (!is_array($base) || !is_array($candidate)
            || empty($base['host']) || empty($candidate['host'])
            || strtolower($base['host']) !== strtolower($candidate['host'])
            || (isset($candidate['port']) ? (int) $candidate['port'] : 443) !== (isset($base['port']) ? (int) $base['port'] : 443)
            || (!empty($candidate['scheme']) && strtolower($candidate['scheme']) !== 'https')) {
            return '';
        }
        $path = isset($candidate['path']) ? (string) $candidate['path'] : '';
        if (strpos($path, $prefix) !== 0) {
            return '';
        }
        return ltrim(substr($path, strlen($prefix)), '/');
    }

    public function upload($path, $filename, $alt_text = '')
    {
        if (!function_exists('curl_init') || !is_readable($filename)) {
            return array('ok' => false, 'status' => 0, 'error' => array(
                'code' => 'local_media_unreadable',
                'message' => __('SNAP could not read this media file.', 'social-networks-auto-poster-facebook-twitter-g'),
                'retryable' => false,
            ));
        }
        $token = NXS_PostSocial_State::token();
        $url = $this->base_url . '/api/v1/snap/' . ltrim($path, '/');
        if (!nxs_validate_remote_url($url, true)) {
            return array('ok' => false, 'status' => 0, 'error' => array(
                'code' => 'invalid_api_url',
                'message' => __('The configured Post.Social API URL is not a safe HTTPS endpoint.', 'social-networks-auto-poster-facebook-twitter-g'),
                'retryable' => false,
            ));
        }
        $ch = curl_init($url);
        $fields = array(
            'media' => new CURLFile($filename),
            'alt_text' => (string) $alt_text,
        );
        curl_setopt_array($ch, array(
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $fields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_HTTPHEADER => array(
                'Accept: application/json',
                'Authorization: Bearer ' . $token,
                'User-Agent: ' . $this->user_agent(),
            ),
        ));
        if (defined('WP_PROXY_HOST') && WP_PROXY_HOST) {
            curl_setopt($ch, CURLOPT_PROXY, WP_PROXY_HOST);
            if (defined('WP_PROXY_PORT') && WP_PROXY_PORT) {
                curl_setopt($ch, CURLOPT_PROXYPORT, WP_PROXY_PORT);
            }
            if (defined('WP_PROXY_USERNAME') && WP_PROXY_USERNAME) {
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, WP_PROXY_USERNAME . ':' . (defined('WP_PROXY_PASSWORD') ? WP_PROXY_PASSWORD : ''));
            }
        }
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            return array('ok' => false, 'status' => 0, 'error' => array(
                'code' => 'api_transport_error', 'message' => $curl_error ?: __('Post.Social is temporarily unavailable.', 'social-networks-auto-poster-facebook-twitter-g'), 'retryable' => true,
            ));
        }
        return $this->normalize_payload($status, json_decode($raw, true), array());
    }

    private function normalize($response)
    {
        if (is_wp_error($response)) {
            return array('ok' => false, 'status' => 0, 'error' => array(
                'code' => 'api_transport_error',
                'category' => 'temporary',
                'message' => __('Post.Social could not be reached. The request will be retried.', 'social-networks-auto-poster-facebook-twitter-g'),
                'retryable' => true,
            ), 'request_id' => null);
        }
        $status = (int) wp_remote_retrieve_response_code($response);
        $headers = wp_remote_retrieve_headers($response);
        $data = json_decode((string) wp_remote_retrieve_body($response), true);
        return $this->normalize_payload($status, $data, $headers);
    }

    private function normalize_payload($status, $data, $headers)
    {
        $data = is_array($data) ? $data : array();
        $error = !empty($data['error']) && is_array($data['error']) ? $data['error'] : null;
        if (($status < 200 || $status >= 300) && $error === null) {
            $message = !empty($data['message']) && is_scalar($data['message']) ? sanitize_text_field($data['message']) : __('Post.Social rejected the request.', 'social-networks-auto-poster-facebook-twitter-g');
            $request_id = !empty($data['request_id']) && is_scalar($data['request_id']) ? sanitize_text_field($data['request_id']) : '';
            if ($request_id !== '') $message .= ' ' . sprintf(__('Request ID: %s', 'social-networks-auto-poster-facebook-twitter-g'), $request_id);
            $error = array(
                'code' => !empty($data['code']) ? sanitize_key($data['code']) : 'api_http_' . (int) $status,
                'category' => $status >= 500 ? 'temporary' : 'request',
                'message' => $message,
                'retryable' => $status === 429 || $status >= 500,
            );
        }
        $retry_after = 0;
        if (is_object($headers) && method_exists($headers, 'offsetExists') && $headers->offsetExists('retry-after')) {
            $retry_after = (int) $headers->offsetGet('retry-after');
        }
        return array(
            'ok' => $status >= 200 && $status < 300,
            'status' => (int) $status,
            'data' => $data,
            'error' => $error,
            'request_id' => isset($data['request_id']) ? $data['request_id'] : null,
            'retry_after' => $retry_after,
        );
    }

    private function user_agent()
    {
        $suffix = substr(hash('sha256', NXS_PostSocial_State::installation_uuid()), 0, 12);
        return 'SNAP/' . NextScripts_SNAP_Version . ' WordPress/' . get_bloginfo('version') . ' Site/' . $suffix;
    }

}

class NXS_PostSocial_Integration
{
    const NONCE = 'nxs_postsocial_action';
    const JOB_HOOK = 'nxs_postsocial_process_job';
    const POLL_HOOK = 'nxs_postsocial_poll_job';
    const DB_VERSION = '2';
    private static $publication_request_jobs = array();
    private static $publication_request_run_jobs = array();

    public static function trial_notice_text($message)
    {
        return str_ireplace(
            'Your trial has ended, please subscribe.',
            __('Publishing paused: Your Post.Social trial has ended. Your accounts are still connected! Choose a plan to resume automatic publishing. View Plans →', 'social-networks-auto-poster-facebook-twitter-g'),
            (string) $message
        );
    }

    public static function register()
    {
        add_action('admin_menu', array(__CLASS__, 'admin_menu'), 50);
        add_action('admin_post_nxs_postsocial_action', array(__CLASS__, 'admin_action'));
        add_action('wp_ajax_nxs_postsocial_start', array(__CLASS__, 'ajax_start'));
        add_action('wp_ajax_nxs_postsocial_poll', array(__CLASS__, 'ajax_poll'));
        add_action('wp_ajax_nxs_postsocial_cancel', array(__CLASS__, 'ajax_cancel'));
        add_action('add_meta_boxes', array(__CLASS__, 'add_meta_box'));
        add_action('save_post', array(__CLASS__, 'save_post_settings'), 20, 2);
        add_action('transition_post_status', array(__CLASS__, 'queue_publish'), 110, 3);
        add_action(self::JOB_HOOK, array(__CLASS__, 'process_job'));
        add_action(self::POLL_HOOK, array(__CLASS__, 'poll_job'));
        add_action('admin_init', array(__CLASS__, 'ensure_schema'));
        add_action('wp_initialize_site', array(__CLASS__, 'initialize_site'), 10, 1);
        add_filter('site_status_tests', array(__CLASS__, 'site_health_tests'));
    }

    public static function activate($network_wide = false)
    {
        if ($network_wide && is_multisite()) {
            foreach (get_sites(array('fields' => 'ids', 'number' => 0)) as $blog_id) {
                switch_to_blog((int) $blog_id);
                self::install_schema();
                restore_current_blog();
            }
            return;
        }
        self::install_schema();
    }

    public static function initialize_site($new_site)
    {
        if (!function_exists('is_plugin_active_for_network')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        if (!is_multisite() || !is_plugin_active_for_network(plugin_basename(NXS_PLPATH . 'NextScripts_SNAP.php'))) {
            return;
        }
        $blog_id = is_object($new_site) && isset($new_site->blog_id) ? (int) $new_site->blog_id : 0;
        if ($blog_id < 1) {
            return;
        }
        switch_to_blog($blog_id);
        self::install_schema();
        restore_current_blog();
    }

    public static function ensure_schema()
    {
        if (get_option('nxs_postsocial_db_version') !== self::DB_VERSION) {
            self::install_schema();
        }
    }

    private static function install_schema()
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table();
        $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_id varchar(64) NOT NULL,
            idempotency_key varchar(128) NOT NULL,
            post_id bigint(20) unsigned NOT NULL,
            action_id varchar(128) NOT NULL,
            payload_hash char(64) NOT NULL,
            payload_json longtext NOT NULL,
            media_ids longtext NULL,
            status varchar(32) NOT NULL,
            remote_type varchar(32) NULL,
            remote_id varchar(32) NULL,
            result_json longtext NULL,
            attempts smallint unsigned NOT NULL DEFAULT 0,
            next_poll_at datetime NULL,
            last_error_code varchar(128) NULL,
            last_error_message text NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY idempotency_key (idempotency_key),
            KEY status_next (status,next_poll_at),
            KEY post_id (post_id)
        ) {$charset};");
        NXS_PostSocial_State::installation_uuid();
        update_option('nxs_postsocial_db_version', self::DB_VERSION, false);
    }

    public static function admin_menu()
    {
        add_submenu_page(
            'nxssnap',
            __('Cloud(Post.Social)', 'social-networks-auto-poster-facebook-twitter-g'),
            __('Cloud(Post.Social)', 'social-networks-auto-poster-facebook-twitter-g'),
            'manage_options',
            'nxssnap-postsocial',
            array(__CLASS__, 'render_admin')
        );
    }

    public static function render_admin()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to manage Post.Social.', 'social-networks-auto-poster-facebook-twitter-g'));
        }
        self::refresh_bootstrap_account_type();
        if (NXS_PostSocial_State::connected()) {
            $billing_result = (new NXS_PostSocial_Api())->request('GET', 'billing');
            if (!empty($billing_result['ok'])) NXS_PostSocial_State::update(array('billing' => $billing_result['data']));
        }
        $state = NXS_PostSocial_State::all();
        $connected = NXS_PostSocial_State::connected();
        $requested_intent = isset($_GET['nxs_ps_intent']) ? self::sanitize_intent(wp_unslash($_GET['nxs_ps_intent'])) : '';
        $pending = NXS_PostSocial_State::pending();
        if (self::pending_expired($pending)) {
            NXS_PostSocial_State::clear_pending();
            $pending = array();
        }
        $accounts = !empty($state['accounts']) && is_array($state['accounts']) ? $state['accounts'] : array();
        $billing = !empty($state['billing']) && is_array($state['billing']) ? $state['billing'] : array();
        $error = !empty($state['last_error']) ? $state['last_error'] : array();
        $recovery_codes = array('snap_token_invalid', 'snap_token_expired', 'snap_token_revoked');
        $ended_codes = array('snap_site_disconnected', 'snap_site_reclaimed');
        $error_code = !empty($error['code']) ? $error['code'] : '';
        $connection_ended = in_array($error_code, $ended_codes, true);
        if ($connection_ended || in_array($error_code, $recovery_codes, true)) {
            $connected = false;
        }
        $needs_recovery = !empty($state['connection_id'])
            && !$connection_ended
            && (!$connected || in_array($error_code, $recovery_codes, true));
        $site_host = wp_parse_url(home_url('/'), PHP_URL_HOST);
        $manage_url = self::safe_web_url(!empty($state['manage_url']) ? $state['manage_url'] : (!empty($state['project_url']) ? $state['project_url'] : NXS_POSTSOCIAL_WEB_URL . '/dashboard'));
        if ($manage_url === '') {
            $manage_url = NXS_POSTSOCIAL_WEB_URL . '/dashboard';
        }
        $pending_url = self::safe_web_url(!empty($pending['connect_url']) ? $pending['connect_url'] : (!empty($pending['browser_url']) ? $pending['browser_url'] : ''));
        $trial_text = '';
        $trial_status = '';
        $trial_days = null;
        if (!empty($billing['trial']) && is_array($billing['trial'])) {
            $trial_status = !empty($billing['trial']['status']) ? sanitize_text_field($billing['trial']['status']) : '';
            $trial_days = isset($billing['trial']['days_remaining']) ? (int) $billing['trial']['days_remaining'] : null;
        }
        if (!empty($billing['restrictions']['reason_code']) && $billing['restrictions']['reason_code'] === 'billing_hold') $trial_text = __('Billing on hold — publishing unavailable', 'social-networks-auto-poster-facebook-twitter-g');
        elseif (!empty($billing['subscription']['active'])) $trial_text = __('Subscription active', 'social-networks-auto-poster-facebook-twitter-g');
        elseif ($trial_status === 'not_started') $trial_text = __('Free trial available', 'social-networks-auto-poster-facebook-twitter-g');
        elseif ($trial_status === 'active') $trial_text = sprintf(_n('Trial active — %d day remaining', 'Trial active — %d days remaining', (int) $trial_days, 'social-networks-auto-poster-facebook-twitter-g'), (int) $trial_days);
        elseif ($trial_status === 'expired') $trial_text = __('Trial expired', 'social-networks-auto-poster-facebook-twitter-g');
        $plans_url = self::safe_web_url(!empty($billing['manage_url']) ? $billing['manage_url'] : 'https://post.social/pricing');
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Post.Social', 'social-networks-auto-poster-facebook-twitter-g'); ?></h1>
            <div id="poststuff"><div id="post-body" class="metabox-holder columns-2"><div id="post-body-content">
            <?php if (!$connected) NXS_PostSocial_Premium::render_offer(); ?>
            <?php if (!empty($error['message'])): ?>
                <div class="notice notice-error"><p><strong><?php echo esc_html(isset($error['code']) ? $error['code'] : 'error'); ?>:</strong> <?php echo esc_html($error['message']); ?></p></div>
            <?php endif; ?>
            <div id="nxs-postsocial-connect-card" class="card" style="max-width:720px;padding:24px;margin-top:16px">
            <?php if (!empty($_GET['nxs_ps_dashboard_error'])): ?><div class="notice notice-error inline"><p><?php esc_html_e('Could not open the Post.Social dashboard. Please try again.', 'social-networks-auto-poster-facebook-twitter-g'); ?></p></div><?php endif; ?>
            <?php if (!$connected): ?>
                <h2 style="margin-top:0"><?php esc_html_e('Post.Social', 'social-networks-auto-poster-facebook-twitter-g'); ?></h2>
                <p><?php esc_html_e('Connect Post.Social to use networks and features that require it.', 'social-networks-auto-poster-facebook-twitter-g'); ?></p>
                <p class="description"><?php esc_html_e('Your first Managed network will set up Post.Social automatically. Use Connect Post.Social to link an existing account.', 'social-networks-auto-poster-facebook-twitter-g'); ?></p>
                <div id="nxs-postsocial-idle"<?php echo !empty($pending) ? ' hidden' : ''; ?>>
                    <button type="button" id="nxs-postsocial-connect" class="button button-primary button-hero"><?php echo esc_html($needs_recovery ? __('Reconnect Post.Social', 'social-networks-auto-poster-facebook-twitter-g') : __('Connect Post.Social', 'social-networks-auto-poster-facebook-twitter-g')); ?></button>
                </div>
                <div id="nxs-postsocial-connecting"<?php echo empty($pending) ? ' hidden' : ''; ?>>
                    <h3><?php esc_html_e('Connecting Post.Social', 'social-networks-auto-poster-facebook-twitter-g'); ?></h3>
                    <p id="nxs-postsocial-status" aria-live="polite"><?php echo esc_html(self::status_message(!empty($pending['status']) ? $pending['status'] : 'pending')); ?></p>
                    <p>
                        <a id="nxs-postsocial-open" class="button" target="_blank" rel="noopener noreferrer" href="<?php echo esc_url($pending_url); ?>"<?php echo $pending_url === '' ? ' hidden' : ''; ?>><?php esc_html_e('Open Post.Social', 'social-networks-auto-poster-facebook-twitter-g'); ?></a>
                        <button type="button" id="nxs-postsocial-cancel" class="button-link-delete"><?php esc_html_e('Cancel', 'social-networks-auto-poster-facebook-twitter-g'); ?></button>
                    </p>
                </div>
                <div id="nxs-postsocial-result" hidden></div>
            <?php else: ?>
                <h2 style="margin-top:0"><?php esc_html_e('Post.Social', 'social-networks-auto-poster-facebook-twitter-g'); ?></h2>
                <p style="color:#008a20;font-size:16px"><strong>✓ <?php esc_html_e('Connected', 'social-networks-auto-poster-facebook-twitter-g'); ?></strong></p>
                <p><?php echo esc_html($site_host ? $site_host : home_url('/')); ?></p>
                <p><strong><?php esc_html_e('Account:', 'social-networks-auto-poster-facebook-twitter-g'); ?></strong> <?php echo esc_html(!empty($state['account_type']) && $state['account_type'] === 'temporary' ? __('Temporary', 'social-networks-auto-poster-facebook-twitter-g') : __('Regular', 'social-networks-auto-poster-facebook-twitter-g')); ?><?php if (!empty($state['account_type']) && $state['account_type'] === 'temporary'): ?> — <?php esc_html_e('Click Open Post.Social Dashboard button to finish setting up your account.', 'social-networks-auto-poster-facebook-twitter-g'); ?><?php endif; ?></p>
                <?php if (!empty($state['project_name'])): ?><p><strong><?php esc_html_e('Project:', 'social-networks-auto-poster-facebook-twitter-g'); ?></strong> <?php echo esc_html($state['project_name']); ?></p><?php endif; ?>
                <?php if ($trial_text !== ''): ?><p><strong><?php esc_html_e('Plan status:', 'social-networks-auto-poster-facebook-twitter-g'); ?></strong> <?php echo esc_html($trial_text); ?></p><?php endif; ?>
                <?php if ($trial_status === 'active' && $trial_days !== null && $trial_days <= 3 && empty($billing['subscription']['active'])): ?><p><?php esc_html_e('Your Post.Social trial is ending soon.', 'social-networks-auto-poster-facebook-twitter-g'); ?> <a href="<?php echo esc_url($plans_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('View Plans', 'social-networks-auto-poster-facebook-twitter-g'); ?></a></p><?php endif; ?>
                <?php if ($trial_status === 'expired' && empty($billing['subscription']['active'])): ?><p><?php esc_html_e('Managed publishing requires an active subscription. Your connected networks and settings are preserved.', 'social-networks-auto-poster-facebook-twitter-g'); ?> <a class="button" href="<?php echo esc_url($plans_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('View Plans', 'social-networks-auto-poster-facebook-twitter-g'); ?></a></p><?php endif; ?>
                <div class="nxs-postsocial-actions" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-top:16px">
                    <?php if (!empty($state['account_type']) && $state['account_type'] === 'temporary'): ?><?php self::action_button('dashboard', __('Open Post.Social Dashboard', 'social-networks-auto-poster-facebook-twitter-g'), 'button button-primary', false, true); ?>
                    <?php else: ?><a class="button button-primary" href="<?php echo esc_url(self::safe_web_url(NXS_POSTSOCIAL_WEB_URL . '/login')); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Sign In to Post.Social', 'social-networks-auto-poster-facebook-twitter-g'); ?></a><?php endif; ?>
                    <?php self::action_button('disconnect', __('Disconnect', 'social-networks-auto-poster-facebook-twitter-g'), 'button', true); ?>
                </div>
            <?php endif; ?>
            </div>
            <?php if ($connected && class_exists('NXS_PostSocial_Accounts')) NXS_PostSocial_Accounts::render_admin_panel(); ?>
            </div>
            <div id="postbox-container-1" class="postbox-container">
                <?php
                $sidebar = new nxs_adminMgmt();
                $sidebar->page = 'toplevel_page_nxssnap';
                add_meta_box('nxs_postsocial_info', __('SNAP Info', 'social-networks-auto-poster-facebook-twitter-g'), array($sidebar, 'metabox_side_info'), $sidebar->page, 'side', 'high');
                add_meta_box('nxs_postsocial_support', __('Support', 'social-networks-auto-poster-facebook-twitter-g'), array($sidebar, 'metabox_side_supbox'), $sidebar->page, 'side', 'low');
                do_meta_boxes($sidebar->page, 'side', null);
                ?>
            </div><br class="clear"></div></div>
        </div>
        <?php if (!$connected): ?>
        <script>
        (function(){
            var nonce=<?php echo wp_json_encode(wp_create_nonce('nxs_postsocial_connect')); ?>;
            var hasPending=<?php echo !empty($pending) ? 'true' : 'false'; ?>;
            var timer=null,started=Date.now(),stopped=false,popup=null;
            var connect=document.getElementById('nxs-postsocial-connect');
            var cancel=document.getElementById('nxs-postsocial-cancel');
            var idle=document.getElementById('nxs-postsocial-idle');
            var connecting=document.getElementById('nxs-postsocial-connecting');
            var status=document.getElementById('nxs-postsocial-status');
            var openLink=document.getElementById('nxs-postsocial-open');
            var result=document.getElementById('nxs-postsocial-result');
            function request(action,extra){
                var data=new URLSearchParams({action:action,_ajax_nonce:nonce});
                Object.keys(extra||{}).forEach(function(key){data.append(key,extra[key]);});
                return fetch(ajaxurl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:data.toString()}).then(function(r){return r.json();});
            }
            function setConnecting(message){idle.hidden=true;connecting.hidden=false;result.hidden=true;if(message){status.textContent=message;}}
            function showRetry(message){
                stopped=true;if(timer){clearTimeout(timer);} connecting.hidden=true;result.hidden=false;
                result.innerHTML='';var p=document.createElement('p');p.textContent=message;result.appendChild(p);
                var b=document.createElement('button');b.type='button';b.className='button button-primary';b.textContent=<?php echo wp_json_encode(__('Try Again', 'social-networks-auto-poster-facebook-twitter-g')); ?>;
                b.addEventListener('click',function(){result.hidden=true;idle.hidden=false;stopped=false;started=Date.now();});result.appendChild(b);
            }
            function schedule(){if(!stopped){timer=setTimeout(poll,2500);}}
            function poll(){
                if(stopped){return;} if(Date.now()-started>600000){showRetry(<?php echo wp_json_encode(__('Connection timed out. You can try again.', 'social-networks-auto-poster-facebook-twitter-g')); ?>);return;}
                request('nxs_postsocial_poll').then(function(r){
                    if(r.success&&r.data){
                        if(r.data.connected){status.textContent=r.data.message||<?php echo wp_json_encode(__('Post.Social connected.', 'social-networks-auto-poster-facebook-twitter-g')); ?>;setTimeout(function(){var intent=r.data.intent||<?php echo wp_json_encode($requested_intent); ?>;if(intent.indexOf('add_account:')===0){window.location.href=<?php echo wp_json_encode(admin_url('admin.php?page=nxssnap&nxs_ps_resume=')); ?>+encodeURIComponent(intent.substring(12));}else{window.location.reload();}},500);return;}
                        status.textContent=r.data.message||status.textContent;if(r.data.terminal){showRetry(r.data.message||<?php echo wp_json_encode(__('Connection could not be completed.', 'social-networks-auto-poster-facebook-twitter-g')); ?>);return;}schedule();return;
                    }
                    var data=r.data||{};if(data.terminal){showRetry(data.message||<?php echo wp_json_encode(__('Connection could not be completed.', 'social-networks-auto-poster-facebook-twitter-g')); ?>);return;}schedule();
                }).catch(function(){status.textContent=<?php echo wp_json_encode(__('Post.Social is temporarily unreachable. Retrying…', 'social-networks-auto-poster-facebook-twitter-g')); ?>;schedule();});
            }
            if(connect){connect.addEventListener('click',function(){
                popup=window.open('about:blank','_blank');if(popup){try{popup.document.title='Post.Social';popup.document.body.textContent=<?php echo wp_json_encode(__('Opening Post.Social…', 'social-networks-auto-poster-facebook-twitter-g')); ?>;}catch(e){}}
                setConnecting(<?php echo wp_json_encode(__('Starting secure connection…', 'social-networks-auto-poster-facebook-twitter-g')); ?>);
                request('nxs_postsocial_start',{intent:<?php echo wp_json_encode($requested_intent); ?>,operation:<?php echo wp_json_encode($needs_recovery ? 'recover' : 'connect'); ?>}).then(function(r){
                    if(!r.success||!r.data){if(popup){popup.close();}showRetry((r.data&&r.data.message)||<?php echo wp_json_encode(__('Could not start the Post.Social connection.', 'social-networks-auto-poster-facebook-twitter-g')); ?>);return;}
                    status.textContent=r.data.message;if(r.data.connect_url){openLink.href=r.data.connect_url;openLink.hidden=false;if(popup){popup.location.href=r.data.connect_url;}}
                    started=Date.now();schedule();
                }).catch(function(){if(popup){popup.close();}showRetry(<?php echo wp_json_encode(__('Post.Social is temporarily unreachable.', 'social-networks-auto-poster-facebook-twitter-g')); ?>);});
            });}
            if(cancel){cancel.addEventListener('click',function(){stopped=true;if(timer){clearTimeout(timer);}request('nxs_postsocial_cancel').finally(function(){window.location.reload();});});}
            window.addEventListener('pagehide',function(){stopped=true;if(timer){clearTimeout(timer);}});
            if(hasPending){setConnecting(status.textContent);schedule();}
        }());
        </script>
        <?php endif;
    }

    private static function action_button($action, $label, $class = 'button', $confirm = false, $new_tab = false)
    {
        ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:0"<?php echo $new_tab ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
            <input type="hidden" name="action" value="nxs_postsocial_action">
            <input type="hidden" name="postsocial_action" value="<?php echo esc_attr($action); ?>">
            <?php wp_nonce_field(self::NONCE); ?>
            <button type="submit" class="<?php echo esc_attr($class); ?>"<?php echo $confirm ? ' onclick="return confirm(\'' . esc_js(__('Disconnect this site? The remote Project and history will be preserved.', 'social-networks-auto-poster-facebook-twitter-g')) . '\')"' : ''; ?>><?php echo esc_html($label); ?></button>
        </form><?php
    }

    public static function admin_action()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Not allowed.', 'social-networks-auto-poster-facebook-twitter-g'));
        }
        check_admin_referer(self::NONCE);
        $action = isset($_POST['postsocial_action']) ? sanitize_key(wp_unslash($_POST['postsocial_action'])) : '';
        if ($action === 'dashboard') {
            self::open_dashboard();
        } elseif ($action === 'connect' || $action === 'recover') {
            self::start_connection('', $action);
        } elseif ($action === 'refresh') {
            self::refresh_remote_state();
        } elseif ($action === 'rotate') {
            self::rotate();
        } elseif ($action === 'disconnect') {
            self::disconnect();
        }
        wp_safe_redirect(admin_url('admin.php?page=nxssnap-postsocial'));
        exit;
    }

    public static function ensure_bootstrap_connection()
    {
        if (NXS_PostSocial_State::connected()) return array('ok' => true, 'data' => NXS_PostSocial_State::all());
        $existing = NXS_PostSocial_State::all();
        if (!empty($existing['connection_id']) || NXS_PostSocial_State::token() !== '') {
            return self::local_error('login_required', __('This website already has a Post.Social connection. Sign in to reconnect it.', 'social-networks-auto-poster-facebook-twitter-g'), true, 409);
        }
        $key = NXS_PostSocial_State::site_key();
        if ($key === '') return self::local_error('secure_storage_unavailable', __('SNAP could not securely store the Post.Social Site Key.', 'social-networks-auto-poster-facebook-twitter-g'));
        $result = (new NXS_PostSocial_Api())->bootstrap($key);
        if (empty($result['ok'])) return $result;
        $data = $result['data'];
        if (empty($data['project_id']) || empty($data['site_uuid']) || !wp_is_uuid($data['site_uuid'])) {
            return self::local_error('invalid_bootstrap_response', __('Post.Social returned an incomplete Project connection.', 'social-networks-auto-poster-facebook-twitter-g'));
        }
        NXS_PostSocial_State::set_token($key);
        if (!hash_equals($key, NXS_PostSocial_State::token())) return self::local_error('secure_storage_unavailable', __('SNAP could not securely store the Post.Social connection.', 'social-networks-auto-poster-facebook-twitter-g'));
        NXS_PostSocial_State::update(array(
            'connection_id' => 'bootstrap:' . sanitize_text_field($data['site_uuid']),
            'bootstrap_site_uuid' => sanitize_text_field($data['site_uuid']),
            'project_id' => sanitize_text_field($data['project_id']),
            'project_name' => sanitize_text_field(get_bloginfo('name')),
            'account_type' => !empty($data['account_type']) && $data['account_type'] === 'regular' ? 'regular' : 'temporary',
            'account_type_checked_at' => time(),
            'credential_type' => 'site_key',
            'last_error' => array(),
        ));
        self::refresh_remote_state();
        return array('ok' => true, 'data' => NXS_PostSocial_State::all());
    }

    private static function refresh_bootstrap_account_type()
    {
        $state = NXS_PostSocial_State::all();
        if (empty($state['credential_type']) || $state['credential_type'] !== 'site_key' || !NXS_PostSocial_State::connected()) return;
        if (!empty($state['account_type_checked_at']) && time() - (int) $state['account_type_checked_at'] < 300) return;
        $key = NXS_PostSocial_State::site_key();
        if ($key === '' || !hash_equals($key, NXS_PostSocial_State::token())) return;
        $result = (new NXS_PostSocial_Api())->bootstrap($key);
        if (!empty($result['ok']) && !empty($result['data']['account_type'])) {
            NXS_PostSocial_State::update(array('account_type' => $result['data']['account_type'] === 'temporary' ? 'temporary' : 'regular', 'account_type_checked_at' => time()));
        }
    }

    private static function open_dashboard()
    {
        if (!NXS_PostSocial_State::connected()) wp_die(esc_html__('Post.Social is not connected.', 'social-networks-auto-poster-facebook-twitter-g'));
        $state = NXS_PostSocial_State::all();
        if (empty($state['account_type']) || $state['account_type'] !== 'temporary') {
            wp_redirect(self::safe_web_url(NXS_POSTSOCIAL_WEB_URL . '/login'));
            exit;
        }
        $result = (new NXS_PostSocial_Api())->request('POST', 'temporary-login', array('project_id' => $state['project_id']));
        if (empty($result['ok']) && !empty($result['error']['code']) && $result['error']['code'] === 'login_required') {
            NXS_PostSocial_State::update(array('account_type' => 'regular'));
            wp_redirect(self::safe_web_url(NXS_POSTSOCIAL_WEB_URL . '/login'));
            exit;
        }
        $url = !empty($result['data']['login_url']) ? self::safe_web_url($result['data']['login_url']) : '';
        if (!empty($result['ok']) && $url !== '' && wp_parse_url($url, PHP_URL_PATH) === '/snap/temporary-login') {
            nocache_headers();
            wp_redirect($url, 302, 'SNAP');
            exit;
        }
        wp_safe_redirect(admin_url('admin.php?page=nxssnap-postsocial&nxs_ps_dashboard_error=1'));
        exit;
    }

    public static function ajax_start()
    {
        check_ajax_referer('nxs_postsocial_connect');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('terminal' => true, 'code' => 'not_authorized', 'message' => __('You are not allowed to manage Post.Social.', 'social-networks-auto-poster-facebook-twitter-g')), 403);
        }
        $intent = isset($_POST['intent']) ? self::sanitize_intent(wp_unslash($_POST['intent'])) : '';
        $operation = isset($_POST['operation']) && sanitize_key(wp_unslash($_POST['operation'])) === 'recover' ? 'recover' : 'connect';
        $result = self::start_connection($intent, $operation);
        if (empty($result['ok'])) {
            wp_send_json_error(self::public_error($result, false), !empty($result['status']) ? (int) $result['status'] : 500);
        }
        wp_send_json_success(self::public_connection_result($result));
    }

    public static function ajax_cancel()
    {
        check_ajax_referer('nxs_postsocial_connect');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('terminal' => true, 'code' => 'not_authorized', 'message' => __('You are not allowed to manage Post.Social.', 'social-networks-auto-poster-facebook-twitter-g')), 403);
        }
        NXS_PostSocial_State::clear_pending();
        wp_send_json_success(array('cancelled' => true, 'terminal' => true, 'status' => 'cancelled', 'message' => self::status_message('cancelled')));
    }

    public static function start_connection($intent = '', $operation = 'connect')
    {
        $intent = self::sanitize_intent($intent);
        $existing = NXS_PostSocial_State::pending();
        if (!empty($existing) && !self::pending_expired($existing) && self::safe_web_url(self::pending_connect_url($existing)) !== '') {
            return array(
                'ok' => true,
                'reused' => true,
                'status' => !empty($existing['status']) ? sanitize_key($existing['status']) : 'pending',
                'connect_url' => self::safe_web_url(self::pending_connect_url($existing)),
                'expires_at' => !empty($existing['expires_at']) ? sanitize_text_field($existing['expires_at']) : '',
                'intent' => !empty($existing['intent']) ? self::sanitize_intent($existing['intent']) : '',
            );
        }
        if (!empty($existing)) {
            NXS_PostSocial_State::clear_pending();
        }

        $pkce = self::pkce_pair();
        if (empty($pkce['verifier']) || empty($pkce['challenge'])) {
            return self::local_error('pkce_generation_failed', __('SNAP could not create a secure connection request.', 'social-networks-auto-poster-facebook-twitter-g'));
        }
        $legacy_secret = self::random_secret();
        if ($legacy_secret === '') {
            return self::local_error('secret_generation_failed', __('SNAP could not create a secure connection request.', 'social-networks-auto-poster-facebook-twitter-g'));
        }
        $site_uuid = NXS_PostSocial_State::installation_uuid();
        $api = new NXS_PostSocial_Api();
        $result = $api->request('POST', 'sites/claims', array(
            'site_uuid' => $site_uuid,
            'installation_uuid' => $site_uuid,
            'home_url' => home_url('/'),
            'site_url' => site_url('/'),
            'site_name' => get_bloginfo('name'),
            'site_title' => get_bloginfo('name'),
            'wordpress_version' => get_bloginfo('version'),
            'snap_version' => NextScripts_SNAP_Version,
            'code_challenge' => $pkce['challenge'],
            'code_challenge_method' => 'S256',
            'intent' => $intent !== '' ? $intent : null,
            // Required by the currently deployed claims contract. It remains server-side.
            'proof_secret' => $legacy_secret,
            'operation' => $operation === 'recover' ? 'recover' : 'connect',
        ), '');
        if (!$result['ok']) {
            self::remember_error($result);
            return $result;
        }
        $data = $result['data'];
        $request_id = self::first_value($data, array('connect_request_id', 'claim_id', 'connection_request_id'));
        $connect_url = self::first_value($data, array('connect_url', 'browser_url'));
        if ($request_id === '' || self::safe_web_url($connect_url) === '') {
            $invalid = self::local_error('invalid_connection_response', __('Post.Social returned an invalid connection response.', 'social-networks-auto-poster-facebook-twitter-g'));
            self::remember_error($invalid);
            return $invalid;
        }
        $status_path = $api->relative_path(self::first_value($data, array('status_url')));
        $exchange_path = $api->relative_path(self::first_value($data, array('exchange_url', 'token_exchange_url')));
        $pending = array(
            'request_id' => sanitize_text_field($request_id),
            'claim_id' => !empty($data['claim_id']) ? sanitize_text_field($data['claim_id']) : '',
            'challenge' => !empty($data['challenge']) ? sanitize_text_field($data['challenge']) : '',
            'code_verifier' => $pkce['verifier'],
            'poll_token' => self::first_value($data, array('polling_token', 'poll_token')),
            'legacy_secret' => $legacy_secret,
            'expires_at' => sanitize_text_field(self::first_value($data, array('expires_at', 'expires'))),
            'connect_url' => self::safe_web_url($connect_url),
            'status_path' => $status_path,
            'exchange_path' => $exchange_path,
            'status' => !empty($data['status']) ? sanitize_key($data['status']) : 'pending',
            'intent' => $intent,
            'operation' => $operation === 'recover' ? 'recover' : 'connect',
            'created_at' => gmdate('c'),
        );
        NXS_PostSocial_State::set_pending($pending);
        $saved_pending = NXS_PostSocial_State::pending();
        if (empty($saved_pending['code_verifier']) || !hash_equals($pkce['verifier'], $saved_pending['code_verifier'])
            || empty($saved_pending['legacy_secret']) || !hash_equals($legacy_secret, $saved_pending['legacy_secret'])
            || ($pending['poll_token'] !== '' && (empty($saved_pending['poll_token']) || !hash_equals($pending['poll_token'], $saved_pending['poll_token'])))) {
            NXS_PostSocial_State::clear_pending();
            $storage_error = self::local_error('secure_storage_unavailable', __('SNAP could not securely store the temporary connection secrets.', 'social-networks-auto-poster-facebook-twitter-g'), true, 500);
            self::remember_error($storage_error);
            return $storage_error;
        }

        // The live v1 API requires a claim proof. New polling-token responses omit challenge.
        if ($pending['challenge'] !== '') {
            $signature = hash_hmac('sha256', $request_id . '|' . $pending['challenge'] . '|' . $site_uuid, $legacy_secret);
            $proof = $api->request('POST', 'sites/claims/' . rawurlencode($request_id) . '/proof', array(
                'challenge' => $pending['challenge'],
                'proof_secret' => $legacy_secret,
                'signature' => $signature,
                'code_challenge' => $pkce['challenge'],
                'code_challenge_method' => 'S256',
            ), '');
            if (!$proof['ok']) {
                NXS_PostSocial_State::clear_pending();
                self::remember_error($proof);
                return $proof;
            }
        }
        NXS_PostSocial_State::update(array('last_error' => array()));
        return array_merge(array('ok' => true, 'reused' => false), $pending);
    }

    public static function ajax_poll()
    {
        check_ajax_referer('nxs_postsocial_connect');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('terminal' => true, 'code' => 'not_authorized', 'message' => __('You are not allowed to manage Post.Social.', 'social-networks-auto-poster-facebook-twitter-g')), 403);
        }
        $result = self::poll_connection();
        if (empty($result['ok'])) {
            wp_send_json_error(self::public_error($result, !empty($result['terminal'])), !empty($result['status']) && (int) $result['status'] >= 400 ? (int) $result['status'] : 400);
        }
        wp_send_json_success(self::public_connection_result($result));
    }

    public static function poll_connection()
    {
        if (NXS_PostSocial_State::connected() && empty(NXS_PostSocial_State::pending())) {
            return array('ok' => true, 'connected' => true, 'terminal' => true, 'status' => 'completed', 'message' => self::status_message('completed'));
        }
        $pending = NXS_PostSocial_State::pending();
        $request_id = !empty($pending['request_id']) ? $pending['request_id'] : (!empty($pending['claim_id']) ? $pending['claim_id'] : '');
        if ($request_id === '') {
            return self::local_error('claim_missing', __('No pending Post.Social connection was found.', 'social-networks-auto-poster-facebook-twitter-g'), true, 409);
        }
        if (self::pending_expired($pending)) {
            NXS_PostSocial_State::clear_pending();
            return self::local_error('connection_expired', self::status_message('expired'), true, 410, 'expired');
        }
        $api = new NXS_PostSocial_Api();
        $headers = self::pending_headers($pending);
        $poll_authorization = !empty($pending['poll_token']) ? $pending['poll_token'] : '';
        $status_path = !empty($pending['status_path']) ? $pending['status_path'] : 'sites/claims/' . rawurlencode($request_id) . '/status';
        $status = $api->request('GET', $status_path, null, $poll_authorization, $headers);
        if (!$status['ok']) {
            self::remember_error($status);
            $terminal = empty($status['error']['retryable']);
            if ($terminal) {
                NXS_PostSocial_State::clear_pending();
            }
            $status['terminal'] = $terminal;
            return $status;
        }
        $status_name = self::normalize_connection_status($status['data']);
        if (in_array($status_name, array('expired', 'cancelled', 'failed'), true)) {
            NXS_PostSocial_State::clear_pending();
            return self::local_error('connection_' . $status_name, self::status_message($status_name), true, 409, $status_name);
        }
        $authorization_code = self::first_value($status['data'], array('authorization_code', 'code'));
        $legacy_ready = !empty($status['data']['browser_confirmed']) && !empty($status['data']['proof_verified']);
        if ($status_name !== 'completed' && $authorization_code === '' && !$legacy_ready) {
            NXS_PostSocial_State::set_pending(array_merge($pending, array('status' => $status_name)));
            return array('ok' => true, 'connected' => false, 'terminal' => false, 'status' => $status_name, 'message' => self::status_message($status_name), 'intent' => !empty($pending['intent']) ? $pending['intent'] : '');
        }

        if ($authorization_code !== '') {
            return self::exchange_authorization_code($api, $pending, $authorization_code, $status['data'], $headers);
        }

        // Backward-compatible completion for the live sites/claims API.
        $complete = $api->request('POST', 'sites/claims/' . rawurlencode($request_id) . '/complete', array(
            'code_verifier' => !empty($pending['code_verifier']) ? $pending['code_verifier'] : '',
            'site_uuid' => NXS_PostSocial_State::installation_uuid(),
            'intent' => !empty($pending['intent']) ? $pending['intent'] : null,
        ), $poll_authorization, $headers);
        if (!$complete['ok']) {
            self::remember_error($complete);
            $terminal = empty($complete['error']['retryable']);
            if ($terminal) {
                NXS_PostSocial_State::clear_pending();
            }
            $complete['terminal'] = $terminal;
            return $complete;
        }
        $authorization_code = self::first_value($complete['data'], array('authorization_code', 'code'));
        if ($authorization_code !== '') {
            return self::exchange_authorization_code($api, $pending, $authorization_code, $complete['data'], $headers);
        }
        return self::store_completed_connection($complete['data'], $pending);
    }

    /** Complete a one-click network connection without exposing PKCE or tokens to JavaScript. */
    public static function complete_network_connection($pending, $response_data)
    {
        if (!is_array($pending) || !is_array($response_data)) {
            return self::local_error('invalid_connection_response', __('Post.Social returned an invalid account connection response.', 'social-networks-auto-poster-facebook-twitter-g'), true, 502);
        }
        $authorization_code = self::first_value($response_data, array('authorization_code', 'code'));
        if ($authorization_code !== '') {
            if (empty($pending['exchange_path'])) $pending['exchange_path'] = 'token';
            return self::exchange_authorization_code(new NXS_PostSocial_Api(), $pending, $authorization_code, $response_data, self::pending_headers($pending));
        }

        // Some authenticated attempts need no new SNAP token and return the
        // newly connected account directly. A first-time attempt must always
        // provide either a one-time authorization code or credentials.
        if (NXS_PostSocial_State::connected()) {
            self::refresh_remote_state();
            return array('ok' => true, 'connected' => true, 'terminal' => true, 'status' => 'completed', 'message' => self::status_message('completed'));
        }
        return self::store_completed_connection($response_data, $pending);
    }

    private static function exchange_authorization_code($api, $pending, $authorization_code, $response_data, $headers)
    {
        $exchange_path = '';
        foreach (array('exchange_url', 'token_exchange_url') as $field) {
            if (!empty($response_data[$field])) {
                $exchange_path = $api->relative_path($response_data[$field]);
                if ($exchange_path !== '') {
                    break;
                }
            }
        }
        if ($exchange_path === '' && !empty($response_data['exchange_path']) && preg_match('#^[a-z0-9/_-]+$#i', $response_data['exchange_path'])) {
            $exchange_path = ltrim($response_data['exchange_path'], '/');
        }
        if ($exchange_path === '' && !empty($pending['exchange_path'])) {
            $exchange_path = $pending['exchange_path'];
        }
        if ($exchange_path === '') {
            $error = self::local_error('exchange_endpoint_missing', __('Post.Social completed sign-in but did not provide a secure token-exchange endpoint.', 'social-networks-auto-poster-facebook-twitter-g'), true, 502);
            self::remember_error($error);
            return $error;
        }
        if (empty($pending['code_verifier'])) {
            $error = self::local_error('pkce_verifier_missing', __('The secure connection verifier is missing. Please try again.', 'social-networks-auto-poster-facebook-twitter-g'), true, 409);
            self::remember_error($error);
            NXS_PostSocial_State::clear_pending();
            return $error;
        }
        $network_flow = !empty($pending['flow']) && $pending['flow'] === 'network_connection';
        $exchange_body = array(
            'code' => (string) $authorization_code,
            'code_verifier' => $pending['code_verifier'],
            'site_uuid' => NXS_PostSocial_State::installation_uuid(),
        );
        if (!$network_flow) $exchange_body['authorization_code'] = (string) $authorization_code;
        $exchange = $api->request(
            'POST',
            $exchange_path,
            $exchange_body,
            $network_flow ? '' : (!empty($pending['poll_token']) ? $pending['poll_token'] : ''),
            $network_flow ? array() : $headers
        );
        if (!$exchange['ok']) {
            self::remember_error($exchange);
            $exchange['terminal'] = empty($exchange['error']['retryable']);
            if (!empty($exchange['terminal'])) {
                NXS_PostSocial_State::clear_pending();
            }
            return $exchange;
        }
        return self::store_completed_connection($exchange['data'], $pending);
    }

    private static function store_completed_connection($data, $pending)
    {
        $credentials = !empty($data['credentials']) && is_array($data['credentials']) ? $data['credentials'] : array();
        $token_data = !empty($data['token']) && is_array($data['token']) ? $data['token'] : array();
        $connection = !empty($data['connection']) && is_array($data['connection']) ? $data['connection'] : array();
        $project = !empty($data['project']) && is_array($data['project']) ? $data['project'] : array();
        $access_token = self::first_value($credentials, array('access_token', 'token', 'value'));
        if ($access_token === '') {
            $access_token = self::first_value($token_data, array('value', 'access_token', 'token'));
        }
        if ($access_token === '') {
            $access_token = self::first_value($data, array('access_token'));
        }
        $connection_id = self::first_value($connection, array('id', 'connection_id', 'site_connection_id'));
        if ($connection_id === '') {
            $connection_id = self::first_value($data, array('connection_id', 'site_connection_id'));
        }
        if ($access_token === '' || $connection_id === '') {
            $error = self::local_error('invalid_token_response', __('Post.Social returned an invalid credential response. No credentials were saved.', 'social-networks-auto-poster-facebook-twitter-g'), true, 502);
            self::remember_error($error);
            NXS_PostSocial_State::clear_pending();
            return $error;
        }
        $refresh_token = self::first_value($credentials, array('refresh_token'));
        if ($refresh_token === '') {
            $refresh_token = self::first_value($data, array('refresh_token'));
        }
        $project_id = self::first_value($project, array('id', 'project_id'));
        if ($project_id === '') {
            $project_id = self::first_value($connection, array('project_id'));
        }
        $project_name = self::first_value($project, array('name', 'project_name'));
        if ($project_name === '') {
            $project_name = self::first_value($connection, array('project_name'));
        }
        $manage_url = self::safe_web_url(self::first_value($data, array('manage_url', 'project_url')));
        if ($manage_url === '') {
            $manage_url = self::safe_web_url(self::first_value($project, array('manage_url', 'url')));
        }
        NXS_PostSocial_State::set_token($access_token);
        NXS_PostSocial_State::set_refresh_token($refresh_token);
        if (NXS_PostSocial_State::token() === '' || !hash_equals($access_token, NXS_PostSocial_State::token())
            || ($refresh_token !== '' && (NXS_PostSocial_State::refresh_token() === '' || !hash_equals($refresh_token, NXS_PostSocial_State::refresh_token())))) {
            NXS_PostSocial_State::clear_connection();
            $error = self::local_error('secure_storage_unavailable', __('SNAP could not securely store the Post.Social credentials.', 'social-networks-auto-poster-facebook-twitter-g'), true, 500);
            self::remember_error($error);
            return $error;
        }
        $network_flow = !empty($pending['flow']) && $pending['flow'] === 'network_connection';
        NXS_PostSocial_State::update(array(
            'pending' => array(),
            'token_id' => sanitize_text_field(self::first_value($token_data, array('id', 'token_id'))),
            'token_expires_at' => sanitize_text_field(self::first_value($credentials, array('expires_at')) ?: self::first_value($token_data, array('expires_at'))),
            'connection_id' => sanitize_text_field($connection_id),
            'project_id' => sanitize_text_field($project_id),
            'project_name' => sanitize_text_field($project_name),
            'manage_url' => $manage_url,
            'account_hint' => sanitize_text_field(self::first_value($data, array('account_hint', 'masked_email'))),
            'accounts' => array(),
            'supported_networks' => array(),
            'supported_networks_refreshed_at' => '',
            'billing' => array(),
            'initial_accounts_imported_for' => $network_flow ? sanitize_text_field($connection_id) : '',
            'last_error' => array(),
        ));
        if (self::refresh_remote_state() && !$network_flow && class_exists('NXS_PostSocial_Accounts')) NXS_PostSocial_Accounts::import_initial_connection_accounts();
        return array(
            'ok' => true,
            'connected' => true,
            'terminal' => true,
            'status' => 'completed',
            'message' => self::status_message('completed'),
            'intent' => !empty($pending['intent']) ? self::sanitize_intent($pending['intent']) : '',
        );
    }

    public static function refresh_remote_state()
    {
        $api = new NXS_PostSocial_Api();
        $capabilities = $api->request('GET', 'capabilities', null, '');
        $accounts = $api->request('GET', 'social-accounts');
        $supported_networks = $api->get_supported_networks();
        $billing = $api->request('GET', 'billing');
        if (!$accounts['ok']) {
            self::remember_error($accounts);
            return false;
        }
        $compatibility = 'Compatible';
        if ($capabilities['ok'] && !empty($capabilities['data']['plugin']['minimum_version']) && version_compare(NextScripts_SNAP_Version, $capabilities['data']['plugin']['minimum_version'], '<')) {
            $compatibility = 'Plugin update required';
        }
        NXS_PostSocial_State::update(array(
            'accounts' => isset($accounts['data']['data']) ? $accounts['data']['data'] : array(),
            'capabilities' => $capabilities['ok'] ? $capabilities['data'] : array(),
            'supported_networks' => $supported_networks['ok'] && !empty($supported_networks['data']['data']) ? array_values($supported_networks['data']['data']) : array(),
            'supported_networks_refreshed_at' => $supported_networks['ok'] ? gmdate('c') : '',
            'billing' => $billing['ok'] ? $billing['data'] : array(),
            'compatibility' => $compatibility,
            'last_contact_at' => gmdate('c'),
            'last_error' => array(),
        ));
        if (class_exists('NXS_PostSocial_Accounts')) {
            $state = NXS_PostSocial_State::all();
            NXS_PostSocial_Accounts::sync_cached_accounts(
                isset($accounts['data']['data']) ? $accounts['data']['data'] : array(),
                !empty($state['project_id']) ? $state['project_id'] : '',
                !empty($state['project_name']) ? $state['project_name'] : ''
            );
        }
        return true;
    }

    private static function rotate()
    {
        $api = new NXS_PostSocial_Api();
        $state = NXS_PostSocial_State::all();
        $idempotency_key = !empty($state['pending_rotation_key'])
            ? $state['pending_rotation_key']
            : wp_generate_uuid4();
        NXS_PostSocial_State::update(array('pending_rotation_key' => $idempotency_key));
        $result = $api->request('POST', 'tokens/rotate', array(
            'idempotency_key' => $idempotency_key,
            'name' => 'SNAP Site',
        ));
        if (!$result['ok']) {
            self::remember_error($result);
            if (empty($result['error']['retryable']) && (!isset($result['error']['action']) || $result['error']['action'] !== 'recover_access')) {
                NXS_PostSocial_State::update(array('pending_rotation_key' => ''));
            }
            return;
        }
        NXS_PostSocial_State::set_token($result['data']['token']['value']);
        NXS_PostSocial_State::update(array(
            'token_id' => $result['data']['token']['id'],
            'token_expires_at' => $result['data']['token']['expires_at'],
            'pending_rotation_key' => '',
            'last_error' => array(),
        ));
    }

    private static function disconnect()
    {
        self::disconnect_connection();
    }

    public static function disconnect_connection()
    {
        $api = new NXS_PostSocial_Api();
        $result = $api->request('DELETE', 'connection');
        if (!$result['ok']) {
            self::remember_error($result);
        } else {
            NXS_PostSocial_State::update(array('last_error' => array()));
        }
        NXS_PostSocial_State::clear_connection();
        return $result;
    }

    public static function add_meta_box()
    {
        if (NXS_PostSocial_State::connected()) {
            add_meta_box('nxs-postsocial', __('Post.Social', 'social-networks-auto-poster-facebook-twitter-g'), array(__CLASS__, 'render_meta_box'), null, 'side', 'default');
        }
    }

    public static function render_meta_box($post)
    {
        $state = NXS_PostSocial_State::all();
        $accounts = !empty($state['accounts']) && is_array($state['accounts']) ? $state['accounts'] : array();
        $imported_ids = class_exists('NXS_PostSocial_Accounts') ? NXS_PostSocial_Accounts::imported_account_ids() : array();
        $selected = (array) get_post_meta($post->ID, '_nxs_postsocial_accounts', true);
        wp_nonce_field('nxs_postsocial_post', 'nxs_postsocial_post_nonce');
        foreach ($accounts as $account) {
            $id = isset($account['id']) ? $account['id'] : '';
            if (in_array($id, $imported_ids, true)) continue;
            $ready = !empty($account['availability']['can_publish']);
            ?><label style="display:block;margin:6px 0"><input type="checkbox" name="nxs_postsocial_accounts[]" value="<?php echo esc_attr($id); ?>" <?php checked(in_array($id, $selected, true)); ?> <?php disabled(!$ready); ?>> <?php echo esc_html(isset($account['display_name']) ? $account['display_name'] : $id); ?> <small>(<?php echo esc_html($account['platform']); ?><?php echo $ready ? '' : ', ' . esc_html__('Needs attention', 'social-networks-auto-poster-facebook-twitter-g'); ?>)</small></label><?php
        }
        if (!empty($imported_ids)) { ?><p class="description"><?php esc_html_e('Imported destinations are selected in the normal SNAP account list and are hidden here to prevent duplicate publishing.', 'social-networks-auto-poster-facebook-twitter-g'); ?></p><?php }
        ?><p><label><?php esc_html_e('Delivery', 'social-networks-auto-poster-facebook-twitter-g'); ?><br><select name="nxs_postsocial_mode"><option value="now"><?php esc_html_e('Publish now', 'social-networks-auto-poster-facebook-twitter-g'); ?></option><option value="scheduled"><?php esc_html_e('Schedule in Post.Social', 'social-networks-auto-poster-facebook-twitter-g'); ?></option></select></label></p>
        <p><label><?php esc_html_e('Scheduled UTC time (ISO 8601)', 'social-networks-auto-poster-facebook-twitter-g'); ?><br><input type="text" style="width:100%" name="nxs_postsocial_scheduled_at" value="<?php echo esc_attr(get_post_meta($post->ID, '_nxs_postsocial_scheduled_at', true)); ?>" placeholder="2026-07-24T18:00:00Z"></label></p><?php
        global $wpdb;
        $jobs = $wpdb->get_results($wpdb->prepare(
            'SELECT status,remote_id,last_error_code,last_error_message,result_json,updated_at FROM ' . self::table() . ' WHERE post_id=%d ORDER BY id DESC LIMIT 5',
            $post->ID
        ), ARRAY_A);
        if ($jobs) {
            ?><hr><strong><?php esc_html_e('Recent Post.Social deliveries', 'social-networks-auto-poster-facebook-twitter-g'); ?></strong><ul><?php
            foreach ($jobs as $job) {
                ?><li><code><?php echo esc_html($job['status']); ?></code><?php
                if (!empty($job['remote_id'])) {
                    echo ' <small>' . esc_html($job['remote_id']) . '</small>';
                }
                if (!empty($job['last_error_message'])) {
                    echo '<br><span style="color:#b32d2e">' . esc_html($job['last_error_message']) . '</span>';
                }
                $result = !empty($job['result_json']) ? json_decode($job['result_json'], true) : array();
                if (!empty($result['results']) && is_array($result['results'])) {
                    echo '<ul>';
                    foreach ($result['results'] as $destination) {
                        $label = !empty($destination['platform']) ? $destination['platform'] : (!empty($destination['account_id']) ? $destination['account_id'] : __('Destination', 'social-networks-auto-poster-facebook-twitter-g'));
                        echo '<li>' . esc_html($label . ': ' . (isset($destination['status']) ? $destination['status'] : 'unknown'));
                        if (!empty($destination['remote_url'])) {
                            echo ' <a target="_blank" rel="noopener noreferrer" href="' . esc_url($destination['remote_url']) . '">' . esc_html__('View', 'social-networks-auto-poster-facebook-twitter-g') . '</a>';
                        }
                        echo '</li>';
                    }
                    echo '</ul>';
                }
                ?></li><?php
            }
            ?></ul><?php
        }
    }

    public static function save_post_settings($post_id, $post)
    {
        if (!isset($_POST['nxs_postsocial_post_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nxs_postsocial_post_nonce'])), 'nxs_postsocial_post')) {
            return;
        }
        if (!current_user_can('edit_post', $post_id) || wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
            return;
        }
        $accounts = isset($_POST['nxs_postsocial_accounts']) ? array_map('sanitize_text_field', (array) wp_unslash($_POST['nxs_postsocial_accounts'])) : array();
        update_post_meta($post_id, '_nxs_postsocial_accounts', array_values(array_unique($accounts)));
        update_post_meta($post_id, '_nxs_postsocial_mode', isset($_POST['nxs_postsocial_mode']) && $_POST['nxs_postsocial_mode'] === 'scheduled' ? 'scheduled' : 'now');
        update_post_meta($post_id, '_nxs_postsocial_scheduled_at', isset($_POST['nxs_postsocial_scheduled_at']) ? sanitize_text_field(wp_unslash($_POST['nxs_postsocial_scheduled_at'])) : '');
    }

    public static function queue_publish($new_status, $old_status, $post)
    {
        if ($new_status !== 'publish' || $old_status === 'publish' || !$post || wp_is_post_revision($post->ID) || wp_is_post_autosave($post->ID) || !NXS_PostSocial_State::connected()) {
            return;
        }
        $accounts = array_values(array_filter((array) get_post_meta($post->ID, '_nxs_postsocial_accounts', true)));
        if (class_exists('NXS_PostSocial_Accounts')) $accounts = array_values(array_diff($accounts, NXS_PostSocial_Accounts::imported_account_ids()));
        if (empty($accounts)) {
            return;
        }
        self::enqueue_publication($post, $accounts, 'postsocial-default', get_post_meta($post->ID, '_nxs_postsocial_mode', true) === 'scheduled' ? 'scheduled' : 'now', (string) get_post_meta($post->ID, '_nxs_postsocial_scheduled_at', true));
    }

    /**
     * Route a normal SNAP account through Post.Social instead of the native
     * network implementation. The durable job stores only Post.Social account
     * IDs, never provider OAuth credentials.
     */
    public static function publish_snap_account($network_code, $index, $post_id, $account)
    {
        if (!NXS_PostSocial_State::connected()) {
            return __('Post.Social is disconnected. Reconnect Post.Social before publishing.', 'social-networks-auto-poster-facebook-twitter-g');
        }
        if (empty($account['postsocial_account_id'])) {
            return __('This Post.Social-backed SNAP account is missing its remote account ID.', 'social-networks-auto-poster-facebook-twitter-g');
        }
        if (empty($account['postsocial_can_publish'])) {
            return !empty($account['postsocial_status_message'])
                ? $account['postsocial_status_message'] . ' ' . __('Reconnect through Post.Social.', 'social-networks-auto-poster-facebook-twitter-g')
                : __('This account is not available for publishing. Reconnect through Post.Social.', 'social-networks-auto-poster-facebook-twitter-g');
        }
        $action_id = substr('snap-' . sanitize_key($network_code) . '-' . absint($index) . '-' . sanitize_key($account['postsocial_account_id']), 0, 128);
        if ((int) $post_id === 0) {
            $test_image_url = '';
            if (self::is_instagram_account($network_code, $account)) {
                $test_image_url = !empty($account['defImg'])
                    ? esc_url_raw($account['defImg'])
                    : 'https://go2ln.com/img/nxs/df/dfImg' . rand(1, 15) . '.jpg';
                if ($test_image_url === '') return self::missing_instagram_image_error($account, true);
            }
            $job_id = self::enqueue_test_publication($account, $action_id, $network_code, $test_image_url);
            if (!$job_id) return __('This Post.Social test publication was already queued.', 'social-networks-auto-poster-facebook-twitter-g');
            $job = self::run_job_now($job_id, true);
            return self::job_result_message($job, $network_code, $account, true);
        }
        $post = get_post((int) $post_id);
        if (!$post || $post->post_status !== 'publish') {
            return __('The WordPress post is no longer publishable.', 'social-networks-auto-poster-facebook-twitter-g');
        }
        if (self::is_instagram_account($network_code, $account) && !(int) get_post_thumbnail_id($post->ID)) {
            return self::missing_instagram_image_error($account, false);
        }
        $job_id = self::enqueue_publication($post, array($account['postsocial_account_id']), $action_id, 'now', '', $account);
        if (!$job_id) return __('Post.Social could not create the publication job.', 'social-networks-auto-poster-facebook-twitter-g');
        if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
            if (isset(self::$publication_request_run_jobs[$job_id])) {
                return self::job_result_message(self::get_job($job_id), $network_code, $account, false);
            }
            self::$publication_request_run_jobs[$job_id] = true;
            $job = self::run_job_now($job_id, false);
            return self::job_result_message($job, $network_code, $account, false);
        }
        return sprintf(__('Publication queued for %1$s through Post.Social (job #%2$d).', 'social-networks-auto-poster-facebook-twitter-g'), sanitize_text_field($network_code), $job_id);
    }

    /** Publish the content entered in Quick Post through the same durable cloud job queue. */
    public static function publish_quick_post_account($network_code, $index, $post_id, $account, $message)
    {
        $failure = array('isPosted' => 0);
        if (!NXS_PostSocial_State::connected()) return $failure + array('Error' => __('Post.Social is disconnected.', 'social-networks-auto-poster-facebook-twitter-g'));
        if (empty($account['postsocial_account_id'])) return $failure + array('Error' => __('This Cloud Connection has no Post.Social account ID.', 'social-networks-auto-poster-facebook-twitter-g'));
        if (empty($account['postsocial_can_publish'])) return $failure + array('Error' => !empty($account['postsocial_status_message']) ? $account['postsocial_status_message'] : __('This Cloud Connection cannot publish. Reconnect it through Post.Social.', 'social-networks-auto-poster-facebook-twitter-g'));

        $post = $post_id ? get_post($post_id) : null;
        $image_url = !empty($message['imageURL']) ? esc_url_raw($message['imageURL']) : '';
        if (self::is_instagram_account($network_code, $account) && $image_url === '') {
            return $failure + array('Error' => __('Instagram requires an image. Add an image URL to Quick Post before publishing.', 'social-networks-auto-poster-facebook-twitter-g'));
        }
        $link_url = !empty($message['url']) ? esc_url_raw($message['url']) : null;
        $snapshot = array(
            'accounts' => array((string) $account['postsocial_account_id']),
            'mode' => 'now', 'scheduled_at' => '', 'attachment_id' => 0,
            'remote_media_url' => $image_url,
            'adjustments' => array('add_title_to_text' => false, 'add_tags_as_hashtags' => false, 'link_preview' => !empty($link_url)),
            'source' => array('type' => $post ? 'wordpress_post' : 'manual', 'wordpress_post_id' => $post ? (int) $post->ID : null,
                'wordpress_post_type' => $post ? $post->post_type : null, 'wordpress_status' => $post ? $post->post_status : null,
                'wordpress_permalink' => $post ? get_permalink($post) : null,
                'wordpress_modified_gmt' => $post ? mysql2date('c', $post->post_modified_gmt, false) : gmdate('c')),
            'content' => array('text' => !empty($message['pText']) ? wp_strip_all_tags($message['pText']) : '',
                'title' => in_array(strtoupper($network_code), array('LI', 'PN'), true) && !empty($message['pTitle']) ? wp_strip_all_tags($message['pTitle']) : null,
                'link_url' => $link_url, 'tags' => array(), 'location' => null),
        );
        if ($snapshot['content']['text'] === '') return $failure + array('Error' => __('Quick Post text is empty.', 'social-networks-auto-poster-facebook-twitter-g'));
        $action_id = substr('snap-quick-' . sanitize_key($network_code) . '-' . absint($index) . '-' . sanitize_key($account['postsocial_account_id']), 0, 128);
        $job_id = self::enqueue_snapshot((int) $post_id, $action_id, $snapshot);
        if (!$job_id) return $failure + array('Error' => __('Post.Social could not create the Quick Post job.', 'social-networks-auto-poster-facebook-twitter-g'));
        if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON && !isset(self::$publication_request_run_jobs[$job_id])) {
            self::$publication_request_run_jobs[$job_id] = true;
            self::run_job_now($job_id, false);
        }
        $job = self::get_job($job_id);
        if ($job && in_array($job['status'], array('failed', 'cancelled', 'expired'), true)) return $failure + array('Error' => !empty($job['last_error_message']) ? $job['last_error_message'] : self::job_result_error($job));
        return array('isPosted' => 1, 'postURL' => $job ? self::job_result_remote_url($job) : '',
            'statusMessage' => $job && $job['status'] === 'published' ? __('Published through Post.Social.', 'social-networks-auto-poster-facebook-twitter-g') : __('Queued for delivery through Post.Social.', 'social-networks-auto-poster-facebook-twitter-g'));
    }

    private static function is_instagram_account($network_code, $account)
    {
        return strtoupper((string) $network_code) === 'IG'
            || (!empty($account['postsocial_platform']) && sanitize_key($account['postsocial_platform']) === 'instagram');
    }

    private static function missing_instagram_image_error($account, $is_test)
    {
        $message = $is_test
            ? __('Instagram requires an image. No usable test image is available, so nothing was sent.', 'social-networks-auto-poster-facebook-twitter-g')
            : __('Instagram requires an image. Add a featured image to the WordPress post before publishing.', 'social-networks-auto-poster-facebook-twitter-g');
        if (function_exists('nxs_LogIt')) {
            nxs_LogIt('E', $is_test ? 'Post.Social Test' : 'Post.Social', !empty($account['nName']) ? $account['nName'] : 'Instagram', 'IG', $message);
        }
        return $message;
    }

    private static function enqueue_publication($post, $accounts, $action_id, $mode = 'now', $scheduled = '', $account_options = array())
    {
        $accounts = array_values(array_unique(array_filter(array_map('sanitize_text_field', (array) $accounts))));
        if (!$post || empty($accounts)) {
            return false;
        }
        sort($accounts);
        $action_id = substr(sanitize_text_field($action_id), 0, 128);
        $mode = $mode === 'scheduled' ? 'scheduled' : 'now';
        $use_account_format = !empty($account_options) && is_array($account_options);
        $message = wp_strip_all_tags($post->post_content);
        $title = get_the_title($post);
        $tags = wp_get_post_tags($post->ID, array('fields' => 'names'));
        $attachment_id = (int) get_post_thumbnail_id($post->ID);
        $link_url = get_permalink($post);
        $adjustments = array('add_title_to_text' => true, 'add_tags_as_hashtags' => true, 'link_preview' => true);
        if ($use_account_format) {
            $format = !empty($account_options['msgFormat']) ? (string) $account_options['msgFormat'] : 'New post (%TITLE%) has been published on %SITENAME% - %URL%';
            $message = self::format_account_value($format, $post, $account_options);
            $title = null;
            $tags = array();
            $attachment_id = !empty($account_options['attchImg']) ? (int) get_post_thumbnail_id($post->ID) : 0;
            $adjustments = array('add_title_to_text' => false, 'add_tags_as_hashtags' => false, 'link_preview' => true);
            $platform = !empty($account_options['postsocial_platform']) ? sanitize_key($account_options['postsocial_platform']) : '';
            if ($platform === 'facebook' || $platform === 'linkedin' || $platform === 'linkedin_company') {
                $post_type = !empty($account_options['postType']) ? strtoupper(sanitize_key($account_options['postType'])) : 'A';
                if (!in_array($post_type, array('T', 'I', 'A'), true)) $post_type = 'A';
                $attachment_id = $post_type === 'I' ? (int) get_post_thumbnail_id($post->ID) : 0;
                $link_url = $post_type === 'A' ? get_permalink($post) : null;
                $adjustments['link_preview'] = $post_type === 'A';
                if (($platform === 'linkedin' || $platform === 'linkedin_company') && $post_type === 'A') {
                    $title_format = !empty($account_options['msgATFormat']) ? $account_options['msgATFormat'] : '%TITLE%';
                    $title = self::format_account_value($title_format, $post, $account_options);
                }
            } elseif ($platform === 'instagram') {
                $attachment_id = (int) get_post_thumbnail_id($post->ID);
                $link_url = null;
                $adjustments['link_preview'] = false;
            } elseif ($platform === 'threads') {
                $attachment_id = !empty($account_options['attchImg']) ? (int) get_post_thumbnail_id($post->ID) : 0;
                $adjustments['link_preview'] = true;
            } elseif ($platform === 'pinterest') {
                $attachment_id = (int) get_post_thumbnail_id($post->ID);
                $title_format = !empty($account_options['msgTFormat']) ? $account_options['msgTFormat'] : '%TITLE%';
                $title = self::format_account_value($title_format, $post, $account_options);
                $link_url = !empty($account_options['cImgURL']) && $account_options['cImgURL'] === 'N' ? null : get_permalink($post);
                $adjustments['link_preview'] = false;
            }
        }
        $payload_snapshot = array(
            'accounts' => $accounts,
            'mode' => $mode,
            'scheduled_at' => $scheduled,
            'attachment_id' => $attachment_id,
            'adjustments' => $adjustments,
            'source' => array(
                'type' => 'wordpress_post',
                'wordpress_post_id' => (int) $post->ID,
                'wordpress_post_type' => $post->post_type,
                'wordpress_status' => $post->post_status,
                'wordpress_permalink' => get_permalink($post),
                'wordpress_modified_gmt' => mysql2date('c', $post->post_modified_gmt, false),
            ),
            'content' => array(
                'text' => $message,
                'title' => $title,
                'link_url' => $link_url,
                'tags' => is_array($tags) ? array_values($tags) : array(),
                'location' => null,
            ),
        );
        return self::enqueue_snapshot((int) $post->ID, $action_id, $payload_snapshot);
    }

    private static function format_account_value($format, $post, $account_options)
    {
        $add_params = function_exists('nxs_makeURLParams') ? nxs_makeURLParams(array('NTNAME' => '', 'NTCODE' => '', 'POSTID' => $post->ID, 'ACCNAME' => !empty($account_options['nName']) ? $account_options['nName'] : '')) : '';
        $value = function_exists('nsFormatMessage') ? nsFormatMessage((string) $format, $post->ID, $add_params, '', $account_options) : str_replace(array('%TITLE%', '%SITENAME%', '%URL%'), array(get_the_title($post), get_bloginfo('name'), get_permalink($post)), (string) $format);
        return wp_strip_all_tags($value);
    }

    private static function enqueue_test_publication($account, $action_id, $network_code, $image_url = '')
    {
        $site_name = htmlspecialchars_decode(get_bloginfo('name'), ENT_QUOTES);
        if ($site_name === '') $site_name = home_url();
        self::cancel_unsent_test_jobs((string) $account['postsocial_account_id']);
        $action_id = substr($action_id . '-test-' . sanitize_key(wp_generate_uuid4()), 0, 128);
        $test_id = strtoupper(substr(hash('sha256', $action_id), 0, 10));
        $snapshot = array(
            'accounts' => array((string) $account['postsocial_account_id']),
            'mode' => 'now', 'scheduled_at' => '', 'attachment_id' => 0, 'remote_media_url' => $image_url,
            'adjustments' => array('add_title_to_text' => false, 'add_tags_as_hashtags' => false, 'link_preview' => true),
            'source' => array('type' => 'manual', 'wordpress_post_id' => null, 'wordpress_post_type' => null, 'wordpress_status' => null, 'wordpress_permalink' => null, 'wordpress_modified_gmt' => gmdate('c'), 'snap_network_code' => sanitize_text_field($network_code), 'snap_account_name' => !empty($account['nName']) ? sanitize_text_field($account['nName']) : sanitize_text_field($network_code)),
            'content' => array('text' => 'Test Post from ' . $site_name . "\n" . home_url() . "\nTest ID: " . $test_id, 'title' => null, 'link_url' => home_url(), 'tags' => array(), 'location' => null),
        );
        return self::enqueue_snapshot(0, $action_id, $snapshot);
    }

    private static function cancel_unsent_test_jobs($account_id)
    {
        global $wpdb;
        $jobs = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE post_id=0 AND status IN (%s,%s) AND (remote_id IS NULL OR remote_id=%s)',
            'pending', 'retrying', ''
        ), ARRAY_A);
        foreach ((array) $jobs as $job) {
            $snapshot = !empty($job['payload_json']) ? json_decode($job['payload_json'], true) : array();
            if (empty($snapshot['accounts']) || !in_array((string) $account_id, array_map('strval', (array) $snapshot['accounts']), true)) continue;
            $wpdb->update(self::table(), array(
                'status' => 'cancelled',
                'next_poll_at' => null,
                'last_error_code' => 'superseded_test',
                'last_error_message' => __('Replaced by a newer Test Post request.', 'social-networks-auto-poster-facebook-twitter-g'),
                'updated_at' => current_time('mysql', true),
            ), array('id' => (int) $job['id']));
            if (function_exists('wp_clear_scheduled_hook')) wp_clear_scheduled_hook(self::JOB_HOOK, array((int) $job['id']));
        }
    }

    private static function enqueue_snapshot($post_id, $action_id, $payload_snapshot)
    {
        $payload_json = wp_json_encode($payload_snapshot);
        $payload_hash = hash('sha256', $payload_json);
        $modified = !empty($payload_snapshot['source']['wordpress_modified_gmt']) ? $payload_snapshot['source']['wordpress_modified_gmt'] : '';
        $mode = !empty($payload_snapshot['mode']) ? $payload_snapshot['mode'] : 'now';
        $request_key = hash('sha256', implode('|', array(NXS_PostSocial_State::installation_uuid(), $action_id, $post_id, $modified, $payload_hash, $mode)));
        if (isset(self::$publication_request_jobs[$request_key])) return self::$publication_request_jobs[$request_key];
        $event_id = wp_generate_uuid4();
        $key = hash('sha256', $request_key . '|' . $event_id);
        global $wpdb;
        $inserted = $wpdb->query($wpdb->prepare(
            'INSERT IGNORE INTO ' . self::table() . ' (event_id,idempotency_key,post_id,action_id,payload_hash,payload_json,status,created_at,updated_at) VALUES (%s,%s,%d,%s,%s,%s,%s,%s,%s)',
            $event_id, $key, $post_id, $action_id, $payload_hash, $payload_json, 'pending', current_time('mysql', true), current_time('mysql', true)
        ));
        if ($inserted) {
            $job_id = (int) $wpdb->insert_id;
            self::$publication_request_jobs[$request_key] = $job_id;
            wp_schedule_single_event(time() + 1, self::JOB_HOOK, array($job_id));
        }
        return !empty($inserted) ? $job_id : 0;
    }

    private static function run_job_now($job_id, $wait_for_terminal)
    {
        $job_id = (int) $job_id;
        if (function_exists('wp_clear_scheduled_hook')) wp_clear_scheduled_hook(self::JOB_HOOK, array($job_id));
        self::process_job($job_id);
        $job = self::get_job($job_id);
        $terminal = array('published', 'partially_published', 'failed', 'cancelled', 'expired', 'retrying');
        $deadline = microtime(true) + ($wait_for_terminal ? 20 : 0);
        while ($wait_for_terminal && $job && !empty($job['remote_id']) && !in_array($job['status'], $terminal, true) && microtime(true) < $deadline) {
            usleep(2000000);
            if (function_exists('wp_clear_scheduled_hook')) wp_clear_scheduled_hook(self::POLL_HOOK, array($job_id));
            self::poll_job($job_id, false);
            $job = self::get_job($job_id);
        }
        return $job;
    }

    private static function get_job($job_id)
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id=%d', (int) $job_id), ARRAY_A);
    }

    private static function job_result_message($job, $network_code, $account, $is_test)
    {
        $network = sanitize_text_field((string) $network_code);
        $account_name = !empty($account['nName']) ? sanitize_text_field($account['nName']) : $network;
        $kind = $is_test ? __('test post', 'social-networks-auto-poster-facebook-twitter-g') : __('post', 'social-networks-auto-poster-facebook-twitter-g');
        if (!$job) return sprintf(__('Post.Social could not create the %s delivery job.', 'social-networks-auto-poster-facebook-twitter-g'), $kind);
        $status = sanitize_key($job['status']);
        $job_id = (int) $job['id'];
        $error = !empty($job['last_error_message']) ? sanitize_text_field($job['last_error_message']) : self::job_result_error($job);
        if ($status === 'published') {
            $message = sprintf(__('SUCCESS: Post.Social confirmed that the %1$s was published to %2$s (job #%3$d).', 'social-networks-auto-poster-facebook-twitter-g'), $kind, $account_name, $job_id);
            $remote_url = self::job_result_remote_url($job);
            if ($remote_url !== '') $message .= ' ' . sprintf(__('Published post: %s', 'social-networks-auto-poster-facebook-twitter-g'), $remote_url);
        } elseif ($status === 'partially_published') {
            $message = sprintf(__('Post.Social only partially published the %1$s to %2$s (job #%3$d). %4$s', 'social-networks-auto-poster-facebook-twitter-g'), $kind, $account_name, $job_id, $error);
        } elseif ($status === 'retrying') {
            $message = sprintf(__('Post.Social could not accept the %1$s for %2$s yet and queued an automatic retry (job #%3$d). %4$s', 'social-networks-auto-poster-facebook-twitter-g'), $kind, $account_name, $job_id, $error);
        } elseif (in_array($status, array('failed', 'cancelled', 'expired'), true)) {
            $message = sprintf(__('Post.Social failed to publish the %1$s to %2$s (job #%3$d). %4$s', 'social-networks-auto-poster-facebook-twitter-g'), $kind, $account_name, $job_id, $error);
        } else {
            $message = sprintf(__('Post.Social is still delivering the %1$s to %2$s. Current provider status: %3$s (job #%4$d). This is not a publication confirmation; SNAP will continue checking in the background.', 'social-networks-auto-poster-facebook-twitter-g'), $kind, $account_name, $status ?: 'pending', $job_id);
        }
        self::log_job_result($job, $network, $account, $is_test, $message);
        return $message;
    }

    private static function job_result_error($job)
    {
        $result = !empty($job['result_json']) ? json_decode($job['result_json'], true) : array();
        if (!is_array($result)) return '';
        if (!empty($result['error']['message'])) return sanitize_text_field($result['error']['message']);
        if (!empty($result['message']) && is_scalar($result['message'])) return sanitize_text_field($result['message']);
        foreach (array('destinations', 'results') as $field) {
            if (empty($result[$field]) || !is_array($result[$field])) continue;
            foreach ($result[$field] as $destination) if (!empty($destination['error']['message'])) return sanitize_text_field($destination['error']['message']);
        }
        return '';
    }

    private static function job_result_remote_url($job)
    {
        $result = !empty($job['result_json']) ? json_decode($job['result_json'], true) : array();
        if (!is_array($result)) return '';
        foreach (array('results', 'destinations') as $field) {
            if (empty($result[$field]) || !is_array($result[$field])) continue;
            foreach ($result[$field] as $destination) {
                if (empty($destination['remote_url'])) continue;
                $url = esc_url_raw($destination['remote_url']);
                if ($url !== '' && nxs_validate_remote_url($url, true)) return $url;
            }
        }
        return '';
    }

    private static function log_job_result($job, $network_code = '', $account = array(), $is_test = false, $message = '')
    {
        if (!$job || !function_exists('nxs_LogIt') || $message === '') return;
        $status = sanitize_key($job['status']);
        $type = $status === 'published' ? 'S' : (in_array($status, array('failed', 'cancelled', 'expired', 'partially_published', 'retrying'), true) ? 'E' : 'I');
        $name = !empty($account['nName']) ? sanitize_text_field($account['nName']) : sanitize_text_field($network_code);
        nxs_LogIt($type, $is_test ? 'Post.Social Test' : 'Post.Social', $name, sanitize_text_field($network_code), $message, 'Job #' . (int) $job['id']);
    }

    public static function process_job($job_id)
    {
        global $wpdb;
        $job = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id=%d', $job_id), ARRAY_A);
        if (!$job || in_array($job['status'], array('published', 'partially_published', 'failed', 'cancelled'), true)) {
            return;
        }
        $snapshot = !empty($job['payload_json']) ? json_decode($job['payload_json'], true) : array();
        $source_type = !empty($snapshot['source']['type']) ? sanitize_key($snapshot['source']['type']) : 'wordpress_post';
        $post = $source_type === 'manual' ? null : get_post((int) $job['post_id']);
        if (($source_type !== 'manual' && (!$post || $post->post_status !== 'publish')) || empty($snapshot['accounts']) || empty($snapshot['content']) || empty($snapshot['source'])) {
            self::fail_job($job_id, 'wordpress_post_unavailable', __('The WordPress post is no longer publishable.', 'social-networks-auto-poster-facebook-twitter-g'));
            return;
        }
        $accounts = array_values(array_filter((array) $snapshot['accounts']));
        $api = new NXS_PostSocial_Api();
        $media_ids = !empty($job['media_ids']) ? json_decode($job['media_ids'], true) : array();
        $media_ids = is_array($media_ids) ? array_values(array_filter($media_ids)) : array();
        $thumbnail_id = !empty($snapshot['attachment_id']) ? (int) $snapshot['attachment_id'] : 0;
        if (!$thumbnail_id && empty($media_ids) && !empty($snapshot['remote_media_url'])) {
            $state = NXS_PostSocial_State::all();
            $uploaded = $api->request('POST', 'media/from-url', array('project_id' => !empty($state['project_id']) ? $state['project_id'] : '', 'url' => $snapshot['remote_media_url']));
            if (!$uploaded['ok']) { self::retry_or_fail($job, $uploaded); return; }
            if (empty($uploaded['data']['id'])) { self::fail_job($job_id, 'invalid_media_response', __('Post.Social did not return a media ID.', 'social-networks-auto-poster-facebook-twitter-g')); return; }
            $media_ids[] = $uploaded['data']['id'];
            $wpdb->update(self::table(), array('media_ids' => wp_json_encode($media_ids), 'updated_at' => current_time('mysql', true)), array('id' => $job_id));
        }
        if ($thumbnail_id && empty($media_ids)) {
            $path = get_attached_file($thumbnail_id);
            if ($path && is_readable($path)) {
                $uploaded = $api->upload('media', $path, get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true));
                if (!$uploaded['ok']) {
                    self::retry_or_fail($job, $uploaded);
                    return;
                }
                $media_ids[] = $uploaded['data']['id'];
                $wpdb->update(self::table(), array(
                    'media_ids' => wp_json_encode($media_ids),
                    'updated_at' => current_time('mysql', true),
                ), array('id' => $job_id));
            }
        }
        $mode = $snapshot['mode'] === 'scheduled' ? 'scheduled' : 'now';
        $scheduled = $mode === 'scheduled' ? $snapshot['scheduled_at'] : null;
        $payload = array(
            'idempotency_key' => $job['idempotency_key'],
            'source' => array(
                'type' => $source_type,
                'site_installation_uuid' => NXS_PostSocial_State::installation_uuid(),
                'action_id' => $job['action_id'],
                'wordpress_post_id' => $source_type === 'manual' ? null : (int) $snapshot['source']['wordpress_post_id'],
                'wordpress_post_type' => $snapshot['source']['wordpress_post_type'],
                'wordpress_status' => $snapshot['source']['wordpress_status'],
                'wordpress_permalink' => $snapshot['source']['wordpress_permalink'],
                'wordpress_modified_gmt' => $snapshot['source']['wordpress_modified_gmt'],
                'event_id' => $job['event_id'],
            ),
            'content' => array(
                'text' => $snapshot['content']['text'],
                'title' => $snapshot['content']['title'],
                'link_url' => $snapshot['content']['link_url'],
                'tags' => $snapshot['content']['tags'],
                'location' => $snapshot['content']['location'],
                'media_ids' => $media_ids,
            ),
            'destinations' => array_map(function ($id) use ($snapshot) {
                $adjustments = !empty($snapshot['adjustments']) && is_array($snapshot['adjustments']) ? $snapshot['adjustments'] : array('add_title_to_text' => true, 'add_tags_as_hashtags' => true, 'link_preview' => true);
                return array('account_id' => $id, 'adjustments' => $adjustments);
            }, $accounts),
            'delivery' => array('mode' => $mode, 'scheduled_at' => $scheduled ?: null),
        );
        $result = $api->request('POST', 'publications', $payload);
        if (!$result['ok']) {
            self::retry_or_fail($job, $result);
            return;
        }
        if (empty($result['data']['id'])) {
            self::fail_job($job_id, 'invalid_publication_response', __('Post.Social accepted the request but did not return a publication ID.', 'social-networks-auto-poster-facebook-twitter-g'));
            return;
        }
        $remote_type = $mode === 'scheduled' ? 'schedule' : 'publication_batch';
        $status = isset($result['data']['status']) ? sanitize_key($result['data']['status']) : 'accepted';
        $terminal = in_array($status, array('published', 'partially_published', 'failed', 'cancelled', 'expired'), true);
        $wpdb->update(self::table(), array(
            'status' => $status,
            'remote_type' => $remote_type,
            'remote_id' => sanitize_text_field($result['data']['id']),
            'result_json' => wp_json_encode($result['data']),
            'attempts' => (int) $job['attempts'] + 1,
            'next_poll_at' => $terminal ? null : gmdate('Y-m-d H:i:s', time() + max(5, (int) (isset($result['data']['next_poll_after']) ? $result['data']['next_poll_after'] : 5))),
            'last_error_code' => null,
            'last_error_message' => null,
            'updated_at' => current_time('mysql', true),
        ), array('id' => $job_id));
        if (!$terminal) wp_schedule_single_event(time() + 5, self::POLL_HOOK, array((int) $job_id));
    }

    public static function poll_job($job_id, $log_terminal = true)
    {
        global $wpdb;
        $job = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id=%d', $job_id), ARRAY_A);
        if (!$job || empty($job['remote_id']) || in_array($job['status'], array('published', 'partially_published', 'failed', 'cancelled', 'expired'), true)) {
            return;
        }
        $path = $job['remote_type'] === 'schedule' ? 'schedules/' : 'publications/';
        $result = (new NXS_PostSocial_Api())->request('GET', $path . rawurlencode($job['remote_id']));
        if (!$result['ok']) {
            self::retry_or_fail($job, $result, true);
            return;
        }
        $status = isset($result['data']['status']) ? sanitize_key($result['data']['status']) : 'accepted';
        $terminal = in_array($status, array('published', 'partially_published', 'failed', 'cancelled', 'expired'), true);
        $delay = max(5, min(300, (int) (isset($result['data']['next_poll_after']) ? $result['data']['next_poll_after'] : 30)));
        $wpdb->update(self::table(), array(
            'status' => $status,
            'next_poll_at' => $terminal ? null : gmdate('Y-m-d H:i:s', time() + $delay),
            'result_json' => wp_json_encode($result['data']),
            'updated_at' => current_time('mysql', true),
        ), array('id' => $job_id));
        $updated_job = array_merge($job, array('status' => $status, 'next_poll_at' => $terminal ? null : gmdate('Y-m-d H:i:s', time() + $delay), 'result_json' => wp_json_encode($result['data']), 'updated_at' => current_time('mysql', true)));
        if ($terminal) {
            self::mark_permission_failure($result['data']);
            if ($log_terminal) {
                $snapshot = !empty($job['payload_json']) ? json_decode($job['payload_json'], true) : array();
                $source = !empty($snapshot['source']) && is_array($snapshot['source']) ? $snapshot['source'] : array();
                $network_code = !empty($source['snap_network_code']) ? $source['snap_network_code'] : '';
                $account = array('nName' => !empty($source['snap_account_name']) ? $source['snap_account_name'] : $network_code);
                self::job_result_message($updated_job, $network_code, $account, !empty($source['type']) && $source['type'] === 'manual');
            }
        }
        if (!$terminal) {
            wp_schedule_single_event(time() + $delay + wp_rand(0, 3), self::POLL_HOOK, array((int) $job_id));
        }
    }

    private static function retry_or_fail($job, $result, $polling = false)
    {
        $error = !empty($result['error']) ? $result['error'] : array();
        $snapshot = !empty($job['payload_json']) ? json_decode($job['payload_json'], true) : array();
        $is_unsent_test = !$polling && empty($job['remote_id']) && !empty($snapshot['source']['type']) && $snapshot['source']['type'] === 'manual';
        if ($is_unsent_test) {
            self::fail_job((int) $job['id'], isset($error['code']) ? $error['code'] : 'test_submission_failed', isset($error['message']) ? $error['message'] : __('Post.Social rejected the test publication.', 'social-networks-auto-poster-facebook-twitter-g'));
            return;
        }
        // A generic API error may conceal a permanent billing hold. Ask the
        // billing endpoint before scheduling retries that cannot succeed.
        if (!$polling && empty($job['remote_id']) && !empty($error['code']) && $error['code'] === 'internal_error') {
            $billing = (new NXS_PostSocial_Api())->request('GET', 'billing');
            $details = !empty($billing['data']) && is_array($billing['data']) ? $billing['data'] : array();
            if (!empty($billing['ok']) && !empty($details['restrictions']['reason_code'])
                && $details['restrictions']['reason_code'] === 'billing_hold'
                && isset($details['can_publish']['managed']) && !$details['can_publish']['managed']) {
                $message = __('Post.Social cannot publish because billing is on hold. Open the Post.Social billing dashboard to restore publishing.', 'social-networks-auto-poster-facebook-twitter-g');
                self::fail_job((int) $job['id'], 'billing_hold', $message);
                $source = !empty($snapshot['source']) && is_array($snapshot['source']) ? $snapshot['source'] : array();
                $network = !empty($source['snap_network_code']) ? $source['snap_network_code'] : '';
                $account = array('nName' => !empty($source['snap_account_name']) ? $source['snap_account_name'] : $network);
                self::job_result_message(array_merge($job, array('status' => 'failed', 'last_error_message' => $message)), $network, $account, false);
                return;
            }
        }
        $retryable = !empty($error['retryable']) || (int) $result['status'] === 0 || (int) $result['status'] === 429 || (int) $result['status'] >= 500;
        $attempts = (int) $job['attempts'] + 1;
        if (!$retryable || $attempts >= 8) {
            self::fail_job((int) $job['id'], isset($error['code']) ? $error['code'] : 'publication_failed', isset($error['message']) ? $error['message'] : __('Post.Social rejected the publication.', 'social-networks-auto-poster-facebook-twitter-g'));
            return;
        }
        $delay = !empty($result['retry_after']) ? (int) $result['retry_after'] : min(300, (int) pow(2, min($attempts, 8)) * 5 + wp_rand(0, 5));
        global $wpdb;
        $wpdb->update(self::table(), array(
            'status' => 'retrying',
            'attempts' => $attempts,
            'next_poll_at' => gmdate('Y-m-d H:i:s', time() + $delay),
            'last_error_code' => isset($error['code']) ? sanitize_key($error['code']) : 'temporary_error',
            'last_error_message' => isset($error['message']) ? sanitize_text_field($error['message']) : __('Post.Social is temporarily unavailable.', 'social-networks-auto-poster-facebook-twitter-g'),
            'updated_at' => current_time('mysql', true),
        ), array('id' => (int) $job['id']));
        wp_schedule_single_event(time() + $delay, $polling ? self::POLL_HOOK : self::JOB_HOOK, array((int) $job['id']));
    }

    private static function fail_job($job_id, $code, $message)
    {
        global $wpdb;
        $wpdb->update(self::table(), array(
            'status' => 'failed',
            'last_error_code' => sanitize_key($code),
            'last_error_message' => sanitize_text_field($message),
            'updated_at' => current_time('mysql', true),
        ), array('id' => (int) $job_id));
    }

    private static function mark_permission_failure($result)
    {
        if (!class_exists('NXS_PostSocial_Accounts')) return;
        foreach (array('results', 'destinations') as $field) {
            if (empty($result[$field]) || !is_array($result[$field])) continue;
            foreach ($result[$field] as $destination) {
                $category = !empty($destination['error']['category']) ? sanitize_key($destination['error']['category']) : '';
                if (!in_array($category, array('permission', 'authentication', 'authorization'), true) || empty($destination['account_id'])) continue;
                $message = !empty($destination['error']['message']) ? sanitize_text_field($destination['error']['message']) : __('Reconnect this account through Post.Social.', 'social-networks-auto-poster-facebook-twitter-g');
                NXS_PostSocial_Accounts::mark_account_unavailable($destination['account_id'], $message);
            }
        }
    }

    private static function remember_error($result)
    {
        $error = !empty($result['error']) && is_array($result['error']) ? $result['error'] : array();
        NXS_PostSocial_State::update(array('last_error' => array(
            'code' => isset($error['code']) ? sanitize_key($error['code']) : 'api_error',
            'message' => isset($error['message']) ? sanitize_text_field($error['message']) : __('Post.Social could not complete the request.', 'social-networks-auto-poster-facebook-twitter-g'),
            'request_id' => isset($result['request_id']) ? sanitize_text_field($result['request_id']) : null,
        )));
    }

    public static function pkce_pair()
    {
        try {
            $verifier = rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
        } catch (Exception $exception) {
            return array('verifier' => '', 'challenge' => '');
        }
        if (strlen($verifier) < 43 || strlen($verifier) > 128) {
            return array('verifier' => '', 'challenge' => '');
        }
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        return array('verifier' => $verifier, 'challenge' => $challenge);
    }

    public static function pending_expired($pending)
    {
        if (empty($pending) || !is_array($pending)) {
            return false;
        }
        if (!empty($pending['expires_at'])) {
            $expires = strtotime($pending['expires_at']);
            if ($expires !== false) {
                return $expires <= time();
            }
        }
        if (!empty($pending['created_at'])) {
            $created = strtotime($pending['created_at']);
            if ($created !== false) {
                return $created + 15 * MINUTE_IN_SECONDS <= time();
            }
        }
        return false;
    }

    private static function normalize_connection_status($data)
    {
        $status = !empty($data['status']) ? sanitize_key($data['status']) : 'pending';
        $map = array(
            'created' => 'pending', 'requested' => 'pending', 'waiting' => 'pending', 'pending' => 'pending',
            'signed_in' => 'authenticated', 'authenticated' => 'authenticated', 'browser_confirmed' => 'authenticated',
            'setup' => 'awaiting_setup', 'setup_required' => 'awaiting_setup', 'awaiting_setup' => 'awaiting_setup',
            'approved' => 'completed', 'confirmed' => 'completed', 'complete' => 'completed', 'completed' => 'completed',
            'expired' => 'expired', 'cancelled' => 'cancelled', 'canceled' => 'cancelled',
            'denied' => 'failed', 'error' => 'failed', 'failed' => 'failed',
        );
        if (!empty($data['authenticated']) && !empty($data['awaiting_setup'])) {
            return 'awaiting_setup';
        }
        if (!empty($data['authenticated']) || !empty($data['browser_confirmed'])) {
            return !empty($data['completed']) ? 'completed' : 'authenticated';
        }
        return isset($map[$status]) ? $map[$status] : 'pending';
    }

    private static function status_message($status)
    {
        $messages = array(
            'pending' => __('Waiting for sign-in…', 'social-networks-auto-poster-facebook-twitter-g'),
            'authenticated' => __('Signed in. Complete setup in Post.Social…', 'social-networks-auto-poster-facebook-twitter-g'),
            'awaiting_setup' => __('Signed in. Complete setup in Post.Social…', 'social-networks-auto-poster-facebook-twitter-g'),
            'completed' => __('Post.Social connected.', 'social-networks-auto-poster-facebook-twitter-g'),
            'expired' => __('Connection expired.', 'social-networks-auto-poster-facebook-twitter-g'),
            'cancelled' => __('Connection cancelled.', 'social-networks-auto-poster-facebook-twitter-g'),
            'failed' => __('Connection failed.', 'social-networks-auto-poster-facebook-twitter-g'),
        );
        return isset($messages[$status]) ? $messages[$status] : $messages['pending'];
    }

    private static function pending_headers($pending)
    {
        $headers = array();
        if (!empty($pending['poll_token'])) {
            $headers['X-SNAP-Poll-Token'] = $pending['poll_token'];
        }
        $legacy_secret = !empty($pending['legacy_secret']) ? $pending['legacy_secret'] : (!empty($pending['proof_secret']) ? $pending['proof_secret'] : '');
        if ($legacy_secret !== '') {
            $headers['X-SNAP-Claim-Secret'] = $legacy_secret;
        }
        return $headers;
    }

    private static function pending_connect_url($pending)
    {
        return !empty($pending['connect_url']) ? $pending['connect_url'] : (!empty($pending['browser_url']) ? $pending['browser_url'] : '');
    }

    private static function safe_web_url($url)
    {
        $url = esc_url_raw((string) $url);
        if ($url === '' || !nxs_validate_remote_url($url, true)) {
            return '';
        }
        $host = wp_parse_url($url, PHP_URL_HOST);
        $allowed = array_filter(array(
            strtolower((string) wp_parse_url(NXS_POSTSOCIAL_WEB_URL, PHP_URL_HOST)),
            'post.social',
        ));
        return $host && in_array(strtolower($host), $allowed, true) ? $url : '';
    }

    private static function sanitize_intent($intent)
    {
        $intent = strtolower(trim((string) $intent));
        return preg_match('/^[a-z0-9:_-]{1,80}$/', $intent) ? $intent : '';
    }

    private static function first_value($data, $keys)
    {
        if (!is_array($data)) {
            return '';
        }
        foreach ($keys as $key) {
            if (isset($data[$key]) && is_scalar($data[$key]) && (string) $data[$key] !== '') {
                return (string) $data[$key];
            }
        }
        return '';
    }

    private static function public_connection_result($result)
    {
        return array(
            'connected' => !empty($result['connected']),
            'terminal' => !empty($result['terminal']),
            'reused' => !empty($result['reused']),
            'status' => !empty($result['status']) && !is_numeric($result['status']) ? sanitize_key($result['status']) : 'pending',
            'message' => !empty($result['message']) ? sanitize_text_field($result['message']) : self::status_message(!empty($result['status']) ? $result['status'] : 'pending'),
            'connect_url' => self::safe_web_url(!empty($result['connect_url']) ? $result['connect_url'] : ''),
            'expires_at' => !empty($result['expires_at']) ? sanitize_text_field($result['expires_at']) : '',
            'intent' => !empty($result['intent']) ? self::sanitize_intent($result['intent']) : '',
        );
    }

    private static function public_error($result, $terminal)
    {
        $error = !empty($result['error']) && is_array($result['error']) ? $result['error'] : array();
        return array(
            'terminal' => (bool) $terminal,
            'code' => !empty($error['code']) ? sanitize_key($error['code']) : 'connection_failed',
            'message' => !empty($error['message']) ? sanitize_text_field($error['message']) : __('Post.Social could not complete the request.', 'social-networks-auto-poster-facebook-twitter-g'),
        );
    }

    private static function local_error($code, $message, $terminal = false, $http_status = 500, $connection_status = 'failed')
    {
        return array(
            'ok' => false,
            'status' => (int) $http_status,
            'terminal' => (bool) $terminal,
            'connection_status' => sanitize_key($connection_status),
            'error' => array('code' => sanitize_key($code), 'message' => sanitize_text_field($message), 'retryable' => !$terminal),
            'request_id' => null,
        );
    }

    public static function site_health_tests($tests)
    {
        $tests['direct']['nxs_postsocial'] = array(
            'label' => __('Post.Social connection', 'social-networks-auto-poster-facebook-twitter-g'),
            'test' => function () {
                $state = NXS_PostSocial_State::all();
                $connected = NXS_PostSocial_State::connected();
                return array(
                    'label' => $connected ? __('Post.Social is connected', 'social-networks-auto-poster-facebook-twitter-g') : __('Post.Social is not connected', 'social-networks-auto-poster-facebook-twitter-g'),
                    'status' => $connected ? 'good' : 'recommended',
                    'badge' => array('label' => 'SNAP', 'color' => 'blue'),
                    'description' => '<p>' . esc_html($connected ? sprintf(__('Last successful API contact: %s', 'social-networks-auto-poster-facebook-twitter-g'), isset($state['last_contact_at']) ? $state['last_contact_at'] : __('unknown', 'social-networks-auto-poster-facebook-twitter-g')) : __('Connect this site from SNAP → Post.Social. No credentials are shown by Site Health.', 'social-networks-auto-poster-facebook-twitter-g')) . '</p>',
                    'actions' => '',
                    'test' => 'nxs_postsocial',
                );
            },
        );
        return $tests;
    }

    private static function random_secret()
    {
        try {
            return rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
        } catch (Exception $exception) {
            return '';
        }
    }

    private static function table()
    {
        global $wpdb;
        return $wpdb->prefix . 'nxs_postsocial_jobs';
    }
}

require_once NXS_PLPATH . 'inc/nxs_postsocial_accounts.php';
NXS_PostSocial_Premium::register();
NXS_PostSocial_Integration::register();
register_activation_hook(NXS_PLPATH . 'NextScripts_SNAP.php', array('NXS_PostSocial_Integration', 'activate'));
