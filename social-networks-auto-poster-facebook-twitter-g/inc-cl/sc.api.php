<?php
// Official Scoop.it API publisher using a user bearer token.
require_once dirname(__DIR__).'/inc/nxs_official_connectors.php';
$nxs_snapAPINts[] = array('code'=>'SC', 'lcode'=>'sc', 'name'=>'Scoop.It');
if (!class_exists('nxs_class_SNAP_SC')) { class nxs_class_SNAP_SC {
    var $ntCode='SC'; var $ntLCode='sc';
    function doPost($options,$message) { if (!is_array($options)) return false; $out=array(); foreach ($options as $ii=>$account) $out[$ii]=$this->doPostToNT($account,$message); return $out; }
    function doPostToNT($options,$message) {
        $error=array('isPosted'=>0,'pDate'=>date('Y-m-d H:i:s'),'Error'=>'');
        if (!is_array($options) || ($options['officialApi'] ?? '')!=='sc' || empty($options['accessToken']) || empty($options['topicURL'])) { $error['Error']='Reconnect Scoop.it with a user API token and topic.'; return $error; }
        $token=$options['accessToken']; $slug=trim($options['topicURL'],' /');
        $topic=NXS_Official_Connectors::request('https://www.scoop.it/api/1/topic?'.http_build_query(array('urlName'=>$slug)),$token,'GET',null,'sc');
        if (empty($topic['topic']['id'])) { $error['Error']=!empty($topic['message'])?$topic['message']:'Scoop.it topic was not found or is inaccessible.'; return $error; }
        $title=!empty($message['pTitle'])?$message['pTitle']:nxs_doFormatMsg($options['msgTFormat'] ?? '%TITLE%',$message);
        $text=!empty($message['pText'])?$message['pText']:nxs_doFormatMsg($options['msgFormat'] ?? '%EXCERPT%',$message);
        $type=$options['postType'] ?? 'A';
        $body=array('action'=>'create','topicId'=>$topic['topic']['id'],'title'=>wp_strip_all_tags($title),'content'=>wp_strip_all_tags($text));
        if ($type==='A' && !empty($message['url'])) $body['url']=$message['url'];
        if ($type!=='T' && !empty($message['imageURL'])) $body['imageUrl']=nxs_getImgfrOpt($message['imageURL'],$options['imgSize'] ?? '');
        if (!empty($options['inclTags']) && !empty($message['tags'])) $body['tag']=is_array($message['tags'])?$message['tags']:array_map('trim',explode(',',$message['tags']));
        $result=NXS_Official_Connectors::request('https://www.scoop.it/api/1/post',$token,'POST',$body,'sc');
        if (empty($result['post']['id']) || (int)($result['http_status'] ?? 0)<200 || (int)($result['http_status'] ?? 0)>=300) { $error['Error']=!empty($result['message'])?$result['message']:'Scoop.it did not return a post ID.'; return $error; }
        $post=$result['post'];
        return array('isPosted'=>1,'postID'=>$post['id'],'postURL'=>$post['scoopUrl'] ?? '','pDate'=>date('Y-m-d H:i:s'));
    }
}}
