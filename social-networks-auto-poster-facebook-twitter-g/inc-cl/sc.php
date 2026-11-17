<?php    
//## NextScripts Scoop.it Connection Class
$nxs_snapAvNts[] = array('code'=>'SC', 'lcode'=>'sc', 'name'=>'Scoop.It', 'type'=>'Blogs/Publishing Platforms', 'ptype'=>'F', 'status'=>'A', 'desc'=>'Autopost to your "Topics". Ability to attach your blogpost to scoop. Ability to make "Image" posts');

require_once dirname(__DIR__).'/inc/nxs_official_connectors.php';

if (!class_exists("nxs_snapClassSC")) { class nxs_snapClassSC extends nxs_snapClassNT { 
  var $ntInfo = array('code'=>'SC', 'lcode'=>'sc', 'name'=>'Scoop.It', 'defNName'=>'', 'tstReq'=>false, 'instrURL'=>'https://www.scoop.it/dev/api/1/intro');
  var $defO = array('nName'=>'', 'do'=>'1', 'accessToken'=>'', 'topicURL'=>'', 'postType'=>'A', 'inclTags'=>1, 'msgTFormat'=>'%TITLE%', 'msgFormat'=>'%EXCERPT%');
  function checkIfFunc() { return true; }
  function toLatestVer($ntOpts) { return $ntOpts; }
  function showGenNTSettings($ntOpts) { $this->nt=$ntOpts; $this->showNTGroup(); }
  function showNewNTSettings($ii) { $this->showGNewNTSettings($ii, $this->defO); }
  function checkIfSetupFinished($options) { return !empty($options['officialApi']) && $options['officialApi']==='sc' && !empty($options['accessToken']) && !empty($options['topicURL']); }
  function accTab($ii,$options,$isNew=false) { $options=array_merge($this->defO,(array)$options); ?>
    <h3>Scoop.it API v1</h3>
    <p>Generate a user access token for your app in <a href="https://www.scoop.it/dev/apps" target="_blank" rel="noopener noreferrer">Scoop.it Developers</a>. It needs permission to publish to your topic.</p>
    <p><label>Access token <input type="password" autocomplete="new-password" name="sc[<?php echo esc_attr($ii); ?>][officialToken]" value="" /></label> <?php if ($this->checkIfSetupFinished($options)) echo '<span>Token saved</span>'; ?></p>
    <p><label>Topic URL name (the part after scoop.it/t/) <input name="sc[<?php echo esc_attr($ii); ?>][topicURL]" value="<?php echo esc_attr($options['topicURL']); ?>" /></label></p>
    <?php $this->elemTitleFormat($ii,'Post title','msgTFormat',$options['msgTFormat']); $this->elemMsgFormat($ii,'Post content','msgFormat',$options['msgFormat']); ?>
    <p><label><input type="checkbox" name="sc[<?php echo esc_attr($ii); ?>][inclTags]" value="1" <?php checked($options['inclTags'],1); ?>> Include tags</label></p>
    <p><label><input type="radio" name="sc[<?php echo esc_attr($ii); ?>][postType]" value="A" <?php checked($options['postType'],'A'); ?>> Link and image</label> <label><input type="radio" name="sc[<?php echo esc_attr($ii); ?>][postType]" value="I" <?php checked($options['postType'],'I'); ?>> Image</label> <label><input type="radio" name="sc[<?php echo esc_attr($ii); ?>][postType]" value="T" <?php checked($options['postType'],'T'); ?>> Text</label></p>
    <?php
  }
  function advTab($ii,$options) { }
  function setNTSettings($post,$options) { foreach ($post as $ii=>$pval) { if (!isset($options[$ii])) $options[$ii]=array(); if (count($pval)===1 && isset($pval['do'])) { $options[$ii]['do']=$pval['do']; continue; } $options[$ii]=$this->saveCommonNTSettings($pval,$options[$ii]); if (!empty($pval['officialToken'])) { $options[$ii]['accessToken']=trim($pval['officialToken']); $options[$ii]['officialApi']='sc'; } if (isset($pval['topicURL'])) $options[$ii]['topicURL']=trim($pval['topicURL']); } return $options; }

  //#### Show Post->Edit Meta Box Settings
  
  function showEdPostNTSettings($ntOpts, $post){ $post_id = $post->ID; $nt = $this->ntInfo['lcode']; $ntU = $this->ntInfo['code'];
      foreach($ntOpts as $ii=>$ntOpt)  { $isFin = $this->checkIfSetupFinished($ntOpt); if (!$isFin) continue; 
        $pMeta = maybe_unserialize(get_post_meta($post_id, 'snap'.$ntU, true)); if (is_array($pMeta) && !empty($pMeta[$ii])) $ntOpt = $this->adjMetaOpt($ntOpt, $pMeta[$ii]);         
        
        if (empty($ntOpt['imgToUse'])) $ntOpt['imgToUse'] = ''; if (empty($ntOpt['urlToUse'])) $ntOpt['urlToUse'] = ''; $postType = isset($ntOpt['postType'])?$ntOpt['postType']:'';
        $msgFormat = !empty($ntOpt['msgFormat'])?htmlentities($ntOpt['msgFormat'], ENT_COMPAT, "UTF-8"):''; $msgTFormat = !empty($ntOpt['msgTFormat'])?htmlentities($ntOpt['msgTFormat'], ENT_COMPAT, "UTF-8"):'';
        $imgToUse = $ntOpt['imgToUse'];  $urlToUse = $ntOpt['urlToUse']; $ntOpt['ii']=$ii; $this->nxs_tmpltAddPostMeta($post, $ntOpt, $pMeta); 
        $this->elemEdTitleFormat($ii, __('Title Format:', 'social-networks-auto-poster-facebook-twitter-g'),$msgTFormat);          
        $this->elemEdMsgFormat($ii, __('Message Format:', 'social-networks-auto-poster-facebook-twitter-g'),$msgFormat);      
        ?>
         <tr style="<?php echo !empty($ntOpt['do'])?'display:table-row;':'display:none;'; ?>" class="nxstbldo nxstbldo<?php echo strtoupper($nt).$ii; ?>"><th scope="row" style="text-align:right; width:150px; vertical-align:top; padding-top: 0px; padding-right:10px;"> <?php _e('Post Type:', 'social-networks-auto-poster-facebook-twitter-g') ?> <br/></th><td>     
        <input type="radio" name="<?php echo esc_attr($nt); ?>[<?php echo esc_attr($ii); ?>][postType]" value="T" <?php if ($postType == 'T') echo 'checked="checked"'; ?> /> <?php _e('Text Post', 'social-networks-auto-poster-facebook-twitter-g') ?>  - <i><?php _e('just text message', 'social-networks-auto-poster-facebook-twitter-g') ?></i><br/>
        <input type="radio" name="<?php echo esc_attr($nt); ?>[<?php echo esc_attr($ii); ?>][postType]" value="I" <?php if ($postType == 'I') echo 'checked="checked"'; ?> /> <?php _e('Post as "Image post"', 'social-networks-auto-poster-facebook-twitter-g') ?> - <i><?php _e('big image with text message', 'social-networks-auto-poster-facebook-twitter-g') ?></i><br/>             
        <input type="radio" name="<?php echo esc_attr($nt); ?>[<?php echo esc_attr($ii); ?>][postType]" value="A" <?php if ( !isset($postType) || $postType == '' || $postType == 'A') echo 'checked="checked"'; ?> /><?php _e('Text Post with "attached" blogpost', 'social-networks-auto-poster-facebook-twitter-g') ?>
     </td></tr>
        <?php
              
        nxs_showImgToUseDlg($nt, $ii, $imgToUse);            
       /* ## Select Image & URL ## */  nxs_showURLToUseDlg($nt, $ii, $urlToUse); $this->nxs_tmpltAddPostMetaEnd($ii);     
     }
  }
  function showEdPostNTSettingsV4($ntOpt, $post){ $post_id = $post->ID; $nt = $this->ntInfo['lcode']; $ntU = $this->ntInfo['code']; $ii = $ntOpt['ii']; //prr($ntOpt['postType']);                                                   
       if (empty($ntOpt['imgToUse'])) $ntOpt['imgToUse'] = ''; if (empty($ntOpt['urlToUse'])) $ntOpt['urlToUse'] = ''; $postType = isset($ntOpt['postType'])?$ntOpt['postType']:'';
       $msgFormat = !empty($ntOpt['msgFormat'])?htmlentities($ntOpt['msgFormat'], ENT_COMPAT, "UTF-8"):''; $msgTFormat = !empty($ntOpt['msgTFormat'])?htmlentities($ntOpt['msgTFormat'], ENT_COMPAT, "UTF-8"):'';
       $imgToUse = $ntOpt['imgToUse'];  $urlToUse = $ntOpt['urlToUse']; $ntOpt['ii']=$ii;        
       //## Title and Message
       $this->elemEdTitleFormat($ii, __('Title Format:', 'social-networks-auto-poster-facebook-twitter-g'),$msgTFormat);        
       $this->elemEdMsgFormat($ii, __('Message Format:', 'social-networks-auto-poster-facebook-twitter-g'),$msgFormat);
        ?>
        
   <div class="nxsPostEd_ElemWrap">   
     <div class="nxsPostEd_ElemLabel"><?php _e('Post Type:', 'social-networks-auto-poster-facebook-twitter-g'); ?></div>   
     <div class="nxsPostEd_Elem">   
        <input type="radio" name="<?php echo esc_attr($nt); ?>[<?php echo esc_attr($ii); ?>][postType]" value="T" class="nxsEdElem" data-ii="<?php echo esc_attr($ii); ?>" data-nt="<?php echo esc_attr($nt); ?>" <?php if ($postType == 'T') echo 'checked="checked"'; ?> /> <?php _e('Text Post', 'social-networks-auto-poster-facebook-twitter-g') ?>  - <i><?php _e('just text message', 'social-networks-auto-poster-facebook-twitter-g') ?></i><br/>
        <input type="radio" name="<?php echo esc_attr($nt); ?>[<?php echo esc_attr($ii); ?>][postType]" value="I" class="nxsEdElem" data-ii="<?php echo esc_attr($ii); ?>" data-nt="<?php echo esc_attr($nt); ?>" <?php if ($postType == 'I') echo 'checked="checked"'; ?> /> <?php _e('Post as "Image post"', 'social-networks-auto-poster-facebook-twitter-g') ?> - <i><?php _e('big image with text message', 'social-networks-auto-poster-facebook-twitter-g') ?></i><br/>             
        <input type="radio" name="<?php echo esc_attr($nt); ?>[<?php echo esc_attr($ii); ?>][postType]" value="A" class="nxsEdElem" data-ii="<?php echo esc_attr($ii); ?>" data-nt="<?php echo esc_attr($nt); ?>" <?php if ( !isset($postType) || $postType == '' || $postType == 'A') echo 'checked="checked"'; ?> /><?php _e('Text Post with "attached" blogpost', 'social-networks-auto-poster-facebook-twitter-g') ?>
     </div>
   </div><?php
       // ## Select Image & URL       
       nxs_showURLToUseDlg($nt, $ii, $urlToUse); 
  }
  
  //#### Save Meta Tags to the Post
  function adjMetaOpt($optMt, $pMeta){ $optMt = $this->adjMetaOptG($optMt, $pMeta);     
    if (!empty($pMeta['postType'])) $optMt['postType'] = $pMeta['postType'];
    return $optMt;
  }
  
  function adjPublishWP(&$options, &$message, $postID){ 
    if (!empty($postID)) { $postType = $options['postType']; $imgURL = '';
      if ($postType=='A') if (trim($options['imgToUse'])!='') $imgURL = $options['imgToUse']; else $imgURL = nxs_getPostImage($postID, !empty($options['wpImgSize'])?$options['wpImgSize']:'medium');  
      if ($postType=='I') if (trim($options['imgToUse'])!='') $imgURL = $options['imgToUse']; else $imgURL = nxs_getPostImage($postID, !empty($options['wpImgSize'])?$options['wpImgSize']:'full');      
      if (preg_match("/noImg.\.png/i", $imgURL)) { $imgURL = ''; $isNoImg = true; }
      $message['imageURL'] = $imgURL;
    }
  }   
  
}}

if (!function_exists("nxs_doPublishToSC")) { function nxs_doPublishToSC($postID, $options){ 
    if (!is_array($options)) $options = maybe_unserialize(get_post_meta($postID, $options, true)); $cl = new nxs_snapClassSC(); $cl->nt[$options['ii']] = $options; return $cl->publishWP($options['ii'], $postID); 
}} 

?>
