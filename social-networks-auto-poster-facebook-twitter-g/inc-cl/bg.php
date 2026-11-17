<?php    
//## NextScripts Blogger  Connection Class
$nxs_snapAvNts[] = array('code'=>'BG', 'lcode'=>'bg', 'name'=>'Blogger', 'type'=>'Blogs/Publishing Platforms', 'ptype'=>'B', 'status'=>'A', 'desc'=>'Autopost to your blog. HTML is supported');

if (!class_exists("nxs_snapClassBG")) { class nxs_snapClassBG extends nxs_snapClassNT { 
  var $ntInfo = array('code'=>'BG', 'lcode'=>'bg', 'name'=>'Blogger', 'defNName'=>'', 'tstReq' => true, 'instrURL'=>'https://www.nextscripts.com/setup-installation-blogger-social-networks-auto-poster-wordpress/');
  //#### Update
  function toLatestVer($ntOpts){ if( !empty($ntOpts['v'])) $v = $ntOpts['v']; else $v = 340; $ntOptsOut = '';  switch ($v) {
      case 340: $ntOptsOut = $this->toLatestVerNTGen($ntOpts); $ntOptsOut['do'] = $ntOpts['do'.$this->ntInfo['code']]; $ntOptsOut['nName'] = $ntOpts['nName'];  $ntOptsOut['blogID'] = $ntOpts['bgBlogID']; 
        if (empty($ntOpts['apiToUse'])) { if (!empty($ntOpts['APIKey'])) $ntOpts['apiToUse'] = 'bg'; if (!empty($ntOpts['bgUName']) && !empty($ntOpts['bgPass'])) $ntOpts['apiToUse'] = 'nx'; } $ntOptsOut['apiToUse'] = $ntOpts['apiToUse'];
        if ($ntOptsOut['apiToUse']=='nx') { $ntOptsOut['uName'] = $ntOpts['bgUName'];  $ntOptsOut['uPass'] = $ntOpts['bgPass'];  } else { $ntOptsOut['appKey'] = $ntOpts['APIKey'];   $ntOptsOut['appSec'] = $ntOpts['APISec']; 
           $ntOptsOut['accessToken'] = $ntOpts['AccessToken']; $ntOptsOut['accessTokenSec'] = $ntOpts['AccessTokenSecret'];  $options['refreshToken'] =  $options['RefreshToken'];  $options['accessTokenExp'] =  $options['AccessTokenExp']; $ntOptsOut['blogInfo'] = $ntOpts['blogInfo']; 
        } $ntOptsOut['inclTags'] = $ntOpts['bgInclTags']; $ntOptsOut['msgFormat'] = $ntOpts['bgMsgFormat'];  $ntOptsOut['msgTFormat'] = $ntOpts['bgMsgTFormat']; 
        $ntOptsOut['blogInfo'] = !empty($ntOpts['blogInfo'])?$ntOpts['blogInfo']:''; $ntOptsOut['blogURL'] = !empty($ntOpts['blogURL'])?$ntOpts['blogURL']:'';  
        $ntOptsOut['isUpdd'] = '1'; $ntOptsOut['v'] = NXS_SETV;
      break;
    }
    return !empty($ntOptsOut)?$ntOptsOut:$ntOpts; 
  }   
  //#### Show Common Settings
  function showGenNTSettings($ntOpts){ $this->nt = $ntOpts;  $this->showNTGroup(); }  
  //#### Show NEW Settings Page
  function showNewNTSettings($ii){ $defO = array('nName'=>'', 'do'=>'1', 'blogID'=>'', 'appKey'=>'', 'appSec'=>'', 'inclTags'=>1, 'inclCatsAsTags'=>0, 'msgFormat'=>"%RAWTEXT%", 'msgTFormat'=>"%TITLE%", 'imgSize'=>'original'); $this->showGNewNTSettings($ii, $defO); }
  //#### Show Unit  Settings  
  function checkIfSetupFinished($options) { return !empty($options['accessToken']); }
  public function doAuth() { $ntInfo = $this->ntInfo; global $nxs_snapSetPgURL;     
    if (isset($_GET['code']) && $_GET['code']!='' && isset($_GET['state']) && strpos(wp_unslash($_GET['state']), 'nxs-bg-') === 0){
      if (!nxs_snap_user_can_access() || !nxs_oauth_state_validate(wp_unslash($_GET['state']), 'bg', $ii) || !isset($this->nt[$ii])) wp_die(esc_html__('Invalid or expired Blogger authorization state.', 'social-networks-auto-poster-facebook-twitter-g'), '', array('response'=>403));
      $at = sanitize_text_field(wp_unslash($_GET['code']));
      echo "----=={ oAuth 2.0 Wordflow }==----<br/>-= This is normal technical authorization info that will dissapear (Unless you get some errors) =- <br/><br/><br/>"; 
      $nxs_snapSetPgURL = nxs_get_admin_url('admin.php?page=nxssnap');
      $options = $this->nt[$ii]; $wprg = array('sslverify'=>true);
      if (isset($options['appKey'])){
        $tknURL = 'https://oauth2.googleapis.com/token';
        $wprg['body'] = array(
            'code' => $at,
            'redirect_uri' => $nxs_snapSetPgURL,
            'client_id' => nxs_gak($options['appKey']),
            'client_secret' => nxs_gas($options['appSec']),
            'grant_type' => 'authorization_code'
        );
        $response = nxs_remote_post($tknURL, $wprg);
        if (is_nxs_error($response) || !is_array($response) || empty($response['body'])) die(esc_html__('Blogger token exchange failed.', 'social-networks-auto-poster-facebook-twitter-g'));
        $resp = json_decode($response['body'], true); if (!is_array($resp) || empty($resp['access_token'])) { echo esc_html(isset($resp['error_description'])?$resp['error_description']:__('Blogger did not return an access token.', 'social-networks-auto-poster-facebook-twitter-g')); die(); }
        if (function_exists('get_option')) $currTime = time() + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ); else  $currTime = time();
        $options['accessToken'] = $resp['access_token']; $options['accessTokenSec'] = 'No Need for oAuth V2';
        $options['accessTokenExp'] = $currTime + $resp['expires_in']; if (!empty($resp['refresh_token'])) $options['refreshToken'] = $resp['refresh_token']; echo "<br/>----=={ Expires: ".esc_html(date('Y-m-d H:i:s', $options['accessTokenExp']))." }==---- <br/>";
        
        if (!empty($options['blogID'])){
          $tknURL = (substr($options['blogID'], 0, 4)=='http') ? 'https://www.googleapis.com/blogger/v3/blogs/byurl/?url='.urlencode($options['blogID']) : 'https://www.googleapis.com/blogger/v3/blogs/'.$options['blogID'];  
          $wprg['headers'] = array(
              'Authorization' => 'Bearer ' . $options['accessToken']
          );
        }        
        $response = nxs_remote_get($tknURL, $wprg); if (is_nxs_error($response)) die(esc_html__('Unable to retrieve Blogger account details.', 'social-networks-auto-poster-facebook-twitter-g')); $user = json_decode($response['body'], true);
        if (!empty($user['url'])) { $options['blogURL'] = $user['url']; $options['blogID'] = $user['id']; $options['blogInfo'] = $user['name']." [".$user['id']."] (".$user['url'].")"; nxs_save_glbNtwrks($ntInfo['lcode'],$ii,$options,'*');                      
          ?><script type="text/javascript">window.location = "<?php echo $nxs_snapSetPgURL; ?>"</script>      
        <?php }        
      }
      die();
    } 
  }    
  
  function accTab($ii, $options, $isNew=false){ global $nxs_snapSetPgURL; $ntInfo = $this->ntInfo; $nt = $ntInfo['lcode']; $oauth_state = nxs_oauth_state_create('bg', $ii); ?>
    <div style="width:100%;"><strong><?php _e('Blogger Blog ID', 'social-networks-auto-poster-facebook-twitter-g'); ?>:</strong><i><?php _e('Log to your Blogger management panel and look at the URL of your blog: http://www.blogger.com/blogger.g?blogID=8959085979163812093#allposts. Your Blog ID will be: 8959085979163812093', 'social-networks-auto-poster-facebook-twitter-g'); ?></i></div><input name="<?php echo esc_attr($nt); ?>[<?php echo esc_attr($ii); ?>][blogID]" style="width: 30%;" value="<?php _e(apply_filters('format_to_edit', htmlentities($options['blogID'], ENT_COMPAT, "UTF-8")), 'social-networks-auto-poster-facebook-twitter-g') ?>" /><br/><br/>     
    
    <div id="nxsAPIBG<?php echo esc_attr($ii); ?>" class="nxs_<?php echo esc_attr($nt); ?>_bgapi_<?php echo esc_attr($ii); ?>">
      <div class="subDiv" id="sub<?php echo esc_attr($ii); ?>DivL" style="display: block;"> <?php $this->elemKeySecret($ii,'Client ID','Client Secret', $options['appKey'], $options['appSec'],'appKey','appSec','https://console.developers.google.com/'); ?>
      <br/><br/>
      <div id="bg_authorize_wrap_<?php echo esc_attr($ii); ?>" style="<?php echo ($options['appKey']!='') ? '' : 'display: none;'; ?>">
          <?php if(!empty($options['accessToken']) && !empty($options['accessTokenSec'])) { ?>
              <?php _e('Your '.$ntInfo['name'].' Account has been authorized.', 'social-networks-auto-poster-facebook-twitter-g'); ?> <br/>Blog ID: <?php _e(apply_filters('format_to_edit', htmlentities($options['blogInfo'], ENT_COMPAT, "UTF-8")), 'social-networks-auto-poster-facebook-twitter-g'); ?>.<br/>
              <?php _e('You can', 'social-networks-auto-poster-facebook-twitter-g'); ?> Re-
          <?php } ?>
          <a id="bg_authLink_<?php echo esc_attr($ii); ?>" href="<?php echo esc_url('https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query(array('redirect_uri'=>$nxs_snapSetPgURL,'response_type'=>'code','client_id'=>nxs_gak($options['appKey']),'scope'=>'https://www.googleapis.com/auth/blogger','prompt'=>'consent','access_type'=>'offline','state'=>$oauth_state), '', '&')); ?>" onclick="var authUrl = jQuery(this).attr('href'); nxs_svSetAdv('bg', '<?php echo esc_js($ii); ?>', '<?php echo esc_js($isNew ? 'domBG'.$ii.'Div' : 'nxsAllAccntsDiv'); ?>', '', authUrl, '1'); return false;">Authorize Your Blogger Account</a>
          <?php if (empty($options['accessToken'])) { ?>
              <div class="blnkg" id="bg_blnkg_<?php echo esc_attr($ii); ?>">&lt;=== <?php _e('Authorize your account', 'social-networks-auto-poster-facebook-twitter-g'); ?> ===</div>
          <?php } ?>
          <br/><br/>
      </div>
      
      <div id="bg_notice_wrap_<?php echo esc_attr($ii); ?>" style="<?php echo ($options['appKey']=='') ? '' : 'display: none;'; ?>">
          <b><?php _e('Authorize Your '.$ntInfo['name'].' Account', 'social-networks-auto-poster-facebook-twitter-g'); ?></b>. <?php _e('Please enter your Client ID and Client Secret above to enable authorization.', 'social-networks-auto-poster-facebook-twitter-g'); ?>
          <br/><br/>
      </div>
      <?php
      static $bg_script_printed = false;
      if (!$bg_script_printed) {
          $bg_script_printed = true;
          ?>
          <script type="text/javascript">
          function nxs_bg_client_changed(ii, redirectUriBase, oauthState) {
              var appKey = jQuery('#bgappKey' + ii).val().trim();
              var authorizeWrap = jQuery('#bg_authorize_wrap_' + ii);
              var noticeWrap = jQuery('#bg_notice_wrap_' + ii);
              var authLink = jQuery('#bg_authLink_' + ii);
              
              if (appKey !== '') {
                  authorizeWrap.show();
                  noticeWrap.hide();
                  
                  var cleanAppKey = appKey;
                  if (cleanAppKey.indexOf('::') > -1) {
                      cleanAppKey = cleanAppKey.split('::')[0];
                  }
                  
                  authLink.attr('href', "https://accounts.google.com/o/oauth2/v2/auth?redirect_uri=" + encodeURIComponent(redirectUriBase) + "&response_type=code&client_id=" + encodeURIComponent(cleanAppKey) + "&scope=https%3A%2F%2Fwww.googleapis.com%2Fauth%2Fblogger&prompt=consent&access_type=offline&state=" + encodeURIComponent(oauthState));
              } else {
                  authorizeWrap.hide();
                  noticeWrap.show();
              }
          }
          </script>
          <?php
      }
      ?>
      <script type="text/javascript">
      jQuery(document).ready(function() {
          jQuery(document).on('input', '#bgappKey<?php echo $ii; ?>, #bgappSec<?php echo $ii; ?>', function() {
              nxs_bg_client_changed(<?php echo absint($ii); ?>, <?php echo wp_json_encode($nxs_snapSetPgURL); ?>, <?php echo wp_json_encode($oauth_state); ?>);
          });
      });
      </script><br/><br/>
      </div>
    </div>
    
    <br/><?php $this->elemTitleFormat($ii,'Message Title Format','msgTFormat',$options['msgTFormat']); $this->elemMsgFormat($ii,'Message Format','msgFormat',$options['msgFormat']); ?>
    <div style="margin: 0px;"><input value="1" type="checkbox" name="<?php echo esc_attr($nt); ?>[<?php echo esc_attr($ii); ?>][inclTags]"  <?php if ((int)$options['inclTags'] == 1) echo "checked"; ?> /> <strong><?php _e('Post with tags', 'social-networks-auto-poster-facebook-twitter-g'); ?></strong></div>
    <div style="margin: 0px;"><input value="1" type="checkbox" name="<?php echo esc_attr($nt); ?>[<?php echo esc_attr($ii); ?>][inclCatsAsTags]"  <?php if ((int)$options['inclCatsAsTags'] == 1) echo "checked"; ?> /> <strong><?php _e('Add Categories as tags/labels', 'social-networks-auto-poster-facebook-twitter-g'); ?></strong></div>
    
     <?php
  }
  function advTab($ii, $options){  $this->showProxies($this->ntInfo['lcode'], $ii, $options); }                             
  function setNTSettings($post, $options){ $otp = array(); //prr($options);
    foreach ($options as $oo => $v){  if (isset($v['ck'])) unset($v['ck']);
        if (isset($oo) && $oo!=='' && !empty($v['appKey']) && !empty($v['appSec'])) $otp[$oo] = $v;
    } $options = $otp;
    foreach ($post as $ii => $pval){       
      if (!empty($pval['blogID']) && !empty($pval['blogID'])){ if (!isset($options[$ii])) $options[$ii] = array(); $options[$ii] = $this->saveCommonNTSettings($pval,$options[$ii]);
        if (isset($pval['inclTags'])) $options[$ii]['inclTags'] = trim($pval['inclTags']); else $options[$ii]['inclTags'] = 0;
        if (isset($pval['inclCatsAsTags'])) $options[$ii]['inclCatsAsTags'] = trim($pval['inclCatsAsTags']); else $options[$ii]['inclCatsAsTags'] = 0;
        if (isset($pval['blogID'])) $options[$ii]['blogID'] = trim($pval['blogID']);
      } elseif ( count($pval)==1 ) if (isset($pval['do'])) $options[$ii]['do'] = $pval['do']; else $options[$ii]['do'] = 0; 
    } return $options;
  }  
    
  //#### Show Post->Edit Meta Box Settings
  
  function showEdPostNTSettings($ntOpts, $post){ $post_id = $post->ID; $nt = $this->ntInfo['lcode']; $ntU = $this->ntInfo['code'];
      foreach($ntOpts as $ii=>$ntOpt)  { $isFin = $this->checkIfSetupFinished($ntOpt); if (!$isFin) continue; 
        $pMeta = maybe_unserialize(get_post_meta($post_id, 'snap'.$ntU, true)); if (is_array($pMeta) && !empty($pMeta[$ii])) $ntOpt = $this->adjMetaOpt($ntOpt, $pMeta[$ii]);         
        
        if (empty($ntOpt['imgToUse'])) $ntOpt['imgToUse'] = ''; if (empty($ntOpt['urlToUse'])) $ntOpt['urlToUse'] = ''; $postType = isset($ntOpt['postType'])?$ntOpt['postType']:'';
        $msgFormat = !empty($ntOpt['msgFormat'])?htmlentities($ntOpt['msgFormat'], ENT_COMPAT, "UTF-8"):''; $msgTFormat = !empty($ntOpt['msgTFormat'])?htmlentities($ntOpt['msgTFormat'], ENT_COMPAT, "UTF-8"):'';
        $imgToUse = $ntOpt['imgToUse'];  $urlToUse = $ntOpt['urlToUse']; $ntOpt['ii']=$ii;
        
        $this->nxs_tmpltAddPostMeta($post, $ntOpt, $pMeta); 
        
          $this->elemEdTitleFormat($ii, __('Title Format:', 'social-networks-auto-poster-facebook-twitter-g'),$msgTFormat);          
          $this->elemEdMsgFormat($ii, __('Message Format:', 'social-networks-auto-poster-facebook-twitter-g'),$msgFormat);  
    
       /* ## Select Image & URL ## */  nxs_showURLToUseDlg($nt, $ii, $urlToUse); $this->nxs_tmpltAddPostMetaEnd($ii);     
     }
  }
  
    function showEdPostNTSettingsV4($ntOpt, $post){ $post_id = $post->ID; $nt = $this->ntInfo['lcode']; $ntU = $this->ntInfo['code']; $ii = $ntOpt['ii']; //prr($ntOpt['postType']);
                                                   
        if (empty($ntOpt['imgToUse'])) $ntOpt['imgToUse'] = ''; if (empty($ntOpt['urlToUse'])) $ntOpt['urlToUse'] = ''; $postType = isset($ntOpt['postType'])?$ntOpt['postType']:'';
        $msgFormat = !empty($ntOpt['msgFormat'])?htmlentities($ntOpt['msgFormat'], ENT_COMPAT, "UTF-8"):''; $msgTFormat = !empty($ntOpt['msgTFormat'])?htmlentities($ntOpt['msgTFormat'], ENT_COMPAT, "UTF-8"):'';
        $imgToUse = $ntOpt['imgToUse'];  $urlToUse = $ntOpt['urlToUse']; $ntOpt['ii']=$ii;
        
        $this->elemEdTitleFormat($ii, __('Title Format:', 'social-networks-auto-poster-facebook-twitter-g'),$msgTFormat);        
        $this->elemEdMsgFormat($ii, __('Message Format:', 'social-networks-auto-poster-facebook-twitter-g'),$msgFormat);            
        //## Select Image & URL
        nxs_showURLToUseDlg($nt, $ii, $urlToUse); 

  }
  
  //#### Save Meta Tags to the Post
  function adjMetaOpt($optMt, $pMeta){ $optMt = $this->adjMetaOptG($optMt, $pMeta);         
    return $optMt;
  }
  
  function adjPublishWP(&$options, &$message, $postID){ 
    if (!empty($postID)) { if (trim($options['imgToUse'])!='') $imgURL = $options['imgToUse']; else $imgURL = nxs_getPostImage($postID, !empty($options['wpImgSize'])?$options['wpImgSize']:'full');
      if (preg_match("/noImg.\.png/i", $imgURL)) { $imgURL = ''; $isNoImg = true; }
      $message['imageURL'] = $imgURL;
    }
  }   
  
}}

if (!function_exists("nxs_doPublishToBG")) { function nxs_doPublishToBG($postID, $options){ if (!is_array($options)) $options = maybe_unserialize(get_post_meta($postID, $options, true)); $cl = new nxs_snapClassBG(); $cl->nt[$options['ii']] = $options; return $cl->publishWP($options['ii'], $postID); }} 

?>
