<?php
//## Instagram publishing through the official Meta API.
$nxs_snapAPINts[] = array('code'=>'IG', 'lcode'=>'ig', 'name'=>'Instagram');

if (!class_exists('nxs_class_SNAP_IG')) { class nxs_class_SNAP_IG {
    var $ntCode = 'IG';
    var $ntLCode = 'ig';

    function doPost($options, $message) {
        if (!is_array($options)) return false;
        $out = array();
        foreach ($options as $ii => $ntOpts) $out[$ii] = $this->doPostToNT($ntOpts, $message);
        return $out;
    }

    function doPostToNT($options, $message) {
        if (!is_array($options) || empty($options['metaDirect']) || $options['metaDirect'] !== 'ig') {
            return array('pgID'=>'', 'isPosted'=>0, 'pDate'=>date('Y-m-d H:i:s'), 'Error'=>'Reconnect this Instagram account with your Instagram app or Post.Social.');
        }
        return NXS_Meta_Direct::publish_instagram($options, $message);
    }
}}
