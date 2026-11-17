<?php
//## NextScripts Facebook Connection Class
$nxs_snapAPINts[] = array('code'=>'BG', 'lcode'=>'bg', 'name'=>'Blogger');

if (!class_exists("nxs_class_SNAP_BG")) { class nxs_class_SNAP_BG {

    var $ntCode = 'BG';
    var $ntLCode = 'bg';

    function doPost($options, $message){ if (!is_array($options)) return false;
        foreach ($options as $ii=>$ntOpts) $out[$ii] = $this->doPostToNT($ntOpts, $message);
        return $out;
    }

    function doPostToNT($options, $message){ $badOut = array('pgID'=>'', 'isPosted'=>0, 'pDate'=>date('Y-m-d H:i:s'), 'Error'=>''); // prr($message);  prr($options);
        //## Check settings
        if (!is_array($options)) { $badOut['Error'] = 'No Options'; return $badOut; }
        if (empty($options['accessToken'])) { $badOut['Error'] = 'Not Configured'; return $badOut; }
        //## Format
        if (!empty($message['pText'])) $msg = $message['pText']; else $msg = nxs_doFormatMsg($options['msgFormat'], $message);
        if (!empty($message['pTitle'])) $msgT = $message['pTitle']; else $msgT = nxs_doFormatMsg($options['msgTFormat'], $message);
        $tags = ''; if ($options['inclTags']=='1' || $options['inclCatsAsTags']=='1') {
            if ($options['inclTags']=='1') $tags = $message['tags'];
            if ($options['inclCatsAsTags']=='1') $ctags = $message['cats'];
            if (!empty($ctags)) { if (empty($tags)) $tags = $ctags; else $tags = $tags . ', '. $ctags; }
            $tags = nsTrnc($tags, 195, ',', '');
        }
        //## Check/Fix HTML
        if (class_exists('DOMDocument')) {$doc = new DOMDocument();  @$doc->loadHTML('<?xml encoding="UTF-8">' .$msg); $doc->encoding = 'UTF-8'; $msg = $doc->saveHTML(); $msg = CutFromTo($msg, '<body>', '</body>');
            $msg = preg_replace('/<br(.*?)\/?>/','<br$1/>',$msg);   $msg = preg_replace('/<img(.*?)\/?>/','<img$1/>',$msg);
            require(NXS_PLPATH . 'inc-cl/apis/htmlNumTable.php');  if (is_array($HTML401NamedToNumeric)) { $msg = strtr($msg, $HTML401NamedToNumeric); $msgT = strtr($msgT, $HTML401NamedToNumeric); }
        }
        $msg = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', "", $msg); $msg = preg_replace('/<!--(.*)-->/Uis', "", $msg);  $nxshf = new NXS_HtmlFixer(); $nxshf->debug = false; $msg = $nxshf->getFixedHtml($msg);
        $msg = str_replace("\r\n","\n", $msg); $msg = str_replace("\n\r","\n", $msg); $msg = str_replace("\r","\n", $msg); $msg = str_replace("\n","<br/>", $msg);
        //## Make Post
        $blogID = $options['blogID'];

        //## Refresh token
        if (function_exists('get_option')) $currTime = time() + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ); else  $currTime = time();
        if ($options['accessTokenExp']<$currTime){
            $tknURL = 'https://oauth2.googleapis.com/token';
            $wprg = array(
                'body' => array(
                    'refresh_token' => $options['refreshToken'],
                    'client_id' => nxs_gak($options['appKey']),
                    'client_secret' => nxs_gas($options['appSec']),
                    'grant_type' => 'refresh_token'
                )
            );
            $response  = nxs_remote_post($tknURL, $wprg); if (is_nxs_error($response)) return 'Token (Refresh) Connection Error: '.print_r($response, true);
            $resp = json_decode($response['body'], true); $options['accessToken'] = $resp['access_token']; $options['accessTokenExp'] = $currTime + $resp['expires_in'];
            nxs_save_glbNtwrks('bg', $options['ii'], $resp['access_token'], 'accessToken'); nxs_save_glbNtwrks('bg', $options['ii'], $options['accessTokenExp'], 'accessTokenExp');
        }
        //## Post
        $post = array("kind"=>"blogger#post", "blog"=>array("id"=>$blogID), "title"=> $msgT,  "content" => $msg );
        if (!empty($tags)) {
            $post['labels'] = array_map('trim', explode(',', $tags));
        }
        $post = json_encode($post); // prr($post);
        $hdrsArr = array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $options['accessToken']
        );
        $advSet = array('headers' => $hdrsArr, 'httpversion' => '1.1', 'timeout' => 45, 'redirection' => 0, 'body' => $post);
        $tknURL = 'https://www.googleapis.com/blogger/v3/blogs/'.$blogID.'/posts';
        $ret = ''; $response  = nxs_remote_post($tknURL, $advSet); //prr($tknURL); prr($response);
        if ((is_object($response) && isset($response->errors))) $badOut['Error'] = print_r($response, true); else $ret = json_decode($response['body'], true);  //prr($ret);
        if (is_array($ret) && !empty($ret['id'])) return array('postID'=>$ret['id'], 'isPosted'=>1, 'postURL'=>$ret['url'], 'pDate'=>date('Y-m-d H:i:s'));
        else { $badOut['Error'].= "Error: ".print_r($ret, true); return $badOut;}
    }
}}
?>