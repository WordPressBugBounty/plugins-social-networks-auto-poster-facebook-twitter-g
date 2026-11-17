<?php    
//## NextScripts Facebook Connection Class
$nxs_snapAvNts[] = array('code'=>'LI', 'lcode'=>'li', 'name'=>'LinkedIn', 'type'=>'Social Networks', 'ptype'=>'B', 'status'=>'A', 'desc'=>'Publish to your profile or company page using your own app or Post.Social.');

if (!function_exists("nxs_ntp_time")) { function nxs_ntp_time($host='time.nist.gov') {
  return time();
}}

if (!class_exists("nxs_snapClassLI")) { class nxs_snapClassLI extends nxs_snapClassNT { 
  var $ntInfo = array('code'=>'LI', 'lcode'=>'li', 'name'=>'LinkedIn', 'defNName'=>'', 'tstReq' => true, 'instrURL'=>'https://www.nextscripts.com/setup-installation-linkedin-social-networks-auto-poster-wordpress/');
  var $defO = array('nName'=>'', 'do'=>'1', 'pgID'=>'', 'appKey'=>'', 'appSec'=>'', 'postType'=>'A', 'inclTags'=>1, 'msgFormat'=>"New post (%TITLE%) has been published on %SITENAME%", 'msgTFormat'=>"%TITLE%", 'msgATFormat'=>"", 'msgAFormat'=>"", 'imgSize'=>'original');
  //#### Update
  function toLatestVer($ntOpts){ if( !empty($ntOpts['v'])) $v = $ntOpts['v']; else $v = 340; $ntOptsOut = '';  switch ($v) {
      case 340: $ntOptsOut = $this->toLatestVerNTGen($ntOpts); $ntOptsOut['do'] = $ntOpts['do'.$this->ntInfo['code']]; $ntOptsOut['nName'] = $ntOpts['nName'];  
         $ntOptsOut['appKey'] = !empty($ntOpts['liAPIKey']) ? $ntOpts['liAPIKey'] : ''; $ntOptsOut['appSec'] = !empty($ntOpts['liAPISec']) ? $ntOpts['liAPISec'] : '';
         $ntOptsOut['accessToken'] = !empty($ntOpts['liAccessToken']) ? $ntOpts['liAccessToken'] : '';
         $ntOptsOut['liUserID'] = !empty($ntOpts['liUserID']) ? $ntOpts['liUserID'] : '';
         $ntOptsOut['liUserInfo'] = !empty($ntOpts['liUserInfo']) ? $ntOpts['liUserInfo'] : '';
         $ntOptsOut['imgSize'] = !empty($ntOpts['imgSize'])?$ntOpts['imgSize']:''; $ntOptsOut['msgFormat'] = $ntOpts['liMsgFormat'];  $ntOptsOut['msgTFormat'] = $ntOpts['liMsgFormatT']; $ntOptsOut['msgAFormat'] = $ntOpts['liMsgAFrmt']; 
        $ntOptsOut['postType'] = !empty($ntOpts['postType']) ? $ntOpts['postType'] : 'A';
        $ntOptsOut['pgID'] = !empty($ntOpts['pgID']) ? $ntOpts['pgID'] : 'p';
        $ntOptsOut = nxs_arrMergeCheck($ntOptsOut, $this->defO); $ntOptsOut['isUpdd'] = '1'; $ntOptsOut['v'] = NXS_SETV;
      break;
    }
    return !empty($ntOptsOut)?$ntOptsOut:$ntOpts; 
  }   
  //#### Show Common Settings
  function showGenNTSettings($ntOpts){ $this->nt = $ntOpts;  $this->showNTGroup(); }  
  //#### Show NEW Settings Page
  function showNewNTSettings($ii){$this->showGNewNTSettings($ii, $this->defO); }
  //#### Show Unit  Settings  
  function checkIfSetupFinished($options) { return !empty($options['appKey']) && !empty($options['appSec']) && !empty($options['accessToken']); }
  public function doAuth() { $ntInfo = $this->ntInfo; global $nxs_snapSetPgURL;     
    // V2 Auth Error
    if (isset($_GET['page']) && $_GET['page']=='nxssnap' && !empty($_GET['error_description']) && isset($_GET['state']) && strpos(wp_unslash($_GET['state']), 'nxs-li-') === 0){
        if (!nxs_snap_user_can_access() || !nxs_oauth_state_validate(wp_unslash($_GET['state']), 'li', $ii) || !isset($this->nt[$ii])) wp_die(esc_html__('Invalid or expired LinkedIn authorization state.', 'social-networks-auto-poster-facebook-twitter-g'), '', array('response'=>403));
        $this->showAuthTop(); $nt = $this->ntInfo['lcode']; $ntU = $this->ntInfo['code']; $isNew = false;
        $nto = $this->nt[$ii];
        echo '----=={ oAuth 2.0 LinkedIn ERROR }==----<br/><br/><div style="color:red;">'; 
        echo esc_html(urldecode(wp_unslash($_GET['error_description'])));
        
        if (stripos($_GET['error_description'],'rw_organization_admin')!==false) echo '<br/>It looks like Marketing API is not approved for your LinkedIn API V2 Application. Please use "Authorize without Marketing API".<br/>';        
        
        $nxs_snapSetPgURL = nxs_get_admin_url('admin.php?page=nxssnap'); $retry_state = nxs_oauth_state_create('li', $ii);
        
        ?>
        <a href="#" onclick="var url = 'https://www.linkedin.com/oauth/v2/authorization?response_type=code&client_id=<?php echo esc_js(rawurlencode(nxs_gak($nto['appKey']))); ?>&scope=r_liteprofile+r_emailaddress+w_member_social&state=<?php echo esc_js(rawurlencode($retry_state)); ?>&redirect_uri=<?php echo esc_js(rawurlencode($nxs_snapSetPgURL)); ?>'; nxs_svSetAdv('<?php echo esc_attr($nt); ?>', '<?php echo esc_attr($ii); ?>', '<?php echo $isNew?'dom'.$ntU.$ii.'Div':'nxsAllAccntsDiv'; ?>','nxs<?php echo $ntU; ?>MsgDiv<?php echo esc_attr($ii); ?>',url,'1'); return false;">Authorize Your LinkedIn Account (<b>without</b> Marketing API)</a>
        <?php
        die('</div></div></div>');
    }
    // V2 Auth
    if (isset($_GET['code']) && $_GET['code']!='' && isset($_GET['state']) && strpos(wp_unslash($_GET['state']), 'nxs-li-') === 0){
      if (!nxs_snap_user_can_access() || !nxs_oauth_state_validate(wp_unslash($_GET['state']), 'li', $ii) || !isset($this->nt[$ii])) wp_die(esc_html__('Invalid or expired LinkedIn authorization state.', 'social-networks-auto-poster-facebook-twitter-g'), '', array('response'=>403));
      $this->showAuthTop(); $at = sanitize_text_field(wp_unslash($_GET['code']));
      echo "----=={ oAuth 2.0 Wordflow }==----<br/><br/>"; 
      $nxs_snapSetPgURL = nxs_get_admin_url('admin.php?page=nxssnap'); $nto = $this->nt[$ii]; $wprg = array('sslverify'=>true);
      if (isset($nto['appKey'])){
        $response = nxs_remote_post('https://www.linkedin.com/oauth/v2/accessToken', array('body'=>array('grant_type'=>'authorization_code','code'=>$at,'redirect_uri'=>$nxs_snapSetPgURL,'client_id'=>nxs_gak($nto['appKey']),'client_secret'=>nxs_gas($nto['appSec']))));
        if (is_nxs_error($response) || !is_array($response) || empty($response['body'])) die(esc_html__('LinkedIn token exchange failed.', 'social-networks-auto-poster-facebook-twitter-g'));
        $resp = json_decode($response['body'], true); if (!is_array($resp) || empty($resp['access_token'])) { echo esc_html(isset($resp['error_description'])?$resp['error_description']:__('LinkedIn did not return an access token.', 'social-networks-auto-poster-facebook-twitter-g')); die('</div></div>'); }
        if (function_exists('get_option')) $currTime = time() + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ); else  $currTime = time();
        $nto['accessToken'] = $resp['access_token']; // $nto['accessTokenSec'] = 'No Need for oAuth V2'; $nto['oAuthVerifier'] = 'No Need for oAuth V2';
        $nto['accessTokenExp'] = $currTime + $resp['expires_in'];    echo "<br/>----=={ Expires: ".date('Y-m-d H:i:s', $nto['accessTokenExp'])." }==---- <br/>";
        $tknURL = 'https://api.linkedin.com/v2/me'; $hddrs=nxs_getNXSHeaders(); $hddrs['Authorization'] = 'Bearer '.$nto['accessToken']; $hddrs['X-RestLi-Protocol-Version']='2.0.0'; $response = nxs_remote_get( $tknURL, nxs_mkRemOptsArr($hddrs) ); 
        if (is_nxs_error($response)) die('</div></div>'); $user = json_decode($response['body'], true);
        if (!empty($user['id'])) { $nto['liUserID'] = $user['id'];  $nto['liUserInfo'] = trim(($user['firstName']['localized']['en_US'] ?? '').' '.($user['lastName']['localized']['en_US'] ?? '')).' ('.$user['id'].')';
          if (empty($nto['pgID'])) $nto['pgID'] = 'p'; nxs_save_glbNtwrks($ntInfo['lcode'],$ii,$nto,'*'); prr($nto['liUserInfo'], 'Authorized user');                    
          
          $pgs = $this->getPageOptions($nto);
          
          
          $opVal = array(); $opNm = 'nxs_snap_li_'.sha1('nxs_snap_li'.$nto['liUserID'].nxs_gak($nto['appKey'])); $opVal['pgList'] = $pgs; nxs_saveOption($opNm, $opVal); 
          echo '<div style="text-align:center;color:green; font-weight: bold; font-size:20px;"> ALL OK. You have been authorized.</div><script type="text/javascript">setTimeout(function(){ window.location = "'.$nxs_snapSetPgURL.'"; }, 1000);</script>';
        }        
      } die('</div></div>');
    }    
  }    
  
  function getPageOptions($options) {
    if (empty($options['accessToken'])) return '';
    $headers=array('Authorization'=>'Bearer '.$options['accessToken'],'X-Restli-Protocol-Version'=>'2.0.0','Linkedin-Version'=>'202609','Accept'=>'application/json');
    $ids=array();
    for ($start=0; $start<500; $start+=100) {
      $url='https://api.linkedin.com/rest/organizationAcls?q=roleAssignee&state=APPROVED&count=100&start='.$start;
      $response=nxs_remote_get($url,array('headers'=>$headers,'timeout'=>30));
      if (is_nxs_error($response) || wp_remote_retrieve_response_code($response)!==200) break;
      $data=json_decode(wp_remote_retrieve_body($response),true);
      if (!is_array($data) || empty($data['elements'])) break;
      foreach ($data['elements'] as $item) {
        $urn=$item['organization'] ?? ($item['organizationTarget'] ?? '');
        if (preg_match('/^urn:li:organization:([0-9]+)$/',$urn,$match)) $ids[$match[1]]=$match[1];
      }
      if (count($data['elements'])<100) break;
    }
    $result='';
    foreach ($ids as $id) {
      $name=$id;
      $response=nxs_remote_get('https://api.linkedin.com/rest/organizations/'.$id,array('headers'=>$headers,'timeout'=>30));
      if (!is_nxs_error($response) && wp_remote_retrieve_response_code($response)===200) {
        $org=json_decode(wp_remote_retrieve_body($response),true);
        if (!empty($org['localizedName'])) $name=$org['localizedName'];
      }
      $result.='<option '.selected((string)($options['pgID'] ?? ''),(string)$id,false).' value="'.esc_attr($id).'">'.esc_html($name).' ('.esc_html($id).')</option>';
    }
    return $result;
  }

  function getListOfPagesLIV2($networks){ $opVal = array(); if (empty($_POST['u'])) return $opVal; $opNm = 'nxs_snap_li_'.sha1('nxs_snap_li'.$_POST['u'].$_POST['p']); $opVal = nxs_getOption($opNm); $ii = sanitize_key($_POST['ii']); if (empty($networks['li'][$ii]['accessToken'])) return $opVal;
     $options=$networks['li'][$ii];
     $currPstAs = !empty($_POST['pgID'])?sanitize_text_field($_POST['pgID']):($options['pgID'] ?? '');
     if (empty($_POST['force']) && !empty($opVal['pgList']) ) $pgs = $opVal['pgList']; else {
         
         $pgs = $this->getPageOptions($options);
         
         
     } $pgCust = (ctype_digit((string)$currPstAs) && stripos($pgs,'value="'.$currPstAs.'"')===false)?'<option selected="selected" value="'.esc_attr($currPstAs).'">'.esc_html($currPstAs).'</option>':'';
     if (!empty($_POST['isOut'])) echo $pgCust.'<option '.($options['pgID']=='p'?'selected="selected" ':'').'value="p">'.__('Profile').'</option>'.$pgs.'<option style="color:#BD5200" value="a">'.__('...enter the Page ID').'</option>';
     $opVal['pgList'] = $pgs; nxs_saveOption($opNm, $opVal); return $opVal;
  }
  
  function accTab($ii, $options, $isNew=false){
    global $nxs_snapSetPgURL;
    $options = array_merge($this->defO, (array)$options);
    $oauth_state = nxs_oauth_state_create('li', $ii); ?>
    <h3>LinkedIn API</h3>
    <div class="subDiv" id="sub<?php echo esc_attr($ii); ?>DivL">
      <?php $this->elemKeySecret($ii, 'Client ID', 'Client Secret', $options['appKey'], $options['appSec'], 'appKey2', 'appSec2', 'https://www.linkedin.com/developer/apps'); ?>
      <?php if (!empty($options['accessToken'])) { ?><p><?php esc_html_e('Your LinkedIn account has been authorized.', 'social-networks-auto-poster-facebook-twitter-g'); ?></p><?php } ?>
      <p><a href="#" onclick="var url='https://www.linkedin.com/oauth/v2/authorization?response_type=code&client_id='+encodeURIComponent(jQuery('#liappKey2<?php echo esc_attr($ii); ?>').val())+'&scope=r_liteprofile+r_emailaddress+w_member_social+w_organization_social+rw_organization_admin&state=<?php echo esc_js(rawurlencode($oauth_state)); ?>&redirect_uri=<?php echo esc_js(rawurlencode($nxs_snapSetPgURL)); ?>';nxs_svSetAdv('li','<?php echo esc_js($ii); ?>','<?php echo $isNew ? 'domLI'.$ii.'Div' : 'nxsAllAccntsDiv'; ?>','nxsLIMsgDiv<?php echo esc_js($ii); ?>',url,'1');return false;"><?php esc_html_e('Authorize LinkedIn with Marketing API', 'social-networks-auto-poster-facebook-twitter-g'); ?></a></p>
      <p><a href="#" onclick="var url='https://www.linkedin.com/oauth/v2/authorization?response_type=code&client_id='+encodeURIComponent(jQuery('#liappKey2<?php echo esc_attr($ii); ?>').val())+'&scope=r_liteprofile+r_emailaddress+w_member_social&state=<?php echo esc_js(rawurlencode($oauth_state)); ?>&redirect_uri=<?php echo esc_js(rawurlencode($nxs_snapSetPgURL)); ?>';nxs_svSetAdv('li','<?php echo esc_js($ii); ?>','<?php echo $isNew ? 'domLI'.$ii.'Div' : 'nxsAllAccntsDiv'; ?>','nxsLIMsgDiv<?php echo esc_js($ii); ?>',url,'1');return false;"><?php esc_html_e('Authorize LinkedIn without Marketing API', 'social-networks-auto-poster-facebook-twitter-g'); ?></a></p>
    </div>
    <div id="nxsLIInfoDiv<?php echo esc_attr($ii); ?>" style="<?php echo empty($options['accessToken']) ? 'display:none;' : ''; ?>">
      <input type="hidden" id="liAuthUser<?php echo esc_attr($ii); ?>" value="<?php echo esc_attr($options['liUserID'] ?? ''); ?>" />
      <strong><?php esc_html_e('Where to Post', 'social-networks-auto-poster-facebook-twitter-g'); ?></strong>
      <select id="lipgID<?php echo esc_attr($ii); ?>" onchange="nxs_liPageChange('<?php echo esc_js($ii); ?>',jQuery(this));" name="li[<?php echo esc_attr($ii); ?>][pgID]">
        <option value="p" <?php selected($options['pgID'], 'p'); ?>><?php esc_html_e('Profile', 'social-networks-auto-poster-facebook-twitter-g'); ?></option>
        <?php echo wp_kses($options['pgList'] ?? '', array('option'=>array('value'=>true, 'selected'=>true))); ?>
        <option value="a"><?php esc_html_e('Enter Company Page ID', 'social-networks-auto-poster-facebook-twitter-g'); ?></option>
      </select>
      <input type="text" style="display:none" id="liInpCst<?php echo esc_attr($ii); ?>" value="<?php echo esc_attr($options['pgID']); ?>" onchange="nxs_InpToDDChange(jQuery(this));" data-tid="lipgID<?php echo esc_attr($ii); ?>" />
      <a href="#" onclick="nxs_liGetPages(<?php echo esc_js($ii); ?>,1);return false;"><?php esc_html_e('Refresh pages', 'social-networks-auto-poster-facebook-twitter-g'); ?></a>
      <img id="li<?php echo esc_attr($ii); ?>ldImg" style="display:none" src="<?php echo esc_url(NXS_PLURL.'img/ajax-loader-sm.gif'); ?>" alt="" />
    </div>
    <div id="nxsLIMsgDiv<?php echo esc_attr($ii); ?>"></div>
    <?php $this->elemMsgFormat($ii, 'Message Format', 'msgFormat', $options['msgFormat']); ?>
    <p><strong><?php esc_html_e('Post Type', 'social-networks-auto-poster-facebook-twitter-g'); ?></strong><br>
      <label><input type="radio" name="li[<?php echo esc_attr($ii); ?>][postType]" value="T" <?php checked($options['postType'], 'T'); ?>> <?php esc_html_e('Text Post', 'social-networks-auto-poster-facebook-twitter-g'); ?></label><br>
      <label><input type="radio" name="li[<?php echo esc_attr($ii); ?>][postType]" value="A" <?php checked($options['postType'] ?: 'A', 'A'); ?>> <?php esc_html_e('Share blog post as an attachment', 'social-networks-auto-poster-facebook-twitter-g'); ?></label>
    </p>
    <?php $this->elemTitleFormat($ii, 'Attachment Title Format', 'msgATFormat', $options['msgATFormat']);
    $this->elemMsgFormat($ii, 'Attachment Text Format', 'msgAFormat', $options['msgAFormat']);
  }
  function advTab($ii, $options){ $this->askForSURL( $this->ntInfo['lcode'], $ii, $options);  $this->showProxies($this->ntInfo['lcode'], $ii, $options); }                             
  //#### Set Unit Settings from POST
  function setNTSettings($post, $options){ $otp = array(); //prr($options);
    foreach ($options as $oo => $v){  if (isset($v['ck'])) unset($v['ck']);
        if (isset($oo) && $oo !== '') $otp[$oo] = $v;
    } $options = $otp;  //  prr($options);
    foreach ($post as $ii => $pval){ //  prr($ii, 'II');    prr($pval);
      if ((!empty($pval['appKey']) && !empty($pval['appSec'])) || (!empty($pval['appKey2']) && !empty($pval['appSec2']))){ 
        if (!isset($options[$ii])) $options[$ii] = array(); $options[$ii] = $this->saveCommonNTSettings($pval,$options[$ii]);
        //## Uniqe Items        
        if (isset($pval['appKey2'])) $options[$ii]['appKey'] = trim($pval['appKey2']);
        if (isset($pval['appSec2'])) $options[$ii]['appSec'] = trim($pval['appSec2']);
        if (isset($pval['pgID'])) $options[$ii]['pgID'] = trim($pval['pgID']);  
      } elseif ( count($pval)==1 ) if (isset($pval['do'])) $options[$ii]['do'] = $pval['do']; else $options[$ii]['do'] = 0; 
    } return $options;
  }  
    
  //#### Show Post->Edit Meta Box Settings
  
  function showEdPostNTSettings($ntOpts, $post){
    foreach ($ntOpts as $ii => $ntOpt) {
      if (!$this->checkIfSetupFinished($ntOpt)) continue;
      $pMeta = maybe_unserialize(get_post_meta($post->ID, 'snapLI', true));
      if (is_array($pMeta) && !empty($pMeta[$ii])) $ntOpt = $this->adjMetaOpt($ntOpt, $pMeta[$ii]);
      $imgToUse = !empty($ntOpt['imgToUse']) ? $ntOpt['imgToUse'] : '';
      $urlToUse = !empty($ntOpt['urlToUse']) ? $ntOpt['urlToUse'] : '';
      $ntOpt['ii'] = $ii;
      $this->nxs_tmpltAddPostMeta($post, $ntOpt, $pMeta);
      $this->elemEdMsgFormat($ii, __('Message Format:', 'social-networks-auto-poster-facebook-twitter-g'), !empty($ntOpt['msgFormat']) ? htmlentities($ntOpt['msgFormat'], ENT_COMPAT, 'UTF-8') : ''); ?>
      <tr><th><?php esc_html_e('Post Type:', 'social-networks-auto-poster-facebook-twitter-g'); ?></th><td>
        <label><input type="radio" name="li[<?php echo esc_attr($ii); ?>][postType]" value="T" <?php checked($ntOpt['postType'] ?? '', 'T'); ?>> <?php esc_html_e('Text Post', 'social-networks-auto-poster-facebook-twitter-g'); ?></label><br>
        <label><input type="radio" name="li[<?php echo esc_attr($ii); ?>][postType]" value="A" <?php checked(($ntOpt['postType'] ?? 'A') === 'T' ? 'T' : 'A', 'A'); ?>> <?php esc_html_e('Share blog post', 'social-networks-auto-poster-facebook-twitter-g'); ?></label>
      </td></tr>
      <?php nxs_showImgToUseDlg('li', $ii, $imgToUse);
      nxs_showURLToUseDlg('li', $ii, $urlToUse);
      $this->nxs_tmpltAddPostMetaEnd($ii);
    }
  }

  function showEdPostNTSettingsV4($ntOpt, $post){
    $ii = $ntOpt['ii'];
    $imgToUse = !empty($ntOpt['imgToUse']) ? $ntOpt['imgToUse'] : '';
    $urlToUse = !empty($ntOpt['urlToUse']) ? $ntOpt['urlToUse'] : '';
    $this->elemEdMsgFormat($ii, __('Message Format:', 'social-networks-auto-poster-facebook-twitter-g'), !empty($ntOpt['msgFormat']) ? htmlentities($ntOpt['msgFormat'], ENT_COMPAT, 'UTF-8') : ''); ?>
    <div class="nxsPostEd_ElemWrap"><div class="nxsPostEd_ElemLabel"><?php esc_html_e('Post Type:', 'social-networks-auto-poster-facebook-twitter-g'); ?></div><div class="nxsPostEd_Elem">
      <label><input type="radio" class="nxsEdElem" name="li[<?php echo esc_attr($ii); ?>][postType]" value="T" <?php checked($ntOpt['postType'] ?? '', 'T'); ?>> <?php esc_html_e('Text Post', 'social-networks-auto-poster-facebook-twitter-g'); ?></label><br>
      <label><input type="radio" class="nxsEdElem" name="li[<?php echo esc_attr($ii); ?>][postType]" value="A" <?php checked(($ntOpt['postType'] ?? 'A') === 'T' ? 'T' : 'A', 'A'); ?>> <?php esc_html_e('Share blog post', 'social-networks-auto-poster-facebook-twitter-g'); ?></label>
    </div></div>
    <?php nxs_showImgToUseDlg('li', $ii, $imgToUse);
    nxs_showURLToUseDlg('li', $ii, $urlToUse);
  }

  //#### Save Meta Tags to the Post
  function adjMetaOpt($optMt, $pMeta){ $optMt = $this->adjMetaOptG($optMt, $pMeta);     
    
    return $optMt;
  }
  
  function adjPublishWP(&$options, &$message, $postID){ //prr($message); prr($options);
    if (!empty($postID)) { if (trim($options['imgToUse'])!='') $imgURL = $options['imgToUse']; else $imgURL = nxs_getPostImage($postID, !empty($options['wpImgSize'])?$options['wpImgSize']:'full');
      if (preg_match("/noImg.\.png/i", $imgURL)) { $imgURL = ''; $isNoImg = true; } $message['imageURL'] = $imgURL; $post = get_post($postID);            
      $addParams = nxs_makeURLParams(array('NTNAME'=>$this->ntInfo['name'], 'NTCODE'=>$this->ntInfo['code'], 'POSTID'=>$postID, 'ACCNAME'=>$options['nName'])); 
                  if ($options['postType']=='A'){ $lng = '';
        //## AUTO - Get Post Descr from SEO Plugins or make it.      
        if (!empty($options['msgAFormat'])) { $dsc = nsFormatMessage($options['msgAFormat'], $postID, $addParams); $urlTitle = (!empty($options['msgATFormat']))?nsFormatMessage($options['msgATFormat'], $postID, $addParams): nxs_doQTrans($post->post_title, $lng);  } 
        else { if (function_exists('aioseop_mrt_fix_meta') && empty($dsc))  $dsc = trim(get_post_meta($postID, '_aioseop_description', true)); 
          if (function_exists('wpseo_admin_init') && empty($dsc)) $dsc = trim(get_post_meta($postID, '_yoast_wpseo_opengraph-description', true));  
          if (function_exists('wpseo_admin_init') && empty($dsc)) $dsc = trim(get_post_meta($postID, '_yoast_wpseo_metadesc', true));      
          if (empty($dsc)) $dsc = trim(nxs_doQTrans($post->post_excerpt, $lng)); 
          if (empty($dsc)) $dsc = trim(nxs_doQTrans($post->post_content, $lng));  
          global $nxs_SNAP; $gOptions = $nxs_SNAP->nxs_options;if (empty($gOptions['brokenCntFilters'])) $dsc = apply_filters('the_content', $dsc);
          if (empty($dsc)) $dsc = get_bloginfo('description'); $urlTitle = nxs_doQTrans($post->post_title, $lng); 
        } $dsc = strip_tags(strip_shortcodes($dsc));// $dsc = nxs_decodeEntitiesFull($dsc); /## This is commented out to support Emoji in Link Description
        $dsc = nsTrnc($dsc, 900, ' '); $message['urlDescr'] = $dsc; if (!empty($urlTitle)) $message['urlTitle'] = strip_tags(strip_shortcodes($urlTitle)); 
      }
    }
  }

}}
if (!function_exists("nxs_doPublishToLI")) { function nxs_doPublishToLI($postID, $options){ if (!is_array($options)) $options = maybe_unserialize(get_post_meta($postID, $options, true)); $cl = new nxs_snapClassLI(); $cl->nt[$options['ii']] = $options; return $cl->publishWP($options['ii'], $postID); }} 

?>
