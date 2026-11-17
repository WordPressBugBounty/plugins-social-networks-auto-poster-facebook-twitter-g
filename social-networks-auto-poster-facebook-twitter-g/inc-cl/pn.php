<?php    
//## NextScripts Pinterest Connection Class
$nxs_snapAvNts[] = array('code'=>'PN', 'lcode'=>'pn', 'name'=>'Pinterest', 'type'=>'Social Networks', 'ptype'=>'P', 'status'=>'A', 'desc'=>'Post your blogpost\'s image to your Pinterest board.');

require_once dirname(__DIR__).'/inc/nxs_official_connectors.php';

if (!class_exists("nxs_snapClassPN")) { class nxs_snapClassPN extends nxs_snapClassNT { 
  var $ntInfo = array('code'=>'PN', 'lcode'=>'pn', 'name'=>'Pinterest', 'defNName'=>'', 'tstReq'=>false, 'instrURL'=>'https://developers.pinterest.com/docs/getting-started/set-up-authentication-and-authorization/');
  var $defO = array('nName'=>'', 'do'=>'1', 'appKey'=>'', 'appSec'=>'', 'pnBoard'=>'', 'defImg'=>'', 'cImgURL'=>'R', 'msgFormat'=>'%EXCERPT%', 'msgTFormat'=>'%TITLE%');
  function checkIfFunc() { return true; }
  function toLatestVer($ntOpts) { return $ntOpts; }
  function showGenNTSettings($ntOpts) { $this->nt=$ntOpts; $this->showNTGroup(); }
  function showNewNTSettings($ii) { $this->showGNewNTSettings($ii, $this->defO); }
  function checkIfSetupFinished($options) { return !empty($options['officialApi']) && $options['officialApi']==='pn' && !empty($options['refreshToken']) && !empty($options['pnBoard']); }
  function doAuth() { NXS_Official_Connectors::handle_oauth('pn', $this->nt); }
  function accTab($ii, $options, $isNew=false) { $options=array_merge($this->defO,(array)$options); ?>
    <h3>Pinterest API v5</h3>
    <?php $this->elemKeySecret($ii,'App ID','App Secret',$options['appKey'],$options['appSec'],'appKey','appSec','https://developers.pinterest.com/apps/'); ?>
    <p>Register this redirect URI in your Pinterest app: <code><?php echo esc_html(NXS_Official_Connectors::callback_url()); ?></code></p>
    <?php if (!empty($options['appKey']) && !empty($options['appSec']) && !$isNew) { ?><p><a class="button" href="<?php echo esc_url(add_query_arg(array('nxs_official_start'=>'pn','acc'=>$ii),NXS_Official_Connectors::callback_url())); ?>">Connect Pinterest</a></p><?php } else { ?><p>Save the App ID and App Secret, then connect Pinterest.</p><?php } ?>
    <?php if (!empty($options['refreshToken']) && !empty($options['officialApi']) && $options['officialApi']==='pn') { ?><p>OAuth account connected.</p><?php } ?>
    <p><label>Board ID <input list="pn-official-boards-<?php echo esc_attr($ii); ?>" name="pn[<?php echo esc_attr($ii); ?>][pnBoard]" value="<?php echo esc_attr($options['pnBoard']); ?>" /></label></p>
    <datalist id="pn-official-boards-<?php echo esc_attr($ii); ?>"><?php
      if (!empty($options['officialApi']) && $options['officialApi']==='pn') {
        $options['ii']=$ii; $token=NXS_Official_Connectors::access_token('pn',$options);
        if ($token!=='') { $boards=NXS_Official_Connectors::request('https://api.pinterest.com/v5/boards?page_size=250',$token,'GET',null,'pn');
          if (!empty($boards['items']) && is_array($boards['items'])) foreach ($boards['items'] as $board) if (!empty($board['id'])) echo '<option value="'.esc_attr($board['id']).'" label="'.esc_attr($board['name'] ?? $board['id']).'"></option>';
        }
      }
    ?></datalist>
    <p class="description">Choose a board from the list or enter its numeric ID. The app needs Pinterest board and Pin permissions.</p>
    <p><label>Default image URL <input type="url" size="55" name="pn[<?php echo esc_attr($ii); ?>][defImg]" value="<?php echo esc_attr($options['defImg']); ?>" /></label></p>
    <p><label><input type="radio" name="pn[<?php echo esc_attr($ii); ?>][cImgURL]" value="R" <?php checked($options['cImgURL'],'R'); ?>> Link to post</label> <label><input type="radio" name="pn[<?php echo esc_attr($ii); ?>][cImgURL]" value="N" <?php checked($options['cImgURL'],'N'); ?>> No clickthrough link</label></p>
    <?php $this->elemTitleFormat($ii,'Pin title','msgTFormat',$options['msgTFormat']); $this->elemMsgFormat($ii,'Pin description','msgFormat',$options['msgFormat']);
  }
  function advTab($ii,$options) { }
  function setNTSettings($post,$options) { foreach ($post as $ii=>$pval) { if (!isset($options[$ii])) $options[$ii]=array(); if (count($pval)===1 && isset($pval['do'])) { $options[$ii]['do']=$pval['do']; continue; } $options[$ii]=$this->saveCommonNTSettings($pval,$options[$ii]); foreach (array('pnBoard','defImg','cImgURL') as $field) if (isset($pval[$field])) $options[$ii][$field]=trim($pval[$field]); } return $options; }

  //#### Show Post->Edit Meta Box Settings
  function showEdPostNTSettings($ntOpts, $post){ $post_id = $post->ID; $nt = $this->ntInfo['lcode']; $ntU = $this->ntInfo['code'];
      foreach($ntOpts as $ii=>$ntOpt)  { $isFin = $this->checkIfSetupFinished($ntOpt); if (!$isFin) continue; 
        $pMeta = maybe_unserialize(get_post_meta($post_id, 'snap'.$ntU, true)); if (is_array($pMeta) && !empty($pMeta[$ii])) $ntOpt = $this->adjMetaOpt($ntOpt, $pMeta[$ii]);         
        
        if (empty($ntOpt['imgToUse'])) $ntOpt['imgToUse'] = ''; if (empty($ntOpt['urlToUse'])) $ntOpt['urlToUse'] = ''; $postType = isset($ntOpt['postType'])?$ntOpt['postType']:'';
        $msgFormat = !empty($ntOpt['msgFormat'])?htmlentities($ntOpt['msgFormat'], ENT_COMPAT, "UTF-8"):''; $msgTFormat = !empty($ntOpt['msgTFormat'])?htmlentities($ntOpt['msgTFormat'], ENT_COMPAT, "UTF-8"):''; 
        $imgToUse = $ntOpt['imgToUse'];  $urlToUse = $ntOpt['urlToUse'];  $ntOpt['ii']=$ii;
         
        $this->nxs_tmpltAddPostMeta($post, $ntOpt, $pMeta); ?>
        <tr style="<?php echo !empty($ntOpt['do'])?'display:table-row;':'display:none;'; ?>" class="nxstbldo nxstbldo<?php echo strtoupper($nt).$ii; ?>"><th scope="row" style="text-align:right; width:150px; padding-top: 5px; padding-right:10px;">Board ID</th>
                <td><input name="pn[<?php echo esc_attr($ii); ?>][pnBoard]" value="<?php echo esc_attr($ntOpt['pnBoard'] ?? ''); ?>" /></td>
        </tr> 
        <?php  $this->elemEdMsgFormat($ii, __('Message Format:', 'social-networks-auto-poster-facebook-twitter-g'),$msgFormat);         
          /* ## Select Image & URL ## */ nxs_showImgToUseDlg($nt, $ii, $imgToUse); nxs_showURLToUseDlg($nt, $ii, $urlToUse); $this->nxs_tmpltAddPostMetaEnd($ii);        
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
     <div class="nxsPostEd_ElemLabel"><?php _e('Board ID', 'social-networks-auto-poster-facebook-twitter-g'); ?></div>   
     <div class="nxsPostEd_Elem">   
       <input name="pn[<?php echo esc_attr($ii); ?>][pnBoard]" class="nxsEdElem" data-ii="<?php echo esc_attr($ii); ?>" data-nt="<?php echo esc_attr($nt); ?>" value="<?php echo esc_attr($ntOpt['pnBoard'] ?? ''); ?>" />
     </div>
   </div><?php
        // ## Select Image & URL 
        nxs_showImgToUseDlg($nt, $ii, $imgToUse);            
        nxs_showURLToUseDlg($nt, $ii, $urlToUse); 
  }
  //#### Save Meta Tags to the Post
  function adjMetaOpt($optMt, $pMeta){ $optMt = $this->adjMetaOptG($optMt, $pMeta);  //   prr($optMt);
    if (!empty($pMeta['pnBoard'])) $optMt['pnBoard'] = $pMeta['pnBoard'];     
    return $optMt;
  }
  
  function adjPublishWP(&$options, &$message, $postID){ 
      
  } 

}}

if (!function_exists("nxs_doPublishToPN")) { function nxs_doPublishToPN($postID, $options){ if (!is_array($options)) $options = maybe_unserialize(get_post_meta($postID, $options, true)); 
  $cl = new nxs_snapClassPN(); $cl->nt[$options['ii']] = $options; return $cl->publishWP($options['ii'], $postID); 
}}  
?>
