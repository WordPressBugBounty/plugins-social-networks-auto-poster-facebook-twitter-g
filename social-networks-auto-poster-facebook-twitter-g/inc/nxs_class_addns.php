<?php

if (!class_exists("nxs_addns")) { class nxs_addns {
	var $addns = [];
	//## Static Part
	public static $lst = [
		'ma'=>['s'=>'f', 'v'=>[4,5], 't'=>'Multi Account Addon', 'u'=>'https://www.nextscripts.net/addons/social-networks-autoposter-addon-multiple-accounts/', 'd'=>'Adds an ability to configure more then one account for each social network and some addidional features.'],
		'mu'=>['s'=>'f', 'v'=>[4,5], 't'=>'Multi User Addon', 'u'=>'https://www.nextscripts.net/addons/social-networks-autoposter-multiple-users', 'd'=>'Allows your WordPress users to add their own Social Network Accounts and post there'],
		'gp'=>['s'=>'d', 'v'=>[4], 't'=>'[Legacy] Pinterest API', 'u'=>'https://www.nextscripts.net/addons/social-networks-autoposter-addon-multiple-accounts/', 'd'=>'Legacy Google+ and Pinterest integration.'],
		'aci'=>['s'=>'cs', 'v'=>[5], 't'=>'Automatic Content Importer', 'u'=>'https://www.nextscripts.net/addons/social-networks-autoposter-addon-multiple-accounts/', 'd'=>''],
		'ci'=>['s'=>'cs', 'v'=>[5], 't'=>'Comments Importer', 'u'=>'https://www.nextscripts.net/addons/social-networks-autoposter-addon-multiple-accounts/', 'd'=>''],
		'vi'=>['s'=>'cs', 'v'=>[5], 't'=>'Video Importer/Exporter', 'u'=>'https://www.nextscripts.net/addons/social-networks-autoposter-addon-multiple-accounts/', 'd'=>''],
		'rp'=>['s'=>'f', 'v'=>[5], 't'=>'Reposter and Scheduler', 'u'=>'https://www.nextscripts.net/addons/reposter/', 'd'=>''],
		'sd'=>['s'=>'f', 'v'=>[4,5], 't'=>'Scheduled and Delayed Posting', 'u'=>'https://www.nextscripts.net/addons/reposter/', 'd'=>''],
		'prx'=>['s'=>'f', 'v'=>[4,5], 't'=>'Proxies', 'u'=>'https://www.nextscripts.net/addons/proxies/', 'd'=>'']
	];
	function gLst(){ return self::$lst; }
	static function chkLst(){
		foreach (self::$lst as $a=>$inf) {
			if (class_exists('nxaddn_'.$a)) self::$lst[$a]['isi'] = true;
		}
	}
	static function getLst($v=5){ $l = [];
		foreach (self::$lst as $a=>$inf) {
			if (in_array($v, $inf['v'])) $l[$a] = $inf; if (class_exists('nxaddn_'.$a)) $l[$a]['isi'] = true;
		}
		return $l;
	}
	static function isActive(){ $isa = false;
		foreach (self::$lst as $a=>$inf) {
			if (class_exists('nxaddn_'.$a)) $isa = true;
		}
		return $isa;
	}

	static function getAddnList(){ $rq = new nxsHttp2; $al = $rq->req('https://www.nextscripts.com/', ['v'=>4,'do'=>'addns']); $lst = self::$lst;
		if (is_array($al) && !empty($al['body'])) { $remoteLst = json_decode($al['body'], true); if (is_array($remoteLst)) $lst = $remoteLst; }
		unset($lst['api']); return wp_json_encode($lst);
	}
	//## Show Pages/Blocks
	static function showAddnsPage(){ self::showAddnsListBlock(); }
	static function showAddnsSideBar(){ return; }
	static function showAddnsOffers(){ return; }
	static function showAddnsVersions($v=5){
		$c = maybe_unserialize(get_site_option('__plugins_cache_200'));
		//## Installed Addons ifno
		if (!empty($c)) foreach ($c as $cn=>$o){ $clName = 'nxaddn_'.$cn; if (class_exists($clName)) $addns[$cn] = $clName::$inf; } //prr($addns);
		$addnTxt = '';if (!empty($addns)) foreach ($addns as $a) $addnTxt .= $a['n'].' <span style="color:#008000;font-weight: bold;">v.'.$a['v'].'</span><br/>';
		if (!empty($addnTxt)) _e('Active Addons:', 'social-networks-auto-poster-facebook-twitter-g'); echo '<div style="padding-left: 15px;">'.$addnTxt.'</div>';
	}
	static function showAddnsListBlock($v=5){
		$c = maybe_unserialize(get_site_option('__plugins_cache_200')); $li = maybe_unserialize(get_site_option('__plugins_cache_201'));
		//## Installed Active Addons ifno
		if (!empty($c)) foreach ($c as $cn=>$o){ $clName = 'nxaddn_'.$cn; if (class_exists($clName)) $addns[$cn] = $clName::$inf; } //prr($addns, 'KKK'); prr($c, 'CCC');
		$addnTxt = '';if (!empty($addns)) foreach ($addns as $cn => $a) $addnTxt .= $a['n'].' <span style="color:#008000;font-weight: bold;">v.'.$a['v'].'</span><span style="color:#333;font-weight: bold;">&nbsp;('.(!empty($li[$cn]['m']) ? $li[$cn]['m'] : '').')</span><br/>';
		//## Installed expired addons ifno
		$addniTxt = ''; if (!empty($li))  foreach ($li as $a) if ($a['s']=='i') $addniTxt .= $a['n'].': <span style="color:orangered;font-weight: bold;">Inactive</span> (<span style="color:#008000;">'.(!empty($a['m']) ? $a['m'] : '').'</span>)<br/>';
		$uid = get_site_option('__nxsuid');
		?>
		<div><a target="_blank" href="https://www.nextscripts.com"><img src="<?php echo NXS_PLURL; ?>img/SNAP_Logo_2014.png"></a></div> <br/>
		<?php wp_nonce_field( 'doLic', 'doLic_wpnonce' ); _e('SNAP Plugin Version', 'social-networks-auto-poster-facebook-twitter-g'); ?>: <span style="color:#008000;font-weight: bold;"><?php echo NextScripts_SNAP_Version; ?></span><br/>

        <?php if (defined('NextScripts_UPG_SNAP_Version')) { _e('SNAP Upgrade Helper Plugin Version', 'social-networks-auto-poster-facebook-twitter-g'); ?>: <span style="color:#008000;font-weight: bold;"><?php echo NextScripts_UPG_SNAP_Version; echo !empty($uid)?(' [ID:'.$uid.']'):''; ?></span><br/> <?php } ?>

		<?php if (!empty($addnTxt)) _e('Active Addons:', 'social-networks-auto-poster-facebook-twitter-g'); echo '<div style="padding-left: 15px;">'.$addnTxt.'</div>'; ?>
		<?php if (!empty($addniTxt)) _e('Installed/Inactive Addons:', 'social-networks-auto-poster-facebook-twitter-g'); echo '<div style="padding-left: 15px;">'.$addniTxt.'</div>'; ?>

		<?php global $nxs_apiLInfo; if (isset($nxs_apiLInfo) && !empty($nxs_apiLInfo)) {
			if ($nxs_apiLInfo['1']==$nxs_apiLInfo['2'] || empty($nxs_apiLInfo['2'])) echo $nxs_apiLInfo['1']; else echo "<b>API:</b> (Google My Business, Pinterest, LinkedIn, Reddit): ".$nxs_apiLInfo['1']."<br/><b>API:</b> (Instagram): ".$nxs_apiLInfo['2']; echo "&nbsp;&nbsp;&nbsp;&nbsp;";
		}
	}
	static function showAddnsActButtons($v=5){ if(defined('NXSAPIVER')||self::isActive()){ $obj = defined('NXSAPIVER')?'API':'Addons'; ?><br/>
		<img id="checkAPI2xLoadingImg" style="display: none;" src='<?php echo NXS_PLURL; ?>img/ajax-loader-sm.gif' /><a href="" id="checkAPI2x">[Check for <?php echo $obj; ?> Update]</a>&nbsp;&nbsp;&nbsp;&nbsp;<a href="" class="showLic">[Change Activation Key]</a> <br/><br/>
	<?php } elseif(defined('NextScripts_UPG_SNAP_Version')) { ?> <br/><span style="color:red;">You have "SNAP Upgrade helper" installed, now please&nbsp;<a href="#" class="showLic">[Enter Activation Key]</a></span><br/><br/> <?php } ?><br/> <?php
	}
}}



?>
