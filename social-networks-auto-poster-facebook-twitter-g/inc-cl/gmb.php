<?php
$nxs_snapAvNts[] = array('code'=>'GMB','lcode'=>'gmb','name'=>'Google Business Profile','type'=>'Blogs/Publishing Platforms','ptype'=>'P','status'=>'A','desc'=>'Create a Google Business Profile post');
require_once dirname(__DIR__).'/inc/nxs_official_connectors.php';
if (!class_exists('nxs_snapClassGMB')) { class nxs_snapClassGMB extends nxs_snapClassNT {
  var $ntInfo = array('code'=>'GMB','lcode'=>'gmb','name'=>'Google Business Profile','defNName'=>'','tstReq'=>false,'imgAct'=>'E','instrURL'=>'https://developers.google.com/my-business/content/basic-setup');
  var $defO = array('nName'=>'','do'=>'1','appKey'=>'','appSec'=>'','postTo'=>'','postType'=>'A','msgFormat'=>'New post: %TITLE% - %URL%','eTtlFormat'=>'%TITLE%','btnType'=>'LEARN_MORE','ebtnType'=>'LEARN_MORE');
  function checkIfFunc() { return true; }
  function showGenNTSettings($ntOpts) { $this->nt=$ntOpts; $this->showNTGroup(); }
  function showNewNTSettings($ii) { $this->showGNewNTSettings($ii,$this->defO); }
  function checkIfSetupFinished($options) { return ($options['officialApi'] ?? '')==='gmb' && !empty($options['refreshToken']) && !empty($options['postTo']); }
  function doAuth() { NXS_Official_Connectors::handle_oauth('gmb',$this->nt); }
  function accTab($ii,$options,$isNew=false) { $options=array_merge($this->defO,(array)$options); ?>
    <h3>Google Business Profile API</h3>
    <p>Use an approved Google Cloud project with the Google My Business, Account Management, and Business Information APIs enabled. Create a Web application OAuth client.</p>
    <?php $this->elemKeySecret($ii,'OAuth Client ID','OAuth Client Secret',$options['appKey'],$options['appSec'],'appKey','appSec','https://console.cloud.google.com/apis/credentials'); ?>
    <p>Add this authorized redirect URI to the OAuth client: <code><?php echo esc_html(NXS_Official_Connectors::callback_url()); ?></code></p>
    <?php if (!empty($options['appKey']) && !empty($options['appSec']) && !$isNew) { ?><p><a class="button" href="<?php echo esc_url(add_query_arg(array('nxs_official_start'=>'gmb','acc'=>$ii),NXS_Official_Connectors::callback_url())); ?>">Connect Google Business Profile</a></p><?php } else { ?><p>Save the OAuth credentials, then connect your Google account.</p><?php } ?>
    <?php if (($options['officialApi'] ?? '')==='gmb' && !empty($options['refreshToken'])) echo '<p>Google account connected.</p>'; ?>
    <p><label>Business location <input size="45" list="gmb-locations-<?php echo esc_attr($ii); ?>" name="gmb[<?php echo esc_attr($ii); ?>][postTo]" value="<?php echo esc_attr($options['postTo']); ?>" placeholder="accounts/123/locations/456" /></label></p>
    <datalist id="gmb-locations-<?php echo esc_attr($ii); ?>"><?php
      if (($options['officialApi'] ?? '')==='gmb' && !empty($options['refreshToken'])) {
        $options['ii']=$ii; $token=NXS_Official_Connectors::access_token('gmb',$options);
        if ($token!=='') {
          $accounts=NXS_Official_Connectors::request('https://mybusinessaccountmanagement.googleapis.com/v1/accounts',$token,'GET',null,'gmb');
          foreach (($accounts['accounts'] ?? array()) as $account) {
            if (empty($account['name']) || !preg_match('~^accounts/[0-9]+$~',$account['name'])) continue;
            $locations=NXS_Official_Connectors::request('https://mybusinessbusinessinformation.googleapis.com/v1/'.$account['name'].'/locations?readMask=name,title&pageSize=100',$token,'GET',null,'gmb');
            foreach (($locations['locations'] ?? array()) as $location) {
              if (empty($location['name']) || !preg_match('~^locations/([0-9]+)$~',$location['name'],$match)) continue;
              echo '<option value="'.esc_attr($account['name'].'/locations/'.$match[1]).'" label="'.esc_attr($location['title'] ?? $location['name']).'"></option>';
            }
          }
        }
      }
    ?></datalist>
    <p class="description">Select a location or enter its resource name as accounts/ACCOUNT_ID/locations/LOCATION_ID.</p>
    <p><label>Post type <select name="gmb[<?php echo esc_attr($ii); ?>][postType]"><option value="A" <?php selected($options['postType'],'A'); ?>>What's New</option><option value="E" <?php selected($options['postType'],'E'); ?>>Event</option></select></label></p>
    <p><label>What's New button <select name="gmb[<?php echo esc_attr($ii); ?>][btnType]"><?php $this->button_options($options['btnType']); ?></select></label></p>
    <?php $this->elemTitleFormat($ii,'Event title','eTtlFormat',$options['eTtlFormat']); ?>
    <p><label>Event button <select name="gmb[<?php echo esc_attr($ii); ?>][ebtnType]"><?php $this->button_options($options['ebtnType']); ?></select></label></p>
    <p class="description">For Event posts, set start and end dates on the post editor.</p>
    <?php $this->elemMsgFormat($ii,'Message Format','msgFormat',$options['msgFormat']);
  }
  function button_options($chosen) { foreach (array('X'=>'No Button','BOOK'=>'Book','ORDER'=>'Order Online','SHOP'=>'Shop','LEARN_MORE'=>'Learn More','SIGN_UP'=>'Sign Up') as $value=>$label) echo '<option value="'.esc_attr($value).'" '.selected($chosen,$value,false).'>'.esc_html($label).'</option>'; }
  function advTab($ii,$options) { $this->askForSURL('gmb',$ii,$options); }
  function setNTSettings($post,$options) { foreach ($post as $ii=>$pval) { if (!isset($options[$ii])) $options[$ii]=array(); if (count($pval)===1 && isset($pval['do'])) { $options[$ii]['do']=$pval['do']; continue; } $options[$ii]=$this->saveCommonNTSettings($pval,$options[$ii]); foreach (array('postTo','postType','btnType','ebtnType','eTtlFormat') as $field) if (isset($pval[$field])) $options[$ii][$field]=trim($pval[$field]); } return $options; }
  function showEdPostNTSettingsV4($ntOpt,$post) { $ntOpt=array_merge($this->defO,$ntOpt); $ii=$ntOpt['ii']; $this->elemEdMsgFormat($ii,__('Message Format:','social-networks-auto-poster-facebook-twitter-g'),htmlentities($ntOpt['msgFormat'],ENT_COMPAT,'UTF-8')); ?>
    <div class="nxsPostEd_ElemWrap"><div class="nxsPostEd_ElemLabel">Post type:</div><div class="nxsPostEd_Elem"><select class="nxsEdElem" data-ii="<?php echo esc_attr($ii); ?>" data-nt="gmb" name="gmb[<?php echo esc_attr($ii); ?>][postType]"><option value="A" <?php selected($ntOpt['postType'],'A'); ?>>What's New</option><option value="E" <?php selected($ntOpt['postType'],'E'); ?>>Event</option></select></div></div>
    <div class="nxsPostEd_ElemWrap"><div class="nxsPostEd_ElemLabel">Event title:</div><div class="nxsPostEd_Elem"><input class="nxsEdElem" data-ii="<?php echo esc_attr($ii); ?>" data-nt="gmb" name="gmb[<?php echo esc_attr($ii); ?>][eTtlFormat]" value="<?php echo esc_attr($ntOpt['eTtlFormat']); ?>" /></div></div>
    <div class="nxsPostEd_ElemWrap"><div class="nxsPostEd_ElemLabel">What's New button:</div><div class="nxsPostEd_Elem"><select class="nxsEdElem" data-ii="<?php echo esc_attr($ii); ?>" data-nt="gmb" name="gmb[<?php echo esc_attr($ii); ?>][btnType]"><?php $this->button_options($ntOpt['btnType']); ?></select></div></div>
    <div class="nxsPostEd_ElemWrap"><div class="nxsPostEd_ElemLabel">Event button:</div><div class="nxsPostEd_Elem"><select class="nxsEdElem" data-ii="<?php echo esc_attr($ii); ?>" data-nt="gmb" name="gmb[<?php echo esc_attr($ii); ?>][ebtnType]"><?php $this->button_options($ntOpt['ebtnType']); ?></select></div></div>
    <?php foreach (array('evStDate'=>'Event start','evEndDate'=>'Event end') as $field=>$label) { ?><div class="nxsPostEd_ElemWrap"><div class="nxsPostEd_ElemLabel"><?php echo esc_html($label); ?>:</div><div class="nxsPostEd_Elem"><input class="nxsEdElem" data-ii="<?php echo esc_attr($ii); ?>" data-nt="gmb" name="gmb[<?php echo esc_attr($ii); ?>][<?php echo esc_attr($field); ?>]" value="<?php echo esc_attr($ntOpt[$field] ?? ''); ?>" placeholder="YYYY-MM-DD HH:MM" /></div></div><?php }
    nxs_showImgToUseDlg('gmb',$ii,$ntOpt['imgToUse'] ?? ''); nxs_showURLToUseDlg('gmb',$ii,$ntOpt['urlToUse'] ?? '');
  }
  function adjMetaOpt($optMt,$pMeta) { $optMt=$this->adjMetaOptG($optMt,$pMeta); foreach (array('postType','btnType','ebtnType','eTtlFormat','evStDate','evEndDate') as $field) if (isset($pMeta[$field])) $optMt[$field]=$pMeta[$field]; return $optMt; }
  function adjPublishWP(&$options,&$message,$postID) { if (!empty($options['eTtlFormat'])) $options['eTtlFormat']=nsFormatMessage($options['eTtlFormat'],$postID,nxs_makeURLParams(array('NTNAME'=>$this->ntInfo['name'],'NTCODE'=>'GMB','POSTID'=>$postID,'ACCNAME'=>$options['nName']))); }
}}
if (!function_exists('nxs_doPublishToGMB')) { function nxs_doPublishToGMB($postID,$options) { if (!is_array($options)) $options=maybe_unserialize(get_post_meta($postID,$options,true)); $cl=new nxs_snapClassGMB(); $cl->nt[$options['ii']]=$options; return $cl->publishWP($options['ii'],$postID); }}
