<?php    
//## NextScripts X/Twitter Connection Class
$nxs_snapAPINts[] = array('code'=>'TW', 'lcode'=>'tw', 'name'=>'X/Twitter');

if (!class_exists("nxs_class_SNAP_TW")) { class nxs_class_SNAP_TW {
    
    var $ntCode = 'TW';
    var $ntLCode = 'tw';
    
    function doPost($options, $message){ if (!is_array($options)) return false; $out = array(); // return false;
      foreach ($options as $ii=>$ntOpts) $out[$ii] = $this->doPostToNT($ntOpts, $message);
      return $out;
    }

    function doPostToNT($options, $message){ global $nxs_urlLen; $out = array('pgID'=>'', 'isPosted'=>0, 'pDate'=>date('Y-m-d H:i:s'), 'Error'=>'');
      if (!function_exists('nxs_remote_get') && function_exists('nxs_remote_get')) { function nxs_remote_get($url){return nxs_remote_get($url);} }
      if (!function_exists('is_nxs_error') && function_exists('is_nxs_error')) { function is_nxs_error($a){return is_nxs_error($a);} }
      //## Check settings
      if (!is_array($options)) { $out['Error'] = 'No Options'; return $out; }
      if ((empty($options['accessToken']) || trim($options['accessToken'])=='') && empty($options['pfmAccountId'])) { $out['Error'] = 'No Auth Token or PostForMe Account ID Found'; return $out; }
      if (!empty($options['pfmAccountId'])) {
          if (empty($options['imgSize'])) $options['imgSize'] = '';
          if ($options['attchImg']=='1') $options['attchImg'] = 'large'; if ($options['attchImg']=='0') $options['attchImg'] = false;
          if (!empty($message['pText'])) $msg = $message['pText']; else $msg = nxs_doFormatMsg($options['msgFormat'], $message);
          $imgURL = '';
          if ($options['attchImg']!=false) { if (isset($message['imageURL'])) $imgURL = trim(nxs_getImgfrOpt($message['imageURL'], $options['imgSize'])); }
          
          $postData = [
              'caption' => $msg,
              'social_accounts' => [$options['pfmAccountId']]
          ];
          if ($options['attchImg'] != false && !empty($imgURL)) {
              $postData['media'] = [
                  ['url' => $imgURL]
              ];
          }
          
          $args = [
              'method' => 'POST',
              'headers' => [
                  'Content-Type' => 'application/json'
              ],
              'body' => json_encode($postData),
              'timeout' => 45,
              'sslverify' => true,
              'reject_unsafe_urls' => true
          ];
          
          $response = wp_safe_remote_post('https://post.social/rauth.php?action=create_post', $args);
          if (is_wp_error($response)) {
              $out['Error'] = 'PostForMe Connection Error: ' . $response->get_error_message();
              return $out;
          }
          
          $code = wp_remote_retrieve_response_code($response);
          $body = wp_remote_retrieve_body($response);
          $data = json_decode($body, true);
          
          if ($code == 200 || $code == 201) {
              $postID = !empty($data['id']) ? $data['id'] : '';
              $postURL = !empty($options['twURL']) ? $options['twURL'] : 'https://x.com';
              
              if (!empty($postID)) {
                  sleep(3); // Initial delay to give PostForMe time to process and publish
                  for ($i = 0; $i < 20; $i++) {
                      $resQuery = wp_safe_remote_get('https://post.social/rauth.php?action=get_post_results&post_id=' . rawurlencode($postID), [
                          'timeout' => 15,
                          'sslverify' => true,
                          'reject_unsafe_urls' => true
                      ]);
                      if (!is_wp_error($resQuery) && wp_remote_retrieve_response_code($resQuery) == 200) {
                          $resData = json_decode(wp_remote_retrieve_body($resQuery), true);
                          $results = isset($resData['data']) ? $resData['data'] : $resData;
                          if (is_array($results) && !empty($results)) {
                              foreach ($results as $result) {
                                  if (isset($result['success'])) {
                                      if ($result['success'] == false) {
                                          $out['Error'] = !empty($result['error']) ? $result['error'] : 'Unknown PostForMe Error';
                                          return $out;
                                      }
                                      
                                      $tweetID = '';
                                      if (!empty($result['platform_data']['id'])) {
                                          $tweetID = $result['platform_data']['id'];
                                      } elseif (!empty($result['details']['responses'][0]['postResponse']['data']['id'])) {
                                          $tweetID = $result['details']['responses'][0]['postResponse']['data']['id'];
                                      }
                                      
                                      $tempURL = '';
                                      if (!empty($result['platform_data']['url'])) {
                                          $tempURL = $result['platform_data']['url'];
                                      } elseif (!empty($tweetID)) {
                                          $tempURL = 'https://x.com/user/status/' . $tweetID;
                                      }
                                      
                                      if (!empty($tempURL)) {
                                          $postURL = $tempURL;
                                          if (!empty($options['twURL'])) {
                                              $path = parse_url($options['twURL'], PHP_URL_PATH);
                                              if (!empty($path)) {
                                                  $handle = trim(str_replace('/', '', $path));
                                                  if (!empty($handle)) {
                                                      $postURL = str_replace('/user/', '/' . $handle . '/', $postURL);
                                                  }
                                              }
                                          }
                                          $postURL = str_replace('twitter.com', 'x.com', $postURL);
                                          break 2;
                                      }
                                  }
                              }
                          }
                      }
                      sleep(1);
                  }
              }
              return array('postID' => $postID, 'isPosted' => 1, 'postURL' => $postURL, 'pDate' => date('Y-m-d H:i:s'));
          } else {
              $out['Error'] = 'PostForMe Error (HTTP ' . $code . '): ' . $body;
              return $out;
          }
      }
      if (empty($options['imgSize'])) $options['imgSize'] = '';
      //## Old Settings Fix
      if ($options['attchImg']=='1') $options['attchImg'] = 'large'; if ($options['attchImg']=='0') $options['attchImg'] = false;
      if (isset($message['img']) && is_string($message['img']) ) $img = trim($message['img']); else $img = ''; 
      //## Format Post
      if (!empty($message['pText'])) $msg = $message['pText']; else $msg = nxs_doFormatMsg($options['msgFormat'], $message);
      if ($options['attchImg']!=false) { if (isset($message['imageURL'])) $imgURL = trim(nxs_getImgfrOpt($message['imageURL'], $options['imgSize'])); else $imgURL = ''; }
      if (empty($imgURL) && $img=='') $options['attchImg'] = false;
      //## Make Post
      $hdrsArr['User-Agent']='Mozilla/5.0 (Windows NT 6.1; WOW64; rv:32.0) Gecko/20100101 Firefox/32.0'; $advSet=array('headers'=>$hdrsArr,'httpversion'=>'1.1','timeout'=>45,'sslverify'=>true,'reject_unsafe_urls'=>true);
      if ($options['attchImg']!=false && $img=='' && $imgURL!='' ) { $imgURL = str_replace(' ', '%20', $imgURL);
        if( ini_get('allow_url_fopen') ) { if (getimagesize($imgURL)!==false) { $img = nxs_remote_get($imgURL, $advSet); if(is_nxs_error($img)) $options['attchImg'] = false; else $img = $img['body']; } else $options['attchImg'] = false; } 
          else { $img = nxs_remote_get($imgURL, $advSet); if(is_nxs_error($img)) $options['attchImg'] = false; elseif (isset($img['body'])&& trim($img['body'])!='') $img = $img['body'];  else $options['attchImg'] = false; }   
      }  
      $twLim = 280; if ($nxs_urlLen>0) { $msg = nsTrnc($msg, $twLim-22+$nxs_urlLen); } else $msg = nsTrnc($msg, $twLim); if (substr($msg, 0, 1)=='@') $msg = ' '.$msg;
      $params_array = array('text' =>$msg);  $mid = ''; $appi = new nxsAPI_TW_Native(); $appi->conn = $options; $check = $appi->check(); if ($check===true) {
            if (!empty($options['in_reply_to_id'])) $params_array['in_reply_to_status_id'] = $options['in_reply_to_id']; //## ?
            //## Upload image and add it to the post
            if ($options['attchImg'] != false && $img != '') { $pa = array('media' => $img);
                $ret = $appi->twReq('https://upload.twitter.com/1.1/media/upload.json', $pa );
                $code = $ret['response']['code']; $resp = $ret['body']; $respJ = json_decode($resp, true);
                if ($code == 200 || $code == 201) { if (!empty($respJ['media_id'])) $mid = $respJ['media_id_string']; }
            }
            if (!empty($mid)) $params_array['media']['media_ids'][] = $mid;
            //## Post
            $ret = $appi->twReq('https://api.twitter.com/2/tweets', $params_array);
            $code = $ret['response']['code']; $resp = $ret['body']; $respJ = json_decode($resp, true);
            //## Fallback in case no mo imgs allowed.
            if ($code == '403' && stripos($resp, 'User is over daily photo limit') !== false && $options['attchImg'] != false && $img != '') {
                $out['Error'] = $out['Error']." User is over daily photo limit. Will post without image\r\n";
                $ret = $appi->twReq('https://api.twitter.com/2/tweets', array('text' => $msg));
                $code = $ret['response']['code']; $resp = $ret['body']; $respJ = json_decode($resp, true);
            }
            if ($code == 200 || $code == 201) {
                $twResp = json_decode($resp, true);
                if (is_array($twResp) && isset($twResp['id_str']) && isset($twResp['user'])) {
                    $twNewPostID = $twResp['id_str']; $twPageID = $twResp['user']['screen_name'];
                } elseif (is_array($twResp) && isset($twResp['data']['id'])) {
                    $twNewPostID = $twResp['data']['id']; $twPageID = $appi->twUser['username'];
                }
                return array('postID' => $twNewPostID, 'isPosted' => 1, 'postURL' => 'https://x.com/' . $twPageID . '/status/' . $twNewPostID, 'pDate' => date('Y-m-d H:i:s'));
            } else {
                $je =  (is_array($respJ) && !empty($respJ['errors']))?"| Error: " . print_r($respJ['errors'], true):'';
                $out['Error'] = $out['Error'] . "| Resp: " . print_r($resp, true) . $je . "| MSG: " . print_r($msg, true);
                return $out;
            }
        } else $out['Error'] = $check;
      return $out;
    }  
    
}}
?>
