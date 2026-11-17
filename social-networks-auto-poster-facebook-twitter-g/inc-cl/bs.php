<?php

if (!class_exists('nxs_snapClassBS')) { class nxs_snapClassBS extends nxs_snapClassNT {
    var $ntInfo = array('code'=>'BS', 'lcode'=>'bs', 'name'=>'Bluesky', 'defNName'=>'handle', 'tstReq'=>false, 'imgAct'=>'E', 'instrURL'=>'https://bsky.app/settings/app-passwords');
    var $defO = array('nName'=>'', 'do'=>1, 'handle'=>'', 'appPassword'=>'', 'msgFormat'=>'New post (%TITLE%) has been published on %SITENAME% - %URL%', 'attchImg'=>1, 'defImg'=>'');

    function showGenNTSettings($ntOpts) { $this->nt = $ntOpts; $this->showNTGroup(); }
    function showNewNTSettings($ii) { $this->showGNewNTSettings($ii, $this->defO); }
    function checkIfSetupFinished($options) { return !empty($options['handle']) && !empty($options['appPassword']); }
    function makeUName($options, $ii) { return !empty($options['handle']) ? '@' . ltrim($options['handle'], '@') : 'Bluesky #' . $ii; }

    function showConn($ii, $options, $isNew=false) {
        $options = array_merge($this->defO, (array) $options); ?>
        <p><label><strong><?php esc_html_e('Bluesky handle', 'social-networks-auto-poster-facebook-twitter-g'); ?></strong><br>
            <input type="text" name="bs[<?php echo esc_attr($ii); ?>][handle]" value="<?php echo esc_attr($options['handle']); ?>" placeholder="name.bsky.social" style="width:60%;max-width:400px" autocomplete="username"></label></p>
        <p><label><strong><?php esc_html_e('App Password', 'social-networks-auto-poster-facebook-twitter-g'); ?></strong><br>
            <input type="password" name="bs[<?php echo esc_attr($ii); ?>][appPassword]" value="" placeholder="<?php echo esc_attr(!empty($options['appPassword']) ? __('Leave blank to keep the saved App Password', 'social-networks-auto-poster-facebook-twitter-g') : 'xxxx-xxxx-xxxx-xxxx'); ?>" style="width:60%;max-width:400px" autocomplete="new-password"></label><br>
            <span class="description"><?php esc_html_e('Create a dedicated App Password in your Bluesky account settings. Your main password is not needed.', 'social-networks-auto-poster-facebook-twitter-g'); ?></span></p>
    <?php }

    function showPostSettings($ii, $options, $isNew=false) {
        $options = array_merge($this->defO, (array) $options);
        $this->elemMsgFormat($ii, __('Bluesky Post Format', 'social-networks-auto-poster-facebook-twitter-g'), 'msgFormat', $options['msgFormat']); ?>
        <input type="hidden" name="bs[<?php echo esc_attr($ii); ?>][attchImg]" value="0">
        <p><label><input type="checkbox" name="bs[<?php echo esc_attr($ii); ?>][attchImg]" value="1" <?php checked(!empty($options['attchImg'])); ?>> <?php esc_html_e('Attach the featured image', 'social-networks-auto-poster-facebook-twitter-g'); ?></label></p>
    <?php }

    function advTab($ii, $options) { $this->showProxies('bs', $ii, $options); }

    function setNTSettings($post, $options) {
        foreach ($post as $ii => $posted) {
            if (!isset($options[$ii])) $options[$ii] = array();
            $previous = $options[$ii];
            $options[$ii] = $this->saveCommonNTSettings($posted, $previous);
            $options[$ii]['handle'] = isset($posted['handle']) ? strtolower(ltrim(trim(sanitize_text_field($posted['handle'])), '@')) : (isset($previous['handle']) ? $previous['handle'] : '');
            if (!empty($posted['appPassword'])) $options[$ii]['appPassword'] = trim($posted['appPassword']);
            elseif (!empty($previous['appPassword'])) $options[$ii]['appPassword'] = $previous['appPassword'];
            $options[$ii]['attchImg'] = !empty($posted['attchImg']) ? 1 : 0;
        }
        return $options;
    }

    function showEdPostNTSettings($ntOpts, $post) {
        foreach ($ntOpts as $ii => $ntOpt) {
            if (!$this->checkIfSetupFinished($ntOpt)) continue;
            $pMeta = maybe_unserialize(get_post_meta($post->ID, 'snapBS', true));
            if (is_array($pMeta) && !empty($pMeta[$ii])) $ntOpt = $this->adjMetaOpt($ntOpt, $pMeta[$ii]);
            $ntOpt['ii'] = $ii;
            $this->nxs_tmpltAddPostMeta($post, $ntOpt, $pMeta);
            $this->showEdPostNTSettingsV4($ntOpt, $post);
            $this->nxs_tmpltAddPostMetaEnd($ii);
        }
    }

    function showEdPostNTSettingsV4($ntOpt, $post) {
        $ntOpt = array_merge($this->defO, $ntOpt); $ii = $ntOpt['ii'];
        $this->elemEdMsgFormat($ii, __('Bluesky Post Format:', 'social-networks-auto-poster-facebook-twitter-g'), htmlentities($ntOpt['msgFormat'], ENT_COMPAT, 'UTF-8'));
        nxs_showImgToUseDlg('bs', $ii, !empty($ntOpt['imgToUse']) ? $ntOpt['imgToUse'] : '');
        nxs_showURLToUseDlg('bs', $ii, !empty($ntOpt['urlToUse']) ? $ntOpt['urlToUse'] : '');
    }
}}

if (!function_exists('nxs_doPublishToBS')) { function nxs_doPublishToBS($postID, $options) {
    if (!is_array($options)) $options = maybe_unserialize(get_post_meta($postID, $options, true));
    $network = new nxs_snapClassBS(); $network->nt[$options['ii']] = $options;
    return $network->publishWP($options['ii'], $postID);
}}
