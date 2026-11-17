<?php

if (!class_exists('nxs_snapClassTH')) { class nxs_snapClassTH extends nxs_snapClassNT {
    var $ntInfo = array('code'=>'TH', 'lcode'=>'th', 'name'=>'Threads', 'defNName'=>'uName', 'tstReq'=>false, 'imgAct'=>'E', 'instrURL'=>'https://developers.facebook.com/docs/threads/get-started/');
    var $defO = array('nName'=>'', 'do'=>1, 'uName'=>'', 'appKey'=>'', 'appSec'=>'', 'accessToken'=>'', 'accessTokenExp'=>0, 'metaUserID'=>'', 'profileURL'=>'', 'metaDirect'=>'', 'msgFormat'=>'New post (%TITLE%) has been published on %SITENAME% - %URL%', 'attchImg'=>1, 'defImg'=>'');

    function checkIfFunc(){ return true; }
    function showGenNTSettings($ntOpts){ $this->nt=$ntOpts; $this->showNTGroup(); }
    function showNewNTSettings($ii){ $this->showGNewNTSettings($ii,$this->defO); }
    function checkIfSetupFinished($options){ return !empty($options['metaDirect']) && $options['metaDirect']==='th' && !empty($options['accessToken']) && !empty($options['metaUserID']); }
    function makeUName($options,$ii){ return 'Threads #'.$ii; }

    function showConn($ii,$options,$isNew=false){
        $options=array_merge($this->defO,(array)$options);
        if (!empty($options['profileURL'])) { ?><p><strong><?php _e('Your Threads URL', 'social-networks-auto-poster-facebook-twitter-g'); ?>:</strong> <a href="<?php echo esc_url($options['profileURL']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($options['profileURL']); ?></a></p><?php }
        if (!empty($options['accessToken'])) { ?><div class="notice notice-success inline"><p><?php printf(esc_html__('Connected to Threads as @%s.', 'social-networks-auto-poster-facebook-twitter-g'), esc_html($options['uName'])); ?></p></div><?php }
        $appKey=!empty($options['appKey'])?nxs_gak($options['appKey']):''; $appSec=!empty($options['appSec'])?nxs_gas($options['appSec']):'';
        $this->elemKeySecret($ii,'Threads App ID','Threads App Secret',$appKey,$appSec,'appKey','appSec','https://developers.facebook.com/apps/'); ?>
        <p><strong><?php _e('OAuth Redirect URL', 'social-networks-auto-poster-facebook-twitter-g'); ?>:</strong><br><code><?php echo esc_html(NXS_Meta_Direct::callback_url()); ?></code><br><span class="description"><?php _e('Add this exact URL to the OAuth redirect URLs in your Threads app.', 'social-networks-auto-poster-facebook-twitter-g'); ?></span></p>
        <?php if (!$isNew): ?><button type="button" class="button button-primary" style="background:#008a20!important;border-color:#006b18!important" onclick="nxsMetaDirectConnectTH('<?php echo esc_js($ii); ?>');return false;"><?php echo !empty($options['accessToken'])?esc_html__('Reconnect Threads', 'social-networks-auto-poster-facebook-twitter-g'):esc_html__('Connect Threads', 'social-networks-auto-poster-facebook-twitter-g'); ?></button><?php endif; ?>
        <script>window.nxsMetaDirectNonces=window.nxsMetaDirectNonces||{ig:{},th:{}};window.nxsMetaDirectNonces.th[<?php echo wp_json_encode((string)$ii); ?>]=<?php echo wp_json_encode(wp_create_nonce('nxs_meta_oauth_th_'.$ii)); ?>;window.nxsMetaDirectConnectTH=window.nxsMetaDirectConnectTH||function(index){var id=document.getElementById('thappKey'+index),sec=document.getElementById('thappSec'+index),nonce=window.nxsMetaDirectNonces.th[index];if(!id||!sec||!id.value.trim()||!sec.value.trim()){alert('Enter the App ID and App Secret first.');return;}if(!nonce){alert('The authorization form expired. Reload this page and try again.');return;}var form=document.createElement('form');form.method='post';form.action=<?php echo wp_json_encode(admin_url('admin-post.php')); ?>;var values={action:'nxs_meta_oauth_start',network:'th',account_index:index,app_id:id.value,app_secret:sec.value,_wpnonce:nonce};if(window.nxsMetaDirectDraftValues)Object.assign(values,window.nxsMetaDirectDraftValues('th',index));Object.keys(values).forEach(function(k){var input=document.createElement('input');input.type='hidden';input.name=k;input.value=values[k];form.appendChild(input);});document.body.appendChild(form);form.submit();};</script>
    <?php }

    function showPostSettings($ii,$options,$isNew=false){ $options=array_merge($this->defO,(array)$options); $this->elemMsgFormat($ii,'Threads Post Format','msgFormat',$options['msgFormat']); ?>
        <p><label><input value="1" type="checkbox" name="th[<?php echo esc_attr($ii); ?>][attchImg]" <?php checked(!empty($options['attchImg'])); ?>> <?php _e('Attach Image to the Post', 'social-networks-auto-poster-facebook-twitter-g'); ?></label></p>
        <div style="width:100%;"><strong><?php _e('URL of the Default Image', 'social-networks-auto-poster-facebook-twitter-g'); ?>:</strong><div class="description"><?php _e('Optional public HTTPS image used when the post has no featured image.', 'social-networks-auto-poster-facebook-twitter-g'); ?></div></div>
        <input style="width:60%;" name="th[<?php echo esc_attr($ii); ?>][defImg]" value="<?php echo esc_attr($options['defImg']); ?>" />
    <?php }

    function advTab($ii,$options){}
    function setNTSettings($post,$options){
        foreach($post as $ii=>$pval){
            if(!isset($options[$ii]))$options[$ii]=array(); $preserve=$options[$ii]; $options[$ii]=$this->saveCommonNTSettings($pval,$options[$ii]);
            foreach(array('accessToken','accessTokenExp','metaUserID','metaDirect','profileURL','uName') as $field)if(!array_key_exists($field,$pval)&&isset($preserve[$field]))$options[$ii][$field]=$preserve[$field];
            if(isset($pval['defImg']))$options[$ii]['defImg']=trim($pval['defImg']); $options[$ii]['attchImg']=!empty($pval['attchImg'])?1:0;
        } return $options;
    }

    function showEdPostNTSettings($ntOpts,$post){ foreach($ntOpts as $ii=>$ntOpt){ if(!$this->checkIfSetupFinished($ntOpt))continue; $pMeta=maybe_unserialize(get_post_meta($post->ID,'snapTH',true)); if(is_array($pMeta)&&!empty($pMeta[$ii]))$ntOpt=$this->adjMetaOpt($ntOpt,$pMeta[$ii]); $ntOpt['ii']=$ii; $this->nxs_tmpltAddPostMeta($post,$ntOpt,$pMeta); $this->showEdPostNTSettingsV4($ntOpt,$post); $this->nxs_tmpltAddPostMetaEnd($ii); }}
    function showEdPostNTSettingsV4($ntOpt,$post){ $ntOpt=array_merge($this->defO,$ntOpt); $ii=$ntOpt['ii']; $this->elemEdMsgFormat($ii,__('Threads Post Format:', 'social-networks-auto-poster-facebook-twitter-g'),htmlentities($ntOpt['msgFormat'],ENT_COMPAT,'UTF-8')); nxs_showImgToUseDlg('th',$ii,!empty($ntOpt['imgToUse'])?$ntOpt['imgToUse']:''); nxs_showURLToUseDlg('th',$ii,!empty($ntOpt['urlToUse'])?$ntOpt['urlToUse']:''); }
    function nxsCptCheck(){}
}}

if(!function_exists('nxs_doPublishToTH')){ function nxs_doPublishToTH($postID,$options){ if(!is_array($options))$options=maybe_unserialize(get_post_meta($postID,$options,true)); $cl=new nxs_snapClassTH(); $cl->nt[$options['ii']]=$options; return $cl->publishWP($options['ii'],$postID); }}
