<?php

if (!class_exists('nxs_class_SNAP_BS')) { class nxs_class_SNAP_BS {
    var $ntCode = 'BS'; var $ntLCode = 'bs';

    function doPost($options, $message) {
        if (!is_array($options)) return false;
        $out = array(); foreach ($options as $ii => $account) $out[$ii] = $this->doPostToNT($account, $message);
        return $out;
    }

    function doPostToNT($options, $message) {
        $failure = array('pgID'=>'', 'postID'=>'', 'isPosted'=>0, 'pDate'=>date('Y-m-d H:i:s'), 'postURL'=>'', 'Error'=>'');
        if (!is_array($options) || empty($options['handle']) || empty($options['appPassword'])) { $failure['Error'] = 'Bluesky handle and App Password are required.'; return $failure; }
        $handle = strtolower(ltrim(trim($options['handle']), '@'));
        $password = (string) $options['appPassword'];
        $text = !empty($message['pText']) ? (string) $message['pText'] : nxs_doFormatMsg(!empty($options['msgFormat']) ? $options['msgFormat'] : '%TITLE% - %URL%', $message);
        $text = trim(html_entity_decode(wp_strip_all_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($text === '') { $failure['Error'] = 'The Bluesky post is empty.'; return $failure; }
        if (function_exists('grapheme_strlen')) $length = grapheme_strlen($text);
        else { $count = preg_match_all('/\X/u', $text); $length = $count !== false ? $count : strlen($text); }
        if ($length > 300) { $failure['Error'] = 'The Bluesky post exceeds 300 characters.'; return $failure; }

        $session = $this->session($handle, $password);
        if (is_wp_error($session)) { $failure['Error'] = $session->get_error_message(); return $failure; }
        $record = array('$type'=>'app.bsky.feed.post', 'text'=>$text, 'createdAt'=>gmdate('Y-m-d\TH:i:s\Z'));
        $facets = $this->link_facets($text);
        if ($facets) $record['facets'] = $facets;
        if (!empty($options['attchImg']) && !empty($message['imageURL'])) {
            $image_url = is_array($message['imageURL']) ? nxs_getImgfrOpt($message['imageURL'], !empty($options['imgSize']) ? $options['imgSize'] : '') : $message['imageURL'];
            $image = $this->upload_image($session, $image_url);
            if (is_wp_error($image) && $image->get_error_code() === 'bluesky_401') {
                delete_transient($this->session_key($handle, $password));
                $session = $this->session($handle, $password);
                $image = is_wp_error($session) ? $session : $this->upload_image($session, $image_url);
            }
            if (is_wp_error($image)) { $failure['Error'] = $image->get_error_message(); return $failure; }
            $record['embed'] = array('$type'=>'app.bsky.embed.images', 'images'=>array(array('alt'=>!empty($message['title']) ? wp_strip_all_tags($message['title']) : '', 'image'=>$image)));
        }
        $result = $this->json_post($session['pds'], 'com.atproto.repo.createRecord', array('repo'=>$session['did'], 'collection'=>'app.bsky.feed.post', 'record'=>$record), $session['accessJwt']);
        if (is_wp_error($result) && $result->get_error_code() === 'bluesky_401') {
            delete_transient($this->session_key($handle, $password));
            $session = $this->session($handle, $password);
            if (!is_wp_error($session)) $result = $this->json_post($session['pds'], 'com.atproto.repo.createRecord', array('repo'=>$session['did'], 'collection'=>'app.bsky.feed.post', 'record'=>$record), $session['accessJwt']);
            else $result = $session;
        }
        if (is_wp_error($result)) { $failure['Error'] = $result->get_error_message(); return $failure; }
        if (empty($result['uri']) || !preg_match('~^at://[^/]+/app\.bsky\.feed\.post/([A-Za-z0-9]+)$~', $result['uri'], $match)) { $failure['Error'] = 'Bluesky did not return a valid post URI.'; return $failure; }
        $published_handle = !empty($session['handle']) ? $session['handle'] : $handle;
        return array('pgID'=>$result['uri'], 'postID'=>$result['uri'], 'isPosted'=>1, 'postURL'=>'https://bsky.app/profile/' . rawurlencode($published_handle) . '/post/' . $match[1], 'pDate'=>date('Y-m-d H:i:s'));
    }

    private function session_key($handle, $password) { return 'nxs_bs_' . substr(hash('sha256', $handle . "\0" . $password), 0, 32); }

    private function session($handle, $password) {
        $key = $this->session_key($handle, $password);
        $cached = get_transient($key);
        if (is_array($cached)) {
            $cached = nxs_unprotect_settings($cached);
            if (!empty($cached['accessJwt']) && !empty($cached['did']) && !empty($cached['pds'])) return $cached;
        }
        $discovery = $this->discover_pds($handle);
        if (is_wp_error($discovery)) return $discovery;
        $session = $this->json_post($discovery['pds'], 'com.atproto.server.createSession', array('identifier'=>$handle, 'password'=>$password));
        if (is_wp_error($session)) return $session;
        if (empty($session['did']) || empty($session['accessJwt']) || !hash_equals($discovery['did'], $session['did'])) return new WP_Error('bluesky_identity', 'The Bluesky identity did not match the account server.');
        $session['pds'] = $discovery['pds'];
        set_transient($key, nxs_protect_settings($session), HOUR_IN_SECONDS);
        return $session;
    }

    private function discover_pds($handle) {
        $identity = $this->json_get('https://public.api.bsky.app/xrpc/com.atproto.identity.resolveHandle?handle=' . rawurlencode($handle));
        if (is_wp_error($identity)) return $identity;
        $did = !empty($identity['did']) ? (string) $identity['did'] : '';
        if (strpos($did, 'did:plc:') === 0 && preg_match('/^did:plc:[a-z0-9]+$/', $did)) $document_url = 'https://plc.directory/' . rawurlencode($did);
        elseif (strpos($did, 'did:web:') === 0) {
            $parts = explode(':', substr($did, 8)); $host = rawurldecode(array_shift($parts));
            if (!preg_match('/^[a-z0-9.-]+$/i', $host)) return new WP_Error('bluesky_did', 'The Bluesky identity is invalid.');
            $document_url = 'https://' . $host . '/' . ($parts ? implode('/', array_map('rawurlencode', $parts)) . '/' : '.well-known/') . 'did.json';
        } else return new WP_Error('bluesky_did', 'The Bluesky identity is invalid.');
        $document = $this->json_get($document_url);
        if (is_wp_error($document)) return $document;
        foreach (isset($document['service']) && is_array($document['service']) ? $document['service'] : array() as $service) {
            if (empty($service['type']) || empty($service['serviceEndpoint'])) continue;
            $types = (array) $service['type'];
            if (!in_array('AtprotoPersonalDataServer', $types, true)) continue;
            $pds = rtrim((string) $service['serviceEndpoint'], '/');
            if (wp_parse_url($pds, PHP_URL_SCHEME) !== 'https' || !wp_http_validate_url($pds)) return new WP_Error('bluesky_pds', 'The Bluesky account server is not a valid HTTPS address.');
            return array('did'=>$did, 'pds'=>$pds);
        }
        return new WP_Error('bluesky_pds', 'The Bluesky account server could not be found.');
    }

    private function json_get($url) {
        $response = wp_safe_remote_get($url, array('timeout'=>15, 'redirection'=>0, 'limit_response_size'=>1048576));
        return $this->json_response($response);
    }

    private function json_post($pds, $method, $payload, $token='') {
        $headers = array('Content-Type'=>'application/json');
        if ($token !== '') $headers['Authorization'] = 'Bearer ' . $token;
        $response = wp_safe_remote_post($pds . '/xrpc/' . $method, array('headers'=>$headers, 'body'=>wp_json_encode($payload), 'timeout'=>25, 'redirection'=>0));
        return $this->json_response($response);
    }

    private function json_response($response) {
        if (is_wp_error($response)) return new WP_Error('bluesky_network', 'Could not reach Bluesky.');
        $status = (int) wp_remote_retrieve_response_code($response);
        if ($status < 200 || $status >= 300) return new WP_Error('bluesky_' . $status, $status === 401 ? 'The Bluesky App Password is invalid or expired.' : 'Bluesky rejected the request (HTTP ' . $status . ').');
        $data = json_decode(wp_remote_retrieve_body($response), true);
        return is_array($data) ? $data : new WP_Error('bluesky_response', 'Bluesky returned an invalid response.');
    }

    private function upload_image($session, $url) {
        if (!is_string($url) || wp_parse_url($url, PHP_URL_SCHEME) !== 'https') return new WP_Error('bluesky_image', 'The featured image must have a public HTTPS URL.');
        $response = wp_safe_remote_get($url, array('timeout'=>20, 'limit_response_size'=>2000001));
        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) return new WP_Error('bluesky_image', 'The featured image could not be downloaded.');
        $body = wp_remote_retrieve_body($response);
        if ($body === '' || strlen($body) > 2000000) return new WP_Error('bluesky_image', 'The featured image must be under 2 MB.');
        $info = @getimagesizefromstring($body);
        $mime = is_array($info) && !empty($info['mime']) ? $info['mime'] : '';
        if (!in_array($mime, array('image/jpeg', 'image/png', 'image/webp'), true)) return new WP_Error('bluesky_image', 'Bluesky accepts JPEG, PNG, or WebP images here.');
        $upload = wp_safe_remote_post($session['pds'] . '/xrpc/com.atproto.repo.uploadBlob', array('headers'=>array('Authorization'=>'Bearer ' . $session['accessJwt'], 'Content-Type'=>$mime), 'body'=>$body, 'timeout'=>30, 'redirection'=>0));
        $data = $this->json_response($upload);
        if (is_wp_error($data)) return $data;
        return !empty($data['blob']) && is_array($data['blob']) ? $data['blob'] : new WP_Error('bluesky_image', 'Bluesky did not return image data.');
    }

    private function link_facets($text) {
        $facets = array();
        if (!preg_match_all('~https?://[^\s<>]+~iu', $text, $matches, PREG_OFFSET_CAPTURE)) return $facets;
        foreach ($matches[0] as $match) {
            $url = rtrim($match[0], '.,!?;:)');
            if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) continue;
            $start = $match[1];
            $facets[] = array('index'=>array('byteStart'=>$start, 'byteEnd'=>$start + strlen($url)), 'features'=>array(array('$type'=>'app.bsky.richtext.facet#link', 'uri'=>$url)));
        }
        return $facets;
    }
}}
