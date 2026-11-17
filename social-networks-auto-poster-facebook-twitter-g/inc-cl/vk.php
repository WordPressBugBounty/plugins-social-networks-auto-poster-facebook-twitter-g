<?php    
//## NextScripts vKontakte(VK) Connection Class
$nxs_snapAvNts[] = array('code'=>'VK', 'lcode'=>'vk', 'name'=>'VK.Com', 'type'=>'Social Networks', 'ptype'=>'F', 'status'=>'A', 'desc'=>'Post text, image or share a link to your profile or group page');

if (!class_exists("nxs_snapClassVK")) { class nxs_snapClassVK extends nxs_snapClassNT { 
  var $ntInfo = array('code'=>'VK', 'lcode'=>'vk', 'name'=>'VK.Com', 'defNName'=>'uName', 'tstReq' => false, 'instrURL'=>'https://www.nextscripts.com/setup-installation-vkontakte-social-networks-auto-poster-wordpress/');    
  
  function toLatestVer($ntOpts){ if( !empty($ntOpts['v'])) $v = $ntOpts['v']; else $v = 340; $ntOptsOut = '';  switch ($v) {
      case 340: $ntOptsOut = $this->toLatestVerNTGen($ntOpts); $ntOptsOut['do'] = $ntOpts['do'.$this->ntInfo['code']]; $ntOptsOut['nName'] = $ntOpts['nName'];  
        $ntOptsOut['url'] = $ntOpts['url']; $ntOptsOut['pgID'] = $ntOpts['vkPgID']; $ntOptsOut['pgIntID'] = $ntOpts['pgIntID']; $ntOptsOut['postType'] = $ntOpts['postType']; 
        
        $ntOptsOut['appID'] = $ntOpts['vkAppID'];  $ntOptsOut['appAuthToken'] = $ntOpts['vkAppAuthToken'];  $ntOptsOut['appAuthUser'] = $ntOpts['vkAppAuthUser']; $ntOptsOut['authResp'] = $ntOpts['apVKAuthResp']; 
        $ntOptsOut['msgFormat'] = $ntOpts['msgFrmt']; 
      break;
    }
    return !empty($ntOptsOut)?$ntOptsOut:$ntOpts; 
  } 
  
  //#### Show Common Settings
  function showGenNTSettings($ntOpts){ $this->nt = $ntOpts; $this->showNTGroup(); return; }  
  //#### Show NEW Settings Page
  function showNewNTSettings($ii){ $defO = array('nName'=>'', 'do'=>'1', 'uName'=>'', 'url'=>'', 'pgID'=>'', 'pgIntID'=>'', 'postType'=>'A', 'appID'=>'', 'appAuthToken'=>'','appAuthUser'=>'','authResp'=>'', 'msgFormat'=>"%EXCERPT%"); 
    $this->showGNewNTSettings($ii, $defO); 
  }
  //#### Show Unit  Settings  
  function checkIfSetupFinished($options) { return !empty($options['appAuthUser']) && !empty($options['appAuthToken']); }
  function doAuth() {
    if (!nxs_snap_user_can_access()) return;
    $page = nxs_get_admin_url('admin.php?page=nxssnap');
    if (isset($_GET['nxs_vk_start'])) {
      $ii = absint($_GET['nxs_vk_start']);
      if (!isset($this->nt[$ii]) || empty($this->nt[$ii]['appID'])) wp_die(esc_html__('Save the VK Application ID before connecting.', 'social-networks-auto-poster-facebook-twitter-g'));
      $state = nxs_oauth_state_create('vk', $ii);
      $verifier = nxs_b64url_encode(random_bytes(48));
      set_transient('nxs_vk_pkce_'.hash('sha256', $state), $verifier, 600);
      $url = 'https://id.vk.ru/authorize?'.http_build_query(array(
        'response_type'=>'code', 'client_id'=>$this->nt[$ii]['appID'], 'redirect_uri'=>$page,
        'state'=>$state, 'code_challenge'=>nxs_b64url_encode(hash('sha256', $verifier, true)),
        'code_challenge_method'=>'S256', 'scope'=>'vkid.personal_info groups wall photos video', 'prompt'=>'consent',
      ), '', '&', PHP_QUERY_RFC3986);
      self::vkRedirect($url, true);
    }
    $state = isset($_GET['state']) ? wp_unslash($_GET['state']) : '';
    if (!is_string($state) || strpos($state, 'nxs-vk-') !== 0) return;
    $pkce_key = 'nxs_vk_pkce_'.hash('sha256', $state);
    $verifier = get_transient($pkce_key);
    delete_transient($pkce_key);
    if (!nxs_oauth_state_validate($state, 'vk', $ii) || !isset($this->nt[$ii]) || !is_string($verifier) || $verifier === '') wp_die(esc_html__('Invalid or expired VK authorization state.', 'social-networks-auto-poster-facebook-twitter-g'), '', array('response'=>403));
    if (!empty($_GET['error'])) wp_die(esc_html(sanitize_text_field(wp_unslash($_GET['error']))));
    $code = isset($_GET['code']) ? sanitize_text_field(wp_unslash($_GET['code'])) : '';
    $device = isset($_GET['device_id']) ? sanitize_text_field(wp_unslash($_GET['device_id'])) : '';
    if ($code === '' || $device === '') wp_die(esc_html__('VK did not return an authorization code and device ID.', 'social-networks-auto-poster-facebook-twitter-g'));
    $token = self::vkTokenRequest(array('grant_type'=>'authorization_code', 'code'=>$code, 'code_verifier'=>$verifier, 'device_id'=>$device, 'state'=>$state, 'client_id'=>$this->nt[$ii]['appID'], 'redirect_uri'=>$page));
    if (empty($token['access_token']) || empty($token['refresh_token'])) wp_die(esc_html__('VK authorization did not return a renewable publishing token.', 'social-networks-auto-poster-facebook-twitter-g'));
    $profile_response = wp_remote_post('https://api.vk.com/method/users.get', array('timeout'=>30, 'redirection'=>0, 'body'=>array('access_token'=>$token['access_token'], 'fields'=>'screen_name', 'v'=>'5.199')));
    $profile_data = is_wp_error($profile_response) ? null : json_decode(wp_remote_retrieve_body($profile_response), true);
    $profile = is_array($profile_data) && !empty($profile_data['response'][0]) ? $profile_data['response'][0] : null;
    if (empty($profile['id']) || (!empty($token['user_id']) && (string)$profile['id'] !== (string)$token['user_id'])) wp_die(esc_html__('VK profile could not be verified. Please reconnect.', 'social-networks-auto-poster-facebook-twitter-g'));
    $account = $this->nt[$ii];
    $account['appAuthToken'] = $token['access_token'];
    $account['appAuthUser'] = (string)$profile['id'];
    $account['vkRefreshToken'] = $token['refresh_token'];
    $account['vkDeviceID'] = $device;
    $account['vkTokenExpires'] = time() + max(0, (int)($token['expires_in'] ?? 0));
    $account['vkAuthFlow'] = 'vkid';
    $account['authResp'] = '';
    $resolved = $this->resolveVKID($account['pgID'] ?? '', $account['appAuthToken'], $account['url'] ?? '');
    if ($resolved !== '') $account['pgIntID'] = $resolved;
    elseif (empty($account['pgIntID'])) $account['pgIntID'] = $account['appAuthUser'];
    nxs_save_glbNtwrks('vk', $ii, $account, '*');
    self::vkRedirect($page, false);
  }
  static function vkRedirect($url, $external) {
    if (!headers_sent()) {
      if ($external) wp_redirect($url); else wp_safe_redirect($url);
      exit;
    }
    echo '<script>window.location.replace('.wp_json_encode($url).');</script>';
    echo '<p><a href="'.esc_url($url).'">'.esc_html__('Continue VK authorization', 'social-networks-auto-poster-facebook-twitter-g').'</a></p>';
    exit;
  }
  static function vkTokenRequest($params) {
    $response = wp_remote_post('https://id.vk.ru/oauth2/auth', array('timeout'=>30, 'redirection'=>0, 'body'=>$params));
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) return array();
    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (!is_array($data) || !empty($data['error']) || empty($data['access_token'])) return array();
    if (isset($data['state']) && (!isset($params['state']) || !hash_equals($params['state'], $data['state']))) return array();
    return $data;
  }
  static function refreshVKToken(&$options) {
    if (($options['vkAuthFlow'] ?? '') !== 'vkid') return true;
    if (!empty($options['appAuthToken']) && (int)($options['vkTokenExpires'] ?? 0) > time()+60) return true;
    if (empty($options['vkRefreshToken']) || empty($options['vkDeviceID']) || empty($options['appID'])) return false;
    $token = self::vkTokenRequest(array('grant_type'=>'refresh_token', 'refresh_token'=>$options['vkRefreshToken'], 'device_id'=>$options['vkDeviceID'], 'state'=>nxs_b64url_encode(random_bytes(32)), 'client_id'=>$options['appID'], 'redirect_uri'=>nxs_get_admin_url('admin.php?page=nxssnap')));
    if (empty($token['access_token'])) return false;
    if (!empty($token['user_id']) && !empty($options['appAuthUser']) && (string)$token['user_id'] !== (string)$options['appAuthUser']) return false;
    $options['appAuthToken'] = $token['access_token'];
    if (!empty($token['refresh_token'])) $options['vkRefreshToken'] = $token['refresh_token'];
    $options['vkTokenExpires'] = time() + max(0, (int)($token['expires_in'] ?? 0));
    if (isset($options['ii'])) nxs_save_glbNtwrks('vk', $options['ii'], array('appAuthToken'=>$options['appAuthToken'], 'vkRefreshToken'=>$options['vkRefreshToken'], 'vkTokenExpires'=>$options['vkTokenExpires']), '*');
    return true;
  }
  function accTab($ii, $options, $isNew=false){ $ntInfo = $this->ntInfo; $nt = $ntInfo['lcode']; ?>
     <div style="width:100%;"><strong><?php echo $ntInfo['name']?>&nbsp;<?php _e('URL', 'social-networks-auto-poster-facebook-twitter-g'); ?>:</strong> </div>
     <div style="font-size: 11px; margin: 0px;"><?php _e('Could be your VK.Com Profile, Public or Group Page', 'social-networks-auto-poster-facebook-twitter-g'); ?></div>
      <input name="vk[<?php echo esc_attr($ii); ?>][url]" id="apurl" style="width: 50%;" value="<?php _e(apply_filters('format_to_edit', htmlentities($options['url'], ENT_COMPAT, "UTF-8")), 'social-networks-auto-poster-facebook-twitter-g') ?>" />                
      <br/><div style="font-size: 11px; margin-top: 4px; color: #555;"><?php _e('Resolved Wall Target ID:', 'social-networks-auto-poster-facebook-twitter-g'); ?> <strong><?php echo !empty($options['pgIntID']) ? esc_html($options['pgIntID']) : __('Not Resolved (Will post to Personal Wall)', 'social-networks-auto-poster-facebook-twitter-g'); ?></strong></div>
      <br/>
      <div style="width:100%;"><strong><?php echo $ntInfo['name']?>&nbsp;<?php _e('Application ID', 'social-networks-auto-poster-facebook-twitter-g'); ?>:</strong> <a href="https://vk.com/apps?act=manage" target="_blank"><?php _e('[VK Apps]', 'social-networks-auto-poster-facebook-twitter-g'); ?></a> </div> 
     <input name="vk[<?php echo esc_attr($ii); ?>][appID]" id="vk_appID_<?php echo esc_attr($ii); ?>" style="width: 30%;" value="<?php _e(apply_filters('format_to_edit', htmlentities($options['appID'], ENT_COMPAT, "UTF-8")), 'social-networks-auto-poster-facebook-twitter-g') ?>" oninput="nxs_vk_app_id_changed('<?php echo esc_attr($ii); ?>');" />  
     <br/><br/>

     <div id="vk_authorize_wrap_<?php echo esc_attr($ii); ?>" style="<?php echo ($options['appID']!='') ? '' : 'display: none;'; ?>">
         <?php if (!empty($options['appAuthUser'])) { ?>
             <p><?php esc_html_e('Your VK account is authorized. User ID:', 'social-networks-auto-poster-facebook-twitter-g'); ?> <?php echo esc_html($options['appAuthUser']); ?></p>
         <?php } ?>
         <?php if (!$isNew && !empty($options['appID'])) { ?>
             <a class="button button-primary" href="<?php echo esc_url(add_query_arg('nxs_vk_start', $ii, nxs_get_admin_url('admin.php?page=nxssnap'))); ?>"><?php echo !empty($options['appAuthUser']) ? esc_html__('Reconnect VK', 'social-networks-auto-poster-facebook-twitter-g') : esc_html__('Connect VK', 'social-networks-auto-poster-facebook-twitter-g'); ?></a>
         <?php } else { ?>
             <p><?php esc_html_e('Save the account and Application ID, then connect VK.', 'social-networks-auto-poster-facebook-twitter-g'); ?></p>
         <?php } ?>
         <p class="description"><?php esc_html_e('Add this redirect URL to your VK ID application:', 'social-networks-auto-poster-facebook-twitter-g'); ?> <code><?php echo esc_html(nxs_get_admin_url('admin.php?page=nxssnap')); ?></code></p>
     </div>
     <div id="vk_notice_wrap_<?php echo esc_attr($ii); ?>" style="<?php echo ($options['appID']=='') ? '' : 'display: none;'; ?>">
         <?php esc_html_e('Enter your VK ID Application ID above, then save the account.', 'social-networks-auto-poster-facebook-twitter-g'); ?>
     </div>
     <script type="text/javascript">
     function nxs_vk_app_id_changed(ii) {
         var appID = document.getElementById('vk_appID_' + ii).value.trim();
         document.getElementById('vk_authorize_wrap_' + ii).style.display = appID ? 'block' : 'none';
         document.getElementById('vk_notice_wrap_' + ii).style.display = appID ? 'none' : 'block';
     }
     </script>
     <?php $this->elemMsgFormat($ii,'Message Text Format','msgFormat',$options['msgFormat']); ?>
    
     <div style="width:100%;"><strong id="altFormatText">Post Type:</strong> &lt;-- (<a id="showShAtt" onmouseout="hidePopShAtt('<?php echo esc_attr($ii); ?>VKX');" onmouseover="showPopShAtt('<?php echo esc_attr($ii); ?>VKX', event);" onclick="return false;" class="underdash" href="https://www.nextscripts.com/blog/"><?php _e('What\'s the difference?', 'social-networks-auto-poster-facebook-twitter-g'); ?></a>) </div>                      
<div style="margin-left: 10px;">
        
        <input type="radio" name="vk[<?php echo esc_attr($ii); ?>][postType]" value="T" <?php if ($options['postType'] == 'T') echo 'checked="checked"'; ?> /> <?php _e('Text Post', 'social-networks-auto-poster-facebook-twitter-g'); ?> - <i><?php _e('just text message', 'social-networks-auto-poster-facebook-twitter-g'); ?></i><br/>                    
        <input type="radio" name="vk[<?php echo esc_attr($ii); ?>][postType]" value="I" <?php if ($options['postType'] == 'I') echo 'checked="checked"'; ?> /> <?php _e('Image Post', 'social-networks-auto-poster-facebook-twitter-g'); ?> - <i><?php _e('big image with text message', 'social-networks-auto-poster-facebook-twitter-g'); ?></i><br/>
        <input type="radio" name="vk[<?php echo esc_attr($ii); ?>][postType]" value="A" <?php if ( empty($options['postType']) || $options['postType'] == 'A') echo 'checked="checked"'; ?> /> <span><?php _e('Text Post with "attached" link', 'social-networks-auto-poster-facebook-twitter-g'); ?></span><br/>

   </div>        
   <div class="popShAtt" style="z-index: 9999" id="popShAtt<?php echo esc_attr($ii); ?>VKX"><h3><?php echo $ntInfo['name']?>&nbsp;<?php _e('Post Types', 'social-networks-auto-poster-facebook-twitter-g'); ?></h3><img src="<?php echo NXS_PLURL; ?>img/vkPostTypesDiff6.png" width="600" height="257"/></div>
   <?php
  }
  function advTab($ii, $options){ $this->askForSURL( $this->ntInfo['lcode'], $ii, $options); }
  function resolveVKID($screenName, $token, $url = '') {
       // 1. Direct match for club/group/public/event/id followed by digits (with optional hyphen)
       if (preg_match('/^(club|public|event|group|club_id)-?(\d+)$/i', $screenName, $matches)) {
           return '-' . $matches[2];
       }
       if (preg_match('/^id-?(\d+)$/i', $screenName, $matches)) {
           return $matches[1];
       }
      
      // 2. Try VK API resolveScreenName if token is available
      if (!empty($token)) {
          $apiUrl = 'https://api.vk.com/method/utils.resolveScreenName?v=5.131&screen_name=' . urlencode($screenName) . '&access_token=' . urlencode($token);
          $response = nxs_remote_get($apiUrl);
          if (!is_nxs_error($response) && !empty($response['body'])) {
              $res = json_decode($response['body'], true);
              if (!empty($res['response']['object_id'])) {
                  $objID = $res['response']['object_id'];
                  $type = $res['response']['type'];
                  if ($type === 'user') {
                      return $objID;
                  } else {
                      return '-' . $objID;
                  }
              }
          }
      }
      
      // 3. Fallback: Parse page contents
      if (!empty($url)) {
          $hdrsArr = nxs_getNXSHeaders($url);
          $advSet = nxs_mkRemOptsArr($hdrsArr);
          $response = nxs_remote_get($url, $advSet);
          if (!is_nxs_error($response) && !empty($response['body'])) {
              $contents = function_exists('mb_convert_encoding') ? mb_convert_encoding($response['body'], 'ISO-8859-1', 'UTF-8') : $response['body'];
              if (stripos($contents, '<meta property="og:url" content="https://vk.com/') !== false) {
                  $k = CutFromTo($contents, '<meta property="og:url" content="https://vk.com/', '"');
                  if (stripos($k, 'club') !== false) return '-' . str_ireplace('club', '', $k);
                  if (stripos($k, 'public') !== false) return '-' . str_ireplace('public', '', $k);
                  if (stripos($k, 'id') !== false) return str_ireplace('id', '', $k);
              }
              if (stripos($contents, '{"group_id":') !== false) return '-' . CutFromTo($contents, '"group_id":', ',');
              if (stripos($contents, '{"public_id":') !== false) return '-' . CutFromTo($contents, '"public_id":', ',');
              if (stripos($contents, '"user_id":') !== false) return CutFromTo($contents, '"user_id":', ',');
          }
      }
      
      return '';
  }

  //#### Set Unit Settings from POST
  function setNTSettings($post, $options){ 
    foreach ($post as $ii => $pval){       
      if (!empty($pval['appID'])){ if (!isset($options[$ii])) $options[$ii] = array(); $options[$ii] = $this->saveCommonNTSettings($pval,$options[$ii]); 
        if (isset($pval['url'])) $options[$ii]['url'] = trim($pval['url']); 
        if (isset($pval['appID'])) $options[$ii]['appID'] = trim($pval['appID']);         
        //## Get VK Info        
        if (isset($pval['url']))  {  $options[$ii]['url'] = trim($pval['url']);   if ( substr($options[$ii]['url'], 0, 4)!=='http' )  $options[$ii]['url'] = 'https://'.$options[$ii]['url'];
          $vkPgID = $options[$ii]['url']; if (substr($vkPgID, -1)=='/') $vkPgID = substr($vkPgID, 0, -1);  $vkPgID = substr(strrchr($vkPgID, "/"), 1); 
          if (strpos($vkPgID, '?')!==false) $vkPgID = substr($vkPgID, 0, strpos($vkPgID, '?')); $options[$ii]['pgID'] = $vkPgID; 
          if (strpos($options[$ii]['url'], '?')!==false) $options[$ii]['url'] = substr($options[$ii]['url'], 0, strpos($options[$ii]['url'], '?'));
          
          // Resolve ID dynamically from URL changes
          $token = !empty($options[$ii]['appAuthToken']) ? $options[$ii]['appAuthToken'] : '';
          $resolvedID = $this->resolveVKID($vkPgID, $token, $options[$ii]['url']);
          if (!empty($resolvedID)) {
              $options[$ii]['pgIntID'] = $resolvedID;
          }
        }          
        $options[$ii]['authResp'] = '';
      } elseif ( count($pval)==1 ) if (isset($pval['do'])) $options[$ii]['do'] = $pval['do']; else $options[$ii]['do'] = 0; 
    } return $options;
  }
    
  //#### Show Post->Edit Meta Box Settings
  function showEdPostNTSettings($ntOpts, $post){ $post_id = $post->ID; $nt = $this->ntInfo['lcode']; $ntU = $this->ntInfo['code'];
      foreach($ntOpts as $ii=>$ntOpt)  { $isFin = $this->checkIfSetupFinished($ntOpt); if (!$isFin) continue; 
        $pMeta = maybe_unserialize(get_post_meta($post_id, 'snap'.$ntU, true)); if (is_array($pMeta) && !empty($pMeta[$ii])) $ntOpt = $this->adjMetaOpt($ntOpt, $pMeta[$ii]);         
        
        if (empty($ntOpt['imgToUse'])) $ntOpt['imgToUse'] = ''; if (empty($ntOpt['urlToUse'])) $ntOpt['urlToUse'] = ''; $postType = isset($ntOpt['postType'])?$ntOpt['postType']:'';
        $msgFormat = !empty($ntOpt['msgFormat'])?htmlentities($ntOpt['msgFormat'], ENT_COMPAT, "UTF-8"):''; $msgTFormat = !empty($ntOpt['msgTFormat'])?htmlentities($ntOpt['msgTFormat'], ENT_COMPAT, "UTF-8"):''; 
        $imgToUse = $ntOpt['imgToUse'];  $urlToUse = $ntOpt['urlToUse'];  $ntOpt['ii']=$ii;
         
        $this->nxs_tmpltAddPostMeta($post, $ntOpt, $pMeta); ?>
        <?php  $this->elemEdMsgFormat($ii, __('Message Format:', 'social-networks-auto-poster-facebook-twitter-g'),$msgFormat); ?>
       <tr style="<?php echo !empty($ntOpt['do'])?'display:table-row;':'display:none;'; ?>" class="nxstbldo nxstbldo<?php echo strtoupper($nt).$ii; ?>"><th scope="row" style="text-align:right; width:150px; vertical-align:top; padding-top: 0px; padding-right:10px;"> <?php _e('Post Type:', 'social-networks-auto-poster-facebook-twitter-g') ?> <br/>
          (<a id="showShAtt" style="font-weight: normal" onmouseout="hidePopShAtt('<?php echo esc_attr($ii); ?>VKX');" onmouseover="showPopShAtt('<?php echo esc_attr($ii); ?>VKX', event);" onclick="return false;" class="underdash" href="#"><?php _e('What\'s the difference?', 'social-networks-auto-poster-facebook-twitter-g'); ?></a>)</th><td>     
          <input class="nxs_postEditCtrl"  type="radio" name="vk[<?php echo esc_attr($ii); ?>][postType]" value="T" <?php if ($postType == 'T') echo 'checked="checked"'; ?> /> <?php _e('Text Post', 'social-networks-auto-poster-facebook-twitter-g') ?> - <i><?php _e('just text message', 'social-networks-auto-poster-facebook-twitter-g') ?></i><br/>       
          <input class="nxs_postEditCtrl"  type="radio" name="vk[<?php echo esc_attr($ii); ?>][postType]" value="I" <?php if ($postType == 'I') echo 'checked="checked"'; ?> /> <?php _e('Image Post', 'social-networks-auto-poster-facebook-twitter-g') ?> - <i><?php _e('big image with text message', 'social-networks-auto-poster-facebook-twitter-g') ?></i><br/>
          <input class="nxs_postEditCtrl"  type="radio" name="vk[<?php echo esc_attr($ii); ?>][postType]" value="A" <?php if ( !isset($postType) || $postType == '' || $postType == 'A') echo 'checked="checked"'; ?> /> <?php _e('Text Post with "attached" blogpost', 'social-networks-auto-poster-facebook-twitter-g') ?>
          <br/><div class="popShAtt" id="popShAtt<?php echo esc_attr($ii); ?>VKX"><h3>vKontakte(VK) <?php _e('Post Types', 'social-networks-auto-poster-facebook-twitter-g') ?></h3><img src="<?php echo NXS_PLURL; ?>img/vkPostTypesDiff6.png" width="600" height="257" alt="<?php _e('Post Types', 'social-networks-auto-poster-facebook-twitter-g') ?>"/></div>
        </td></tr>
        <?php /* ## Select Image & URL ## */ nxs_showURLToUseDlg($nt, $ii, $urlToUse); $this->nxs_tmpltAddPostMetaEnd($ii);        
     }
  }  
  
  function showEdPostNTSettingsV4($ntOpt, $post){ $post_id = $post->ID; $nt = $this->ntInfo['lcode']; $ntU = $this->ntInfo['code']; $ii = $ntOpt['ii']; //prr($ntOpt['postType']);
                                                   
        if (empty($ntOpt['imgToUse'])) $ntOpt['imgToUse'] = ''; if (empty($ntOpt['urlToUse'])) $ntOpt['urlToUse'] = ''; $postType = isset($ntOpt['postType'])?$ntOpt['postType']:'';
        $msgFormat = !empty($ntOpt['msgFormat'])?htmlentities($ntOpt['msgFormat'], ENT_COMPAT, "UTF-8"):''; $msgTFormat = !empty($ntOpt['msgTFormat'])?htmlentities($ntOpt['msgTFormat'], ENT_COMPAT, "UTF-8"):'';
        $imgToUse = $ntOpt['imgToUse'];  $urlToUse = $ntOpt['urlToUse']; $ntOpt['ii']=$ii;
        $this->elemEdMsgFormat($ii, __('Message Format:', 'social-networks-auto-poster-facebook-twitter-g'),$msgFormat);
        ?>
     <div class="nxsPostEd_ElemWrap">   <div class="nxsPostEd_ElemLabel">   
       <?php _e('Post Type:', 'social-networks-auto-poster-facebook-twitter-g'); ?>(<a id="showShAtt" style="font-weight: normal" onmouseout="hidePopShAtt('<?php echo esc_attr($ii); ?>VKX');" onmouseover="showPopShAtt('<?php echo esc_attr($ii); ?>VKX', event);" onclick="return false;" class="underdash" href="#"><?php _e('What\'s the difference?', 'social-networks-auto-poster-facebook-twitter-g'); ?></a>)
     </div>   
     <div class="nxsPostEd_Elem">   
        <input type="radio" name="vk[<?php echo esc_attr($ii); ?>][postType]" value="T" class="nxsEdElem" data-ii="<?php echo esc_attr($ii); ?>" data-nt="<?php echo esc_attr($nt); ?>" <?php if ($postType == 'T') echo 'checked="checked"'; ?> /> <?php _e('Text Post', 'social-networks-auto-poster-facebook-twitter-g') ?> - <i><?php _e('just text message', 'social-networks-auto-poster-facebook-twitter-g') ?></i><br/>       
          <input type="radio" name="vk[<?php echo esc_attr($ii); ?>][postType]" value="I" class="nxsEdElem" data-ii="<?php echo esc_attr($ii); ?>" data-nt="<?php echo esc_attr($nt); ?>" <?php if ($postType == 'I') echo 'checked="checked"'; ?> /> <?php _e('Image Post', 'social-networks-auto-poster-facebook-twitter-g') ?> - <i><?php _e('big image with text message', 'social-networks-auto-poster-facebook-twitter-g') ?></i><br/>
          <input type="radio" name="vk[<?php echo esc_attr($ii); ?>][postType]" value="A" class="nxsEdElem" data-ii="<?php echo esc_attr($ii); ?>" data-nt="<?php echo esc_attr($nt); ?>" <?php if ( !isset($postType) || $postType == '' || $postType == 'A') echo 'checked="checked"'; ?> /> <?php _e('Text Post with "attached" blogpost', 'social-networks-auto-poster-facebook-twitter-g') ?>
          
     </div>
     <div class="popShAtt" id="popShAtt<?php echo esc_attr($ii); ?>VKX"><h3>vKontakte(VK) <?php _e('Post Types', 'social-networks-auto-poster-facebook-twitter-g') ?></h3><img src="<?php echo NXS_PLURL; ?>img/vkPostTypesDiff6.png" width="600" height="257" alt="<?php _e('Post Types', 'social-networks-auto-poster-facebook-twitter-g') ?>"/></div>
   </div>  
        <?php
        nxs_showImgToUseDlg($nt, $ii, $imgToUse);            
       /* ## Select Image & URL ## */  nxs_showURLToUseDlg($nt, $ii, $urlToUse); 

  }
  
  //#### Save Meta Tags to the Post
  function adjMetaOpt($optMt, $pMeta){ $optMt = $this->adjMetaOptG($optMt, $pMeta);  //   prr($optMt);    
      
    return $optMt;
  }
  
  function adjPublishWP(&$options, &$message, $postID){ 
      
  }
}}

if (!function_exists("nxs_doPublishToVK")) { function nxs_doPublishToVK($postID, $options){ if (!is_array($options)) $options = maybe_unserialize(get_post_meta($postID, $options, true)); 
  $cl = new nxs_snapClassVK(); $cl->nt[$options['ii']] = $options; return $cl->publishWP($options['ii'], $postID); 
}}
?>
