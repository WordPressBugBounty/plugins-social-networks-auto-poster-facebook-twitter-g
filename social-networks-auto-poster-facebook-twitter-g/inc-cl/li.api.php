<?php
// LinkedIn Posts API publisher. Existing SNAP account credentials and formats remain valid.
$nxs_snapAPINts[] = array('code'=>'LI', 'lcode'=>'li', 'name'=>'LinkedIn');
if (!class_exists('nxs_class_SNAP_LI')) { class nxs_class_SNAP_LI {
    var $ntCode='LI'; var $ntLCode='li';
    function doPost($options,$message) {
        if (!is_array($options)) return false;
        $out=array(); foreach ($options as $ii=>$account) $out[$ii]=$this->doPostToNT($account,$message);
        return $out;
    }
    function uploadArticleThumbnail($url, $owner, $headers) {
        if (!$url || !wp_http_validate_url($url)) return '';
        $download=nxs_remote_get($url,array('timeout'=>30,'limit_response_size'=>10 * MB_IN_BYTES));
        if (is_nxs_error($download) || wp_remote_retrieve_response_code($download)!==200) return '';
        $bytes=wp_remote_retrieve_body($download);
        $info=@getimagesizefromstring($bytes);
        if (!$info || !in_array($info['mime'],array('image/jpeg','image/png','image/gif'),true)) return '';
        $init=nxs_remote_post('https://api.linkedin.com/rest/images?action=initializeUpload',array('headers'=>$headers,'body'=>wp_json_encode(array('initializeUploadRequest'=>array('owner'=>$owner))),'timeout'=>30));
        if (is_nxs_error($init) || wp_remote_retrieve_response_code($init)!==200) return '';
        $upload=json_decode(wp_remote_retrieve_body($init),true);
        $uploadURL=$upload['value']['uploadUrl'] ?? '';
        $imageURN=$upload['value']['image'] ?? '';
        $host=parse_url($uploadURL,PHP_URL_HOST);
        if (!wp_http_validate_url($uploadURL) || !is_string($host) || !preg_match('/(^|\.)linkedin\.com$/i',$host) || !preg_match('/^urn:li:image:[A-Za-z0-9_-]+$/',$imageURN)) return '';
        $sent=nxs_remote_request($uploadURL,array('method'=>'PUT','headers'=>array('Authorization'=>$headers['Authorization'],'Content-Type'=>$info['mime']),'body'=>$bytes,'timeout'=>45));
        if (is_nxs_error($sent) || !in_array(wp_remote_retrieve_response_code($sent),array(200,201,204),true)) return '';
        return $imageURN;
    }
    function doPostToNT($options,$message) {
        $error=array('postID'=>'','isPosted'=>0,'pDate'=>date('Y-m-d H:i:s'),'Error'=>'');
        if (!is_array($options) || empty($options['accessToken'])) { $error['Error']='LinkedIn account is not connected.'; return $error; }
        $page=isset($options['pgID']) ? trim((string)$options['pgID']) : '';
        if ($page!=='' && $page!=='p' && !ctype_digit($page)) { $error['Error']='Invalid LinkedIn Page ID.'; return $error; }
        if (($page==='' || $page==='p') && empty($options['liUserID'])) { $error['Error']='LinkedIn member ID is missing; reconnect the account.'; return $error; }
        $author=$page!=='' && $page!=='p' ? 'urn:li:organization:'.$page : 'urn:li:person:'.$options['liUserID'];
        $format=$options['msgFormat'] ?? '%TITLE%';
        $text=!empty($message['pText']) ? $message['pText'] : nxs_doFormatMsg($format,$message);
        $text=trim(nxs_decodeEntitiesFull(wp_strip_all_tags($text)));
        if ($text==='') { $error['Error']='LinkedIn post text is empty.'; return $error; }
        $post=array(
            'author'=>$author,
            'commentary'=>$text,
            'visibility'=>'PUBLIC',
            'distribution'=>array('feedDistribution'=>'MAIN_FEED','targetEntities'=>array(),'thirdPartyDistributionChannels'=>array()),
            'lifecycleState'=>'PUBLISHED',
            'isReshareDisabledByAuthor'=>false,
        );
        $type=$options['postType'] ?? 'A';
        if ($type==='A' || $type==='I') {
            $url=$message['url'] ?? '';
            if (!$url || !wp_http_validate_url($url)) { $error['Error']='LinkedIn article URL is missing or invalid.'; return $error; }
            $title=!empty($message['urlTitle']) ? $message['urlTitle'] : nxs_doFormatMsg($options['msgATFormat'] ?? '%TITLE%',$message);
            $title=trim(nxs_decodeEntitiesFull(wp_strip_all_tags($title)));
            if ($title==='') $title=trim(nxs_decodeEntitiesFull(wp_strip_all_tags($message['pTitle'] ?? $message['title'] ?? '')));
            if ($title==='') { $error['Error']='LinkedIn article title is empty.'; return $error; }
            $article=array('source'=>$url,'title'=>nsTrnc($title,399));
            $description=!empty($message['urlDescr']) ? $message['urlDescr'] : nxs_doFormatMsg($options['msgAFormat'] ?? '',$message);
            $description=trim(nxs_decodeEntitiesFull(wp_strip_all_tags($description)));
            if ($description!=='') $article['description']=nsTrnc($description,4000);
            $imageURL=!empty($message['imageURL']) ? nxs_getImgfrOpt($message['imageURL'],$options['imgSize'] ?? '') : '';
            $imageURN=$this->uploadArticleThumbnail($imageURL,$author,array('Authorization'=>'Bearer '.$options['accessToken'],'X-Restli-Protocol-Version'=>'2.0.0','Linkedin-Version'=>'202609','Content-Type'=>'application/json','Accept'=>'application/json'));
            if ($imageURN!=='') $article['thumbnail']=$imageURN;
            $post['content']=array('article'=>$article);
        }
        $headers=array('Authorization'=>'Bearer '.$options['accessToken'],'X-Restli-Protocol-Version'=>'2.0.0','Linkedin-Version'=>'202609','Content-Type'=>'application/json','Accept'=>'application/json');
        $response=nxs_remote_post('https://api.linkedin.com/rest/posts',array('headers'=>$headers,'body'=>wp_json_encode($post),'timeout'=>30));
        if (is_nxs_error($response)) { $error['Error']=$response->get_error_message(); return $error; }
        $status=wp_remote_retrieve_response_code($response);
        $id=wp_remote_retrieve_header($response,'x-restli-id');
        if ($status!==201 || !is_string($id) || !preg_match('/^urn:li:(?:share|ugcPost):[0-9]+$/',$id)) {
            $body=json_decode(wp_remote_retrieve_body($response),true);
            $error['Error']=is_array($body) && !empty($body['message']) ? sanitize_text_field($body['message']) : 'LinkedIn Posts API did not confirm the post (HTTP '.$status.').';
            return $error;
        }
        return array('isPosted'=>1,'postID'=>$id,'postURL'=>'https://www.linkedin.com/feed/update/'.$id,'pDate'=>date('Y-m-d H:i:s'));
    }
}}
