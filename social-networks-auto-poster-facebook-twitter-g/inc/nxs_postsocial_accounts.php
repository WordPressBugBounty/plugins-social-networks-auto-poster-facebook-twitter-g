<?php
/**
 * SNAP account management for Post.Social-backed destinations.
 *
 * Provider credentials remain in Post.Social. SNAP stores only safe remote
 * identity/status metadata in its existing nxsSNAPNetworks account option.
 */

if (!defined('ABSPATH')) {
    exit;
}

class NXS_PostSocial_Accounts
{
    const BACKEND = 'postsocial';
    const NONCE = 'nxs_postsocial_accounts';
    const SUPPORTED_NETWORKS_CACHE_TTL = 300;
    const LEGACY_GENERIC_FORMAT = 'New post (%TITLE%) has been published on %SITENAME% - %URL%';

    public static function register()
    {
        add_action('admin_init', array(__CLASS__, 'maybe_refresh_supported_networks'), 5);
        add_action('admin_init', array(__CLASS__, 'maybe_import_initial_accounts'), 20);
        add_action('wp_ajax_nxs_postsocial_projects', array(__CLASS__, 'ajax_projects'));
        add_action('wp_ajax_nxs_postsocial_project_accounts', array(__CLASS__, 'ajax_project_accounts'));
        add_action('wp_ajax_nxs_postsocial_import_accounts', array(__CLASS__, 'ajax_import_accounts'));
        add_action('wp_ajax_nxs_postsocial_account_start', array(__CLASS__, 'ajax_account_start'));
        add_action('wp_ajax_nxs_postsocial_account_poll', array(__CLASS__, 'ajax_account_poll'));
        add_action('wp_ajax_nxs_postsocial_account_select', array(__CLASS__, 'ajax_account_select'));
        add_action('wp_ajax_nxs_postsocial_account_cancel', array(__CLASS__, 'ajax_account_cancel'));
        add_action('wp_ajax_nxs_postsocial_account_update', array(__CLASS__, 'ajax_account_update'));
        add_action('admin_footer', array(__CLASS__, 'render_oauth_return_script'));
    }

    public static function is_account($account)
    {
        return is_array($account)
            && ((!empty($account['nxs_backend']) && $account['nxs_backend'] === self::BACKEND)
                || (!empty($account['connection_type']) && $account['connection_type'] === self::BACKEND));
    }

    public static function imported_account_ids()
    {
        $ids = array();
        foreach (self::snap_networks() as $items) foreach ((array) $items as $account) {
            if (self::is_account($account) && !empty($account['postsocial_account_id'])) $ids[] = (string) $account['postsocial_account_id'];
        }
        return array_values(array_unique($ids));
    }

    public static function maybe_import_initial_accounts()
    {
        if (!NXS_PostSocial_State::connected()) return;
        self::import_initial_connection_accounts();
    }

    public static function import_initial_connection_accounts()
    {
        $state = NXS_PostSocial_State::all();
        // A bootstrapped Cloud connection discovers all provider accounts before
        // the administrator chooses which Pages to add in the OAuth return popup.
        if (!empty($state['credential_type']) && $state['credential_type'] === 'site_key') return array('imported' => 0, 'updated' => 0, 'skipped' => 0);
        $connection_id = !empty($state['connection_id']) ? (string) $state['connection_id'] : '';
        if ($connection_id === '' || (!empty($state['initial_accounts_imported_for']) && hash_equals((string) $state['initial_accounts_imported_for'], $connection_id))) return array('imported' => 0, 'updated' => 0, 'skipped' => 0);
        $accounts = !empty($state['accounts']) && is_array($state['accounts']) ? $state['accounts'] : array();
        if (empty($accounts)) return array('imported' => 0, 'updated' => 0, 'skipped' => 0);
        $project = array('id' => !empty($state['project_id']) ? $state['project_id'] : '', 'name' => !empty($state['project_name']) ? $state['project_name'] : '');
        $summary = self::import_remote_accounts($project, $accounts);
        NXS_PostSocial_State::update(array('initial_accounts_imported_for' => $connection_id));
        return $summary;
    }

    public static function network_can_authorize($network)
    {
        $network = sanitize_key($network);
        $advertised = self::cached_supported_networks();
        if (in_array($network, array('bs', 'vk'), true)) return empty($advertised) || self::connection_module_id_for_network($network) > 0;
        foreach ($advertised as $item) {
            if (self::snap_network_for_platform(self::network_code($item)) === $network && self::authorization_supported($item)) {
                return true;
            }
        }
        // Keep the core managed-OAuth networks available during a temporary
        // capabilities outage. A successfully cached API list takes priority.
        return empty($advertised) && in_array($network, array('fb', 'ig', 'li', 'pn', 'tw', 'th'), true);
    }

    public static function network_has_api_authorization($network)
    {
        return self::network_can_authorize($network);
    }

    /**
     * Keep the Add New Network methods in sync with capability changes made in
     * Post.Social. The previous cache was only refreshed while connecting the
     * site, so newly enabled networks could remain hidden indefinitely.
     */
    public static function maybe_refresh_supported_networks()
    {
        if (!current_user_can('manage_options') || !NXS_PostSocial_State::connected()) return false;
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if (!in_array($page, array('nxssnap', 'nxssnap-postsocial'), true)) return false;
        return self::refresh_supported_networks_cache();
    }

    public static function refresh_supported_networks_cache($force = false)
    {
        $state = NXS_PostSocial_State::all();
        $last = !empty($state['supported_networks_refreshed_at']) ? strtotime((string) $state['supported_networks_refreshed_at']) : 0;
        if (!$force && $last > 0 && (time() - $last) < self::SUPPORTED_NETWORKS_CACHE_TTL && ($state['supported_networks_plugin_version'] ?? '') === NextScripts_SNAP_Version) return false;

        $result = (new NXS_PostSocial_Api())->get_supported_networks();
        if (empty($result['ok'])) return false;
        $items = self::normalize_collection($result['data'], array('networks', 'supported_networks'));
        NXS_PostSocial_State::update(array(
            'supported_networks' => $items,
            'supported_networks_refreshed_at' => gmdate('c'),
            'supported_networks_plugin_version' => NextScripts_SNAP_Version,
            'last_contact_at' => gmdate('c'),
        ));
        return true;
    }

    public static function render_admin_panel()
    {
        if (!NXS_PostSocial_State::connected()) return;
        $initial = array('projects' => array(), 'accounts' => array(), 'conflicts' => array('count' => 0, 'message' => ''), 'error' => '');
        $state = NXS_PostSocial_State::all();
        $api = new NXS_PostSocial_Api();
        $projects_result = $api->get_projects();
        if ($projects_result['ok']) {
            $selected_project = array();
            foreach (self::normalize_collection($projects_result['data'], array('projects')) as $project) {
                $id = self::first($project, array('id', 'project_id'));
                if ($id === '') continue;
                $selected = !empty($state['project_id']) && hash_equals((string) $state['project_id'], $id);
                $initial['projects'][] = array('id' => $id, 'name' => self::first($project, array('name', 'project_name', 'title')) ?: $id, 'selected' => $selected);
                if ($selected || !$selected_project) $selected_project = $project;
            }
            if ($selected_project) {
                $project_id = self::first($selected_project, array('id', 'project_id'));
                $accounts_result = $api->get_project_accounts($project_id, $selected_project);
                if ($accounts_result['ok']) {
                    $remote = self::normalize_collection($accounts_result['data'], array('accounts', 'social_accounts'));
                    self::sync_cached_accounts($remote, $project_id, self::first($selected_project, array('name', 'project_name', 'title')) ?: $project_id);
                    $initial['accounts'] = self::public_accounts($remote);
                    $initial['conflicts'] = self::project_conflicts($project_id);
                } else $initial['error'] = !empty($accounts_result['error']['message']) ? $accounts_result['error']['message'] : __('Could not load accounts.', 'social-networks-auto-poster-facebook-twitter-g');
            } else $initial['error'] = __('No Post.Social Projects are available.', 'social-networks-auto-poster-facebook-twitter-g');
        } else $initial['error'] = !empty($projects_result['error']['message']) ? $projects_result['error']['message'] : __('Could not load Projects.', 'social-networks-auto-poster-facebook-twitter-g');
        $current_project_id = !empty($state['project_id']) ? (string) $state['project_id'] : '';
        ?>
        <div id="nxs-postsocial-import" class="card" style="max-width:900px;padding:24px;margin-top:22px">
            <h2 style="margin-top:0"><?php esc_html_e('Import / Sync accounts from Post.Social', 'social-networks-auto-poster-facebook-twitter-g'); ?></h2>
            <p><?php esc_html_e('Choose a Post.Social Project and import any of its connected accounts into the regular SNAP account list. Running this again synchronizes account names and status without creating duplicates.', 'social-networks-auto-poster-facebook-twitter-g'); ?></p>
            <p><label><strong><?php esc_html_e('Project:', 'social-networks-auto-poster-facebook-twitter-g'); ?></strong><br><select id="nxs-ps-project" data-current-project-id="<?php echo esc_attr($current_project_id); ?>" style="min-width:320px"><?php foreach ($initial['projects'] as $project): ?><option value="<?php echo esc_attr($project['id']); ?>"<?php selected($project['selected'] || (!$current_project_id && $project['id'] === self::first($selected_project, array('id', 'project_id')))); ?>><?php echo esc_html($project['name']); ?></option><?php endforeach; ?></select></label></p>
            <p><button type="button" class="button" id="nxs-ps-refresh-list"><?php esc_html_e('Refresh / Sync list', 'social-networks-auto-poster-facebook-twitter-g'); ?></button></p>
            <div id="nxs-ps-switch" hidden class="notice notice-warning inline" style="padding:10px 14px"></div>
            <div id="nxs-ps-accounts" style="margin:16px 0"></div>
            <p id="nxs-ps-import-controls" hidden><label><input type="checkbox" id="nxs-ps-select-all"> <?php esc_html_e('Select All', 'social-networks-auto-poster-facebook-twitter-g'); ?></label>&nbsp;&nbsp;
                <button type="button" class="button button-primary" id="nxs-ps-import-selected"><?php esc_html_e('Import Selected Accounts', 'social-networks-auto-poster-facebook-twitter-g'); ?></button></p>
            <p id="nxs-ps-empty-project-controls" hidden><button type="button" class="button button-primary" id="nxs-ps-use-empty-project"><?php esc_html_e('Use this Project', 'social-networks-auto-poster-facebook-twitter-g'); ?></button></p>
            <p id="nxs-ps-import-status" aria-live="polite"></p>
        </div>
        <?php self::render_admin_script($initial);
    }

    private static function render_admin_script($initial)
    {
        ?>
        <script>
        (function(){
            var projects=document.getElementById('nxs-ps-project'),accounts=document.getElementById('nxs-ps-accounts'); if(!projects||!accounts)return;
            var controls=document.getElementById('nxs-ps-import-controls'),emptyControls=document.getElementById('nxs-ps-empty-project-controls'),emptyButton=document.getElementById('nxs-ps-use-empty-project'),status=document.getElementById('nxs-ps-import-status'),switchBox=document.getElementById('nxs-ps-switch'),nonce=<?php echo wp_json_encode(wp_create_nonce(self::NONCE)); ?>,initial=<?php echo wp_json_encode($initial, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
            function call(action,data){var body=new URLSearchParams(Object.assign({action:action,_ajax_nonce:nonce},data||{}));return fetch(ajaxurl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body.toString()}).then(function(r){return r.json();});}
            function message(text,bad){status.textContent=text||'';status.style.color=bad?'#b32d2e':'#008a20';}
            function switchChoice(){var checked=switchBox.querySelector('input[name="nxs_ps_switch"]:checked');return checked?checked.value:'';}
            function loadProjects(){var selectedId=projects.value;message(<?php echo wp_json_encode(__('Loading Projects…', 'social-networks-auto-poster-facebook-twitter-g')); ?>);call('nxs_postsocial_projects').then(function(r){if(!r.success)throw new Error((r.data&&r.data.message)||'Could not load Projects.');projects.innerHTML='';projects.dataset.currentProjectId='';r.data.projects.forEach(function(p){var option=document.createElement('option');option.value=p.id;option.textContent=p.name;option.selected=selectedId?p.id===selectedId:!!p.selected;if(p.selected)projects.dataset.currentProjectId=p.id;projects.appendChild(option);});if(!projects.value)throw new Error(<?php echo wp_json_encode(__('No Post.Social Projects are available.', 'social-networks-auto-poster-facebook-twitter-g')); ?>);loadAccounts();}).catch(function(e){message(e.message,true);});}
            function renderAccounts(data){accounts.innerHTML='';controls.hidden=true;emptyControls.hidden=true;switchBox.hidden=true;var hasConflicts=!!(data.conflicts&&data.conflicts.count);if(hasConflicts){switchBox.hidden=false;switchBox.innerHTML='<p><strong>'+<?php echo wp_json_encode(__('Switch Post.Social Project?', 'social-networks-auto-poster-facebook-twitter-g')); ?>+'</strong></p><p>'+data.conflicts.message+'</p><label><input type="radio" name="nxs_ps_switch" value="remove"> '+<?php echo wp_json_encode(__('Remove them from SNAP', 'social-networks-auto-poster-facebook-twitter-g')); ?>+'</label><br><label><input type="radio" name="nxs_ps_switch" value="keep"> '+<?php echo wp_json_encode(__('Keep them', 'social-networks-auto-poster-facebook-twitter-g')); ?>+'</label><p><em>'+<?php echo wp_json_encode(__('Accounts kept from the previous Project may stop working if the current Post.Social connection no longer allows access to that Project.', 'social-networks-auto-poster-facebook-twitter-g')); ?>+'</em></p>';}
                if(!data.accounts.length){accounts.textContent=<?php echo wp_json_encode(__('No connected accounts were found in this Project.', 'social-networks-auto-poster-facebook-twitter-g')); ?>;var isCurrent=projects.dataset.currentProjectId===projects.value;emptyButton.textContent=hasConflicts?<?php echo wp_json_encode(__('Switch to this Project', 'social-networks-auto-poster-facebook-twitter-g')); ?>:<?php echo wp_json_encode(__('Use this Project', 'social-networks-auto-poster-facebook-twitter-g')); ?>;emptyControls.hidden=isCurrent;return;}
                data.accounts.forEach(function(a){var row=document.createElement(a.imported?'div':'label');row.style.cssText='display:block;padding:10px;border-bottom:1px solid #ddd';if(a.imported){var badge=document.createElement('span');badge.style.cssText='display:inline-block;min-width:140px;color:#008a20;font-weight:600';badge.textContent=<?php echo wp_json_encode(__('[Already in SNAP]', 'social-networks-auto-poster-facebook-twitter-g')); ?>;row.appendChild(badge);}else{var cb=document.createElement('input');cb.type='checkbox';cb.className='nxs-ps-account';cb.value=a.id;cb.disabled=!a.compatible;cb.checked=a.compatible&&a.can_publish;row.appendChild(cb);}var suffix=a.imported?'':(!a.compatible?' — '+<?php echo wp_json_encode(__('Not supported by SNAP', 'social-networks-auto-poster-facebook-twitter-g')); ?>:(!a.can_publish?' — '+a.status_label:''));row.appendChild(document.createTextNode(' '+a.network_name+' — '+a.display_name+(a.username?' @'+a.username:'')+suffix));accounts.appendChild(row);});controls.hidden=!accounts.querySelector('.nxs-ps-account:not(:disabled)');document.getElementById('nxs-ps-select-all').checked=false;message(<?php echo wp_json_encode(__('Account list synchronized.', 'social-networks-auto-poster-facebook-twitter-g')); ?>);
            }
            function loadAccounts(){accounts.textContent=<?php echo wp_json_encode(__('Loading and synchronizing accounts…', 'social-networks-auto-poster-facebook-twitter-g')); ?>;controls.hidden=true;emptyControls.hidden=true;switchBox.hidden=true;message('');call('nxs_postsocial_project_accounts',{project_id:projects.value}).then(function(r){if(!r.success)throw new Error((r.data&&r.data.message)||'Could not load accounts.');renderAccounts(r.data);}).catch(function(e){accounts.innerHTML='';message(e.message,true);});}
            projects.addEventListener('change',loadAccounts);document.getElementById('nxs-ps-refresh-list').addEventListener('click',loadProjects);
            document.getElementById('nxs-ps-select-all').addEventListener('change',function(e){accounts.querySelectorAll('.nxs-ps-account:not(:disabled)').forEach(function(cb){cb.checked=e.target.checked;});});
            document.getElementById('nxs-ps-import-selected').addEventListener('click',function(){var selected=[];accounts.querySelectorAll('.nxs-ps-account:checked').forEach(function(cb){selected.push(cb.value);});if(!selected.length){message(<?php echo wp_json_encode(__('Select at least one account.', 'social-networks-auto-poster-facebook-twitter-g')); ?>,true);return;}if(!switchBox.hidden&&!switchChoice()){message(<?php echo wp_json_encode(__('Choose whether to remove or keep accounts from the previous Project.', 'social-networks-auto-poster-facebook-twitter-g')); ?>,true);return;}message(<?php echo wp_json_encode(__('Importing accounts…', 'social-networks-auto-poster-facebook-twitter-g')); ?>);call('nxs_postsocial_import_accounts',{project_id:projects.value,account_ids:selected.join(','),switch_mode:switchChoice()}).then(function(r){if(!r.success)throw new Error((r.data&&r.data.message)||'Import failed.');message(r.data.message);loadAccounts();}).catch(function(e){message(e.message,true);});});
            emptyButton.addEventListener('click',function(){if(!switchBox.hidden&&!switchChoice()){message(<?php echo wp_json_encode(__('Choose whether to remove or keep accounts from the previous Project.', 'social-networks-auto-poster-facebook-twitter-g')); ?>,true);return;}emptyButton.disabled=true;message(<?php echo wp_json_encode(__('Switching Project…', 'social-networks-auto-poster-facebook-twitter-g')); ?>);call('nxs_postsocial_import_accounts',{project_id:projects.value,account_ids:'',switch_mode:switchChoice()}).then(function(r){if(!r.success)throw new Error((r.data&&r.data.message)||'Could not switch Project.');projects.dataset.currentProjectId=projects.value;emptyControls.hidden=true;switchBox.hidden=true;message(r.data.message);}).catch(function(e){message(e.message,true);}).finally(function(){emptyButton.disabled=false;});});
            if(initial.error){message(initial.error,true);}else{renderAccounts(initial);}
        }());
        </script>
        <?php
    }

    public static function render_add_method($network_info, $index, $existing_account_id = '')
    {
        $network = !empty($network_info['lcode']) ? sanitize_key($network_info['lcode']) : '';
        if ($network === '' || !self::network_can_authorize($network)) {
            return;
        }
        $name = !empty($network_info['name']) ? $network_info['name'] : strtoupper($network);
        $provider = self::connection_provider_name($network, $name);
        ?>
        <div class="nxs-ps-add-method" style="padding:4px 0 16px">
            <h3><?php esc_html_e('Fast & Easy Setup via Post.Social Cloud Service', 'social-networks-auto-poster-facebook-twitter-g'); ?></h3>
            <p><?php echo esc_html(sprintf(__('Connect your %1$s account in one click with official %2$s API authorization. No developer accounts or technical configuration needed.', 'social-networks-auto-poster-facebook-twitter-g'), $name, $provider)); ?></p>
            <button type="button" class="button button-primary nxs-ps-authorize" style="background:#008a20!important;border-color:#006b18!important;color:#fff!important" data-network="<?php echo esc_attr($network); ?>" data-reauthorize="<?php echo esc_attr($existing_account_id); ?>"><?php echo esc_html($existing_account_id !== '' ? sprintf(__('Reconnect %s', 'social-networks-auto-poster-facebook-twitter-g'), $name) : sprintf(__('Connect to %s', 'social-networks-auto-poster-facebook-twitter-g'), $name)); ?></button>
            <p class="nxs-ps-authorize-status" aria-live="polite"></p>
            <?php self::render_managed_benefits($network); ?>
        </div>
        <?php self::render_authorize_script();
    }

    private static function connection_provider_name($network, $name)
    {
        $providers = array('fb' => 'Meta', 'ig' => 'Meta', 'th' => 'Meta', 'tw' => 'X', 'li' => 'LinkedIn', 'pn' => 'Pinterest', 'gmb' => 'Google', 'rd' => 'Reddit');
        return isset($providers[$network]) ? $providers[$network] : $name;
    }

    private static function render_managed_benefits($network)
    {
        $state = NXS_PostSocial_State::all();
        $billing = !empty($state['billing']) && is_array($state['billing']) ? $state['billing'] : array();
        $trial = !empty($billing['trial']) && is_array($billing['trial']) ? $billing['trial'] : array();
        $status = !empty($trial['status']) ? sanitize_key($trial['status']) : '';
        if (self::render_expired_trial_notice()) return;
        ?>
        <div class="nxs-ps-connect-benefits" style="background:#f4f9f4;border:1px solid #c5dcc7;border-radius:4px;padding:12px 16px;margin-top:16px;max-width:700px;font-size:13px">
            <p style="margin:0 0 6px;font-size:13px!important"><?php esc_html_e('Powered by Post.Social cloud gateway.', 'social-networks-auto-poster-facebook-twitter-g'); ?>
            <?php if (!NXS_PostSocial_State::connected() || $status === 'not_started'): ?><strong><?php esc_html_e('Try it risk-free:', 'social-networks-auto-poster-facebook-twitter-g'); ?></strong><?php endif; ?></p>
            <?php if (!empty($billing['subscription']['active'])): ?>
                <p style="margin:0"><?php esc_html_e('Your Post.Social subscription is active.', 'social-networks-auto-poster-facebook-twitter-g'); ?></p>
            <?php elseif ($status === 'active'): ?>
                <?php $days_remaining = isset($trial['days_remaining']) ? max(0, (int) $trial['days_remaining']) : 0; ?>
                <p style="margin:0"><?php echo esc_html(sprintf(_n('Your trial is active — %d day remaining.', 'Your trial is active — %d days remaining.', $days_remaining, 'social-networks-auto-poster-facebook-twitter-g'), $days_remaining)); ?></p>
            <?php elseif (!NXS_PostSocial_State::connected() || $status === 'not_started'): ?>
                <ul style="list-style:disc;margin:4px 0 0 20px"><li style="font-size:13px!important;line-height:1.5"><?php esc_html_e('Free trial starts', 'social-networks-auto-poster-facebook-twitter-g'); ?> <strong><?php esc_html_e('only after your first successful post', 'social-networks-auto-poster-facebook-twitter-g'); ?></strong></li><li style="font-size:13px!important;line-height:1.5"><?php esc_html_e('No credit card required', 'social-networks-auto-poster-facebook-twitter-g'); ?></li></ul>
            <?php else: ?>
                <p style="margin:0"><?php esc_html_e('Your existing Post.Social account and plan will be used.', 'social-networks-auto-poster-facebook-twitter-g'); ?></p>
            <?php endif; ?>
            <?php if ($network === 'tw'): ?><p style="margin:8px 0 0"><?php esc_html_e('Post.Social is less expensive than maintaining your own paid X API access.', 'social-networks-auto-poster-facebook-twitter-g'); ?></p><?php endif; ?>
        </div>
        <?php
    }

    private static function render_expired_trial_notice()
    {
        $state = NXS_PostSocial_State::all();
        $billing = !empty($state['billing']) && is_array($state['billing']) ? $state['billing'] : array();
        if (empty($billing['trial']['status']) || sanitize_key($billing['trial']['status']) !== 'expired' || !empty($billing['subscription']['active'])) return false;
        ?>
        <div class="nxs-ps-trial-ended" style="box-sizing:border-box;background:#fff;border:1px solid #c3c4c7;border-left:4px solid #008a20;border-radius:4px;padding:16px 18px;margin:16px 0;max-width:740px;font-size:13px;line-height:1.5">
            <p style="margin:0 0 8px;font-size:14px;line-height:1.4"><strong><?php esc_html_e("Your free trial has ended, but you're all set to keep publishing!", 'social-networks-auto-poster-facebook-twitter-g'); ?></strong></p>
            <p style="margin:0 0 14px;font-size:13px;line-height:1.5"><?php esc_html_e('Your social accounts and settings are still here. Simply choose a Post.Social plan to resume automatic publishing right where you left off.', 'social-networks-auto-poster-facebook-twitter-g'); ?></p>
            <?php NXS_PostSocial_Premium::render_billing_button(__('Continue Publishing →', 'social-networks-auto-poster-facebook-twitter-g'), 'nxs-ps-billing-action', '', 'display:inline-flex;align-items:center;box-sizing:border-box;min-height:40px;padding:8px 16px;background:#008a20;color:#fff;border:1px solid #006b18;border-radius:4px;font-family:inherit;font-size:13px;font-weight:600;line-height:1.4;box-shadow:none;cursor:pointer'); ?>
        </div>
        <?php
        return true;
    }

    public static function render_direct_method_notice($network, $name)
    {
        $provider = self::connection_provider_name($network, $name);
        $is_meta = in_array($network, array('fb', 'ig', 'th'), true);
        $review_required = in_array($network, array('li', 'pn'), true);
        ?>
        <div class="nxs-ps-direct-notice" style="background:#fff8e8;border:1px solid #e5ca83;border-radius:4px;padding:12px 16px;margin:0 0 18px;max-width:760px">
            <p style="margin:0 0 8px"><strong>ℹ️ <?php echo esc_html($network === 'bs' || $network === 'tg' ? __('Manual setup required', 'social-networks-auto-poster-facebook-twitter-g') : __('Complicated setup & developer access required', 'social-networks-auto-poster-facebook-twitter-g')); ?></strong></p>
            <?php if ($is_meta): ?>
                <p style="margin:0 0 8px"><?php esc_html_e('Using your own Meta API keys is completely free, but it requires an active Meta Developer account, creating a business app, and submitting it for official Meta App Review & Approval (which can take several weeks and may be rejected).', 'social-networks-auto-poster-facebook-twitter-g'); ?></p>
            <?php elseif ($review_required): ?>
                <p style="margin:0 0 8px"><?php echo esc_html(sprintf(__('Using your own %1$s API keys is completely free, but it requires an active %1$s Developer account, creating an app, and submitting it for official %1$s App Review & Approval (which can take several weeks and may be rejected).', 'social-networks-auto-poster-facebook-twitter-g'), $provider)); ?></p>
            <?php elseif ($network === 'tw'): ?>
                <p style="margin:0 0 8px"><?php esc_html_e('Using your own X API keys requires a developer account and paid official X API access. Post.Social is less expensive than maintaining your own X API access.', 'social-networks-auto-poster-facebook-twitter-g'); ?></p>
            <?php elseif ($network === 'bs'): ?>
                <p style="margin:0 0 8px"><?php esc_html_e('To connect directly, enter your Bluesky handle and App Password. A developer account or app review is not required.', 'social-networks-auto-poster-facebook-twitter-g'); ?></p>
            <?php elseif ($network === 'tg'): ?>
                <p style="margin:0 0 8px"><?php esc_html_e('To connect directly, create and configure your own Telegram bot.', 'social-networks-auto-poster-facebook-twitter-g'); ?></p>
            <?php else: ?>
                <p style="margin:0 0 8px"><?php echo esc_html(sprintf(__('Using your own %s API credentials requires you to set up and maintain the provider app yourself.', 'social-networks-auto-poster-facebook-twitter-g'), $provider)); ?></p>
            <?php endif; ?>
            <p style="margin:0"><em><?php esc_html_e('If you just want to start posting right away without the setup hassle, use the Cloud Connection tab.', 'social-networks-auto-poster-facebook-twitter-g'); ?></em></p>
        </div>
        <?php
    }

    private static function render_authorize_script()
    {
        static $rendered = false;
        if ($rendered) return;
        $rendered = true;
        ?>
        <script>
        (function(){
            if(window.nxsPostSocialAuthorizeBound)return;
            window.nxsPostSocialAuthorizeBound=true;
            var nonce=<?php echo wp_json_encode(wp_create_nonce(self::NONCE)); ?>;
            function call(action,data){var body=new URLSearchParams(Object.assign({action:action,_ajax_nonce:nonce},data||{}));return fetch(ajaxurl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body.toString()}).then(function(r){return r.json();});}
            function draftFor(button){var draft={},form=button.closest('.nxs-dual-method-account');if(form&&!button.dataset.reauthorize){var nickname=form.querySelector('.nxs-add-account-nickname'),settings={};if(nickname&&nickname.value.trim()!==nickname.dataset.suggested)draft.nickname=nickname.value.trim();form.querySelectorAll('.nxs-add-publishing-settings [name]').forEach(function(field){if(field.disabled||((field.type==='checkbox'||field.type==='radio')&&!field.checked))return;var match=field.name.match(/\[([^\]]+)\]$/);if(match)settings[match[1]]=field.value;});draft.settings=settings;}return draft;}
            document.addEventListener('click',function(e){var button=e.target.closest('.nxs-ps-authorize');if(!button)return;e.preventDefault();if(button.disabled||button.dataset.nxsAuthorizePending==='1')return;button.dataset.nxsAuthorizePending='1';var box=button.closest('.nxs-ps-add-method'),status=box.querySelector('.nxs-ps-authorize-status');button.disabled=true;status.style.color='';status.textContent=<?php echo wp_json_encode(__('Starting authorization…', 'social-networks-auto-poster-facebook-twitter-g')); ?>;
                var draft=draftFor(button);
                call('nxs_postsocial_account_start',{network:button.dataset.network,reauthorize:button.dataset.reauthorize||'',settings_draft:JSON.stringify(draft),resume_draft:new URLSearchParams(window.location.search).has('nxs_ps_resume')?'1':''}).then(function(r){if(!r.success){if(r.data&&r.data.code==='login_required'&&r.data.sign_in_url){status.textContent=r.data.message;var link=document.createElement('a');link.className='button';link.href=r.data.sign_in_url;link.textContent=<?php echo wp_json_encode(__('Sign In to Post.Social', 'social-networks-auto-poster-facebook-twitter-g')); ?>;status.appendChild(document.createElement('br'));status.appendChild(link);button.disabled=false;delete button.dataset.nxsAuthorizePending;return;}throw new Error((r.data&&r.data.message)||'Could not start authorization.');}if(!r.data.authorization_url){throw new Error('Post.Social did not return an authorization URL.');}status.textContent=<?php echo wp_json_encode(__('Redirecting to authorization…', 'social-networks-auto-poster-facebook-twitter-g')); ?>;window.location.assign(r.data.authorization_url);}).catch(function(err){button.disabled=false;delete button.dataset.nxsAuthorizePending;status.style.color='#b32d2e';status.textContent=err.message;});
            });
        }());
        </script>
        <?php
    }

    public static function render_oauth_return_script()
    {
        if (!is_admin() || !current_user_can('manage_options') || empty($_GET['nxs_ps_oauth_return'])) return;
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if ($page !== 'nxssnap') return;
        $pending = NXS_PostSocial_State::account_pending();
        $return_state = isset($_GET['state']) ? sanitize_text_field(wp_unslash($_GET['state'])) : '';
        $attempt_id = isset($_GET['attempt_id']) ? sanitize_text_field(wp_unslash($_GET['attempt_id'])) : '';
        $return_error = isset($_GET['error']) ? sanitize_text_field(wp_unslash($_GET['error'])) : '';
        $valid = !empty($pending)
            && $return_state !== '' && !empty($pending['state']) && hash_equals((string) $pending['state'], $return_state)
            && (!empty($pending['flow']) && $pending['flow'] === 'managed_connection'
                ? !empty($pending['attempt_id'])
                : $attempt_id !== '' && !empty($pending['attempt_id']) && hash_equals((string) $pending['attempt_id'], $attempt_id));
        $clean_url = admin_url('admin.php?page=nxssnap');
        ?>
        <script>
        (function(){
            var wrap=document.querySelector('#wpbody-content .wrap')||document.getElementById('wpbody-content'),notice=document.createElement('div'),text=document.createElement('p');notice.className='notice notice-info';notice.appendChild(text);if(wrap)wrap.insertBefore(notice,wrap.firstChild);
            <?php if (!$valid): ?>notice.className='notice notice-error';text.textContent=<?php echo wp_json_encode(__('SNAP could not verify the returned authorization. Please try connecting the account again.', 'social-networks-auto-poster-facebook-twitter-g')); ?>;return;<?php endif; ?>
            var nonce=<?php echo wp_json_encode(wp_create_nonce(self::NONCE)); ?>,started=Date.now();text.textContent=<?php echo wp_json_encode(__('Completing account authorization…', 'social-networks-auto-poster-facebook-twitter-g')); ?>;
            function call(action,data){var body=new URLSearchParams(Object.assign({action:action,_ajax_nonce:nonce},data||{}));return fetch(ajaxurl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body.toString()}).then(function(response){return response.json();});}
            function choose(accounts){
                notice.remove();
                jQuery.pgwModal({content:'<div id="nxs-ps-page-choice"></div>',title:<?php echo wp_json_encode(__('Choose Facebook Pages', 'social-networks-auto-poster-facebook-twitter-g')); ?>,maxWidth:800,closeOnEscape:false,closeOnBackgroundClick:false});
                var form=document.getElementById('nxs-ps-page-choice');form.style.cssText='min-height:230px;padding:8px 12px 16px';
                var heading=document.createElement('p');heading.textContent=<?php echo wp_json_encode(__('Choose which connected Facebook Pages to add to SNAP:', 'social-networks-auto-poster-facebook-twitter-g')); ?>;form.appendChild(heading);
                accounts.forEach(function(account){var label=document.createElement('label'),checkbox=document.createElement('input');label.style.cssText='display:block;margin:10px 0';checkbox.type='checkbox';checkbox.value=account.id;label.appendChild(checkbox);label.appendChild(document.createTextNode(' '+account.display_name+(account.username&&account.username!==account.display_name?' (@'+account.username+')':'')));form.appendChild(label);});
                var add=document.createElement('button');add.type='button';add.className='button button-primary';add.textContent=<?php echo wp_json_encode(__('Add Selected Pages', 'social-networks-auto-poster-facebook-twitter-g')); ?>;
                var skip=document.createElement('button');skip.type='button';skip.className='button';skip.style.marginLeft='8px';skip.textContent=<?php echo wp_json_encode(__('Choose Later', 'social-networks-auto-poster-facebook-twitter-g')); ?>;
                var feedback=document.createElement('p'),saving=false;feedback.setAttribute('aria-live','polite');form.appendChild(add);form.appendChild(skip);form.appendChild(feedback);jQuery.pgwModal('reposition');
                jQuery(document).one('PgwModal::Close.nxsPageChoice',function(){if(saving)return;call('nxs_postsocial_account_select',{account_ids:'',skip:'1'}).then(function(){window.location.replace(<?php echo wp_json_encode($clean_url); ?>);}).catch(function(){window.location.reload();});});
                function submit(skipSelection){var selected=Array.from(form.querySelectorAll('input:checked')).map(function(item){return item.value;});if(!skipSelection&&!selected.length){feedback.textContent=<?php echo wp_json_encode(__('Select at least one Page.', 'social-networks-auto-poster-facebook-twitter-g')); ?>;return;}saving=true;add.disabled=true;skip.disabled=true;jQuery('#pgwModal .pm-close').hide();feedback.textContent=<?php echo wp_json_encode(__('Saving your selection…', 'social-networks-auto-poster-facebook-twitter-g')); ?>;call('nxs_postsocial_account_select',{account_ids:selected.join(','),skip:skipSelection?'1':'0'}).then(function(response){if(!response.success)throw new Error((response.data&&response.data.message)||'Could not save your selection.');jQuery.pgwModal('close');window.location.replace(<?php echo wp_json_encode($clean_url); ?>);}).catch(function(error){saving=false;add.disabled=false;skip.disabled=false;jQuery('#pgwModal .pm-close').show();feedback.textContent=error.message;});}
                add.addEventListener('click',function(){submit(false);});skip.addEventListener('click',function(){submit(true);});
            }
            function poll(){call('nxs_postsocial_account_poll',{return_error:<?php echo wp_json_encode($return_error); ?>}).then(function(response){if(!response.success)throw new Error((response.data&&response.data.message)||'Authorization failed.');text.textContent=response.data.message;if(response.data.selection_required){choose(response.data.accounts);return;}if(response.data.completed){notice.className='notice notice-success';setTimeout(function(){window.location.replace(<?php echo wp_json_encode($clean_url); ?>);},700);return;}if(response.data.terminal){notice.className='notice notice-error';return;}if(Date.now()-started>600000)throw new Error(<?php echo wp_json_encode(__('Authorization timed out. Try again.', 'social-networks-auto-poster-facebook-twitter-g')); ?>);setTimeout(poll,2000);}).catch(function(error){notice.className='notice notice-error';text.textContent=error.message;});}
            poll();
        }());
        </script>
        <?php
    }

    public static function render_account_settings($network_info, $index, $account, $network_object = null)
    {
        $status = !empty($account['postsocial_can_publish']);
        $network = !empty($network_info['lcode']) ? sanitize_key($network_info['lcode']) : '';
        $code = !empty($network_info['code']) ? sanitize_key($network_info['code']) : strtoupper($network);
        $name = !empty($network_info['name']) ? $network_info['name'] : strtoupper($network);
        $nickname = !empty($account['nName']) ? $account['nName'] : (!empty($account['postsocial_display_name']) ? $account['postsocial_display_name'] : $name);
        $account_url = self::account_url($account);
        $direct_manage_url = self::direct_account_manage_url($network, $account);
        $state = NXS_PostSocial_State::all();
        $trial_expired = !empty($state['billing']['trial']['status']) && sanitize_key($state['billing']['trial']['status']) === 'expired' && empty($state['billing']['subscription']['active']);
        $root_id = 'do' . $network_info['code'] . $index . 'Div';
        ?>
        <div id="<?php echo esc_attr($root_id); ?>" class="insOneDiv nxs-ps-account-settings" data-network="<?php echo esc_attr($network); ?>" data-index="<?php echo esc_attr($index); ?>">
            <input type="hidden" name="apDoS<?php echo esc_attr($network_info['code'] . $index); ?>" value="1" id="apDoS<?php echo esc_attr($network_info['code'] . $index); ?>" />
            <div style="width:100%;"><strong><?php esc_html_e('Account Nickname', 'social-networks-auto-poster-facebook-twitter-g'); ?>:</strong>&nbsp;<i><?php esc_html_e('Just so you can easily identify it', 'social-networks-auto-poster-facebook-twitter-g'); ?></i></div>
            <input type="text" class="nxs-ps-account-nickname" style="width:50%;font-weight:bold;color:#005800;border:1px solid #ACACAC;" value="<?php echo esc_attr($nickname); ?>" />

            <?php if ($trial_expired) self::render_expired_trial_notice(); ?>

            <ul class="nsx_tabs"><li><a href="#nsx<?php echo esc_attr($network . $index); ?>_tab1"><?php esc_html_e('Account Info', 'social-networks-auto-poster-facebook-twitter-g'); ?></a></li><li><a href="#nsx<?php echo esc_attr($network . $index); ?>_tab2"><?php esc_html_e('Advanced', 'social-networks-auto-poster-facebook-twitter-g'); ?></a></li></ul>
            <div class="nsx_tab_container">
                <div id="nsx<?php echo esc_attr($network . $index); ?>_tab1" class="nsx_tab_content" style="background-image:url(<?php echo esc_url(nxs_network_asset_url(!empty($network_info['imgcode']) ? $network_info['imgcode'] : $network, true)); ?>);background-repeat:no-repeat;background-position:90% 10%;">
                    <?php if (!$status && !$trial_expired): ?>
                        <div class="notice notice-error inline" style="margin:0 0 16px;padding:9px 12px;"><p><?php echo esc_html(!empty($account['postsocial_status_message']) ? $account['postsocial_status_message'] : __('Authorization required', 'social-networks-auto-poster-facebook-twitter-g')); ?></p></div>
                    <?php endif; ?>
                    <p><strong><?php echo esc_html(sprintf(__('Your %s URL:', 'social-networks-auto-poster-facebook-twitter-g'), $name)); ?></strong>
                        <?php if ($account_url !== ''): ?><a href="<?php echo esc_url($account_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($account_url); ?></a><?php else: ?><span><?php esc_html_e('Not available', 'social-networks-auto-poster-facebook-twitter-g'); ?></span><?php endif; ?>
                    </p>
                    <?php if (in_array($network, array('bs', 'vk'), true) && self::network_has_api_authorization($network)): ?>
                        <div class="nxs-ps-add-method" style="padding:4px 0 12px;">
                            <button type="button" class="button button-primary nxs-ps-authorize" style="background:#008a20!important;border-color:#006b18!important;color:#fff!important" data-network="<?php echo esc_attr($network); ?>" data-reauthorize="<?php echo esc_attr($account['postsocial_account_id']); ?>"><?php echo esc_html(sprintf(__('Reconnect to %s', 'social-networks-auto-poster-facebook-twitter-g'), $name)); ?></button>
                            <p class="nxs-ps-authorize-status" aria-live="polite"></p>
                        </div>
                    <?php elseif ($direct_manage_url !== '' && !empty($account['postsocial_connection_mode']) && $account['postsocial_connection_mode'] === 'direct'): ?>
                        <div class="nxs-ps-add-method" style="padding:4px 0 12px;">
                            <a class="button button-primary" href="<?php echo esc_url($direct_manage_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html(sprintf(__('Reconnect to %s', 'social-networks-auto-poster-facebook-twitter-g'), $name)); ?></a>
                        </div>
                    <?php elseif (self::network_has_api_authorization($network)): ?>
                        <div class="nxs-ps-add-method" style="padding:4px 0 12px;">
                            <button type="button" class="button button-primary nxs-ps-authorize" style="background:#008a20!important;border-color:#006b18!important;color:#fff!important" data-network="<?php echo esc_attr($network); ?>" data-reauthorize="<?php echo esc_attr($account['postsocial_account_id']); ?>"><?php echo esc_html(sprintf(__('Reconnect to %s', 'social-networks-auto-poster-facebook-twitter-g'), $name)); ?></button>
                            <p class="nxs-ps-authorize-status" aria-live="polite"></p>
                        </div>
                    <?php elseif ($direct_manage_url !== ''): ?>
                        <div class="nxs-ps-add-method" style="padding:4px 0 12px;">
                            <a class="button button-primary" style="background:#008a20!important;border-color:#006b18!important;color:#fff!important" href="<?php echo esc_url($direct_manage_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html(sprintf(__('Reconnect to %s', 'social-networks-auto-poster-facebook-twitter-g'), $name)); ?></a>
                        </div>
                    <?php endif; ?>
                    <div class="nxs-ps-publishing-settings">
                        <?php self::render_publishing_settings($network, $index, $account, $network_object); ?>
                    </div>
                    <p><strong><?php esc_html_e('Test your settings', 'social-networks-auto-poster-facebook-twitter-g'); ?>:</strong>&nbsp;&nbsp;
                        <a href="#" class="NXSButton" onclick="testPost('<?php echo esc_attr($code); ?>','<?php echo esc_attr($index); ?>');return false;"><?php echo esc_html(sprintf(__('Submit Test Post to %s', 'social-networks-auto-poster-facebook-twitter-g'), $name)); ?></a>
                    </p>
                </div>
                <div id="nsx<?php echo esc_attr($network . $index); ?>_tab2" class="nsx_tab_content nxs-ps-advanced-settings">
                    <?php if (is_object($network_object)): ?>
                        <?php if ($network === 'fb' || $network === 'li') $network_object->askForSURL($network, $index, $account); ?>
                        <?php $network_object->showNTPosTypes($network, $index, $account); ?>
                        <?php $network_object->showNTFilters($network, $index, $account); ?>
                        <?php $network_object->showImgSizeChoice($network, $index, !empty($account['wpImgSize']) ? $account['wpImgSize'] : 'full'); ?>
                        <?php $network_object->addPostingDelaySelV4($network, $index, $account); ?>
                    <?php endif; ?>
                </div>
            </div><br />
            <div class="submitX nxclear" style="padding-bottom:0;">
                <button type="button" class="button button-primary nxs-ps-save-account"><?php esc_html_e('Update Account Info', 'social-networks-auto-poster-facebook-twitter-g'); ?></button>
                <span class="nxs-ps-save-status" aria-live="polite" style="margin-left:10px;"></span>
            </div>
        </div>
        <?php self::render_authorize_script(); self::render_settings_script();
    }

    private static function render_publishing_settings($network, $index, $account, $network_object)
    {
        $defaults = self::default_publishing_options($network);
        $options = array_merge($defaults, (array) $account);
        if ($network !== 'tw' && !empty($options['msgFormat']) && $options['msgFormat'] === self::LEGACY_GENERIC_FORMAT) $options['msgFormat'] = $defaults['msgFormat'];
        if (!is_object($network_object)) {
            ?>
            <p><strong><?php esc_html_e('Message Format', 'social-networks-auto-poster-facebook-twitter-g'); ?>:</strong></p>
            <textarea name="<?php echo esc_attr($network); ?>[<?php echo esc_attr($index); ?>][msgFormat]" rows="4" style="width:95%;max-width:765px;"><?php echo esc_textarea($options['msgFormat']); ?></textarea>
            <?php
            return;
        }
        if ($network === 'fb') {
            $network_object->elemMsgFormat($index, __('Post Format', 'social-networks-auto-poster-facebook-twitter-g'), 'msgFormat', $options['msgFormat']);
            self::render_post_type_choice($network, $index, $options['postType'], array(
                'T' => array(__('Text Post', 'social-networks-auto-poster-facebook-twitter-g'), __('Text only; no image or link preview.', 'social-networks-auto-poster-facebook-twitter-g')),
                'I' => array(__('Image Post', 'social-networks-auto-poster-facebook-twitter-g'), __('Featured image with the formatted post text.', 'social-networks-auto-poster-facebook-twitter-g')),
                'A' => array(__('Shared Link', 'social-networks-auto-poster-facebook-twitter-g'), __('Formatted post text with a Facebook link preview.', 'social-networks-auto-poster-facebook-twitter-g')),
            ));
        } elseif ($network === 'ig') {
            $network_object->elemMsgFormat($index, __('Image Description Format', 'social-networks-auto-poster-facebook-twitter-g'), 'msgFormat', $options['msgFormat']);
            ?><input type="hidden" name="ig[<?php echo esc_attr($index); ?>][attchImg]" value="1" /><p class="description"><?php esc_html_e('Instagram posts use the WordPress featured image.', 'social-networks-auto-poster-facebook-twitter-g'); ?></p><?php
        } elseif ($network === 'li') {
            $network_object->elemMsgFormat($index, __('Message Format', 'social-networks-auto-poster-facebook-twitter-g'), 'msgFormat', $options['msgFormat']);
            self::render_post_type_choice($network, $index, $options['postType'], array(
                'T' => array(__('Text Post', 'social-networks-auto-poster-facebook-twitter-g'), __('Text only; no image or link preview.', 'social-networks-auto-poster-facebook-twitter-g')),
                'I' => array(__('Image Post', 'social-networks-auto-poster-facebook-twitter-g'), __('Featured image with the formatted message.', 'social-networks-auto-poster-facebook-twitter-g')),
                'A' => array(__('Shared Link', 'social-networks-auto-poster-facebook-twitter-g'), __('Formatted message with a LinkedIn link preview.', 'social-networks-auto-poster-facebook-twitter-g')),
            ));
            ?><div class="nxs-ps-li-link-title" style="<?php echo $options['postType'] === 'A' ? '' : 'display:none;'; ?>"><?php $network_object->elemTitleFormat($index, __('Link Title Format', 'social-networks-auto-poster-facebook-twitter-g'), 'msgATFormat', $options['msgATFormat']); ?></div>
            <script>(function(){var root=document.currentScript.closest('.nxs-ps-publishing-settings'),title=root.querySelector('.nxs-ps-li-link-title');root.querySelectorAll('input[name$="[postType]"]').forEach(function(input){input.addEventListener('change',function(){title.style.display=input.checked&&input.value==='A'?'':'none';});});}());</script><?php
        } elseif ($network === 'pn') {
            $network_object->elemTitleFormat($index, __('Pin Title Format', 'social-networks-auto-poster-facebook-twitter-g'), 'msgTFormat', $options['msgTFormat']);
            $network_object->elemMsgFormat($index, __('Pin Description Format', 'social-networks-auto-poster-facebook-twitter-g'), 'msgFormat', $options['msgFormat']);
            ?><div style="margin:8px 0"><strong><?php esc_html_e('Clickthrough URL', 'social-networks-auto-poster-facebook-twitter-g'); ?>:</strong><br />
                <label><input type="radio" name="pn[<?php echo esc_attr($index); ?>][cImgURL]" value="R" <?php checked($options['cImgURL'], 'R'); ?> /> <?php esc_html_e('WordPress Post URL', 'social-networks-auto-poster-facebook-twitter-g'); ?></label>&nbsp;&nbsp;
                <label><input type="radio" name="pn[<?php echo esc_attr($index); ?>][cImgURL]" value="N" <?php checked($options['cImgURL'], 'N'); ?> /> <?php esc_html_e('No Clickthrough URL', 'social-networks-auto-poster-facebook-twitter-g'); ?></label>
            </div><input type="hidden" name="pn[<?php echo esc_attr($index); ?>][attchImg]" value="1" /><p class="description"><?php esc_html_e('Pins use the WordPress featured image and the board selected in Post.Social.', 'social-networks-auto-poster-facebook-twitter-g'); ?></p><?php
        } else {
            $network_object->elemMsgFormat($index, __('Message Format', 'social-networks-auto-poster-facebook-twitter-g'), 'msgFormat', $options['msgFormat']);
            ?><input type="hidden" name="<?php echo esc_attr($network); ?>[<?php echo esc_attr($index); ?>][attchImg]" value="0" /><p><label><input type="checkbox" name="<?php echo esc_attr($network); ?>[<?php echo esc_attr($index); ?>][attchImg]" value="1" <?php checked(!empty($options['attchImg'])); ?> /> <strong><?php esc_html_e('Attach Image to the Post', 'social-networks-auto-poster-facebook-twitter-g'); ?></strong></label></p><?php
        }
    }

    public static function render_new_publishing_settings($network, $index, $account, $network_object)
    {
        self::render_publishing_settings($network, $index, $account, $network_object);
    }

    private static function render_post_type_choice($network, $index, $selected, $choices)
    {
        ?><div style="margin:8px 0"><strong><?php esc_html_e('Post Type', 'social-networks-auto-poster-facebook-twitter-g'); ?>:</strong><br /><?php
        foreach ($choices as $value => $labels) {
            ?><label style="display:block;margin:4px 0"><input type="radio" name="<?php echo esc_attr($network); ?>[<?php echo esc_attr($index); ?>][postType]" value="<?php echo esc_attr($value); ?>" <?php checked($selected, $value); ?> /> <strong><?php echo esc_html($labels[0]); ?></strong> — <span class="description"><?php echo esc_html($labels[1]); ?></span></label><?php
        }
        ?></div><?php
    }

    private static function save_publishing_settings($network, $posted, $account)
    {
        if (isset($posted['msgTFormat'])) $account['msgTFormat'] = sanitize_text_field($posted['msgTFormat']);
        if (isset($posted['msgATFormat'])) $account['msgATFormat'] = sanitize_text_field($posted['msgATFormat']);
        if ($network === 'fb' || $network === 'li') {
            $post_type = !empty($posted['postType']) ? strtoupper(sanitize_key($posted['postType'])) : 'A';
            $account['postType'] = in_array($post_type, array('T', 'I', 'A'), true) ? $post_type : 'A';
            $account['attchImg'] = $account['postType'] === 'I' ? 1 : 0;
        } elseif ($network === 'ig' || $network === 'pn') {
            $account['attchImg'] = 1;
        } else {
            $account['attchImg'] = !empty($posted['attchImg']) ? 1 : 0;
        }
        if ($network === 'pn') $account['cImgURL'] = !empty($posted['cImgURL']) && $posted['cImgURL'] === 'N' ? 'N' : 'R';
        return $account;
    }

    private static function render_settings_script()
    {
        static $rendered = false;
        if ($rendered) return;
        $rendered = true;
        ?>
        <script>
        (function(){
            var nonce=<?php echo wp_json_encode(wp_create_nonce(self::NONCE)); ?>;
            document.addEventListener('click',function(event){
                var button=event.target.closest('.nxs-ps-save-account');if(!button)return;event.preventDefault();
                var root=button.closest('.nxs-ps-account-settings'),status=root.querySelector('.nxs-ps-save-status');
                var body=new URLSearchParams({action:'nxs_postsocial_account_update',_ajax_nonce:nonce,network:root.dataset.network,index:root.dataset.index,nickname:root.querySelector('.nxs-ps-account-nickname').value});
                root.querySelectorAll('.nxs-ps-publishing-settings [name],.nxs-ps-advanced-settings [name]').forEach(function(field){if(field.disabled)return;if((field.type==='checkbox'||field.type==='radio')&&!field.checked)return;if(field.tagName==='SELECT'&&field.multiple){Array.from(field.selectedOptions).forEach(function(option){body.append(field.name,option.value);});}else{body.append(field.name,field.value);}});
                button.disabled=true;status.style.color='';status.textContent=<?php echo wp_json_encode(__('Saving…', 'social-networks-auto-poster-facebook-twitter-g')); ?>;
                fetch(ajaxurl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body.toString()}).then(function(response){return response.json();}).then(function(response){if(!response.success)throw new Error((response.data&&response.data.message)||'Could not save the account.');status.style.color='#008a20';status.textContent=response.data.message;}).catch(function(error){status.style.color='#b32d2e';status.textContent=error.message;}).finally(function(){button.disabled=false;});
            });
        }());
        </script>
        <?php
    }

    public static function ajax_account_update()
    {
        self::ajax_guard();
        $network = isset($_POST['network']) ? sanitize_key(wp_unslash($_POST['network'])) : '';
        $index = isset($_POST['index']) ? absint($_POST['index']) : -1;
        $networks = self::snap_networks();
        if ($network === '' || $index < 0 || empty($networks[$network][$index]) || !self::is_account($networks[$network][$index])) {
            wp_send_json_error(array('message' => __('The Post.Social account could not be found.', 'social-networks-auto-poster-facebook-twitter-g')), 404);
        }
        $nickname = isset($_POST['nickname']) ? sanitize_text_field(wp_unslash($_POST['nickname'])) : '';
        $posted_settings = isset($_POST[$network][$index]) && is_array($_POST[$network][$index]) ? wp_unslash($_POST[$network][$index]) : array();
        $format = isset($posted_settings['msgFormat']) ? sanitize_textarea_field($posted_settings['msgFormat']) : '';
        if ($nickname === '' || $format === '') {
            wp_send_json_error(array('message' => __('Account Nickname and Message Format are required.', 'social-networks-auto-poster-facebook-twitter-g')), 400);
        }
        $existing = $networks[$network][$index];
        $posted_settings['do'] = isset($existing['do']) ? $existing['do'] : 1;
        $posted_settings['nName'] = $nickname;
        $posted_settings['msgFormat'] = $format;
        $network_class = 'nxs_snapClass' . strtoupper($network);
        if (class_exists($network_class)) {
            $network_object = new $network_class();
            $networks[$network][$index] = $network_object->saveCommonNTSettings($posted_settings, $existing);
        }
        $networks[$network][$index] = self::save_publishing_settings($network, $posted_settings, $networks[$network][$index]);
        $networks[$network][$index]['nName'] = $nickname;
        $networks[$network][$index]['msgFormat'] = $format;
        self::save_snap_networks($networks);
        wp_send_json_success(array('message' => __('Account settings saved.', 'social-networks-auto-poster-facebook-twitter-g')));
    }

    public static function ajax_projects()
    {
        self::ajax_guard();
        $result = (new NXS_PostSocial_Api())->get_projects();
        if (!$result['ok']) self::send_api_error($result);
        $projects = self::normalize_collection($result['data'], array('projects'));
        $state = NXS_PostSocial_State::all();
        $out = array();
        foreach ($projects as $project) {
            $id = self::first($project, array('id', 'project_id'));
            if ($id === '') continue;
            $out[] = array('id' => $id, 'name' => self::first($project, array('name', 'project_name', 'title')) ?: $id, 'selected' => !empty($state['project_id']) && hash_equals((string) $state['project_id'], $id));
        }
        wp_send_json_success(array('projects' => $out, 'scoped_connection' => !empty($result['data']['scoped_connection'])));
    }

    public static function ajax_project_accounts()
    {
        self::ajax_guard();
        $project_id = self::posted_project_id();
        $project = self::verified_project($project_id);
        $result = (new NXS_PostSocial_Api())->get_project_accounts($project_id, $project);
        if (!$result['ok']) self::send_api_error($result);
        $remote = self::normalize_collection($result['data'], array('accounts', 'social_accounts'));
        self::sync_cached_accounts($remote, $project_id, self::first($project, array('name', 'project_name', 'title')) ?: $project_id);
        wp_send_json_success(array('accounts' => self::public_accounts($remote), 'conflicts' => self::project_conflicts($project_id)));
    }

    public static function ajax_import_accounts()
    {
        self::ajax_guard();
        $project_id = self::posted_project_id();
        $project = self::verified_project($project_id);
        $switch_mode = isset($_POST['switch_mode']) ? sanitize_key(wp_unslash($_POST['switch_mode'])) : '';
        $ids = isset($_POST['account_ids']) ? array_filter(array_map('sanitize_text_field', explode(',', wp_unslash($_POST['account_ids'])))) : array();
        $result = (new NXS_PostSocial_Api())->get_project_accounts($project_id, $project);
        if (!$result['ok']) self::send_api_error($result);
        $remote = self::normalize_collection($result['data'], array('accounts', 'social_accounts'));
        $selected = array_values(array_filter($remote, function ($account) use ($ids) { return self::first($account, array('status')) !== 'disconnected' && in_array(self::first($account, array('id', 'account_id', 'social_account_id')), $ids, true); }));
        self::handle_project_switch($project_id, $switch_mode);
        $summary = self::import_remote_accounts($project, $selected);
        self::set_preferred_project($project);
        $project_name = self::first($project, array('name', 'project_name', 'title')) ?: $project_id;
        $message = empty($ids)
            ? sprintf(__('Switched to Post.Social Project %s.', 'social-networks-auto-poster-facebook-twitter-g'), $project_name)
            : sprintf(_n('%d account imported.', '%d accounts imported.', $summary['imported'], 'social-networks-auto-poster-facebook-twitter-g'), $summary['imported']) . ($summary['updated'] ? ' ' . sprintf(__('%d existing account updated.', 'social-networks-auto-poster-facebook-twitter-g'), $summary['updated']) : '');
        wp_send_json_success(array('imported' => $summary['imported'], 'updated' => $summary['updated'], 'skipped' => $summary['skipped'], 'message' => $message));
    }

    public static function ajax_account_start()
    {
        self::ajax_guard();
        $network = isset($_POST['network']) ? sanitize_key(wp_unslash($_POST['network'])) : '';
        $existing = isset($_POST['reauthorize']) ? sanitize_text_field(wp_unslash($_POST['reauthorize'])) : '';
        $draft = self::settings_draft_from_request($network, $existing, !empty($_POST['resume_draft']));
        if ($existing === '' && !empty($draft)) NXS_PostSocial_State::update(array('account_settings_draft' => array('network' => $network, 'user_id' => get_current_user_id(), 'created_at' => time(), 'settings' => $draft)));
        if ($existing !== '') {
            $local_account = self::find_local_account($existing, $network);
            if (empty($local_account)) wp_send_json_error(array('code' => 'invalid_reauthorization_account', 'message' => __('This Post.Social account is not configured in SNAP.', 'social-networks-auto-poster-facebook-twitter-g')), 400);
        }
        if (!NXS_PostSocial_State::connected()) {
            $bootstrap = NXS_PostSocial_Integration::ensure_bootstrap_connection();
            if (empty($bootstrap['ok'])) {
                if (!empty($bootstrap['error']['code']) && $bootstrap['error']['code'] === 'login_required') {
                    wp_send_json_error(array('code' => 'login_required', 'message' => __('This website is already connected to Post.Social. Sign in to your existing Post.Social account to connect it with SNAP.', 'social-networks-auto-poster-facebook-twitter-g'), 'sign_in_url' => admin_url('admin.php?page=nxssnap-postsocial&nxs_ps_intent=add_account:' . rawurlencode($network))), 409);
                }
                self::send_api_error($bootstrap);
            }
        }
        $state = NXS_PostSocial_State::all();
        if (in_array($network, array('bs', 'vk'), true) || (!empty($state['credential_type']) && $state['credential_type'] === 'site_key')) {
            self::ajax_managed_account_start($network, $existing, $state, $draft);
        }
        $api = new NXS_PostSocial_Api();
        $capabilities = $api->get_network_connection_capabilities();
        if (!$capabilities['ok']) self::send_api_error($capabilities);
        self::verify_bootstrap_network($network, $capabilities['data']);
        $pkce = NXS_PostSocial_Integration::pkce_pair();
        $csrf_state = self::random_url_token(32);
        if (empty($pkce['verifier']) || empty($pkce['challenge']) || $csrf_state === '') {
            wp_send_json_error(array('code' => 'secure_request_failed', 'message' => __('SNAP could not create a secure authorization request.', 'social-networks-auto-poster-facebook-twitter-g')), 500);
        }
        $previous = NXS_PostSocial_State::account_pending();
        if (!empty($previous) && empty($previous['completed_at'])) $api->cancel_network_connection($previous);
        $project_id = !empty($state['project_id']) ? sanitize_text_field($state['project_id']) : '';
        if ($existing !== '' && !empty($local_account['postsocial_project_id'])) $project_id = sanitize_text_field($local_account['postsocial_project_id']);
        $body = array(
            'site_uuid' => NXS_PostSocial_State::installation_uuid(),
            'site_url' => home_url('/'),
            'site_name' => get_bloginfo('name'),
            'network' => self::platform_for_snap_network($network),
            'return_url' => add_query_arg('nxs_ps_oauth_return', '1', admin_url('admin.php?page=nxssnap')),
            'state' => $csrf_state,
            'code_challenge' => $pkce['challenge'],
            'code_challenge_method' => 'S256',
            'idempotency_key' => wp_generate_uuid4(),
            'intent' => 'add_account',
            'wordpress_version' => get_bloginfo('version'),
            'snap_version' => NextScripts_SNAP_Version,
        );
        if ($existing !== '') {
            $body['social_account_id'] = $existing;
            $body['reauthorize'] = true;
        }
        $result = $api->start_network_connection($body);
        if (!$result['ok']) self::send_api_error($result);
        $data = self::resource_payload($result['data']);
        $authorization_url = self::safe_public_url(self::first($data, array('connection_url', 'authorization_url', 'authorize_url', 'browser_url', 'connect_url')));
        $attempt_id = self::first($data, array('attempt_id', 'id', 'connection_id'));
        $status_path = self::first($data, array('status_path'));
        $status_url = self::first($data, array('status_url', 'poll_url'));
        if ($status_path === '' && $status_url !== '') $status_path = $api->relative_path($status_url);
        if ($status_path === '' && $attempt_id !== '') $status_path = 'network-connections/' . rawurlencode($attempt_id);
        $exchange_path = $api->relative_path(self::first($data, array('exchange_url', 'token_exchange_url')));
        if ($exchange_path === '') $exchange_path = 'token';
        if ($authorization_url === '' || ($status_path === '' && $status_url === '')) wp_send_json_error(array('code' => 'invalid_authorization_response', 'message' => __('Post.Social returned an incomplete authorization response.', 'social-networks-auto-poster-facebook-twitter-g')), 502);
        NXS_PostSocial_State::set_account_pending(array(
            'attempt_id' => $attempt_id, 'project_id' => $project_id, 'project_name' => !empty($state['project_name']) ? sanitize_text_field($state['project_name']) : '',
            'network' => $network, 'existing_account_id' => $existing, 'status_path' => $status_path, 'status_url' => $status_url,
            'exchange_path' => $exchange_path, 'cancel_path' => 'network-connections/' . rawurlencode($attempt_id),
            'state' => $csrf_state, 'code_verifier' => $pkce['verifier'], 'poll_token' => self::first($data, array('poll_token', 'polling_token')),
            'expires_at' => self::first($data, array('expires_at')), 'created_at' => gmdate('c'), 'flow' => 'network_connection', 'settings_draft' => $draft,
        ));
        $saved = NXS_PostSocial_State::account_pending();
        if (empty($saved['code_verifier']) || !hash_equals($pkce['verifier'], $saved['code_verifier']) || empty($saved['poll_token'])) {
            NXS_PostSocial_State::clear_account_pending();
            wp_send_json_error(array('code' => 'secure_storage_unavailable', 'message' => __('SNAP could not securely store the temporary authorization data.', 'social-networks-auto-poster-facebook-twitter-g')), 500);
        }
        wp_send_json_success(array('authorization_url' => $authorization_url, 'status' => 'pending', 'message' => sprintf(__('Waiting for %s authorization…', 'social-networks-auto-poster-facebook-twitter-g'), self::network_label($network))));
    }

    private static function ajax_managed_account_start($network, $existing, $state, $draft = array())
    {
        if (!self::network_can_authorize($network) || empty($state['project_id'])) {
            wp_send_json_error(array('code' => 'network_authorization_unavailable', 'message' => __('This network cannot currently be connected through Post.Social.', 'social-networks-auto-poster-facebook-twitter-g')), 400);
        }
        $api = new NXS_PostSocial_Api();
        $previous = NXS_PostSocial_State::account_pending();
        if (!empty($previous) && !self::pending_expired($previous)
            && !empty($previous['flow']) && $previous['flow'] === 'managed_connection'
            && empty($previous['completed_at']) && !empty($previous['attempt_id'])
            && !empty($previous['connection_url']) && self::safe_public_url($previous['connection_url']) !== ''
            && !empty($previous['network']) && $previous['network'] === $network
            && (string) ($previous['existing_account_id'] ?? '') === (string) $existing) {
            wp_send_json_success(array('authorization_url' => $previous['connection_url'], 'attempt_id' => $previous['attempt_id'], 'status' => 'pending', 'message' => sprintf(__('Waiting for %s authorization…', 'social-networks-auto-poster-facebook-twitter-g'), self::network_label($network))));
        }
        if (!empty($previous)) NXS_PostSocial_State::clear_account_pending();
        $csrf_state = self::random_url_token(32);
        if ($csrf_state === '') wp_send_json_error(array('code' => 'secure_request_failed', 'message' => __('SNAP could not create a secure authorization request.', 'social-networks-auto-poster-facebook-twitter-g')), 500);
        $return_url = add_query_arg(array('nxs_ps_oauth_return' => '1', 'state' => $csrf_state), admin_url('admin.php?page=nxssnap'));
        $module_id = self::connection_module_id_for_network($network);
        if (!$module_id && in_array($network, array('bs', 'vk'), true)) {
            self::refresh_supported_networks_cache(true);
            $module_id = self::connection_module_id_for_network($network);
        }
        if (in_array($network, array('bs', 'vk'), true) && !$module_id) {
            wp_send_json_error(array('code' => 'network_authorization_unavailable', 'message' => __('Post.Social does not currently advertise an OAuth connection for this network.', 'social-networks-auto-poster-facebook-twitter-g')), 400);
        }
        $result = $api->start_managed_connection($state['project_id'], self::platform_for_snap_network($network), $return_url, $module_id);
        if (empty($result['ok'])) self::send_api_error($result);
        $data = self::resource_payload($result['data']);
        $attempt_id = self::first($data, array('attempt_id'));
        $authorization_url = self::safe_public_url(self::first($data, array('connection_url')));
        if ($attempt_id === '' || $authorization_url === '') wp_send_json_error(array('code' => 'invalid_authorization_response', 'message' => __('Post.Social returned an incomplete authorization response.', 'social-networks-auto-poster-facebook-twitter-g')), 502);
        NXS_PostSocial_State::set_account_pending(array(
            'flow' => 'managed_connection', 'attempt_id' => $attempt_id, 'connection_url' => $authorization_url,
            'project_id' => $state['project_id'], 'project_name' => !empty($state['project_name']) ? $state['project_name'] : '',
            'network' => $network, 'existing_account_id' => $existing,
            'status_path' => 'managed-connections/' . rawurlencode($attempt_id),
            'state' => $csrf_state, 'expires_at' => self::first($data, array('expires_at')), 'created_at' => gmdate('c'), 'settings_draft' => $draft,
        ));
        wp_send_json_success(array('authorization_url' => $authorization_url, 'attempt_id' => $attempt_id, 'status' => 'pending', 'message' => sprintf(__('Waiting for %s authorization…', 'social-networks-auto-poster-facebook-twitter-g'), self::network_label($network))));
    }

    public static function ajax_account_poll()
    {
        self::ajax_guard();
        $pending = NXS_PostSocial_State::account_pending();
        if (empty($pending)) wp_send_json_error(array('terminal' => true, 'code' => 'authorization_not_found', 'message' => __('No active Post.Social authorization was found.', 'social-networks-auto-poster-facebook-twitter-g')), 409);
        if (!empty($pending['selection_accounts'])) {
            wp_send_json_success(array('terminal' => false, 'selection_required' => true, 'accounts' => self::public_accounts($pending['selection_accounts']), 'message' => __('Choose which connected Facebook Pages to add to SNAP.', 'social-networks-auto-poster-facebook-twitter-g')));
        }
        if (!empty($pending['completed_at'])) {
            $completed_at = strtotime($pending['completed_at']);
            if ($completed_at !== false && $completed_at + 10 * MINUTE_IN_SECONDS > time()) {
                wp_send_json_success(array('terminal' => true, 'completed' => true, 'status' => 'completed', 'message' => __('Post.Social account connected.', 'social-networks-auto-poster-facebook-twitter-g')));
            }
            NXS_PostSocial_State::clear_account_pending();
            wp_send_json_error(array('terminal' => true, 'code' => 'authorization_expired', 'message' => __('Authorization expired. Try again.', 'social-networks-auto-poster-facebook-twitter-g')), 410);
        }
        if (self::pending_expired($pending)) {
            NXS_PostSocial_State::clear_account_pending();
            NXS_PostSocial_State::update(array('account_settings_draft' => array()));
            wp_send_json_success(array('terminal' => true, 'completed' => false, 'status' => 'expired', 'message' => __('Authorization expired. Try again.', 'social-networks-auto-poster-facebook-twitter-g')));
        }
        $result = (new NXS_PostSocial_Api())->get_network_connection_status($pending);
        if (!$result['ok']) self::send_api_error($result);
        $payload = self::resource_payload($result['data']);
        if (!self::valid_network_connection_response($pending, $payload)) {
            NXS_PostSocial_State::clear_account_pending();
            NXS_PostSocial_State::update(array('account_settings_draft' => array()));
            wp_send_json_error(array('terminal' => true, 'code' => 'authorization_response_mismatch', 'message' => __('Post.Social returned a mismatched authorization response. Please try again.', 'social-networks-auto-poster-facebook-twitter-g')), 409);
        }
        $status = self::connection_status($payload);
        if (in_array($status, array('failed', 'cancelled', 'expired'), true)) {
            if ($status === 'failed' && !empty($_POST['return_error'])) $payload['return_error'] = sanitize_text_field(wp_unslash($_POST['return_error']));
            NXS_PostSocial_State::clear_account_pending();
            NXS_PostSocial_State::update(array('account_settings_draft' => array()));
            wp_send_json_success(array('terminal' => true, 'completed' => false, 'status' => $status, 'message' => self::connection_message($status, $pending, $payload)));
        }
        if ($status !== 'completed') wp_send_json_success(array('terminal' => false, 'completed' => false, 'status' => $status, 'message' => self::connection_message($status, $pending, $payload)));
        $completion = NXS_PostSocial_Integration::complete_network_connection($pending, $payload);
        if (empty($completion['ok'])) self::send_api_error($completion);
        $accounts = self::normalize_collection($payload, array('accounts', 'social_accounts'));
        if (empty($accounts) && !empty($payload['account']) && is_array($payload['account'])) $accounts = array($payload['account']);
        $current_state = NXS_PostSocial_State::all();
        if (empty($accounts) && !empty($current_state['accounts']) && is_array($current_state['accounts'])) {
            $platform = self::platform_for_snap_network($pending['network']);
            $accounts = array_values(array_filter($current_state['accounts'], function ($account) use ($platform) { return self::network_code($account) === $platform; }));
        }
        if (!empty($pending['existing_account_id'])) {
            $existing_id = (string) $pending['existing_account_id'];
            $accounts = array_values(array_filter($accounts, function ($account) use ($existing_id) { return self::first($account, array('id', 'account_id', 'social_account_id')) === $existing_id; }));
        }
        $project_data = !empty($payload['project']) && is_array($payload['project']) ? $payload['project'] : array();
        $project = array(
            'id' => self::first($project_data, array('id', 'project_id')) ?: (!empty($current_state['project_id']) ? $current_state['project_id'] : $pending['project_id']),
            'name' => self::first($project_data, array('name', 'project_name')) ?: (!empty($current_state['project_name']) ? $current_state['project_name'] : $pending['project_name']),
        );
        if ($pending['network'] === 'fb' && empty($pending['existing_account_id'])) {
            $choices = self::facebook_page_choices($accounts);
            if (count($choices) > 1) {
                NXS_PostSocial_State::set_account_pending(array(
                    'flow' => !empty($pending['flow']) ? $pending['flow'] : '',
                    'state' => !empty($pending['state']) ? $pending['state'] : '',
                    'attempt_id' => !empty($pending['attempt_id']) ? $pending['attempt_id'] : '',
                    'network' => 'fb', 'project' => $project,
                    'settings_draft' => !empty($pending['settings_draft']) ? $pending['settings_draft'] : array(),
                    'selection_accounts' => $choices, 'completed_at' => gmdate('c'),
                ));
                wp_send_json_success(array('terminal' => false, 'selection_required' => true, 'accounts' => self::public_accounts($choices), 'message' => __('Choose which connected Facebook Pages to add to SNAP.', 'social-networks-auto-poster-facebook-twitter-g')));
            }
        }
        $summary = self::import_remote_accounts($project, $accounts);
        if (empty($pending['existing_account_id']) && !empty($pending['settings_draft'])) self::apply_settings_draft($pending['network'], $accounts, $pending['settings_draft']);
        NXS_PostSocial_State::update(array('account_settings_draft' => array()));
        if (!empty($project['id'])) self::set_preferred_project($project);
        $connected_count = max(count($accounts), $summary['imported'] + $summary['updated'], 1);
        // A browser can repeat the return request after the first poll succeeds.
        // Retain only a short-lived completion receipt, never the OAuth secrets.
        NXS_PostSocial_State::set_account_pending(array(
            'flow' => !empty($pending['flow']) ? $pending['flow'] : '',
            'state' => !empty($pending['state']) ? $pending['state'] : '',
            'attempt_id' => !empty($pending['attempt_id']) ? $pending['attempt_id'] : '',
            'completed_at' => gmdate('c'),
        ));
        wp_send_json_success(array('terminal' => true, 'completed' => true, 'status' => 'completed', 'imported' => $summary['imported'], 'updated' => $summary['updated'], 'message' => sprintf(_n('%d account connected.', '%d accounts connected.', $connected_count, 'social-networks-auto-poster-facebook-twitter-g'), $connected_count)));
    }

    public static function ajax_account_select()
    {
        self::ajax_guard();
        $pending = NXS_PostSocial_State::account_pending();
        $completed_at = !empty($pending['completed_at']) ? strtotime($pending['completed_at']) : false;
        if (empty($pending['selection_accounts']) || $completed_at === false || $completed_at + DAY_IN_SECONDS <= time()) {
            wp_send_json_error(array('message' => __('The Page selection has expired. Open the Post.Social account list to import connected Pages.', 'social-networks-auto-poster-facebook-twitter-g')), 410);
        }
        $skip = isset($_POST['skip']) && wp_unslash($_POST['skip']) === '1';
        $ids = isset($_POST['account_ids']) ? array_unique(array_filter(array_map('sanitize_text_field', explode(',', wp_unslash($_POST['account_ids']))))) : array();
        $offered = array();
        foreach ($pending['selection_accounts'] as $account) $offered[self::first($account, array('id'))] = $account;
        if (!$skip && (!$ids || count(array_intersect($ids, array_keys($offered))) !== count($ids))) {
            wp_send_json_error(array('message' => __('Select one or more of the available Facebook Pages.', 'social-networks-auto-poster-facebook-twitter-g')), 400);
        }
        $selected = $skip ? array() : array_values(array_intersect_key($offered, array_flip($ids)));
        $summary = $selected ? self::import_remote_accounts($pending['project'], $selected) : array('imported' => 0, 'updated' => 0);
        if ($selected && !empty($pending['settings_draft'])) self::apply_settings_draft('fb', $selected, $pending['settings_draft']);
        NXS_PostSocial_State::update(array('account_settings_draft' => array()));
        if (!empty($pending['project']['id'])) self::set_preferred_project($pending['project']);
        NXS_PostSocial_State::set_account_pending(array(
            'flow' => !empty($pending['flow']) ? $pending['flow'] : '',
            'state' => !empty($pending['state']) ? $pending['state'] : '',
            'attempt_id' => !empty($pending['attempt_id']) ? $pending['attempt_id'] : '',
            'completed_at' => gmdate('c'),
        ));
        $message = $skip ? __('No Pages were added to SNAP. You can import them later from Post.Social.', 'social-networks-auto-poster-facebook-twitter-g') : sprintf(_n('%d Page added to SNAP.', '%d Pages added to SNAP.', count($selected), 'social-networks-auto-poster-facebook-twitter-g'), count($selected));
        wp_send_json_success(array('completed' => true, 'imported' => $summary['imported'], 'updated' => $summary['updated'], 'message' => $message));
    }

    private static function facebook_page_choices($accounts)
    {
        $choices = array();
        foreach ((array) $accounts as $account) {
            if (!is_array($account) || self::snap_network_for_platform(self::network_code($account)) !== 'fb') continue;
            $id = self::first($account, array('id', 'account_id', 'social_account_id'));
            if ($id === '') continue;
            $choice = array('id' => $id, 'platform' => 'facebook');
            foreach (array('display_name', 'name', 'username', 'status', 'status_message', 'connection_mode', 'remote_account_id', 'provider_account_id', 'platform_account_id', 'external_id') as $key) {
                if (isset($account[$key]) && is_scalar($account[$key])) $choice[$key] = sanitize_text_field($account[$key]);
            }
            foreach (array('account_url', 'page_url', 'public_url', 'profile_link', 'permalink', 'url', 'profile_url', 'avatar_url', 'photo_url', 'picture_url', 'profile_image_url', 'image_url') as $key) {
                if (isset($account[$key]) && is_scalar($account[$key])) $choice[$key] = esc_url_raw($account[$key]);
            }
            if (isset($account['can_publish'])) $choice['can_publish'] = (bool) $account['can_publish'];
            if (!empty($account['availability']) && is_array($account['availability'])) {
                $choice['availability'] = array(
                    'can_publish' => !empty($account['availability']['can_publish']),
                    'message' => !empty($account['availability']['message']) ? sanitize_text_field($account['availability']['message']) : '',
                );
            }
            $choices[$id] = $choice;
        }
        return array_values($choices);
    }

    private static function settings_draft_from_request($network, $existing, $resume = false)
    {
        if ($existing !== '') return array();
        $raw = isset($_POST['settings_draft']) && is_string($_POST['settings_draft']) ? wp_unslash($_POST['settings_draft']) : '';
        $input = strlen($raw) <= 10000 ? json_decode($raw, true) : null;
        $saved = NXS_PostSocial_State::all();
        $saved = isset($saved['account_settings_draft']) ? $saved['account_settings_draft'] : array();
        if (($resume || !is_array($input)) && is_array($saved) && isset($saved['network'], $saved['user_id'], $saved['created_at'], $saved['settings']) && $saved['network'] === $network && (int) $saved['user_id'] === get_current_user_id() && time() - (int) $saved['created_at'] < 900 && (empty($input['nickname']))) $input = $saved['settings'];
        if (!is_array($input)) return array();
        $draft = array();
        if (isset($input['nickname']) && is_scalar($input['nickname'])) $draft['nickname'] = sanitize_text_field($input['nickname']);
        $allowed = array('msgFormat', 'msgTFormat', 'msgATFormat', 'msgAFormat', 'postType', 'attchImg', 'cImgURL', 'defImg', 'imgUpl', 'btnType', 'ebtnType', 'eTtlFormat');
        $draft['settings'] = array();
        foreach ((array) ($input['settings'] ?? array()) as $field => $value) {
            if (!in_array($field, $allowed, true) || !is_scalar($value)) continue;
            $draft['settings'][$field] = $field === 'msgFormat' ? sanitize_textarea_field((string) $value) : sanitize_text_field((string) $value);
        }
        return $draft;
    }

    private static function apply_settings_draft($network, $remote_accounts, $draft)
    {
        $ids = array();
        foreach ((array) $remote_accounts as $remote) {
            if (self::snap_network_for_platform(self::network_code($remote)) !== $network) continue;
            $id = self::first($remote, array('id', 'account_id', 'social_account_id'));
            if ($id !== '') $ids[$id] = true;
        }
        if (!$ids) return;
        $networks = self::snap_networks();
        if (empty($networks[$network])) return;
        foreach ($networks[$network] as $index => $account) {
            if (!self::is_account($account) || empty($account['postsocial_account_id']) || !isset($ids[$account['postsocial_account_id']])) continue;
            if (count($ids) === 1 && !empty($draft['nickname'])) $account['nName'] = $draft['nickname'];
            foreach ((array) ($draft['settings'] ?? array()) as $field => $value) {
                if ($field === 'defImg') $value = esc_url_raw($value);
                if ($field === 'attchImg') $value = $value ? 1 : 0;
                $account[$field] = $value;
            }
            if (($network === 'fb' || $network === 'li') && isset($account['postType'])) $account['attchImg'] = $account['postType'] === 'I' ? 1 : 0;
            if ($network === 'ig' || $network === 'pn') $account['attchImg'] = 1;
            $networks[$network][$index] = $account;
        }
        self::save_snap_networks($networks);
    }

    public static function ajax_account_cancel()
    {
        self::ajax_guard();
        $pending = NXS_PostSocial_State::account_pending();
        if (!empty($pending) && (empty($pending['flow']) || $pending['flow'] !== 'managed_connection')) (new NXS_PostSocial_Api())->cancel_network_connection($pending);
        NXS_PostSocial_State::clear_account_pending();
        NXS_PostSocial_State::update(array('account_settings_draft' => array()));
        wp_send_json_success(array('terminal' => true, 'cancelled' => true));
    }

    public static function import_remote_accounts($project, $accounts)
    {
        $networks = self::snap_networks();
        $project_id = self::first($project, array('id', 'project_id'));
        $project_name = self::first($project, array('name', 'project_name', 'title')) ?: $project_id;
        $summary = array('imported' => 0, 'updated' => 0, 'skipped' => 0);
        $new_accounts = array();
        foreach ((array) $accounts as $remote) {
            $remote_id = self::first($remote, array('id', 'account_id', 'social_account_id'));
            $platform = self::network_code($remote);
            $network = self::snap_network_for_platform($platform);
            if ($remote_id === '' || $network === '' || !self::snap_supports_network($network)) { $summary['skipped']++; continue; }
            $metadata = self::account_metadata($remote, $project_id, $project_name);
            $found = false;
            foreach ($networks as $lcode => $items) foreach ((array) $items as $index => $existing) {
                if (self::is_account($existing) && !empty($existing['postsocial_account_id']) && hash_equals((string) $existing['postsocial_account_id'], $remote_id)) {
                    $networks[$lcode][$index] = self::merge_remote_metadata($existing, $metadata);
                    $found = true; $summary['updated']++; break 2;
                }
            }
            if ($found) continue;
            if (!isset($networks[$network]) || !is_array($networks[$network])) $networks[$network] = array();
            $index = empty($networks[$network]) ? 0 : 1 + max(array_map('intval', array_keys($networks[$network])));
            $networks[$network][$index] = $metadata;
            $summary['imported']++;
            $new_accounts[] = $remote;
        }
        self::save_snap_networks($networks);
        $state = NXS_PostSocial_State::all();
        $saved = isset($state['account_settings_draft']) ? $state['account_settings_draft'] : array();
        if (!empty($new_accounts) && is_array($saved) && in_array(($saved['network'] ?? ''), array('bs', 'vk'), true) && (int) ($saved['user_id'] ?? 0) === get_current_user_id() && time() - (int) ($saved['created_at'] ?? 0) < DAY_IN_SECONDS && !empty($saved['settings'])) {
            self::apply_settings_draft($saved['network'], $new_accounts, $saved['settings']);
            NXS_PostSocial_State::update(array('account_settings_draft' => array()));
        }
        return $summary;
    }

    public static function remove_other_project_accounts($project_id)
    {
        $networks = self::snap_networks(); $removed = 0;
        foreach ($networks as $network => $items) foreach ((array) $items as $index => $account) {
            if (self::is_account($account) && !empty($account['postsocial_project_id']) && !hash_equals((string) $account['postsocial_project_id'], (string) $project_id)) {
                unset($networks[$network][$index]); $removed++;
            }
        }
        self::save_snap_networks($networks);
        return $removed;
    }

    public static function remove_local_account($remote_id)
    {
        $networks = self::snap_networks(); $removed = 0;
        foreach ($networks as $network => $items) foreach ((array) $items as $index => $account) {
            if (self::is_account($account) && !empty($account['postsocial_account_id']) && hash_equals((string) $account['postsocial_account_id'], (string) $remote_id)) {
                unset($networks[$network][$index]); $removed++;
            }
        }
        if ($removed) self::save_snap_networks($networks);
        return $removed;
    }

    public static function mark_account_unavailable($remote_id, $message)
    {
        $networks = self::snap_networks(); $changed = false;
        foreach ($networks as $network => $items) foreach ((array) $items as $index => $account) {
            if (!self::is_account($account) || empty($account['postsocial_account_id']) || !hash_equals((string) $account['postsocial_account_id'], (string) $remote_id)) continue;
            $networks[$network][$index]['postsocial_can_publish'] = 0;
            $networks[$network][$index]['postsocial_status'] = 'reauthorization_required';
            $networks[$network][$index]['postsocial_status_message'] = sanitize_text_field($message);
            $changed = true;
        }
        if ($changed) self::save_snap_networks($networks);
        return $changed;
    }

    public static function sync_cached_accounts($remote_accounts, $project_id, $project_name)
    {
        $by_id = array(); foreach ((array) $remote_accounts as $remote) { $id = self::first($remote, array('id', 'account_id', 'social_account_id')); if ($id !== '') $by_id[$id] = $remote; }
        $networks = self::snap_networks(); $changed = false;
        foreach ($networks as $network => $items) foreach ((array) $items as $index => $account) {
            if (!self::is_account($account) || empty($account['postsocial_account_id']) || empty($account['postsocial_project_id']) || !hash_equals((string) $account['postsocial_project_id'], (string) $project_id)) continue;
            if (isset($by_id[$account['postsocial_account_id']])) $networks[$network][$index] = self::merge_remote_metadata($account, self::account_metadata($by_id[$account['postsocial_account_id']], $project_id, $project_name));
            else { $networks[$network][$index]['postsocial_can_publish'] = 0; $networks[$network][$index]['postsocial_status'] = 'inaccessible'; $networks[$network][$index]['postsocial_status_message'] = __('Account is no longer accessible through the current Post.Social connection.', 'social-networks-auto-poster-facebook-twitter-g'); }
            $changed = true;
        }
        if ($changed) self::save_snap_networks($networks);
    }

    private static function account_metadata($remote, $project_id, $project_name)
    {
        $display = self::first($remote, array('display_name', 'name', 'username', 'id'));
        $platform = self::network_code($remote);
        $network = self::snap_network_for_platform($platform);
        $availability = !empty($remote['availability']) && is_array($remote['availability']) ? $remote['availability'] : array();
        $can_publish = isset($availability['can_publish']) ? (bool) $availability['can_publish'] : (!empty($remote['can_publish']) || self::first($remote, array('status')) === 'active');
        $message = self::first($availability, array('message')) ?: self::first($remote, array('status_message'));
        if (!$can_publish && $message === '') $message = __('Authorization required', 'social-networks-auto-poster-facebook-twitter-g');
        $profile_url = self::first($remote, array('account_url', 'page_url', 'public_url', 'profile_link', 'permalink', 'url', 'profile_url'));
        $avatar_url = self::first($remote, array('avatar_url', 'photo_url', 'picture_url', 'profile_image_url', 'image_url'));
        if (self::is_profile_image_url($profile_url)) {
            if ($avatar_url === '') $avatar_url = $profile_url;
            $profile_url = '';
        }
        $metadata = array(
            'nxs_backend' => self::BACKEND, 'connection_type' => self::BACKEND, 'do' => 1,
            'nName' => $display, 'postsocial_project_id' => sanitize_text_field($project_id), 'postsocial_project_name' => sanitize_text_field($project_name),
            'postsocial_account_id' => self::first($remote, array('id', 'account_id', 'social_account_id')), 'postsocial_platform' => $platform,
            'postsocial_display_name' => $display, 'postsocial_username' => self::first($remote, array('username', 'handle', 'remote_username')),
            'postsocial_remote_account_id' => self::first($remote, array('remote_account_id', 'provider_account_id', 'platform_account_id', 'external_id')), 'postsocial_status' => self::first($remote, array('status')) ?: ($can_publish ? 'active' : 'unavailable'),
            'postsocial_can_publish' => $can_publish ? 1 : 0, 'postsocial_status_message' => $message,
            'postsocial_connection_mode' => sanitize_key(self::first($remote, array('connection_mode', 'mode'))),
            'postsocial_profile_url' => self::safe_profile_url($profile_url), 'postsocial_avatar_url' => self::safe_profile_url($avatar_url),
            'postsocial_manage_url' => self::safe_public_url(self::first($remote, array('manage_url', 'reauthorize_url'))),
            'v' => defined('NXS_SETV') ? NXS_SETV : 5,
        );
        return array_merge($metadata, self::default_publishing_options($network));
    }

    private static function merge_remote_metadata($existing, $metadata)
    {
        $merged = array_merge($existing, $metadata);
        foreach (array('nName', 'msgFormat', 'msgTFormat', 'msgATFormat', 'postType', 'attchImg', 'cImgURL', 'useSURL', 'fav', 'fltrsOn', 'fltrs', 'wpImgSize', 'nDays', 'nHrs', 'nMin') as $field) {
            if ($field === 'msgFormat' && isset($existing[$field], $metadata[$field]) && $existing[$field] === self::LEGACY_GENERIC_FORMAT && $metadata[$field] !== self::LEGACY_GENERIC_FORMAT) continue;
            if (array_key_exists($field, $existing)) $merged[$field] = $existing[$field];
        }
        return $merged;
    }

    private static function public_accounts($remote_accounts)
    {
        $imported = array(); foreach (self::snap_networks() as $items) foreach ((array) $items as $account) if (self::is_account($account) && !empty($account['postsocial_account_id'])) $imported[(string) $account['postsocial_account_id']] = true;
        $out = array();
        foreach ((array) $remote_accounts as $remote) {
            // Post.Social retains disconnected history in this API response;
            // its Social Accounts page shows only current connections.
            if (self::first($remote, array('status')) === 'disconnected') continue;
            $id = self::first($remote, array('id', 'account_id', 'social_account_id')); if ($id === '') continue;
            $platform = self::network_code($remote); $network = self::snap_network_for_platform($platform); $availability = !empty($remote['availability']) && is_array($remote['availability']) ? $remote['availability'] : array();
            $can_publish = isset($availability['can_publish']) ? (bool) $availability['can_publish'] : (self::first($remote, array('status')) === 'active');
            $out[] = array('id' => $id, 'network' => $network, 'network_name' => self::network_label($network ?: $platform), 'display_name' => self::first($remote, array('display_name', 'name', 'username')) ?: $id, 'username' => self::first($remote, array('username', 'handle')), 'status' => self::first($remote, array('status')), 'status_label' => self::first($availability, array('message')) ?: __('Needs attention', 'social-networks-auto-poster-facebook-twitter-g'), 'can_publish' => $can_publish, 'compatible' => $network !== '' && self::snap_supports_network($network), 'imported' => isset($imported[$id]));
        }
        return $out;
    }

    private static function project_conflicts($project_id)
    {
        $state = NXS_PostSocial_State::all();
        if (!empty($state['project_id']) && hash_equals((string) $state['project_id'], (string) $project_id)) return array('count' => 0, 'projects' => array(), 'message' => '');
        $names = array(); $count = 0;
        foreach (self::snap_networks() as $items) foreach ((array) $items as $account) if (self::is_account($account) && !empty($account['postsocial_project_id']) && !hash_equals((string) $account['postsocial_project_id'], (string) $project_id)) { $count++; if (!empty($account['postsocial_project_name'])) $names[$account['postsocial_project_name']] = true; }
        return array('count' => $count, 'projects' => array_keys($names), 'message' => $count ? sprintf(_n('You currently have %d SNAP account imported from %s.', 'You currently have %d SNAP accounts imported from %s.', $count, 'social-networks-auto-poster-facebook-twitter-g'), $count, esc_html(implode(', ', array_keys($names)))) : '');
    }

    private static function handle_project_switch($project_id, $mode)
    {
        $conflicts = self::project_conflicts($project_id);
        if (!$conflicts['count']) return;
        if (!in_array($mode, array('remove', 'keep'), true)) wp_send_json_error(array('code' => 'project_switch_choice_required', 'message' => __('Choose whether to remove or keep accounts from the previous Project.', 'social-networks-auto-poster-facebook-twitter-g')), 409);
        if ($mode === 'remove') self::remove_other_project_accounts($project_id);
    }

    private static function verified_project($project_id)
    {
        $result = (new NXS_PostSocial_Api())->get_projects(); if (!$result['ok']) self::send_api_error($result);
        foreach (self::normalize_collection($result['data'], array('projects')) as $project) if (hash_equals(self::first($project, array('id', 'project_id')), (string) $project_id)) return $project;
        wp_send_json_error(array('code' => 'invalid_project', 'message' => __('The selected Post.Social Project is not available.', 'social-networks-auto-poster-facebook-twitter-g')), 400);
    }

    private static function verified_supported_network($network)
    {
        $result = (new NXS_PostSocial_Api())->get_supported_networks(); if (!$result['ok']) self::send_api_error($result);
        $items = self::normalize_collection($result['data'], array('networks', 'supported_networks'));
        foreach ($items as $item) if (self::snap_network_for_platform(self::network_code($item)) === $network && self::authorization_supported($item)) return $item;
        wp_send_json_error(array('code' => 'network_authorization_unavailable', 'message' => __('Post.Social does not currently advertise authorization support for this network.', 'social-networks-auto-poster-facebook-twitter-g')), 400);
    }

    private static function verify_bootstrap_network($network, $capabilities)
    {
        $network = sanitize_key($network);
        $connectable = !empty($capabilities['connectable_networks']) && is_array($capabilities['connectable_networks']) ? $capabilities['connectable_networks'] : array();
        $platform = self::platform_for_snap_network($network);
        foreach ($connectable as $item) {
            $code = is_array($item) ? self::network_code($item) : sanitize_key((string) $item);
            if ($code === $platform) return;
        }
        wp_send_json_error(array('code' => 'network_authorization_unavailable', 'message' => __('This network cannot currently be connected automatically.', 'social-networks-auto-poster-facebook-twitter-g')), 400);
    }

    private static function valid_network_connection_response($pending, $payload)
    {
        $attempt_id = self::first($payload, array('attempt_id', 'id'));
        if ($attempt_id !== '' && (empty($pending['attempt_id']) || !hash_equals((string) $pending['attempt_id'], $attempt_id))) return false;
        $network = self::first($payload, array('network', 'platform'));
        if ($network !== '' && self::snap_network_for_platform($network) !== $pending['network']) return false;
        $state = self::first($payload, array('state'));
        if ($state !== '' && (empty($pending['state']) || !hash_equals((string) $pending['state'], $state))) return false;
        return true;
    }

    private static function random_url_token($bytes)
    {
        try {
            return rtrim(strtr(base64_encode(random_bytes((int) $bytes)), '+/', '-_'), '=');
        } catch (Exception $exception) {
            return '';
        }
    }

    private static function cached_supported_networks()
    {
        $state = NXS_PostSocial_State::all();
        return !empty($state['supported_networks']) && is_array($state['supported_networks']) ? $state['supported_networks'] : array();
    }

    private static function connection_module_id_for_network($network)
    {
        $ids = array();
        foreach (self::cached_supported_networks() as $item) {
            if (self::snap_network_for_platform(self::network_code($item)) !== $network || !self::authorization_supported($item)) continue;
            $id = isset($item['connection_module_id']) ? absint($item['connection_module_id']) : 0;
            if ($id > 0) $ids[$id] = true;
        }
        return count($ids) === 1 ? (int) array_key_first($ids) : 0;
    }

    private static function find_local_account($remote_id, $network)
    {
        $networks = self::snap_networks();
        if (empty($networks[$network]) || !is_array($networks[$network])) return array();
        foreach ($networks[$network] as $account) if (self::is_account($account) && !empty($account['postsocial_account_id']) && hash_equals((string) $account['postsocial_account_id'], (string) $remote_id)) return $account;
        return array();
    }

    private static function authorization_supported($item)
    {
        if (!is_array($item)) return false;
        foreach (array('authorization_supported', 'can_connect', 'connectable', 'managed_connection', 'connect') as $field) if (isset($item[$field])) return (bool) $item[$field];
        if (!empty($item['authorization']) && is_array($item['authorization'])) return !isset($item['authorization']['supported']) || !empty($item['authorization']['supported']);
        foreach (array('start_connection', 'authorize_url', 'authorization_url') as $field) if (!empty($item[$field])) return true;
        return false;
    }

    private static function normalize_collection($data, $fields)
    {
        if (!is_array($data)) return array();
        foreach ((array) $fields as $field) if (isset($data[$field]) && is_array($data[$field])) return array_values($data[$field]);
        if (isset($data['data']) && is_array($data['data'])) return array_values($data['data']);
        return array_values(array_filter($data, 'is_array'));
    }

    private static function resource_payload($data)
    {
        if (!is_array($data)) return array();
        if (isset($data['data']) && is_array($data['data'])) {
            $keys = array_keys($data['data']);
            $is_list = empty($keys) || $keys === range(0, count($keys) - 1);
            if (!$is_list) return $data['data'];
        }
        return $data;
    }

    private static function network_code($item)
    {
        return sanitize_key(self::first($item, array('platform', 'network', 'network_code', 'code', 'slug')));
    }

    public static function snap_network_for_platform($platform)
    {
        $map = array('bluesky' => 'bs', 'bs' => 'bs', 'facebook' => 'fb', 'fb' => 'fb', 'instagram' => 'ig', 'ig' => 'ig', 'threads' => 'th', 'th' => 'th', 'linkedin' => 'li', 'linkedin_company' => 'li', 'li' => 'li', 'x' => 'tw', 'twitter' => 'tw', 'tw' => 'tw', 'pinterest' => 'pn', 'pn' => 'pn', 'google_business_profile' => 'gmb', 'google_business' => 'gmb', 'gmb' => 'gmb', 'telegram' => 'tg', 'tg' => 'tg', 'vk' => 'vk', 'vkontakte' => 'vk', 'wordpress' => 'wp', 'wordpress_website' => 'wp', 'wp' => 'wp', 'reddit' => 'rd', 'rd' => 'rd', 'tumblr' => 'tr', 'tr' => 'tr', 'ok' => 'ok', 'odnoklassniki' => 'ok', 'medium' => 'md');
        $platform = sanitize_key($platform); return isset($map[$platform]) ? $map[$platform] : '';
    }

    private static function platform_for_snap_network($network)
    {
        $map = array('bs' => 'bluesky', 'fb' => 'facebook', 'ig' => 'instagram', 'th' => 'threads', 'li' => 'linkedin', 'tw' => 'x', 'pn' => 'pinterest', 'gmb' => 'google_business_profile', 'tg' => 'telegram', 'vk' => 'vk', 'wp' => 'wordpress', 'rd' => 'reddit', 'tr' => 'tumblr', 'ok' => 'ok', 'md' => 'medium');
        return isset($map[$network]) ? $map[$network] : $network;
    }

    private static function network_label($network)
    {
        global $nxs_snapAvNts; foreach ((array) $nxs_snapAvNts as $item) if (!empty($item['lcode']) && $item['lcode'] === $network) return $item['name'];
        return ucfirst(str_replace('_', ' ', $network));
    }

    private static function snap_supports_network($network)
    {
        global $nxs_snapAvNts; foreach ((array) $nxs_snapAvNts as $item) if (!empty($item['lcode']) && $item['lcode'] === $network) return true; return false;
    }

    private static function snap_networks()
    {
        global $nxs_SNAP; return isset($nxs_SNAP) && is_array($nxs_SNAP->nxs_accts) ? $nxs_SNAP->nxs_accts : array();
    }

    private static function save_snap_networks($networks)
    {
        global $nxs_SNAP; if (isset($nxs_SNAP)) $nxs_SNAP->saveNetworksOptions($networks);
    }

    private static function set_preferred_project($project)
    {
        NXS_PostSocial_State::update(array('project_id' => self::first($project, array('id', 'project_id')), 'project_name' => self::first($project, array('name', 'project_name', 'title'))));
    }

    private static function posted_project_id()
    {
        $id = isset($_POST['project_id']) ? sanitize_text_field(wp_unslash($_POST['project_id'])) : ''; if ($id === '') wp_send_json_error(array('code' => 'project_required', 'message' => __('Select a Post.Social Project.', 'social-networks-auto-poster-facebook-twitter-g')), 400); return $id;
    }

    private static function ajax_guard()
    {
        check_ajax_referer(self::NONCE); if (!current_user_can('manage_options')) wp_send_json_error(array('code' => 'not_authorized', 'message' => __('You are not allowed to manage Post.Social accounts.', 'social-networks-auto-poster-facebook-twitter-g')), 403);
    }

    private static function send_api_error($result)
    {
        $error = !empty($result['error']) && is_array($result['error']) ? $result['error'] : array(); $status = !empty($result['status']) ? (int) $result['status'] : 500;
        if ($status === 401) { NXS_PostSocial_State::update(array('last_error' => $error)); $error['message'] = __('Your Post.Social connection has expired. Reconnect Post.Social to continue.', 'social-networks-auto-poster-facebook-twitter-g'); }
        wp_send_json_error(array('code' => !empty($error['code']) ? sanitize_key($error['code']) : 'postsocial_api_error', 'message' => !empty($error['message']) ? sanitize_text_field($error['message']) : __('Post.Social could not complete the request.', 'social-networks-auto-poster-facebook-twitter-g')), max(400, $status));
    }

    private static function connection_status($data)
    {
        $status = sanitize_key(self::first($data, array('status')) ?: 'pending'); $map = array('created' => 'pending', 'waiting' => 'pending', 'opening' => 'pending', 'authorized' => 'completed', 'connected' => 'completed', 'complete' => 'completed', 'completed' => 'completed', 'error' => 'failed', 'denied' => 'failed', 'canceled' => 'cancelled'); return isset($map[$status]) ? $map[$status] : $status;
    }

    private static function connection_message($status, $pending, $payload = array())
    {
        $network = !empty($pending['network']) ? sanitize_key($pending['network']) : '';
        $name = self::network_label($network);
        if ($status === 'failed') {
            $detail = self::connection_error_detail($payload);
            if ($detail !== '' && (stripos($detail, 'no valid accounts found') !== false || sanitize_key(str_replace(' ', '_', $detail)) === 'no_valid_accounts_found')) {
                if ($network === 'fb') return __('Facebook sign-in succeeded, but no Facebook Pages available to this account were found. Make sure this Facebook user manages at least one Page and that you grant Post.Social access to it, then try again.', 'social-networks-auto-poster-facebook-twitter-g');
                return sprintf(__('Sign-in succeeded, but no publishable %s accounts were found. Make sure the account has a supported publishing destination and try again.', 'social-networks-auto-poster-facebook-twitter-g'), $name);
            }
            if ($detail !== '') return sprintf(__('Authorization failed: %s', 'social-networks-auto-poster-facebook-twitter-g'), $detail);
            if ($network === 'fb') return __('Facebook authorization failed and Post.Social did not provide more details. This commonly happens when the Facebook user has no Pages or did not grant access to a Page. Make sure the user manages at least one Facebook Page, grant access to it, and try again.', 'social-networks-auto-poster-facebook-twitter-g');
        }
        $messages = array('pending' => sprintf(__('Waiting for %s authorization…', 'social-networks-auto-poster-facebook-twitter-g'), $name), 'awaiting_login' => __('Continue sign-in to authorize the account…', 'social-networks-auto-poster-facebook-twitter-g'), 'awaiting_project' => __('Choose a Project to continue…', 'social-networks-auto-poster-facebook-twitter-g'), 'awaiting_provider' => sprintf(__('Authorize %s to continue…', 'social-networks-auto-poster-facebook-twitter-g'), $name), 'selecting' => __('Select the account or Page to continue…', 'social-networks-auto-poster-facebook-twitter-g'), 'finishing' => __('Finishing connection…', 'social-networks-auto-poster-facebook-twitter-g'), 'failed' => __('Authorization failed. Post.Social did not provide any additional details.', 'social-networks-auto-poster-facebook-twitter-g'), 'cancelled' => __('Authorization cancelled.', 'social-networks-auto-poster-facebook-twitter-g'), 'expired' => __('Authorization expired.', 'social-networks-auto-poster-facebook-twitter-g'));
        return isset($messages[$status]) ? $messages[$status] : $messages['pending'];
    }

    private static function connection_error_detail($payload)
    {
        if (!is_array($payload)) return '';
        $nodes = array($payload);
        foreach (array('error', 'failure', 'last_error', 'provider_error', 'result') as $key) if (!empty($payload[$key]) && is_array($payload[$key])) $nodes[] = $payload[$key];
        foreach ($nodes as $node) {
            foreach (array('error_message', 'message', 'status_message', 'detail', 'description', 'failure_reason', 'reason', 'return_error') as $key) {
                if (empty($node[$key]) || !is_scalar($node[$key])) continue;
                $detail = sanitize_text_field((string) $node[$key]);
                $generic = strtolower(trim($detail, " .:\t\n\r\0\x0B"));
                if ($detail !== '' && !in_array($generic, array('failed', 'error', 'authorization failed', 'unknown error'), true)) return substr($detail, 0, 300);
            }
            if (!empty($node['error']) && is_scalar($node['error'])) {
                $detail = sanitize_text_field((string) $node['error']);
                if ($detail !== '') return substr($detail, 0, 300);
            }
            $code = self::first($node, array('error_code', 'code'));
            if (in_array($code, array('no_valid_accounts', 'no_valid_accounts_found'), true)) return 'No valid accounts found';
        }
        return '';
    }

    private static function pending_expired($pending)
    {
        $expires = !empty($pending['expires_at']) ? strtotime($pending['expires_at']) : false; if ($expires !== false) return $expires <= time(); $created = !empty($pending['created_at']) ? strtotime($pending['created_at']) : false; return $created !== false && $created + 15 * MINUTE_IN_SECONDS <= time();
    }

    private static function safe_public_url($url)
    {
        $url = esc_url_raw((string) $url);
        if ($url === '' || !nxs_validate_remote_url($url, true)) return '';
        $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
        $allowed = array_filter(array(strtolower((string) wp_parse_url(NXS_POSTSOCIAL_WEB_URL, PHP_URL_HOST)), 'post.social'));
        return in_array($host, $allowed, true) ? $url : '';
    }

    private static function safe_profile_url($url)
    {
        $url = esc_url_raw((string) $url);
        return $url !== '' && nxs_validate_remote_url($url, true) ? $url : '';
    }

    private static function account_url($account)
    {
        if (!empty($account['postsocial_profile_url']) && !self::is_profile_image_url($account['postsocial_profile_url'])) {
            $profile_url = self::safe_profile_url($account['postsocial_profile_url']);
            if ($profile_url !== '') return $profile_url;
        }
        $platform = !empty($account['postsocial_platform']) ? sanitize_key($account['postsocial_platform']) : '';
        $username = !empty($account['postsocial_username']) ? ltrim(trim((string) $account['postsocial_username']), '@') : '';
        $remote_id = !empty($account['postsocial_remote_account_id']) ? trim((string) $account['postsocial_remote_account_id']) : '';
        if ($platform === 'facebook' && ($username === '' || preg_match('/\s/', $username))) {
            $avatar_url = !empty($account['postsocial_avatar_url']) ? $account['postsocial_avatar_url'] : (!empty($account['postsocial_profile_url']) ? $account['postsocial_profile_url'] : '');
            $avatar_identity = self::facebook_identity_from_avatar($avatar_url);
            if ($avatar_identity !== '') $username = $avatar_identity;
            elseif ($username !== '') $username = preg_replace('/\s+/', '', $username);
        }
        $identity = $username !== '' ? $username : $remote_id;
        if ($identity === '') return '';
        $encoded = rawurlencode($identity);
        $patterns = array(
            'bluesky' => 'https://bsky.app/profile/%s', 'x' => 'https://x.com/%s', 'twitter' => 'https://x.com/%s',
            'instagram' => 'https://www.instagram.com/%s/', 'threads' => 'https://www.threads.com/@%s', 'facebook' => 'https://www.facebook.com/%s',
            'pinterest' => 'https://www.pinterest.com/%s/', 'telegram' => 'https://t.me/%s',
            'vk' => 'https://vk.com/%s', 'reddit' => 'https://www.reddit.com/user/%s',
            'tumblr' => 'https://%s.tumblr.com/', 'medium' => 'https://medium.com/@%s',
        );
        return isset($patterns[$platform]) ? self::safe_profile_url(sprintf($patterns[$platform], $encoded)) : '';
    }

    private static function direct_account_manage_url($network, $account)
    {
        if (!empty($account['postsocial_manage_url'])) {
            $manage_url = self::safe_public_url($account['postsocial_manage_url']);
            if ($manage_url !== '') return $manage_url;
        }
        $mode = !empty($account['postsocial_connection_mode']) ? sanitize_key($account['postsocial_connection_mode']) : '';
        // Existing VK imports predate connection-mode persistence. Post.Social
        // exposes them as Direct accounts and edits their credentials in its UI.
        if ($mode !== 'direct' && sanitize_key($network) !== 'vk') return '';
        $project_id = !empty($account['postsocial_project_id']) ? sanitize_text_field($account['postsocial_project_id']) : '';
        $account_id = !empty($account['postsocial_account_id']) ? sanitize_text_field($account['postsocial_account_id']) : '';
        if ($project_id === '' || $account_id === '') return '';
        $url = rtrim(NXS_POSTSOCIAL_WEB_URL, '/') . '/projects/' . rawurlencode($project_id) . '/social-accounts/' . rawurlencode($account_id) . '/credentials';
        return self::safe_public_url($url);
    }

    private static function is_profile_image_url($url)
    {
        $path = strtolower((string) wp_parse_url((string) $url, PHP_URL_PATH));
        return $path !== '' && (preg_match('/\.(?:avif|gif|jpe?g|png|webp)(?:$|\/)/i', $path) || strpos($path, 'social-account-photos') !== false || strpos($path, 'profile_image') !== false || strpos($path, 'avatar') !== false);
    }

    private static function facebook_identity_from_avatar($url)
    {
        if (!self::is_profile_image_url($url)) return '';
        $filename = rawurldecode(basename((string) wp_parse_url((string) $url, PHP_URL_PATH)));
        if (!preg_match('/^(.+?)(?:_profile)?\.(?:avif|gif|jpe?g|png|webp)$/i', $filename, $match)) return '';
        $identity = preg_replace('/[^A-Za-z0-9._-]/', '', $match[1]);
        return is_string($identity) ? $identity : '';
    }

    private static function default_publishing_options($network)
    {
        $defaults = array('msgFormat' => self::LEGACY_GENERIC_FORMAT, 'attchImg' => 1);
        if ($network === 'fb') return array('msgFormat' => 'New post (%TITLE%) has been published on %SITENAME%', 'postType' => 'A', 'attchImg' => 0);
        if ($network === 'ig') return array('msgFormat' => "%TITLE%\n%HTAGS%", 'attchImg' => 1);
        if ($network === 'th') return array('msgFormat' => 'New post (%TITLE%) has been published on %SITENAME% - %URL%', 'attchImg' => 1);
        if ($network === 'bs') return array('msgFormat' => 'New post (%TITLE%) has been published on %SITENAME% - %URL%', 'attchImg' => 1);
        if ($network === 'li') return array('msgFormat' => 'New post (%TITLE%) has been published on %SITENAME%', 'msgATFormat' => '%TITLE%', 'postType' => 'A', 'attchImg' => 0);
        if ($network === 'pn') return array('msgFormat' => '%EXCERPT%', 'msgTFormat' => '%TITLE%', 'cImgURL' => 'R', 'attchImg' => 1);
        return $defaults;
    }

    private static function first($data, $keys)
    {
        if (!is_array($data)) return ''; foreach ((array) $keys as $key) if (isset($data[$key]) && is_scalar($data[$key]) && (string) $data[$key] !== '') return sanitize_text_field((string) $data[$key]); return '';
    }
}

NXS_PostSocial_Accounts::register();
