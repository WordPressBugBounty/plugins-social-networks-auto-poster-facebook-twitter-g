<?php
// SNAP 4.x non-API Pro functions, now bundled with SNAP.
// Keep function/class guards so legacy Helper addons can load in either order.
if (!function_exists('nxsv4SMAS')){function nxsv4SMAS(){}} if (!function_exists('nxs_doSMAS4')){function nxs_doSMAS4(){}} if (!function_exists('ns_SMASV41')){function ns_SMASV41(){}} 
if (!function_exists("rndString")) {function rndString($lngth){$str='';$chars="abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";$size=strlen($chars);for($i=0;$i<$lngth;$i++){$str .= $chars[rand(0,$size-1)];} return $str;}}
if (!function_exists("nxs_mkImgNm")){function nxs_mkImgNm($fn, $cType){ $iex = array(".png", ".jpg", ".gif", ".jpeg"); $map = array('image/gif'=>'.gif','image/jpeg'=>'.jpg','image/png'=>'.png');
  $fn = str_replace($iex, '', $fn); if (isset($map[$cType])){return $fn.$map[$cType];} else return $fn.".jpg";    
}}
if (!function_exists("nxs_clFN")){ function nxs_clFN($fn){$sch = array("?", "[", "]", "/", "\\", "=", "<", ">", ":", ";", ",", "'", "\"", "&", "$", "#", "*", "(", ")", "|", "~", "`", "!", "{", "}"); 
  return trim(preg_replace('/[\s-]+/', '-', str_replace($sch, '', $fn)), '.-_');    
}}
if (!class_exists("nxs_IPTC")) { class nxs_IPTC { const CREATED_DATE = '055'; const CREATED_TIME = '060'; const COPYRIGHT_STRING = '116'; const CAPTION = '120';
    private $_meta = array(); private $_hasMeta = false; private $_allowedExt = array('jpg', 'jpeg', 'pjpeg'); private $_filename;
    public function __construct($filename)    {
        if (!file_exists($filename)) { throw new Exception( 'Image not found!' ); }
        if (!is_writable($filename)) { throw new Exception( "File \"{$filename}\" is not writable!" ); }
        $parts = explode('.', strtolower($filename));
        if (!in_array(end($parts), $this->_allowedExt)) { throw new Exception( 'Support only for the following extensions: ' . implode(',', $this->_allowedExt) ); }
        $size = getimagesize($filename, $imageinfo);
        if (empty($size['mime']) || $size['mime'] != 'image/jpeg') { throw new Exception( 'Support only JPEG images' ); }
        $this->_hasMeta = isset($imageinfo["APP13"]);
        if ($this->_hasMeta) { $this->_meta = iptcparse($imageinfo["APP13"]); }
        $this->_filename = $filename;
    }
    public function set($tag, $data) { $data = $this->_charset_decode($data); $this->_meta["2#{$tag}"] = array($data); $this->_hasMeta = true; return $this; }   
    public function binary() { $iptc = '';
        foreach (array_keys($this->_meta) as $key) {
            $tag = str_replace("2#", "", $key);
            foreach ($this->_meta[$key] as $value)  $iptc .= $this->iptcMakeTag(2, $tag, $value);
        } return $iptc;
    }
    public function iptcMakeTag($rec, $dat, $val){
        $iptcTag = chr(0x1c) . chr($rec) . chr($dat);
        if (is_array($val)) { $src = ''; foreach ($val as $item) { $len = strlen($item); $src .= $iptcTag . $this->_testBitSize($len) . $item; } return $src; }
        $len = strlen($val); $src = $iptcTag . $this->_testBitSize($len) . $val; return $src;
    }
    public function write() { $content = iptcembed($this->binary(), $this->_filename, 0); if ($content === false) { throw new Exception( 'Failed to save IPTC data into file' ); } unlink($this->_filename);
        if ($file = fopen($this->_filename, "w")) { fwrite($file, $content); fclose($file); return true; } return false;
    }
    private function _testBitSize($len) { if ($len < 0x8000) return chr($len >> 8) . chr($len & 0xff);
        return chr(0x1c) . chr(0x04) . chr(($len >> 24) & 0xff) . chr(($len >> 16) & 0xff) . chr(($len >> 8) & 0xff) . chr(($len) & 0xff);
    }
    private function _charset_decode($data) { $result = array();
        if (is_array($data)) { $iterator = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($data));
            foreach ($iterator as $key => $value) $result[] = utf8_decode($value); 
        } else return utf8_decode($data);
        return $result;
    }
    private function _charset_encode($data) { $result = array();
        if (is_array($data)) { $iterator = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($data));
            foreach ($iterator as $key => $value)  $result[] = utf8_encode($value);
        } else return utf8_encode($data);
        return $result;
    }
}}
if (!function_exists("nxs_doSMAS1")) { function nxs_doSMAS1($obj, $var){ if (method_exists($obj,'showNewNTSettings')) $obj->showNewNTSettings($var); }}
if (!function_exists('nxs_v4doSMAS5')){function nxs_v4doSMAS5($nt, $ii, $ntOpt){ $ntU = strtoupper($nt); if ( isset($_GET['page']) && $_GET['page']=='NextScripts_SNAP.php') return;
  global $nxs_SNAP, $nxs_plurl; $delay="902397431"; if (!isset($nxs_SNAP)) return; $options = $nxs_SNAP->nxs_options; if ($options['nxsHTDP']=='I') return '';
  if (empty($ntOpt['timeToRun'])) $ntOpt['timeToRun'] = ''; ?>
  <div class="nxsPostEd_ElemWrap"><div class="nxstbldo nxstbldo<?php echo strtoupper($nt).$ii; ?>"><span style="font-weight: bold;"><?php _e('Time to post:', 'social-networks-auto-poster-facebook-twitter-g') ?></span>
  <input type="hidden" name="<?php echo $nt; ?>[<?php echo $ii; ?>][timeToRun]" id="<?php echo $nt; ?><?php echo $ii; ?>timeToRun"  value="<?php echo $ntOpt['timeToRun'];?>" />
  <a href="#" onclick="var dd = jQuery('#<?php echo $nt; ?><?php echo $ii; ?>timeToRun').val(); if (dd!='') nxs_fillTime(dd*1000); jQuery('#nxs_timeID').val('<?php echo $nt; ?><?php echo $ii; ?>'); jQuery.pgwModal({ target: '#showSetTimeInt', title: 'Post', maxWidth: 800, closeOnBackgroundClick : true});  return false;">[<?php _e('Set time', 'social-networks-auto-poster-facebook-twitter-g') ?>]</a> 
  <span id="<?php echo $nt; ?><?php echo $ii; ?>timeToRunTxt"><?php // prr($ntOpt);
    if (!isset($ntOpt['timeToRun']) || (int)$ntOpt['timeToRun']<(!empty($ntOpt['postTime'])?$ntOpt['postTime']:time())) { 
      if (!isset($ntOpt['nMin'])) $ntOpt['nMin'] = 0; if (!isset($ntOpt['nHrs'])) $ntOpt['nHrs'] = 0; if (!isset($ntOpt['nDays'])) $ntOpt['nDays'] = 0;                  
      $delay = (int)$ntOpt['nDays']*86400+(int)$ntOpt['nHrs']*3600+(int)$ntOpt['nMin']*60; 
      if ($delay>0) { $ntOpt['timeToRun'] = (!empty($ntOpt['postTime'])?$ntOpt['postTime']:time())+(int)$delay; 
        echo date_i18n('F j, Y, G:i', $ntOpt['timeToRun']+( empty($ntOpt['postTime'])?(get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ):0));
        echo " (Delay: ".((int)$ntOpt['nDays']>0?$ntOpt['nDays'].' Day'.((int)$ntOpt['nDays']!=1?'s, ':', '):'').((int)$ntOpt['nHrs']>0?$ntOpt['nHrs'].' Hour'.((int)$ntOpt['nHrs']!=1?'s, ':', '):'').(int)$ntOpt['nMin'].' Minute'.((int)$ntOpt['nMin']!=1?'s. ':'. ').")";       
      } elseif ((int)$ntOpt['timeToRun']>0) echo date_i18n('F j, Y, G:i', $ntOpt['timeToRun']+( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS )); else { /* $ntOpt['timeToRun'] = time(); */ _e('Immediately'); }
    } else echo date_i18n('F j, Y, G:i', $ntOpt['timeToRun']+( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS )); ?> </span>
  <div style="position: relative;" id="do<?php echo $ntU; ?>_T<?php echo $ii; ?>"></div></div> </div> <?php
}}
if (!function_exists('nxs_v4doSMAS5X')){function nxs_v4doSMAS5X($nt, $ii, $ntOpt){ $ntU = strtoupper($nt); if ( isset($_GET['page']) && $_GET['page']=='NextScripts_SNAP.php') return;
  global $nxs_SNAP, $nxs_plurl; $delay="902397431"; if (!isset($nxs_SNAP)) return; $options = $nxs_SNAP->nxs_options; if ($options['nxsHTDP']=='I') return '';
  if (empty($ntOpt['timeToRun'])) $ntOpt['timeToRun'] = 'i'; $nttD = $ntOpt['timeToRun']; 
  //## If post shedulled for the future remove posting times that occure before that time.
  if (!empty($nttD)) { $nttA = explode('|',$nttD);     
    if (!empty($ntOpt['postTime']) && $ntOpt['postTime']>time()) { $nttAX = array(); $immid = false; foreach ($nttA as $jj=>$ntt) if ($ntt=='i' || $ntt<$ntOpt['postTime']) $immid = true; else $nttAX[] = $ntt; if ($immid) array_unshift($nttAX , 'i'); $nttA = $nttAX; } 
    $nttD = implode('|',$nttA); $ntOpt['timeToRun'] = $nttD;
  } ?>
  <div class="nxsPostEd_ElemWrap"><div class="nxstbldo nxstbldo<?php echo strtoupper($nt).$ii; ?>"><span style="font-weight: bold;"><?php _e('Time to post:', 'social-networks-auto-poster-facebook-twitter-g') ?></span>
  <input type="hidden" name="<?php echo $nt; ?>[<?php echo $ii; ?>][timeToRun]" id="<?php echo $nt; ?><?php echo $ii; ?>timeToRun"  value="<?php echo $ntOpt['timeToRun'];?>" />
  <input type="hidden" id="<?php echo $nt; ?><?php echo $ii; ?>timeToRunWM"  value="A" />
  <a href="#" onclick="nxs_showTimeDialog('<?php echo $nt.$ii; ?>','A'); return false;">[<?php _e('Add', 'social-networks-auto-poster-facebook-twitter-g') ?>]</a> 
  <span id="<?php echo $nt; ?><?php echo $ii; ?>timeToRunTxt"><?php if (!empty($nttD)) { $nttA = explode('|',$nttD); 
    foreach ($nttA as $jj=>$ntt) { 
    if (!isset($ntt) || (int)$ntt<(!empty($ntOpt['postTime'])?$ntOpt['postTime']:time())) { 
      if (!isset($ntOpt['nMin'])) $ntOpt['nMin'] = 0; if (!isset($ntOpt['nHrs'])) $ntOpt['nHrs'] = 0; if (!isset($ntOpt['nDays'])) $ntOpt['nDays'] = 0;                  
      $delay = (int)$ntOpt['nDays']*86400+(int)$ntOpt['nHrs']*3600+(int)$ntOpt['nMin']*60;
      if ($delay>0) { $ntt = (!empty($ntOpt['postTime'])?$ntOpt['postTime']:time())+(int)$delay; echo date_i18n('F j, Y, G:i', $ntt+( empty($ntOpt['postTime'])?(get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ):0));
        echo " (Delay: ".((int)$ntOpt['nDays']>0?$ntOpt['nDays'].' Day'.((int)$ntOpt['nDays']!=1?'s, ':', '):'').((int)$ntOpt['nHrs']>0?$ntOpt['nHrs'].' Hour'.((int)$ntOpt['nHrs']!=1?'s, ':', '):'').(int)$ntOpt['nMin'].' Minute'.((int)$ntOpt['nMin']!=1?'s. ':'. ').")";       
      } elseif ((int)$ntt>0 && (int)$ntt>(!empty($ntOpt['postTime'])?$ntOpt['postTime']:time())) echo date_i18n('F j, Y, G:i', $ntt+( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS )); else { /* $ntt = time(); */ _e('Immediately'); ?>
        <a href="#" onclick="nxs_showTimeDialog('<?php echo $nt.$ii; ?>','C'); return false;">[<?php _e('Change', 'social-networks-auto-poster-facebook-twitter-g') ?>]</a> <?php      
      }
    } else { echo date_i18n('F j, Y, G:i', $ntt+( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ))." "; ?>
       <a href="#" onclick="nxs_showTimeDialog('<?php echo $nt.$ii; ?>','<?php echo $jj; ?>'); return false;">[<?php _e('Change', 'social-networks-auto-poster-facebook-twitter-g') ?>]</a>&nbsp;|&nbsp; <?php 
    }    
  }} ?> </span>
  <div style="position: relative;" id="do<?php echo $ntU; ?>_T<?php echo $ii; ?>"></div></div> </div>  <?php
}}
if (!function_exists('nxs_v4doSMAS41')){function nxs_v4doSMAS41($options){  ?><br/>
<b><?php _e('Post:', 'social-networks-auto-poster-facebook-twitter-g'); ?> </b> <?php _e('You can limit reposting to only specific days and hours.', 'social-networks-auto-poster-facebook-twitter-g'); ?>&nbsp;(<?php _e('Current server time is: ', 'social-networks-auto-poster-facebook-twitter-g'); echo date_i18n('Y-m-d H:i'); ?>)     
     <br/>
<div style="padding-left: 15px;">
     <input type="radio" name="nxs_rpstr[rpstBtwHrsType]" value="A" <?php if (!isset($options['rpstBtwHrsType']) || $options['rpstBtwHrsType']=='A') echo 'checked="checked"'; ?> />     
     <?php _e('All the time', 'social-networks-auto-poster-facebook-twitter-g'); ?>
     <br/>
     <input type="radio" name="nxs_rpstr[rpstBtwHrsType]" value="D" <?php if (isset($options['rpstBtwHrsType']) && $options['rpstBtwHrsType']=='D') echo 'checked="checked"'; ?> />
     <?php _e('Only between hours', 'social-networks-auto-poster-facebook-twitter-g'); ?>&nbsp;<input type="text" name="nxs_rpstr[rpstBtwHrsF]" style="width: 35px;" value="<?php echo isset($options['rpstBtwHrsF'])?$options['rpstBtwHrsF']:'8'; ?>" />
     &nbsp;<?php _e('and', 'social-networks-auto-poster-facebook-twitter-g'); ?>&nbsp;
     <input type="text" name="nxs_rpstr[rpstBtwHrsT]" style="width: 35px;" value="<?php echo isset($options['rpstBtwHrsT'])?$options['rpstBtwHrsT']:'21'; ?>" />
     &nbsp;<?php _e('on', 'social-networks-auto-poster-facebook-twitter-g');?>&nbsp;
     <input value="7" <?php if (isset($options['rpstBtwDays']) && in_array('7', $options['rpstBtwDays'])) echo "checked"; ?> type="checkbox" name="nxs_rpstr[rpstBtwDays][]"/>&nbsp;Sun&nbsp;&nbsp;
     <input value="1" <?php if (!isset($options['rpstBtwDays']) || in_array('1', $options['rpstBtwDays'])) echo "checked"; ?> type="checkbox" name="nxs_rpstr[rpstBtwDays][]"/>&nbsp;Mon&nbsp;&nbsp;
     <input value="2" <?php if (!isset($options['rpstBtwDays']) || in_array('2', $options['rpstBtwDays'])) echo "checked"; ?> type="checkbox" name="nxs_rpstr[rpstBtwDays][]"/>&nbsp;Tue&nbsp;&nbsp;
     <input value="3" <?php if (!isset($options['rpstBtwDays']) || in_array('3', $options['rpstBtwDays'])) echo "checked"; ?> type="checkbox" name="nxs_rpstr[rpstBtwDays][]"/>&nbsp;Wen&nbsp;&nbsp;
     <input value="4" <?php if (!isset($options['rpstBtwDays']) || in_array('4', $options['rpstBtwDays'])) echo "checked"; ?> type="checkbox" name="nxs_rpstr[rpstBtwDays][]"/>&nbsp;Th&nbsp;&nbsp;
     <input value="5" <?php if (!isset($options['rpstBtwDays']) || in_array('5', $options['rpstBtwDays'])) echo "checked"; ?> type="checkbox" name="nxs_rpstr[rpstBtwDays][]"/>&nbsp;Fr&nbsp;&nbsp;
     <input value="6" <?php if (isset($options['rpstBtwDays']) && in_array('6', $options['rpstBtwDays'])) echo "checked"; ?> type="checkbox" name="nxs_rpstr[rpstBtwDays][]"/>&nbsp;Sat&nbsp;&nbsp;
     </div> <?php
}}
if (!function_exists('nxs_v4doSMAS412')){function nxs_v4doSMAS412($options){ ?><br/>
      <input type="radio" name="nxs_rpstr[rpstStop]" value="O" <?php if (empty($options['rpstStop']) || $options['rpstStop']=='O') echo 'checked="checked"'; ?> /><?php _e('Turn Reposting Off', 'social-networks-auto-poster-facebook-twitter-g') ?><br/>
      <input type="radio" name="nxs_rpstr[rpstStop]" value="W" <?php if (!empty($options['rpstStop']) && $options['rpstStop']=='W') echo 'checked="checked"'; ?> /><?php _e('Wait for new posts', 'social-networks-auto-poster-facebook-twitter-g') ?><br/>
      <input type="radio" name="nxs_rpstr[rpstStop]" value="R" <?php if (!empty($options['rpstStop']) && $options['rpstStop']=='R') echo 'checked="checked"'; ?> /><?php _e('Loop it. Reset and Start from the beginning', 'social-networks-auto-poster-facebook-twitter-g') ?><br/>
      <input type="radio" name="nxs_rpstr[rpstStop]" value="N" <?php if (!empty($options['rpstStop']) && $options['rpstStop']=='N') echo 'checked="checked"'; ?> /><?php _e('Repeat', 'social-networks-auto-poster-facebook-twitter-g') ?> <input type="text" name="nxs_rpstr[rpstStopRpt]" style="width: 35px;" value="<?php echo isset($options['rpstStopRpt'])?$options['rpstStopRpt']:'5'; ?>" />&nbsp;<?php _e('Times', 'social-networks-auto-poster-facebook-twitter-g') ?><br/>
      <?php
}}
if (!function_exists('nxs_v4doSMAS413')){function nxs_v4doSMAS413($options){  $currTime = time() + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ); ?>
<br/><input type="radio" name="nxs_rpstr[rpstTimes]" value="S" class="rpstrTimes" <?php if (!empty($options['rpstTimes']) && $options['rpstTimes']=='S') echo 'checked="checked"'; ?> /> <?php _e('Post on specific date/time', 'social-networks-auto-poster-facebook-twitter-g') ?>
<div style="margin-left:20px;display:<?php echo (!empty($options['rpstTimes']) && $options['rpstTimes']=='S')?'block':'none';?>;" id="rpstPostSpTimesOptions"><?php
 if (!empty($options['rpstCustTD']) && is_array($options['rpstCustTD'])) foreach ($options['rpstCustTD'] as $td) { 
   if(strtotime($td)>$currTime) echo '<div><input type=\'text\' class=\'rpstPostDtSpTimesOptions search\' name=\'nxs_rpstr[rpstCustTD][]\' value="'.$td.'" /><a href="#" onclick="jQuery(this).parent().remove();return false;"><img src="'.NXS_PLURL.'img/delIcon.png"/></a></div>';
 }
?><br/><a href="#" id="rpstsAddTime" onclick="jQuery('#rpstPostSpTimesOptions').append('<div><input type=\'text\' class=\'rpstPostDtSpTimesOptionsX search\' name=\'nxs_rpstr[rpstCustTD][]\' /><a href=\'#\' onclick=\'jQuery(this).parent().remove();return false;\'><img src=\'<?php echo NXS_PLURL;?>img/delIcon.png\'/></a></div>');jQuery('.rpstPostDtSpTimesOptionsX').adatepicker({language: 'en', minDate: new Date(), timepicker: true}); return false;">[<?php _e('Add post date/time', 'social-networks-auto-poster-facebook-twitter-g') ?>]</a></div><script type="text/javascript">jQuery('.rpstPostDtSpTimesOptions').adatepicker({language: 'en',minDate: new Date(), timepicker: true});</script>
<?php
}}    
if (!function_exists('nxs_cleanSNAP_post_meta')){ function nxs_cleanSNAP_post_meta($po){ $poOut = array(); if (!empty($po)&&is_array($po)) {foreach ($po as $ii=>$p) if ((isset($ii) && ($ii === "0"||$ii === 0)) || !empty($ii)) $poOut[$ii] = $p; return $poOut;} else return $po; }}

if (!function_exists("nxs_v4doSMAS2")){ function nxs_v4doSMAS2($postObj, $NXS_POST, $publtype, $aj=false){  global $nxs_SNAP, $nxs_snapAvNts, $blog_id, $nxs_tpWMPU;  //  echo " | nxs_doSMAS2 | "; prr($postArr);
   if (!isset($nxs_SNAP)) return; $options = $nxs_SNAP->nxs_options;  $isPost = isset($NXS_POST["snapEdIT"]);  $postID = $postObj->ID;       
   $uid = $postObj->post_author; $isItUserWhoCan = (!user_can($uid, 'manage_options' ) && user_can($uid, 'haveown_snap_accss')); //if ($isItUserWhoCan) $uid = $postUser;  
   if ($isItUserWhoCan) { $nxs_SNAP = new nxs_SNAP($postUser); $networks = $nxs_SNAP->nxs_acctsU; $uid = $postUser; global $nxs_uid; $nxs_uid = $uid; } else { $networks = $nxs_SNAP->nxs_accts; }
   foreach ($nxs_snapAvNts as $avNt) { 
    if (!empty($networks[$avNt['lcode']]) && count($networks[$avNt['lcode']])>0) { $clName = 'nxs_snapClass'.$avNt['code']; 
      if ($isPost && isset($NXS_POST[$avNt['lcode']])) $po = $NXS_POST[$avNt['lcode']]; else $po =  maybe_unserialize(get_post_meta($postID, 'snap'.$avNt['code'], true)); $po = nxs_cleanSNAP_post_meta($po);
      if (isset($po) && is_array($po)) $isPostMeta = true; else { $isPostMeta = false; $po = $networks[$avNt['lcode']]; update_post_meta($postID, 'snap'.$avNt['code'], $po); }
      delete_post_meta($postID, 'snap_isAutoPosted'); add_post_meta($postID, 'snap_isAutoPosted', time());
      foreach ($po as $ii=>$pp){ $optMt = $networks[$avNt['lcode']][$ii]; $ntClInst = new $clName();  if ($isPostMeta)  $optMt = $ntClInst->adjMetaOpt($optMt, $pp); if (!((class_exists('NXS_PostSocial_Accounts') && NXS_PostSocial_Accounts::is_account($optMt)) || $ntClInst->checkIfSetupFinished($optMt))) continue;  $optMt['ii'] = $ii; $publTempType = '';
        if ($optMt['do']=='2'){ $rMsg=nxs_snapCheckFilters($optMt, $postObj); if($rMsg!==false){ nxs_LogIt('I', 'Skipped', $avNt['name'].' ('.$optMt['nName'].')','', 'Filter(Network) - Excluded - Post ID:('.$postID.')',$rMsg, 'snap', $uid ); continue; } else $optMt['do'] = 1; }
        if ($optMt['do']=='1') {  $publTempType = '';
          if ($publtype=='A' && (!empty($optMt['nDays']) || !empty($optMt['nMin']) || !empty($optMt['nHrs']) || !empty($optMt['timeToRun']))) $publTempType='S';     //   nxsLogIt($avNt['lcode'].'|'.$ii.'|'. $avNt['name'].' - '.$publtype.' X '.$publTempType.' Y '.$optMt['timeToRun']);
          
          if ($publtype=='A') { $publTempType='S'; if ( empty($optMt['nDays']) && empty($optMt['nMin']) & empty($optMt['nHrs']) && empty($optMt['timeToRun'])) $optMt['timeToRun'] = 'i'; }
          
          if ($publtype=='S' || $publTempType =='S') { $publTempType = ''; if (!isset($optMt['nMin'])) $optMt['nMin'] = 0; if (!isset($optMt['nHrs'])) $optMt['nHrs'] = 0; if (!isset($optMt['nDays'])) $optMt['nDays'] = 0;                    
            if ($optMt['nHrs']>0 || $optMt['nMin']>0 || $optMt['nDays']>0) { $delay = $optMt['nMin']*60+$optMt['nHrs']*3600+$optMt['nDays']*86400;
              nxs_LogIt('I', 'Delayed', $avNt['name'].' ('.$optMt['nName'].')', '','Post has been delayed',' for '.$delay.' Seconds ('.($optMt['nDays']>0?$optMt['nDays'].' Days':'')." ".($optMt['nHrs']>0?$optMt['nHrs'].' Hours':'')." ".($optMt['nMin']>0?$optMt['nMin'].' Minutes':'').')','snap',$uid );
            } else $delay = rand(5,30);               
            global $wpdb; if (empty($optMt['timeToRun'])) $optMt['timeToRun'] = time(); $ttrs = explode('|',$optMt['timeToRun']); 
            foreach ($ttrs as $ttr) { if ($ttr=='i') $ttr = time();  $ttr =  $ttr + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS )+$delay;               
              $dbItem = array('datecreated'=>date_i18n('Y-m-d H:i:s'), 'type'=>'S', 'postid'=>$postID, 'nttype'=>$avNt['code'], 'refid'=>$ii, 'timetorun'=> date_i18n('Y-m-d H:i:s', $ttr), 'extInfo'=>serialize($optMt), 'descr'=> (!empty($optMt['nName'])?'('.$avNt['code'].' - '.$optMt['nName'].') ':'').'Post ID:('.$postID.')', 'uid'=>$uid);  
              $nxDB = $wpdb->insert( $wpdb->prefix . "nxs_query", $dbItem );  $lid = $wpdb->insert_id;               
              nxs_LogIt('BI', 'Scheduled', $avNt['name'].' ('.$optMt['nName'].')','','Scheduled for '.date_i18n('Y-m-d H:i:s', $ttr).")", ' PostID:('.$postID.')','snap',$uid ); 
            }
          } else { if (class_exists('NXS_PostSocial_Accounts') && NXS_PostSocial_Accounts::is_account($optMt)) { $ntClInst->nt[$ii] = $optMt; $ntClInst->publishWP($ii, $postID); } else { $fname = 'nxs_doPublishTo'.$avNt['code']; $fname($postID, $optMt); } }
        } else {  nxs_LogIt('GR', 'Skipped', $avNt['name'].' ('.$optMt['nName'].')','', '-=[Unchecked Account]=-', 'PostID: '.$postID.'','snap',$uid ); }
      }
    }                   
   }
}}
if (!function_exists('nxs_v4doSMAS4')){function nxs_v4doSMAS4($nt, $ii, $opts){ $hrs = isset($opts['nHrs'])?$opts['nHrs']:0; $min = isset($opts['nMin'])?$opts['nMin']:0; $days = isset($opts['nDays'])?$opts['nDays']:0;
  $outTXt = '<div style="width:100%;"><strong>Posting Delay:</strong> <i>You can delay posting to this account. Will work only if "Settings Tab"->"How to make auto-posts?" is set to "Schedule"</i> </div> Delay for: '; 
  $outD = '<input  style="width:30px;" value="'.$days.'" type="text" name="'.$nt.'['.$ii.'][delayDays]"/> Days &nbsp;&nbsp;&nbsp;';    
  $outH = '<select id="'.$nt.$ii.'delayHrs" name="'.$nt.'['.$ii.'][delayHrs]"><option value="0">0</option>
      <option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option><option value="5">5</option><option value="6">6</option><option value="7">7</option>
      <option value="8">8</option><option value="9">9</option><option value="10">10</option><option value="11">11</option><option value="12">12</option><option value="13">13</option><option value="14">14</option>
      <option value="15">15</option><option value="16">16</option><option value="17">17</option><option value="18">18</option><option value="19">19</option><option value="20">20</option><option value="21">21</option>
      <option value="22">22</option><option value="23">23</option></select> Hours &nbsp;&nbsp;&nbsp;';
  $outM = '<select id="'.$nt.$ii.'delayMin" name="'.$nt.'['.$ii.'][delayMin]"><option value="0">0</option>
      <option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option><option value="5">5</option><option value="6">6</option><option value="7">7</option>
      <option value="8">8</option><option value="9">9</option><option value="10">10</option><option value="11">11</option><option value="12">12</option><option value="13">13</option><option value="14">14</option>
      <option value="15">15</option><option value="16">16</option><option value="17">17</option><option value="18">18</option><option value="19">19</option><option value="20">20</option><option value="21">21</option>
      <option value="22">22</option><option value="23">23</option><option value="24">24</option><option value="25">25</option><option value="26">26</option><option value="27">27</option><option value="28">28</option>
      <option value="29">29</option><option value="30">30</option><option value="31">31</option><option value="32">32</option><option value="33">33</option><option value="34">34</option><option value="35">35</option>
      <option value="36">36</option><option value="37">37</option><option value="38">38</option><option value="39">39</option><option value="40">40</option><option value="41">41</option><option value="42">42</option>
      <option value="43">43</option><option value="44">44</option><option value="45">45</option><option value="46">46</option><option value="47">47</option><option value="48">48</option><option value="49">49</option>
      <option value="50">50</option><option value="51">51</option><option value="52">52</option><option value="53">53</option><option value="54">54</option><option value="55">55</option><option value="56">66</option>
      <option value="57">57</option><option value="58">58</option><option value="59">59</option></select> Minutes <br/><br/>';
  $outH = str_replace('value="'.$hrs.'"',' selected="selected" value="'.$hrs.'"', $outH); $outM = str_replace('value="'.$min.'"',' selected="selected" value="'.$min.'"', $outM); return $outTXt.$outD.$outH.$outM;
}}
if (!function_exists('nxs_doSMAS42')){function nxs_doSMAS42($options){ ?><option value="1" <?php if (isset($options['rpstType']) && $options['rpstType']=='1') echo 'selected="selected"' ?>>Randomly</option><?php }}
if (!function_exists('nxs_doSMAS7')) {function nxs_doSMAS7($args, $filter){//## Taxonomies     
    if( !empty( $filter['nxs_tax_names'] ) && !empty( $filter['nxs_term_names'] ) || ( !empty( $filter['nxs_count_term_compares'] ) && $filter['nxs_count_term_compares'] > 1 )  ) {        
      $term_compares = array(); $count_term_compares = empty( $filter['nxs_count_term_compares'] ) ? 1 : $filter['nxs_count_term_compares']; $term_compares['relation'] = empty( $filter['nxs_term_relation'] ) ? 'AND' : $filter['nxs_term_relation'];        
      for( $i = 1; $i <= $count_term_compares; $i++ ) { $postfix = $i > 1 ? '_'. $i : ''; if (empty($filter["nxs_tax_names$postfix"])) continue; $new_term_compare = array();            
        if( !empty( $filter["nxs_tax_names$postfix"] ) ) { $new_term_compare['taxonomy'] = $filter["nxs_tax_names$postfix"];  $new_term_compare['field'] = 'term_id';           }
        if( !empty( $filter["nxs_term_names$postfix"] ) ) $new_term_compare['terms'] = array_map( 'nxs_get_normalize_parameter', $filter["nxs_term_names$postfix"] );        
        if( !empty( $filter["nxs_term_operator$postfix"] ) ) $new_term_compare['operator'] = $filter["nxs_term_operator$postfix"];                
        $new_term_compare['include_children'] = empty( $filter["nxs_term_children$postfix"] ) ? true : $filter["nxs_term_children$postfix"];                
        if( !empty( $new_term_compare ) ) $term_compares[] = $new_term_compare;                
      } $args['tax_query'] = $term_compares;    
    }        
    //## Custom fields
    if( !empty( $filter['post_meta'][0]['key'] ) || !empty( $filter['post_meta'][0]['value'] ) || ( !empty( $filter['nxs_count_meta_compares'] ) && $filter['nxs_count_meta_compares'] > 1 )  ) {
        $meta_compares = array(); $count_compares = empty( $filter['nxs_count_meta_compares'] ) ? 1 : $filter['nxs_count_meta_compares']; $filter['post_meta'][0]['relation'] = empty( $filter['post_meta'][0]['relation'] ) ? 'AND' : $filter['post_meta'][0]['relation'];        
        for( $i = 0; $i < $count_compares; $i++ ) {  $new_compare = array();            
            if( !empty( $filter['post_meta'][$i]['key'] ) ) $new_compare['key'] = $filter['post_meta'][$i]['key'];            
            if( !empty( $filter['post_meta'][$i]['value'] ) ) { $values = $filter['post_meta'][$i]['value'];     //  prr($values);   prr(count($values));                       
                if (count($values)>1){ $new_compare['value'] = array();    
                  foreach( $values as $value ) { $position_delimiter = mb_strrpos( $value, '||' );                    
                    if ($position_delimiter>0) { $meta_value = get_post_meta( intval( mb_substr( $value, $position_delimiter + 2 ) ), mb_substr( $value, 0, $position_delimiter ), true ); } else $meta_value = $value;
                    $new_compare['value'][] = is_array( $meta_value ) ? serialize( $meta_value ) : $meta_value;
                  }        
                } else  $new_compare['value'] = $values[0];
            }        
            if( !empty( $filter['post_meta'][$i]['operator'] ) ) { switch( $filter['post_meta'][$i]['operator'] ) { case 'gt': $new_compare['compare'] = '>'; break; case 'gt=': $new_compare['compare'] = '>='; break; case 'lt': $new_compare['compare'] = '<'; break;
                case 'lt=': $new_compare['compare'] = '<='; break; default: $new_compare['compare'] = $filter['post_meta'][$i]['operator'];
            }} if( !empty( $filter['post_meta'][$i]['type'] ) ) $new_compare['type'] = $filter['post_meta'][$i]['type']; if( !empty( $new_compare ) ) $meta_compares[] = $new_compare;                
        } if (!empty($meta_compares)) $meta_compares['relation'] = $filter['post_meta'][0]['relation']; $args['meta_query'] = $meta_compares;    
    } return $args;   
}}

// SNAP 4.x Multi User Pro functions.
if (!function_exists('ns_SMASV4M1')){function ns_SMASV4M1(){}}
if (!function_exists('nxssnapmu_columns_head')){ function nxssnapmu_columns_head($defaults) { $defaults['snap_sasn'] = '{SNAP} Settings'; return $defaults;}}
if (!function_exists('nxssnapmu_columns_content')){ function nxssnapmu_columns_content($column_name, $blogID) { global $nxs_plurl, $nxs_SNAP;
  if ($column_name == 'snap_sasn') { $def = (!empty($nxs_SNAP->nxs_ntoptions['nxsSUType'])?$nxs_SNAP->nxs_ntoptions['nxsSUType']:'O'); $l = (!empty($nxs_SNAP->nxs_ntoptions['l'])?$nxs_SNAP->nxs_ntoptions['l']:array());
  ?>       <img id="nxsWmpuLoadingImg<?php echo $blogID; ?>" style="display: none;" src='<?php echo $nxs_plurl; ?>img/ajax-loader-sm.gif' /> 
        <input type="radio" onchange="nxs_saveSiteSets('<?php echo $blogID; ?>', this.value);" name="nxs_snapSID<?php echo $blogID; ?>" value="O" <?php if (isset($l[$blogID]) && $l[$blogID]=='O' || (!isset($l[$blogID]) && $def =='O')) echo 'checked="checked"'; ?> />Can use own SN Accounts <a href="<?php echo esc_url(get_admin_url($blogID)); ?>options-general.php?page=NextScripts_SNAP.php">[Manage]</a><br/>        
        <input type="radio" onchange="nxs_saveSiteSets('<?php echo $blogID; ?>', this.value);" name="nxs_snapSID<?php echo $blogID; ?>" value="S" <?php if (isset($l[$blogID]) && $l[$blogID]=='S' || (!isset($l[$blogID]) && $def =='S')) echo 'checked="checked"'; ?> />Auto-post to Super Admin's SN<br/>        
        <input type="radio" onchange="nxs_saveSiteSets('<?php echo $blogID; ?>', this.value);" name="nxs_snapSID<?php echo $blogID; ?>" value="D" <?php if (isset($l[$blogID]) && $l[$blogID]=='D' || (!isset($l[$blogID]) && $def =='D')) echo 'checked="checked"'; ?> />Disable SNAP<br/>        
       <?php 
    }   
}}
if (!function_exists('nxs_add_style')){ function nxs_add_style() {  wp_nonce_field( 'nxssnap', 'nxssnap_wpnonce' ); echo '<style>#blog_id { width:7%; }</style>';?> 
    <script type="text/javascript">    
    function nxs_saveSiteSets(sid, val){ var bgn=''; if (sid!='A') bgn=sid;  jQuery('#nxsWmpuLoadingImg'+bgn).show();
      var data = { action: 'nxs_saveSiteSets', id: 0, sid: sid, sset: val, _wpnonce: jQuery('input#nxssnap_wpnonce').val()}; jQuery.post(ajaxurl, data, function(response) {  
         if (sid=='A') alert('OK. Done.');   jQuery('#nxsWmpuLoadingImg'+bgn).hide();
      });
    }</script><?php
}}
if (!function_exists('nxs_saveNewSiteDefSets_ajax')){ function nxs_saveNewSiteDefSets_ajax(){ check_ajax_referer('nxssnap');  $ntoptions = get_site_option('nxsSNAPOptions');    
   if (isset($_POST['sset'])) $ntoptions['nxsSUType'] = $_POST['sset']; update_site_option('nxsSNAPOptions', $ntoptions);  echo "OK"; die();
  } add_action('wp_ajax_nxs_saveNewSiteDefSets', 'nxs_saveNewSiteDefSets_ajax'); 
}
if (!function_exists('nxs_saveSiteSets_ajax')){ function nxs_saveSiteSets_ajax(){ check_ajax_referer('nxssnap'); global $nxs_SNAP; $nxsBlgSts = !(empty($nxs_SNAP->nxs_ntoptions['l']))?$nxs_SNAP->nxs_ntoptions['l']:array();
   if ($_POST['sid']=='A'){  global $wpdb; $allBlogs = $wpdb->get_results("SELECT blog_id FROM wp_blogs where blog_id > 1"); foreach( $allBlogs as $aBlog ) $nxsBlgSts[$aBlog] = $_POST['sset']; } 
     else { $nxsBlgSts[$_POST['sid']] = $_POST['sset'];   if( is_super_admin() && $_POST['sid']=='1' &&  $_POST['sset']!='O')  $nxsBlgSts[$_POST['sid']] = 'O'; }
   $nxs_SNAP->nxs_ntoptions['l'] = $nxsBlgSts; update_site_option($nxs_SNAP->dbOptionsName, $nxs_SNAP->nxs_ntoptions); echo "OK"; die();
}}
if (!function_exists('showSNAP_WPMU_OptionsPageExt')){ function showSNAP_WPMU_OptionsPageExt($nxs_SNAutoPoster){
  global $nxs_snapAvNts, $nxs_snapThisPageUrl, $wpdb, $nxs_isWPMU, $nxs_tpWMPU, $nxs_plurl; $options = $nxs_SNAutoPoster->nxs_options;   $ntoptions = $nxs_SNAutoPoster->nxs_ntoptions;   
  $blogname = $wpdb->get_results("SELECT option_value FROM ". $wpdb->options . " WHERE option_name='blogname' ");  $nxs_tpWMPU = $ntoptions['nxsSUType'];
  $mainBlog = $wpdb->get_results("SELECT domain, path FROM ". $wpdb->blogs . " WHERE blog_id=1;"); $mainBlog = $mainBlog[0]->domain.($mainBlog[0]->path!=='/'?$mainBlog[0]->path:''); 
  ?>
  <script type="text/javascript">jQuery(document).ready(function(){jQuery(".nsxmu_tab_content").hide(); jQuery("ul.nsxmu_tabs li:first").addClass("active").show(); jQuery(".nsxmu_tab_content:first").show();
    jQuery("ul.nsxmu_tabs li").click(function() { jQuery("ul.nsxmu_tabs li").removeClass("active"); jQuery(this).addClass("active"); jQuery(".nsxmu_tab_content").hide(); 
      var activemuTab = jQuery(this).find("a").attr("href"); jQuery(activemuTab).fadeIn(); return false;
    }); });
    
    function nxs_saveNewSiteDefSets(val){ jQuery('#nxsWmpuLoadingImg').show(); 
      var data = { action: 'nxs_saveNewSiteDefSets', id: 0, sset: val, _wpnonce: jQuery('input#nxssnap_wpnonce').val()}; jQuery.post(ajaxurl, data, function(response) {  
         jQuery('#nxsWmpuLoadingImg').hide();
      });
    }
    
  </script>           
  <style type="text/css"> ul.nsxmu_tabs {margin: 0;padding: 0;float: left;list-style: none;height: 32px;border-bottom: 1px solid #999;border-left: 1px solid #999;width: 100%;}
    ul.nsxmu_tabs li {float: left;margin: 0;padding: 0;height: 31px;line-height: 31px;border: 1px solid #999;border-left: none;margin-bottom: -1px;overflow: hidden;position: relative;background: #e0e0e0;}
    ul.nsxmu_tabs li a {text-decoration: none;color: #000; display: block; font-size: 1.2em; padding: 0 20px; border: 1px solid #fff; outline: none;}
    ul.nsxmu_tabs li a:hover { background: #ccc;} html ul.nsxmu_tabs li.active, html ul.nsxmu_tabs li.active a:hover  { background: #fff; border-bottom: 1px solid #fff; }
    .nsxmu_tab_container {border: 1px solid #999; border-top: none; overflow: hidden; clear: both; float: left; width: 100%; background: #fff;}
    .nsxmu_tab_content {padding: 10px;}
  </style>          

<ul class="nsxmu_tabs">
  <li><a id="nxsmu_stab" href="#nsxmu_tab1">Main Account: <?php echo nsTrnc($blogname[0]->option_value, 75, " ", "...")." (".nsSubStrEl($mainBlog, 50).")"; ?></a></li>
  <li><a style="color: #<?php if ($nxs_tpWMPU=='S') echo "000080"; elseif ($nxs_tpWMPU=='O')  echo "008000"; else echo "800000"; ?>;" href="#nsxmu_tab0">SNAP for WPMU</a></li>
</ul>


<div class="nsxmu_tab_container">
    <div id="nsxmu_tab1" class="nsxmu_tab_content"> 
      <?php if ($nxs_tpWMPU==''||$nxs_tpWMPU=='D'){ ?><span style="color: #800000; font-weight: bold;">SNAP is Disabled for new Sites/Blogs. Please click "SNAP for WPMU" <b style="font-size: 26px;">&uarr;</b> Tab to enable it.</span><br/><br/><?php }  
        $nxs_SNAutoPoster->showSNAutoPosterOptionsPage(); ?> 
    </div>
    <div id="nsxmu_tab0" class="nsxmu_tab_content">    
    <h3> Manage SNAP for each individual site: </h3>          
    &nbsp;&nbsp;&nbsp;&gt;&gt;&nbsp;&nbsp;<a style="font-weight: normal; font-size: 14px; line-height: 24px;" href="<?php echo admin_url(); ?>network/sites.php">Click here to go to the list of sites and manage SNAP for each site</a>
   <h3> Default Settings for New Sites: </h3>      
   <div style="margin-left: 25px;">
      <input type="radio" onchange="nxs_saveNewSiteDefSets(this.value);"  <?php if (isset($ntoptions['nxsSUType']) && $ntoptions['nxsSUType']=='S') echo ' checked="checked" '; ?> name="nxsSUType" value="S" /> <b>Super Admin Mode.</b> <i>Super Admin configures all Social Networks. All posts made in all blogs will be re-posted to all neteworks. Blog owners and other users don't see and can't change any settings or options</i><br/>        
      <input type="radio" onchange="nxs_saveNewSiteDefSets(this.value);" <?php if (isset($ntoptions['nxsSUType']) && $ntoptions['nxsSUType']=='O') echo ' checked="checked" '; ?> name="nxsSUType" value="O" /> <b>Regular Mode</b> <i>Blog owners can configure and autopost to their own Social Networks</i><br/>   
      <input type="radio" onchange="nxs_saveNewSiteDefSets(this.value);" <?php if ( (!isset($ntoptions['nxsSUType']) || isset($ntoptions['nxsSUType']) && $ntoptions['nxsSUType']=='D')) echo ' checked="checked" '; ?> name="nxsSUType" value="D" /> <b>Disabled</b> <i>SNAP is disabled for new sites.</i><br/>
      </div>      
          <br/><img id="nxsWmpuLoadingImg" style="display: none;" src='<?php echo $nxs_plurl; ?>img/ajax-loader-sm.gif' />      
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a style="font-size: 14px;" onclick="nxs_saveSiteSets('A', 'S');return false;" href="#">[Set "Super Admin Mode" on all sites]</a>
&nbsp;&nbsp;<a style="font-size: 14px;" onclick="nxs_saveSiteSets('A', 'O');return false;" href="#">[Set "Regular Mode" on all sites]</a>
&nbsp;&nbsp;<a style="font-size: 14px;" onclick="nxs_saveSiteSets('A', 'D');return false;" href="#">[Disable SNAP on all sites]</a>

    </div>
</div>
<?php
}}

