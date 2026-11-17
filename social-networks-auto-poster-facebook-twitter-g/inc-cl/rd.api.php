<?php
// Official Reddit OAuth API publisher.
require_once dirname(__DIR__).'/inc/nxs_official_connectors.php';
$nxs_snapAPINts[] = array('code'=>'RD', 'lcode'=>'rd', 'name'=>'Reddit');
if (!class_exists('nxs_class_SNAP_RD')) { class nxs_class_SNAP_RD {
    var $ntCode='RD'; var $ntLCode='rd';
    function doPost($options,$message) { if (!is_array($options)) return false; $out=array(); foreach ($options as $ii=>$account) $out[$ii]=$this->doPostToNT($account,$message); return $out; }
    function doPostToNT($options,$message) {
        $error=array('isPosted'=>0,'pDate'=>date('Y-m-d H:i:s'),'Error'=>'');
        if (!is_array($options) || empty($options['rdSubReddit'])) { $error['Error']='Subreddit is not configured.'; return $error; }
        $subreddit=preg_replace('/^r\//','',trim($options['rdSubReddit']));
        if (!preg_match('/^[A-Za-z0-9_]{2,21}$/',$subreddit)) { $error['Error']='Invalid subreddit name.'; return $error; }
        $token=NXS_Official_Connectors::access_token('rd',$options);
        if ($token==='') { $error['Error']='Reconnect Reddit using your app and OAuth.'; return $error; }
        $title=!empty($message['pTitle'])?$message['pTitle']:nxs_doFormatMsg($options['msgTFormat'] ?? '%TITLE%',$message);
        $text=!empty($message['pText'])?$message['pText']:nxs_doFormatMsg($options['msgFormat'] ?? '%EXCERPT%',$message);
        $kind=($options['postType'] ?? 'A')==='T'?'self':'link';
        $body=array('api_type'=>'json','sr'=>$subreddit,'kind'=>$kind,'title'=>nsTrnc(wp_strip_all_tags($title),300),'resubmit'=>'false');
        if ($kind==='self') $body['text']=wp_strip_all_tags($text); else { if (empty($message['url'])) { $error['Error']='Link URL is missing.'; return $error; } $body['url']=$message['url']; }
        $result=NXS_Official_Connectors::request('https://oauth.reddit.com/api/submit',$token,'POST',$body,'rd');
        $errors=$result['json']['errors'] ?? array();
        if (!empty($errors)) { $error['Error']=wp_json_encode($errors); return $error; }
        $data=$result['json']['data'] ?? array();
        if (empty($data['name']) || (int)($result['http_status'] ?? 0)<200 || (int)($result['http_status'] ?? 0)>=300) { $error['Error']=!empty($result['message'])?$result['message']:'Reddit did not confirm the submission.'; return $error; }
        return array('isPosted'=>1,'postID'=>$data['name'],'postURL'=>!empty($data['url'])?$data['url']:'https://www.reddit.com/r/'.$subreddit.'/','pDate'=>date('Y-m-d H:i:s'));
    }
}}
