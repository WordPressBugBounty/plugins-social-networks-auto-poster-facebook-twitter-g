<?php
// Google Business Profile Local Posts API publisher.
require_once dirname(__DIR__).'/inc/nxs_official_connectors.php';
$nxs_snapAPINts[] = array('code'=>'GMB','lcode'=>'gmb','name'=>'Google Business Profile');
if (!class_exists('nxs_class_SNAP_GMB')) { class nxs_class_SNAP_GMB {
  var $ntCode='GMB'; var $ntLCode='gmb';
  function doPost($options,$message) { if (!is_array($options)) return false; $out=array(); foreach ($options as $ii=>$account) $out[$ii]=$this->doPostToNT($account,$message); return $out; }
  function doPostToNT($options,$message) {
    $error=array('isPosted'=>0,'pDate'=>date('Y-m-d H:i:s'),'Error'=>'');
    if (!is_array($options) || empty($options['postTo']) || !preg_match('~^accounts/[0-9]+/locations/[0-9]+$~',$options['postTo'])) { $error['Error']='Choose a Google Business Profile location.'; return $error; }
    $token=NXS_Official_Connectors::access_token('gmb',$options);
    if ($token==='') { $error['Error']='Connect Google Business Profile using OAuth credentials.'; return $error; }
    $summary=!empty($message['pText'])?$message['pText']:nxs_doFormatMsg($options['msgFormat'] ?? '%TITLE%',$message);
    $summary=trim(wp_strip_all_tags($summary));
    if ($summary==='') { $error['Error']='Google Business Profile post text is empty.'; return $error; }
    $body=array('languageCode'=>str_replace('_','-',get_locale()),'summary'=>$summary,'topicType'=>($options['postType'] ?? 'A')==='E'?'EVENT':'STANDARD');
    $image=!empty($message['imageURL'])?trim(nxs_getImgfrOpt($message['imageURL'],$options['imgSize'] ?? '')):'';
    if ($image && wp_http_validate_url($image)) $body['media']=array(array('mediaFormat'=>'PHOTO','sourceUrl'=>$image));
    $button=$body['topicType']==='EVENT'?($options['ebtnType'] ?? 'X'):($options['btnType'] ?? 'X');
    if (in_array($button,array('BOOK','ORDER','SHOP','LEARN_MORE','SIGN_UP'),true) && !empty($message['url']) && wp_http_validate_url($message['url'])) $body['callToAction']=array('actionType'=>$button,'url'=>$message['url']);
    if ($body['topicType']==='EVENT') {
      $start=self::event_time($options['evStDate'] ?? ''); $end=self::event_time($options['evEndDate'] ?? '');
      if (!$start || !$end || $end['timestamp']<=$start['timestamp']) { $error['Error']='Event start and end dates are required, with end after start.'; return $error; }
      $title=trim(wp_strip_all_tags($options['eTtlFormat'] ?? ($message['title'] ?? '')));
      if ($title==='') { $error['Error']='Event title is required.'; return $error; }
      $body['event']=array('title'=>$title,'schedule'=>array('startDate'=>$start['date'],'startTime'=>$start['time'],'endDate'=>$end['date'],'endTime'=>$end['time']));
    }
    $url='https://mybusiness.googleapis.com/v4/'.$options['postTo'].'/localPosts';
    $result=NXS_Official_Connectors::request($url,$token,'POST',$body,'gmb');
    if (empty($result['name']) || (int)($result['http_status'] ?? 0)<200 || (int)($result['http_status'] ?? 0)>=300) {
      $error['Error']=$result['error']['message'] ?? ($result['message'] ?? 'Google Business Profile did not confirm the post.');
      return $error;
    }
    return array('isPosted'=>1,'postID'=>$result['name'],'postURL'=>$result['searchUrl'] ?? 'https://business.google.com/','pDate'=>date('Y-m-d H:i:s'));
  }
  private static function event_time($value) {
    if (!is_string($value) || trim($value)==='') return false;
    try { $date=new DateTimeImmutable($value,wp_timezone()); } catch (Exception $e) { return false; }
    return array('timestamp'=>$date->getTimestamp(),'date'=>array('year'=>(int)$date->format('Y'),'month'=>(int)$date->format('n'),'day'=>(int)$date->format('j')),'time'=>array('hours'=>(int)$date->format('G'),'minutes'=>(int)$date->format('i'),'seconds'=>0));
  }
}}
