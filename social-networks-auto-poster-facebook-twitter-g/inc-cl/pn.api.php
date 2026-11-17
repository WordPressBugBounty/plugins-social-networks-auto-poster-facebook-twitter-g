<?php
// Official Pinterest API v5 publisher.
require_once dirname(__DIR__).'/inc/nxs_official_connectors.php';
$nxs_snapAPINts[] = array('code'=>'PN', 'lcode'=>'pn', 'name'=>'Pinterest');
if (!class_exists('nxs_class_SNAP_PN')) { class nxs_class_SNAP_PN {
    var $ntCode='PN'; var $ntLCode='pn';
    function doPost($options,$message) { if (!is_array($options)) return false; $out=array(); foreach ($options as $ii=>$account) $out[$ii]=$this->doPostToNT($account,$message); return $out; }
    function doPostToNT($options,$message) {
        $error=array('isPosted'=>0,'pDate'=>date('Y-m-d H:i:s'),'Error'=>'');
        if (!is_array($options) || empty($options['pnBoard'])) { $error['Error']='Pinterest board is not configured.'; return $error; }
        $token=NXS_Official_Connectors::access_token('pn',$options);
        if ($token==='') { $error['Error']='Reconnect Pinterest with your own app or Post.Social.'; return $error; }
        $image=!empty($message['imageURL']) ? trim(nxs_getImgfrOpt($message['imageURL'],!empty($options['imgSize'])?$options['imgSize']:'')) : '';
        if ($image==='' && !empty($options['defImg'])) $image=trim($options['defImg']);
        if (!filter_var($image,FILTER_VALIDATE_URL) || strpos($image,'https://')!==0) { $error['Error']='Pinterest requires a public HTTPS image URL.'; return $error; }
        $title=!empty($message['pTitle'])?$message['pTitle']:nxs_doFormatMsg($options['msgTFormat'] ?? '%TITLE%',$message);
        $description=!empty($message['pText'])?$message['pText']:nxs_doFormatMsg($options['msgFormat'] ?? '%EXCERPT%',$message);
        $body=array('board_id'=>(string)$options['pnBoard'],'title'=>nsTrnc(wp_strip_all_tags($title),100),'description'=>nsTrnc(wp_strip_all_tags($description),500),'media_source'=>array('source_type'=>'image_url','url'=>$image,'is_standard'=>true));
        if (($options['cImgURL'] ?? 'R')!=='N' && !empty($message['url'])) $body['link']=$message['url'];
        $result=NXS_Official_Connectors::request('https://api.pinterest.com/v5/pins',$token,'POST',$body,'pn');
        if (empty($result['id']) || (int)($result['http_status'] ?? 0)!==201) { $error['Error']=!empty($result['message'])?$result['message']:'Pinterest did not return a Pin ID.'; return $error; }
        return array('isPosted'=>1,'postID'=>$result['id'],'postURL'=>'https://www.pinterest.com/pin/'.$result['id'].'/','pDate'=>date('Y-m-d H:i:s'));
    }
}}
