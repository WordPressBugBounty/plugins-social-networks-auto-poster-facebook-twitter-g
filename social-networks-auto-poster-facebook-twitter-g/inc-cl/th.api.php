<?php

if (!class_exists('nxs_class_SNAP_TH')) { class nxs_class_SNAP_TH {
    var $ntCode='TH'; var $ntLCode='th';
    function doPost($options,$message){ if(!is_array($options))return false; $out=array(); foreach($options as $ii=>$ntOpts)$out[$ii]=$this->doPostToNT($ntOpts,$message); return $out; }
    function doPostToNT($options,$message){ if(!is_array($options))return array('pgID'=>'','postID'=>'','isPosted'=>0,'pDate'=>date('Y-m-d H:i:s'),'postURL'=>'','Error'=>'No Options'); return NXS_Meta_Direct::publish_threads($options,$message); }
}}

