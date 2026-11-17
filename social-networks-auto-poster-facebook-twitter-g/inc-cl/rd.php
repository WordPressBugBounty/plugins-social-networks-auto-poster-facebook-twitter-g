<?php    
//## NextScripts Reddit Connection Class
$nxs_snapAvNts[] = array('code'=>'RD', 'lcode'=>'rd', 'name'=>'Reddit', 'type'=>'Link Sharing/Boormarks', 'ptype'=>'P', 'status'=>'A', 'desc'=>'Autopost to your subreddits');

require_once dirname(__DIR__).'/inc/nxs_official_connectors.php';

if (!class_exists("nxs_snapClassRD")) { class nxs_snapClassRD extends nxs_snapClassNT { 
  var $ntInfo = array('code'=>'RD', 'lcode'=>'rd', 'name'=>'Reddit', 'defNName'=>'', 'tstReq'=>false, 'instrURL'=>'https://www.reddit.com/dev/api/');
  var $defO = array('nName'=>'', 'do'=>'1', 'appKey'=>'', 'appSec'=>'', 'rdSubReddit'=>'', 'postType'=>'A', 'msgTFormat'=>'%TITLE%', 'msgFormat'=>'%EXCERPT%');
  function checkIfFunc() { return true; }
  function toLatestVer($ntOpts) { return $ntOpts; }
  function showGenNTSettings($ntOpts) { $this->nt=$ntOpts; $this->showNTGroup(); }
  function showNewNTSettings($ii) { $this->showGNewNTSettings($ii, $this->defO); }
  function checkIfSetupFinished($options) { return !empty($options['officialApi']) && $options['officialApi']==='rd' && !empty($options['refreshToken']) && !empty($options['rdSubReddit']); }
  function doAuth() { NXS_Official_Connectors::handle_oauth('rd', $this->nt); }
  function accTab($ii,$options,$isNew=false) { $options=array_merge($this->defO,(array)$options); ?>
    <h3>Reddit OAuth API</h3>
    <?php $this->elemKeySecret($ii,'Client ID','Client Secret',$options['appKey'],$options['appSec'],'appKey','appSec','https://www.reddit.com/prefs/apps/'); ?>
    <p>Create a Reddit web app and register this redirect URI: <code><?php echo esc_html(NXS_Official_Connectors::callback_url()); ?></code></p>
    <?php if (!empty($options['appKey']) && !empty($options['appSec']) && !$isNew) { ?><p><a class="button" href="<?php echo esc_url(add_query_arg(array('nxs_official_start'=>'rd','acc'=>$ii),NXS_Official_Connectors::callback_url())); ?>">Connect Reddit</a></p><?php } else { ?><p>Save the Client ID and Client Secret, then connect Reddit.</p><?php } ?>
    <?php if (!empty($options['refreshToken']) && !empty($options['officialApi']) && $options['officialApi']==='rd') { ?><p>Connected as <?php echo esc_html($options['uName'] ?? 'Reddit user'); ?>.</p><?php } ?>
    <p><label>Subreddit <input name="rd[<?php echo esc_attr($ii); ?>][rdSubReddit]" value="<?php echo esc_attr($options['rdSubReddit']); ?>" /></label></p>
    <p><label><input type="radio" name="rd[<?php echo esc_attr($ii); ?>][postType]" value="A" <?php checked($options['postType'],'A'); ?>> Link post</label> <label><input type="radio" name="rd[<?php echo esc_attr($ii); ?>][postType]" value="T" <?php checked($options['postType'],'T'); ?>> Text post</label></p>
    <?php $this->elemTitleFormat($ii,'Post title','msgTFormat',$options['msgTFormat']); $this->elemMsgFormat($ii,'Post text','msgFormat',$options['msgFormat']);
  }
  function advTab($ii,$options) { }
  function setNTSettings($post,$options) { foreach ($post as $ii=>$pval) { if (!isset($options[$ii])) $options[$ii]=array(); if (count($pval)===1 && isset($pval['do'])) { $options[$ii]['do']=$pval['do']; continue; } $options[$ii]=$this->saveCommonNTSettings($pval,$options[$ii]); if (isset($pval['rdSubReddit'])) $options[$ii]['rdSubReddit']=preg_replace('/^r\//','',trim($pval['rdSubReddit'])); } return $options; }

  //#### Show Post->Edit Meta Box Settings
  function showEdPostNTSettings($ntOpts, $post){ $post_id = $post->ID; $nt = $this->ntInfo['lcode']; $ntU = $this->ntInfo['code'];
      foreach($ntOpts as $ii=>$ntOpt)  { $isFin = $this->checkIfSetupFinished($ntOpt); if (!$isFin) continue; 
        $pMeta = maybe_unserialize(get_post_meta($post_id, 'snap'.$ntU, true)); if (is_array($pMeta) && !empty($pMeta[$ii])) $ntOpt = $this->adjMetaOpt($ntOpt, $pMeta[$ii]);         
        
        if (empty($ntOpt['imgToUse'])) $ntOpt['imgToUse'] = ''; if (empty($ntOpt['urlToUse'])) $ntOpt['urlToUse'] = ''; $postType = isset($ntOpt['postType'])?$ntOpt['postType']:'';
        $msgFormat = !empty($ntOpt['msgFormat'])?htmlentities($ntOpt['msgFormat'], ENT_COMPAT, "UTF-8"):''; $msgTFormat = !empty($ntOpt['msgTFormat'])?htmlentities($ntOpt['msgTFormat'], ENT_COMPAT, "UTF-8"):''; 
        $imgToUse = $ntOpt['imgToUse'];  $urlToUse = $ntOpt['urlToUse'];  $ntOpt['ii']=$ii;
         
        $this->nxs_tmpltAddPostMeta($post, $ntOpt, $pMeta); ?>
        <?php  $this->elemEdTitleFormat($ii, __('Title Format:', 'social-networks-auto-poster-facebook-twitter-g'),$msgTFormat);  $this->elemEdMsgFormat($ii, __('Message Format:', 'social-networks-auto-poster-facebook-twitter-g'),$msgFormat); ?>
         <tr style="<?php echo !empty($ntOpt['do'])?'display:table-row;':'display:none;'; ?>" class="nxstbldo nxstbldo<?php echo strtoupper($nt).$ii; ?>"><th scope="row" style="text-align:right; width:150px; vertical-align:top; padding-top: 0px; padding-right:10px;"> <?php _e('Post Type:', 'social-networks-auto-poster-facebook-twitter-g') ?> </th><td>             
        <input type="radio" name="rd[<?php echo esc_attr($ii); ?>][postType]" value="A" <?php if ( empty($postType) || $postType == 'A') echo 'checked="checked"'; ?> /><?php _e('Link Post', 'social-networks-auto-poster-facebook-twitter-g') ?>
        <br/>
      <input type="radio" name="rd[<?php echo esc_attr($ii); ?>][postType]" value="T" <?php if ($postType == 'T') echo 'checked="checked"'; ?> /> <?php _e('Text Post', 'social-networks-auto-poster-facebook-twitter-g') ?><br/>               
     </td></tr>
        <?php
        
          /* ## Select Image & URL ## */ nxs_showURLToUseDlg($nt, $ii, $urlToUse); $this->nxs_tmpltAddPostMetaEnd($ii);        
     }
  }  
  function showEdPostNTSettingsV4($ntOpt, $post){ $post_id = $post->ID; $nt = $this->ntInfo['lcode']; $ntU = $this->ntInfo['code']; $ii = $ntOpt['ii']; //prr($ntOpt['postType']);
        if (empty($ntOpt['imgToUse'])) $ntOpt['imgToUse'] = ''; if (empty($ntOpt['urlToUse'])) $ntOpt['urlToUse'] = ''; $postType = isset($ntOpt['postType'])?$ntOpt['postType']:'';
        $msgFormat = !empty($ntOpt['msgFormat'])?htmlentities($ntOpt['msgFormat'], ENT_COMPAT, "UTF-8"):''; $msgTFormat = !empty($ntOpt['msgTFormat'])?htmlentities($ntOpt['msgTFormat'], ENT_COMPAT, "UTF-8"):'';
        $imgToUse = $ntOpt['imgToUse'];  $urlToUse = $ntOpt['urlToUse'];
        
        $this->elemEdTitleFormat($ii, __('Title Format:', 'social-networks-auto-poster-facebook-twitter-g'),$msgTFormat); 
        $this->elemEdMsgFormat($ii, __('Message Format:', 'social-networks-auto-poster-facebook-twitter-g'),$msgFormat);            
        ?>
        
   <div class="nxsPostEd_ElemWrap">   
     <div class="nxsPostEd_ElemLabel"><?php _e('Post Type:', 'social-networks-auto-poster-facebook-twitter-g'); ?></div>   
     <div class="nxsPostEd_Elem">   
       <input type="radio" name="rd[<?php echo esc_attr($ii); ?>][postType]" class="nxsEdElem" value="A" data-ii="<?php echo esc_attr($ii); ?>" data-nt="<?php echo esc_attr($nt); ?>" <?php if ( empty($postType) || $postType == 'A') echo 'checked="checked"'; ?> /><?php _e('Link Post', 'social-networks-auto-poster-facebook-twitter-g') ?>
        <br/>
      <input type="radio" name="rd[<?php echo esc_attr($ii); ?>][postType]" class="nxsEdElem" value="T" data-ii="<?php echo esc_attr($ii); ?>" data-nt="<?php echo esc_attr($nt); ?>" <?php if ($postType == 'T') echo 'checked="checked"'; ?> /> <?php _e('Text Post', 'social-networks-auto-poster-facebook-twitter-g') ?>
     </div>
   </div><?php
        // ## Select Image & URL         
        nxs_showURLToUseDlg($nt, $ii, $urlToUse); 
  }
  //#### Save Meta Tags to the Post
  function adjMetaOpt($optMt, $pMeta){ $optMt = $this->adjMetaOptG($optMt, $pMeta);  //   prr($optMt);
    //if (!empty($pMeta['dlBoard'])) $optMt['dlBoard'] = $pMeta['dlBoard']; if (!empty($pMeta['dlBoard'])) $optMt['dlBoard'] = $pMeta['dlBoard']; else $optMt['dlBoard'] = 0;            
    return $optMt;
  }
  
  function adjPublishWP(&$options, &$message, $postID){ 
      
  } 

}}

if (!function_exists("nxs_doPublishToRD")) { function nxs_doPublishToRD($postID, $options){ if (!is_array($options)) $options = maybe_unserialize(get_post_meta($postID, $options, true)); 
  $cl = new nxs_snapClassRD(); $cl->nt[$options['ii']] = $options; return $cl->publishWP($options['ii'], $postID); 
}}  
?>
