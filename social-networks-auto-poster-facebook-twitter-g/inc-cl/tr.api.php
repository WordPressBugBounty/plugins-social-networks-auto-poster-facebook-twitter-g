<?php
// Tumblr Neue Post Format publisher. Existing OAuth 1 credentials and blog IDs are reused.
$nxs_snapAPINts[] = array('code'=>'TR', 'lcode'=>'tr', 'name'=>'Tumblr');
if (!class_exists('nxs_class_SNAP_TR')) { class nxs_class_SNAP_TR {
    var $ntCode = 'TR'; var $ntLCode = 'tr';

    function doPost($options, $message) {
        if (!is_array($options)) return false;
        $out = array(); foreach ($options as $ii=>$account) $out[$ii] = $this->doPostToNT($account, $message);
        return $out;
    }

    function doPostToNT($options, $message) {
        $error = array('postID'=>'', 'isPosted'=>0, 'pDate'=>date('Y-m-d H:i:s'), 'Error'=>'');
        if (!is_array($options) || empty($options['appKey']) || empty($options['appSec']) || empty($options['accessToken']) || empty($options['accessTokenSec'])) {
            $error['Error'] = 'Tumblr account is not connected.'; return $error;
        }
        $blog = trim((string)($options['pgID'] ?? ''));
        if ($blog === '' && !empty($options['trURL'])) $blog = trim(preg_replace('~^https?://~i', '', $options['trURL']), '/');
        if ($blog === '' || !preg_match('/^[a-zA-Z0-9._:-]+$/', $blog)) { $error['Error'] = 'Invalid Tumblr blog ID.'; return $error; }

        $msg = !empty($message['pText']) ? $message['pText'] : nxs_doFormatMsg($options['msgFormat'] ?? '%TITLE%', $message);
        $title = !empty($message['pTitle']) ? $message['pTitle'] : nxs_doFormatMsg($options['msgTFormat'] ?? '%TITLE%', $message);
        $hasInlineImage = stripos($msg, '<img') !== false;
        $msg = trim(html_entity_decode(wp_strip_all_tags(preg_replace('~<\s*(?:br|/p|/div|/blockquote)\b[^>]*>~i', "\n", $msg)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $title = trim(html_entity_decode(wp_strip_all_tags($title), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $content = array();
        if ($title !== '' && ($options['postType'] ?? 'T') === 'T') $content[] = array('type'=>'text', 'text'=>$title, 'subtype'=>'heading1');
        if ($msg !== '') $content[] = array('type'=>'text', 'text'=>$msg);
        $url = $message['url'] ?? '';
        $media = null;
        $type = $options['postType'] ?? 'T';
        if ($type === 'I' || ($type === 'T' && $hasInlineImage && !empty($message['imageURL']))) {
                $image = !empty($message['imageURL']) ? nxs_getImgfrOpt($message['imageURL'], $options['imgSize'] ?? '') : '';
                if (!$image || !wp_http_validate_url($image)) { $error['Error'] = 'Tumblr image URL is missing or invalid.'; return $error; }
                $download = nxs_remote_get($image, array('timeout'=>30, 'limit_response_size'=>10 * MB_IN_BYTES));
                if (is_nxs_error($download)) { $error['Error'] = $download->get_error_message(); return $error; }
                $bytes = wp_remote_retrieve_body($download);
                $info = @getimagesizefromstring($bytes);
                if (!$info || !in_array($info['mime'], array('image/jpeg','image/png','image/gif','image/webp'), true)) { $error['Error'] = 'Tumblr image could not be read.'; return $error; }
                $identifier = 'snap-image';
                $block = array('type'=>'image', 'media'=>array(array('identifier'=>$identifier, 'type'=>$info['mime'], 'width'=>$info[0], 'height'=>$info[1])));
                if (($options['cImgURL'] ?? 'R') !== 'N' && $url && wp_http_validate_url($url)) {
                    $clickthrough = ($options['cImgURL'] ?? 'R') === 'S' ? nxs_mkShortURL($url) : $url;
                    if (wp_http_validate_url($clickthrough)) $block['attribution'] = array('type'=>'link', 'url'=>$clickthrough);
                }
                array_unshift($content, $block);
                $media = array('identifier'=>$identifier, 'mime'=>$info['mime'], 'body'=>$bytes);
        }
        switch ($type) {
            case 'V':
                $video = trim((string)($message['videoURL'] ?? ''));
                if ($video !== '') {
                    if (preg_match('/^[A-Za-z0-9_-]{11}$/', $video)) $video = 'https://www.youtube.com/watch?v=' . $video;
                    elseif (ctype_digit($video)) $video = 'https://vimeo.com/' . $video;
                    if (wp_http_validate_url($video)) $content[] = array('type'=>'link', 'url'=>$video);
                }
                break;
            case 'U':
                $audio = trim((string)($message['audioURL'] ?? ''));
                if ($audio && wp_http_validate_url($audio)) $content[] = array('type'=>'link', 'url'=>$audio);
                break;
        }
        if (!$content) { $error['Error'] = 'Tumblr post content is empty.'; return $error; }
        if (($options['postType'] ?? 'T') === 'T' && $url && wp_http_validate_url($url)) $content[] = array('type'=>'link', 'url'=>$url);
        $payload = array('content'=>$content, 'state'=>'published');
        if (!empty($message['tags'])) $payload['tags'] = $message['tags'];
        if (($options['fillSrcURL'] ?? '') === '1' && $url && wp_http_validate_url($url)) $payload['source_url'] = $url;
        if (!empty($message['postDate'])) {
            $time = strtotime($message['postDate']);
            if ($time && $time <= time()) $payload['date'] = gmdate('c', $time);
        }
        require_once(NXS_PLPATH . 'inc-cl/apis/trOAuth.php');
        $tumblr = new TumblrOAuth(nxs_gak($options['appKey']), nxs_gas($options['appSec']), $options['accessToken'], $options['accessTokenSec']);
        $result = $tumblr->postNpf('https://api.tumblr.com/v2/blog/' . rawurlencode($blog) . '/posts', $payload, $media);
        $status = $result->meta->status ?? 0;
        if ($status == 201 && !empty($result->response->id)) {
            $id = (string)$result->response->id;
            return array('postID'=>$id, 'isPosted'=>1, 'postURL'=>'https://www.tumblr.com/' . rawurlencode($blog) . '/' . rawurlencode($id), 'pDate'=>date('Y-m-d H:i:s'));
        }
        $detail = $result->errors[0]->title ?? ($result->meta->msg ?? 'Unknown error');
        $error['Error'] = 'Tumblr HTTP ' . $status . ': ' . sanitize_text_field($detail);
        return $error;
    }
}}
