<?php    
//## NextScripts Instagram Connection Class (##Can't replace - it has .png)
$nxs_snapAvNts[] = array('code'=>'IG', 'lcode'=>'ig', 'name'=>'Instagram', 'type'=>'Social Networks', 'type'=>'Social Networks', 'ptype'=>'P', 'status'=>'A', 'desc'=>'Upload your blogpost\'s image to your Instagram account.');

if (!class_exists("nxs_snapClassIG")) { class nxs_snapClassIG extends nxs_snapClassNT {   
  var $ntInfo = array('code'=>'IG', 'lcode'=>'ig', 'name'=>'Instagram', 'defNName'=>'uName', 'tstReq' => false, 'imgAct'=>'E', 'instrURL'=>'https://developers.facebook.com/docs/instagram-platform/instagram-api-with-instagram-login/');
  var $defO = array('nName'=>'', 'do'=>'1', 'uName'=>'', 'appKey'=>'', 'appSec'=>'', 'accessToken'=>'', 'metaUserID'=>'', 'profileURL'=>'', 'metaDirect'=>'', 'msgTFormat'=>'', 'defImg'=>'', 'attchImg'=>1, 'msgFormat'=>"%TITLE% \n%HTAGS%");
  
  function checkIfFunc() { return true; }
  //#### Show Common Settings
  function showGenNTSettings($ntOpts){ $this->nt = $ntOpts; $this->showNTGroup(); return; }  
  //#### Show NEW Settings Page
  function showNewNTSettings($ii){  $this->showGNewNTSettings($ii, $this->defO); }
  //#### Show Unit  Settings  
  function checkIfSetupFinished($options) { return !empty($options['metaDirect']) && $options['metaDirect']==='ig' && !empty($options['accessToken']) && !empty($options['metaUserID']); }
  function showConn($ii, $options, $isNew=false){
    $options = array_merge($this->defO, (array)$options); $nt = $this->ntInfo['lcode'];
    if (!empty($options['profileURL'])) { ?><p><strong><?php _e('Your Instagram URL', 'social-networks-auto-poster-facebook-twitter-g'); ?>:</strong> <a href="<?php echo esc_url($options['profileURL']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($options['profileURL']); ?></a></p><?php }
    if (!empty($options['accessToken'])) { ?><div class="notice notice-success inline"><p><?php printf(esc_html__('Connected to Instagram as @%s.', 'social-networks-auto-poster-facebook-twitter-g'), esc_html($options['uName'])); ?></p></div><?php }
    $appKey = !empty($options['appKey']) ? nxs_gak($options['appKey']) : ''; $appSec = !empty($options['appSec']) ? nxs_gas($options['appSec']) : '';
    $this->elemKeySecret($ii, 'Instagram App ID', 'Instagram App Secret', $appKey, $appSec, 'appKey', 'appSec', 'https://developers.facebook.com/apps/'); ?>
    <p><strong><?php _e('OAuth Redirect URL', 'social-networks-auto-poster-facebook-twitter-g'); ?>:</strong><br><code><?php echo esc_html(NXS_Meta_Direct::callback_url()); ?></code><br><span class="description"><?php _e('Add this exact URL to the OAuth redirect URLs in your Meta app.', 'social-networks-auto-poster-facebook-twitter-g'); ?></span></p>
    <?php if (!$isNew): ?><button type="button" class="button button-primary" style="background:#008a20!important;border-color:#006b18!important" onclick="nxsMetaDirectConnect('ig','<?php echo esc_js($ii); ?>');return false;"><?php echo !empty($options['accessToken']) ? esc_html__('Reconnect Instagram', 'social-networks-auto-poster-facebook-twitter-g') : esc_html__('Connect Instagram', 'social-networks-auto-poster-facebook-twitter-g'); ?></button><?php endif; ?>
    <?php self::renderMetaConnectScript($ii);
  }
  function showPostSettings($ii, $options, $isNew=false){ $options = array_merge($this->defO, (array)$options); $this->elemMsgFormat($ii,'Instagram Caption Format','msgFormat',$options['msgFormat']); ?>
    <p><label><input value="1" type="checkbox" name="ig[<?php echo esc_attr($ii); ?>][attchImg]" checked disabled> <?php _e('Attach image (required by Instagram)', 'social-networks-auto-poster-facebook-twitter-g'); ?></label></p>
    <div style="width:100%;"><strong><?php _e('URL of the Default Image', 'social-networks-auto-poster-facebook-twitter-g'); ?>:</strong><div class="description"><?php _e('Used when a post has no featured image. It must be a public HTTPS image URL.', 'social-networks-auto-poster-facebook-twitter-g'); ?></div></div>
    <input style="width:60%;" name="ig[<?php echo esc_attr($ii); ?>][defImg]" value="<?php echo esc_attr($options['defImg']); ?>" />
  <?php }
  static function renderMetaConnectScript($ii){ ?>
    <script>window.nxsMetaDirectNonces=window.nxsMetaDirectNonces||{ig:{},th:{}};window.nxsMetaDirectNonces.ig[<?php echo wp_json_encode((string)$ii); ?>]=<?php echo wp_json_encode(wp_create_nonce('nxs_meta_oauth_ig_'.$ii)); ?>;window.nxsMetaDirectConnect=window.nxsMetaDirectConnect||function(network,index){var prefix=network==='ig'?'ig':'th',id=document.getElementById(prefix+'appKey'+index),sec=document.getElementById(prefix+'appSec'+index),nonce=window.nxsMetaDirectNonces[network]&&window.nxsMetaDirectNonces[network][index];if(!id||!sec||!id.value.trim()||!sec.value.trim()){alert('Enter the App ID and App Secret first.');return;}if(!nonce){alert('The authorization form expired. Reload this page and try again.');return;}var form=document.createElement('form');form.method='post';form.action=<?php echo wp_json_encode(admin_url('admin-post.php')); ?>;var values={action:'nxs_meta_oauth_start',network:network,account_index:index,app_id:id.value,app_secret:sec.value,_wpnonce:nonce};if(window.nxsMetaDirectDraftValues)Object.assign(values,window.nxsMetaDirectDraftValues(network,index));Object.keys(values).forEach(function(k){var input=document.createElement('input');input.type='hidden';input.name=k;input.value=values[k];form.appendChild(input);});document.body.appendChild(form);form.submit();};</script>
  <?php }
  //#### Set Unit Settings from POST
  function setNTSettings($post, $options){
    foreach ($post as $ii => $pval){
      if (!isset($options[$ii])) $options[$ii] = array();
      $preserve = $options[$ii]; $options[$ii] = $this->saveCommonNTSettings($pval,$options[$ii]);
      foreach (array('accessToken','accessTokenExp','metaUserID','metaDirect','profileURL','uName') as $field) if (!array_key_exists($field,$pval) && isset($preserve[$field])) $options[$ii][$field] = $preserve[$field];
      if (isset($pval['defImg'])) $options[$ii]['defImg'] = trim($pval['defImg']);
      $options[$ii]['attchImg'] = 1;
    } return $options;
  }
  //#### Show Post->Edit Meta Box  Settings
  function showEdPostNTSettings($ntOpts, $post){ $post_id = $post->ID; $nt = $this->ntInfo['lcode']; $ntU = $this->ntInfo['code'];
      foreach($ntOpts as $ii=>$ntOpt)  { $isFin = $this->checkIfSetupFinished($ntOpt); if (!$isFin) continue; 
        $pMeta = maybe_unserialize(get_post_meta($post_id, 'snap'.$ntU, true)); if (is_array($pMeta) && !empty($pMeta[$ii])) $ntOpt = $this->adjMetaOpt($ntOpt, $pMeta[$ii]);        
        
        if (empty($ntOpt['imgToUse'])) $ntOpt['imgToUse'] = ''; if (empty($ntOpt['urlToUse'])) $ntOpt['urlToUse'] = ''; $postType = isset($ntOpt['postType'])?$ntOpt['postType']:'';
        $msgFormat = !empty($ntOpt['msgFormat'])?htmlentities($ntOpt['msgFormat'], ENT_COMPAT, "UTF-8"):''; $msgTFormat = !empty($ntOpt['msgTFormat'])?htmlentities($ntOpt['msgTFormat'], ENT_COMPAT, "UTF-8"):''; 
        $imgToUse = $ntOpt['imgToUse'];  $urlToUse = $ntOpt['urlToUse']; $ntOpt['ii']=$ii;
         
        $this->nxs_tmpltAddPostMeta($post, $ntOpt, $pMeta); ?>
                
        <?php $this->elemEdMsgFormat($ii, __('Message Format:', 'social-networks-auto-poster-facebook-twitter-g'),$msgFormat); 
          /* ## Select Image & URL ## */ nxs_showImgToUseDlg($nt, $ii, $imgToUse); nxs_showURLToUseDlg($nt, $ii, $urlToUse); $this->nxs_tmpltAddPostMetaEnd($ii);        
     }
  }  
  function showEdPostNTSettingsV4($ntOpt, $post){ $post_id = $post->ID; $nt = $this->ntInfo['lcode']; $ntU = $this->ntInfo['code']; $ii = $ntOpt['ii']; $ntOpt = array_merge($this->defO, $ntOpt);
       if (empty($ntOpt['imgToUse'])) $ntOpt['imgToUse'] = ''; if (empty($ntOpt['urlToUse'])) $ntOpt['urlToUse'] = ''; $postType = isset($ntOpt['postType'])?$ntOpt['postType']:'';
       $msgFormat = !empty($ntOpt['msgFormat'])?htmlentities($ntOpt['msgFormat'], ENT_COMPAT, "UTF-8"):''; $msgTFormat = !empty($ntOpt['msgTFormat'])?htmlentities($ntOpt['msgTFormat'], ENT_COMPAT, "UTF-8"):'';
       $imgToUse = $ntOpt['imgToUse'];  $urlToUse = $ntOpt['urlToUse']; $ntOpt['ii']=$ii;        
       //## Title and Message       
       $this->elemEdMsgFormat($ii, __('Message Format:', 'social-networks-auto-poster-facebook-twitter-g'),$msgFormat); ?>
       <?php // ## Select Image & URL
       nxs_showImgToUseDlg($nt, $ii, $imgToUse);            
       nxs_showURLToUseDlg($nt, $ii, $urlToUse); 
  }
  //#### Save Meta Tags to the Post
  function adjMetaOpt($optMt, $pMeta){ $optMt = $this->adjMetaOptG($optMt, $pMeta); return $optMt; }  
  function adjPublishWP(&$options, &$message, $postID){  }   
  function adjAfterPost(&$options, &$ret){ }


}}


if (!function_exists("nxs_doPublishToIG")) { function nxs_doPublishToIG($postID, $options){ if (!is_array($options)) $options = maybe_unserialize(get_post_meta($postID, $options, true));
  ini_set('memory_limit','256M'); $cl = new nxs_snapClassIG(); $cl->nt[$options['ii']] = $options; return $cl->publishWP($options['ii'], $postID); 
}}  
?>
