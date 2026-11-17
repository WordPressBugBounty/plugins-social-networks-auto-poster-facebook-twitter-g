<?php 
if (!class_exists('nxs_adminMgmt')){
    class nxs_adminMgmt {        
        
        var $page;
        var $pluginName = 'SNAP';
        
        public function __construct() {
          //add_action( 'admin_menu', array( $this, 'nxs_adminMenu' ) );        
           
        }
        public function init() {
          add_action( 'admin_menu', array( $this, 'nxs_adminMenu' ) );           
        }
        //##MU
        function ntAdminMenu() {
          
          global $nxs_SNAP;   //   prr($nxs_SNAP->sMode['l']);
          if ($nxs_SNAP->sMode['l']=='F' || $nxs_SNAP->sMode['l']=='P') {  
            
            $p = $this->page = add_menu_page('Social Networks Auto Poster', 'SNAP|AutoPoster', 'manage_options', 'nxssnap-ntadmin', array($this, 'showPage_ntdashboard'), NXS_PLURL.'img/snap-icon.png');
             add_action( 'load-'.$p,  array( $this, 'nxs_load_scripts' ));
          
          } else {            
            $this->page = add_menu_page( 'Social Networks Auto Poster', 'SNAP|AutoPoster','manage_options','nxssnap',array( $this, 'showPage_accounts' ), NXS_PLURL.'img/snap-icon.png');                            
            $p[] = add_submenu_page( 'nxssnap',__( 'Accounts', 'social-networks-auto-poster-facebook-twitter-g' ), __( 'Accounts', 'social-networks-auto-poster-facebook-twitter-g' ), 'manage_options', 'nxssnap', array( $this, 'showPage_accounts' ) ,0 );
            $p[] = add_submenu_page( 'nxssnap',__( 'Users/Sites', 'social-networks-auto-poster-facebook-twitter-g' ), __( 'Users/Sites', 'social-networks-auto-poster-facebook-twitter-g' ), 'manage_options', 'nxssnap-ntadmin', array( $this, 'showPage_userssites' ) ,0 );
            //add_submenu_page( 'nxssnap',__( 'Query/Timeline', 'social-networks-auto-poster-facebook-twitter-g' ), __( 'Query/Timeline', 'social-networks-auto-poster-facebook-twitter-g' ), 'manage_options', 'nxssnap-query', array( $this, 'showPage_query' ) ,0 );                       
            $p[] = add_submenu_page( 'nxssnap',__( 'Help/Support', 'social-networks-auto-poster-facebook-twitter-g' ), __( 'Help/Support', 'social-networks-auto-poster-facebook-twitter-g' ), 'manage_options', 'nxs-help', array( $this, 'showPage_about' ) ,0 );
            foreach ($p as $a) add_action( 'load-'.$a,  array( $this, 'nxs_load_scripts' ));
          }
          
        }
        function showPage_ntdashboard() {
            add_meta_box( $this->page.'_dashboard', __( 'Welcome to the Social Networks Autoposter (SNAP)', 'social-networks-auto-poster-facebook-twitter-g' ), array( $this, 'metabox_ntdashboard' ), $this->page, 'normal' );            
            $this->showPage_side(); $this->showPage($this->pluginName.': '. __( 'Dashboard', 'social-networks-auto-poster-facebook-twitter-g' )); 
        }
        public function metabox_ntdashboard($post){ global $nxs_SNAP;     // prr($nxs_SNAP->sMode['l']);
          if ($nxs_SNAP->sMode['l']=='F' || $nxs_SNAP->sMode['l']=='P') { $cs = get_site_option('nxs_nts');                    
          if (function_exists('get_sites')) { $sitesX = get_sites(array('public'=> 1, 'archived' => 0, 'mature' => 0, 'spam' => 0, 'deleted' => 0)); 
              $sites = array(); foreach ($sitesX as $ss) $sites[] = array('blog_id'=>$ss->blog_id, 'domain'=>$ss->domain, 'path'=>$ss->path) ;          
          } else $sites = wp_get_sites(array('public'=> 1, 'archived' => 0, 'mature' => 0, 'spam' => 0, 'deleted' => 0)); 
          //## Update from V3
          if (empty($cs)) { switch_to_blog(1); $k = get_option('nxsSNAPNetworks'); if (!empty($k)) update_site_option('nxs_nts', 1);  else { $k = get_option('NS_SNAutoPoster'); if (!empty($k)) update_site_option('nxs_nts', 1); } }
          
          $nxsOne = NextScripts_SNAP_Version;  $sMode = $nxs_SNAP->sMode; ?>                        
              <?php _e('Plugin Version', 'social-networks-auto-poster-facebook-twitter-g'); ?>: <span style="color:#008000;font-weight: bold;"><?php echo wp_kses($nxsOne, wp_kses_allowed_html( 'post' )); ?></span>
              <?php if($sMode['l']=='P') { ?> [Pro]&nbsp;&nbsp;&nbsp;(One User, Multiple Accounts)&nbsp;&nbsp;<?php } elseif ($sMode['l']=='M'){ ?> [Multiuser Pro]&nbsp;&nbsp;&nbsp;(Multiple Users, Multiple Accounts)<?php } else {?> <span style="color:#800000; font-weight: bold;">[Free]&nbsp;&nbsp;&nbsp;(One User, One account per Network)</span><?php } ?>
           <?php if (defined('NXSAPIVER')) echo '<span id="nxsAPIUpd">API</span> Version: <span style="color:#008000;font-weight: bold;">'.NXSAPIVER.'</span>';            
           
           if (defined('NXSAPIVER')){ ?>&nbsp;&nbsp;|&nbsp;&nbsp; <img id="checkAPI2xLoadingImg" style="display: none;" src='<?php echo NXS_PLURL; ?>img/ajax-loader-sm.gif' /><a href="" id="checkAPI2x">[Check for API Update]</a>&nbsp;&nbsp;&nbsp;&nbsp;<a href="" class="showLic">[Change Activation Key]</a><?php } echo '<br/><br/>';         
          
            if (!empty($_POST['nxs_ntsiteid']) && check_admin_referer('nxsSsPageWPN', 'nxsSsPageWPN_wpnonce') ) { $csN = (int)sanitize_key($_POST['nxs_ntsiteid']);
              if (!empty($cs) && $csN!=$cs) { switch_to_blog($cs); delete_option('nxsSNAPNetworks'); delete_option('nxsSNAPOptions');restore_current_blog();} $cs = $csN; update_site_option('nxs_nts', $cs); 
            } foreach ( $sites as $i => $site ) { $blog = get_blog_details($site['blog_id']); $sites[$i]['name'] = $blog->blogname; if ( $sites[$i]['blog_id']==$cs) $cSite = $sites[$i]; }  
            // uasort( $sites, function( $site_a, $site_b ) { return strcasecmp( $site_a[ 'name' ], $site_b[ 'name' ] ); });
            ?>  <?php _e( 'You are using "Single User" version of the plugin. This version could be used only on a single site by a single user.', 'social-networks-auto-poster-facebook-twitter-g' ); ?>&nbsp;<a target="_blank" href="https://www.nextscripts.com/social-networks-auto-poster-pro-for-wpmu"><?php _e( 'Get SNAP Pro Multiuser Version', 'social-networks-auto-poster-facebook-twitter-g' ); ?></a> <br/><br/>     
            <form method="post" action="" novalidate="novalidate"> <?php wp_nonce_field( 'nxsSsPageWPN', 'nxsSsPageWPN_wpnonce' ); ?>
              <span style="font-size: 15px; font-weight: bold;"><?php _e( 'Please select a site where you would like to use plugin: ', 'social-networks-auto-poster-facebook-twitter-g' ); ?></span> <select name="nxs_ntsiteid"> <?php
            foreach ( $sites as $i => $site ) echo '<option '.($site['blog_id']==$cs?'selected':'').' value="'.esc_attr($site['blog_id']).'">[ID: '.$site['blog_id'].'] '.$site['name'].' - '.$site['domain'].($site['path']!='/'?$site['path']:'').'</option>'; ?></select><br/> <div style="padding-top:10px;">
            <?php if (!empty($cs) && !empty($cSite) && is_array($cSite)) { ?> <div style="padding-bottom:10px;"> 
              <?php _e( 'You already have SNAP Configured on: ', 'social-networks-auto-poster-facebook-twitter-g');?><b><?php echo 'ID:'.$cSite['blog_id'].' - '.$cSite['name'].' ('.$cSite['domain'].($cSite['path']!='/'?$cSite['path']:'').')'; ?></b><br/><br/>
              !!!! <?php _e( 'Selecting a different site will <b>delete</b> all current settings', 'social-networks-auto-poster-facebook-twitter-g' ); ?> !!!!<br/><br/>
              <input type="checkbox" onchange="jQuery('#nxsNTOSubmit').toggle();"/> <?php _e( 'I undestand that all current settings will be deleted', 'social-networks-auto-poster-facebook-twitter-g' ); ?><br/>
            </div><?php } ?>
            <input type="submit" <?php if (!empty($cs) && !empty($cSite) && is_array($cSite)) { ?>style="display: none" <?php } ?> name="submit" id="nxsNTOSubmit" class="button button-primary" value="<?php _e( 'Save Changes', 'social-networks-auto-poster-facebook-twitter-g' ); ?>"></div></form> <?php 
          } else { //## MU COde 
             //  if (function_exists('showSNAP_WPMU_OptionsPageExt')) { showSNAP_WPMU_OptionsPageExt($nxs_SNAP); }
             
             
             
          }
        }
        
       public function nxs_adminMenu() { wp_enqueue_script( 'postbox' );
          //$this->page = add_menu_page( 'Social Networks Auto Poster', '-={<span style="font-weight:bold; color:#2ecc2e;">SNAP</span>}=-','manage_options','nxssnap',array( $this, 'showPage_dashboard' ), NXS_RELPATH.'/img/snap-icon.png');
         // $this->page = add_menu_page( 'Social Networks Auto Poster', '-={SNAP}=-','manage_options','nxssnap',array( $this, 'showPage_dashboard' ), NXS_RELPATH.'/img/snap-icon.png');
          $this->page = add_menu_page( 'Social Networks Auto Poster', 'SNAP|AutoPoster','haveown_snap_accss','nxssnap',array( $this, 'showPage_accounts' ), NXS_PLURL.'img/snap-icon.png');

          $p[] = add_submenu_page( 'nxssnap',__( 'Accounts', 'social-networks-auto-poster-facebook-twitter-g' ), __( 'Accounts', 'social-networks-auto-poster-facebook-twitter-g' ), 'haveown_snap_accss', 'nxssnap', array( $this, 'showPage_accounts' )  );
          $p[] = add_submenu_page( 'nxssnap',__( 'Quick Post', 'social-networks-auto-poster-facebook-twitter-g' ), __( 'Quick Post', 'social-networks-auto-poster-facebook-twitter-g' ), 'haveown_snap_accss', 'nxssnap-post', array( $this, 'showPage_dashboard' )  );
          // Keep the old Query URL available for bookmarks, but show only one menu item.
          $p[] = add_submenu_page( 'nxssnap',__( 'Query/Timeline', 'social-networks-auto-poster-facebook-twitter-g' ), __( 'Query/Timeline', 'social-networks-auto-poster-facebook-twitter-g' ), 'haveown_snap_accss', 'nxssnap-query', array( $this, 'showPage_query' )  );
          remove_submenu_page( 'nxssnap', 'nxssnap-query' );
          $p[] = add_submenu_page( 'nxssnap',__( 'Reposter', 'social-networks-auto-poster-facebook-twitter-g' ), __( 'Reposter', 'social-networks-auto-poster-facebook-twitter-g' ), 'manage_options', 'nxssnap-reposter', array( $this, 'showPage_reposter' )  );
          $p[] = add_submenu_page( 'nxssnap',__( 'Settings', 'social-networks-auto-poster-facebook-twitter-g' ), __( 'Settings', 'social-networks-auto-poster-facebook-twitter-g' ), 'manage_options', 'nxssnap-settings', array( $this, 'showPage_settings' )  );
          $p[] = add_submenu_page( 'nxssnap',__( 'Log/Query', 'social-networks-auto-poster-facebook-twitter-g' ), __( 'Log/Query', 'social-networks-auto-poster-facebook-twitter-g' ), 'haveown_snap_accss', 'nxs-log', array( $this, 'showPage_log' )  );
          $p[] = add_submenu_page( 'nxssnap',__( 'Help/Support', 'social-networks-auto-poster-facebook-twitter-g' ), __( 'Help/Support', 'social-networks-auto-poster-facebook-twitter-g' ), 'manage_options', 'nxs-help', array( $this, 'showPage_about' )  );
          foreach ($p as $a) add_action( 'load-'.$a,  array( $this, 'nxs_load_scripts' ));
        }

        function nxs_load_scripts () { add_action( 'admin_enqueue_scripts', array( $this, 'nx_enqueue_bootstrap') ); }
        function nx_enqueue_bootstrap() {
            $path =  str_ireplace( '/inc/', '', plugin_dir_url( __FILE__ ) );
            wp_enqueue_script( 'nxsaci-bootstrap', $path . '/js-css/bootstrap.js', array( 'jquery' ),  NextScripts_SNAP_Version);
            wp_enqueue_style( 'nxsaci-bootstrap',  $path . '/js-css/bootstrap.css', array(),  NextScripts_SNAP_Version );
          //  wp_enqueue_style( 'nxsaci-fa',  $path . '/js-css/fa/css/all.min.css', array(),  NextScripts_SNAP_Version );
        }            
                      
        public function showPage($title, $object=null, $logQueryTab='') {  ?>
            <div class="wrap" id="<?php echo esc_attr($this->page); ?>"><div id="nxsDivWrap"> <?php // $this->show_follow_icons(); ?>
                <h2><?php echo wp_kses($title, wp_kses_allowed_html( 'post' )) ?></h2>
                <?php if ($logQueryTab === 'log' || $logQueryTab === 'query') { ?>
                <nav class="nav-tab-wrapper" aria-label="<?php echo esc_attr__( 'Log/Query pages', 'social-networks-auto-poster-facebook-twitter-g' ); ?>">
                  <a class="nav-tab<?php echo $logQueryTab === 'log' ? ' nav-tab-active' : ''; ?>" href="<?php echo esc_url(nxs_get_admin_url('admin.php?page=nxs-log')); ?>"<?php echo $logQueryTab === 'log' ? ' aria-current="page"' : ''; ?>><?php esc_html_e( 'Log/History', 'social-networks-auto-poster-facebook-twitter-g' ); ?></a>
                  <a class="nav-tab<?php echo $logQueryTab === 'query' ? ' nav-tab-active' : ''; ?>" href="<?php echo esc_url(nxs_get_admin_url('admin.php?page=nxs-log&tab=query')); ?>"<?php echo $logQueryTab === 'query' ? ' aria-current="page"' : ''; ?>><?php esc_html_e( 'Query/Timeline', 'social-networks-auto-poster-facebook-twitter-g' ); ?></a>
                </nav>
                <?php } ?>
                <div id="poststuff">                    
                  <div id="post-body" class="metabox-holder columns-2">
                    <div id="post-body-content" style="position: relative;" class=""><?php do_meta_boxes( $this->page, 'normal', $object ); ?></div>
                    <div id="postbox-container-1" class="postbox-container"><?php  do_meta_boxes( $this->page, 'side', null ); ?></div>
                    <br class="clear"/>
                  </div>
                </div>
            </div></div>
            <script type="text/javascript">
              jQuery(document).ready(function($) { $('.if-js-closed').removeClass('if-js-closed').addClass('closed'); if (typeof(postboxes)!='undefined') postboxes.add_postbox_toggles('<?php echo esc_attr($this->page); ?>');});
            </script> <?php 
        }                    
        //## Side Boxes
        public function showPage_side() { 
            add_meta_box( $this->page.'_info', __( 'SNAP Info', 'social-networks-auto-poster-facebook-twitter-g' ), array( $this, 'metabox_side_info' ), $this->page, 'side', 'high' );
            if ((current_user_can( 'manage_options' ))) {
              add_meta_box( $this->page.'_supportbox', __( 'Support', 'social-networks-auto-poster-facebook-twitter-g' ), array( $this, 'metabox_side_supbox' ), $this->page, 'side', 'low' );
            }
            add_meta_box( $this->page.'_instrbox', __( 'Instructions', 'social-networks-auto-poster-facebook-twitter-g' ), array( $this, 'metabox_side_instrbox' ), $this->page, 'side', 'low' );
        }            
        public function showPage_dashboard() { global $nxs_SNAP;  if (!isset($nxs_SNAP)) return; $t = $nxs_SNAP->nxs_accts; 
            $nc = 0; if (!empty($t)) foreach ($t as $kt=>$tt) if ( (strlen($kt)==2 || strlen($kt)==3) && is_array($tt)) $nc = $nc + count($tt);
            if ($nc>0) add_meta_box( $this->page.'_newPost', __( 'New Post to Social Networks', 'social-networks-auto-poster-facebook-twitter-g' ), array( $this, 'metabox_newPost' ), $this->page, 'normal' );
              else  add_meta_box( $this->page.'_newPost', __( 'Please add at least one account...', 'social-networks-auto-poster-facebook-twitter-g' ), array( $this, 'metabox_emptyaccss' ), $this->page, 'normal' );
            $this->showPage_side();      $this->showPage($this->pluginName.': '. __( 'Dashboard', 'social-networks-auto-poster-facebook-twitter-g' ));         
        }

        public function showPage_addons() {
            add_meta_box( $this->page.'_addons', __( 'Social Networks Autoposter (SNAP) Addons', 'social-networks-auto-poster-facebook-twitter-g' ), array( $this, 'metabox_addons' ), $this->page, 'normal' );
            $this->showPage_side();  $this->showPage($this->pluginName.': '. __( 'SNAP Addons', 'social-networks-auto-poster-facebook-twitter-g' ));
        }
        public function metabox_conns($post) { $conns = new sm_ntConns();  $conns->showMainInterface(); prr($conns->conns); }
        public function metabox_addons($post) { global $nxs_SNAP;  if (!isset($nxs_SNAP)) return; $nxs_SNAP->showAddonsTab();}

        public function showPage_accounts() { 
            add_meta_box( $this->page.'_accounts', __( 'Social Networks Autoposter (SNAP) Accounts', 'social-networks-auto-poster-facebook-twitter-g' ), array( $this, 'metabox_accounts' ), $this->page, 'normal' );                        
            $this->showPage_side();  $this->showPage($this->pluginName.': '. __( 'SNAP Accounts', 'social-networks-auto-poster-facebook-twitter-g' ));             
        }        
        public function showPage_userssites() { 
            add_meta_box( $this->page.'_accounts', __( 'Social Networks Autoposter (SNAP) Users/Sites Management', 'social-networks-auto-poster-facebook-twitter-g' ), array( $this, 'metabox_userssites' ), $this->page, 'normal' );                        
            $this->showPage_side();  $this->showPage($this->pluginName.': '. __( 'SNAP Accounts', 'social-networks-auto-poster-facebook-twitter-g' ));             
        }        
        public function showPage_query() {
            $this->showPage_log_query('query');
        }
        public function showPage_reposter() { if (!current_user_can('manage_options')) wp_die(esc_html__('You are not allowed to manage SNAP reposters.', 'social-networks-auto-poster-facebook-twitter-g'), '', array('response'=>403)); if (!empty($_GET['item'])) { $itemID = absint($_GET['item']); $post = get_post($itemID); if (!$post || $post->post_type !== 'nxs_filter') $post = null; } else $post = null;
          
            if (!empty($_GET['action'])&& $_GET['action']=='edit' && !empty($_GET['item']) && !empty($post) ) { if (!current_user_can('edit_post', $post->ID)) wp_die(esc_html__('You cannot edit this reposter.', 'social-networks-auto-poster-facebook-twitter-g'), '', array('response'=>403));
              if (!empty($_POST['nxs_snap_reposter_update'])) { check_admin_referer('nxs_edit_reposter_'.$post->ID); nxs_Filters::save_filter($post->ID); } ?> 
              <form method="post" id="nxs_form_rep"> <?php wp_nonce_field('nxs_edit_reposter_'.$post->ID); ?><input name="pid" value="<?php echo esc_attr($post->ID); ?>" type="hidden" /> <input name="action" value="nxs_snap_aj" type="hidden" /> <input id="nxs_resetStats" name="resetStats" value="0" type="hidden" />
                <input name="nxsact" value="saveRpst" type="hidden" /> <?php nxs_Filters::showEdit($this->page);
            }
            elseif (!empty($_GET['action'])&& $_GET['action']=='delete' && !empty($_GET['item']) && !empty($post) ) { check_admin_referer('nxsRepDel_'.$post->ID); if (!current_user_can('delete_post', $post->ID)) wp_die(esc_html__('You cannot delete this reposter.', 'social-networks-auto-poster-facebook-twitter-g'), '', array('response'=>403));
               wp_delete_post($post->ID, true); ?> <script type="text/javascript">window.location = "<?php echo esc_url(nxs_get_admin_url('admin.php?page=nxssnap-reposter')); ?>"</script>  <?php
            } elseif (!empty($_POST['action'])&& $_POST['action']=='delete' && !empty($_POST['nxs_filter']) ) {
               check_admin_referer('my_bulk_action_nonce', 'my_bulk_action_nonce'); foreach ((array)$_POST['nxs_filter'] as $rr) { $rr = absint($rr); if ($rr && get_post_type($rr)==='nxs_filter' && current_user_can('delete_post', $rr)) wp_delete_post($rr, true); }
               ?> <script type="text/javascript">window.location = "<?php echo esc_url(nxs_get_admin_url('admin.php?page=nxssnap-reposter')); ?>"</script>  <?php
            } else {            
              add_meta_box( $this->page.'_reposter', __( 'Social Networks Autoposter (SNAP) Auto-Reposter', 'social-networks-auto-poster-facebook-twitter-g' ), array( $this, 'metabox_reposter' ), $this->page, 'normal' );                        
            }            
            $this->showPage_side(); $this->showPage($this->pluginName.': '. __( 'Auto-Reposter Configuration', 'social-networks-auto-poster-facebook-twitter-g') , $post);                     
            
            if (!empty($post)) echo "</form>";  
          
        }        
        public function showPage_settings() { 
            add_meta_box( $this->page.'_settings', __( 'Social Networks Autoposter (SNAP) Settings', 'social-networks-auto-poster-facebook-twitter-g' ), array( $this, 'metabox_settings' ), $this->page, 'normal' );                        
            $this->showPage_side();  $this->showPage($this->pluginName.': '. __( 'SNAP Settings', 'social-networks-auto-poster-facebook-twitter-g' ));             
        }        
        public function showPage_log() {
            $tab = isset($_GET['tab']) && is_string($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : '';
            $this->showPage_log_query($tab === 'query' ? 'query' : 'log');
        }
        private function showPage_log_query($tab) {
            if ($tab === 'query') {
                add_meta_box( $this->page.'_query', __( 'Social Networks Autoposter (SNAP) Query', 'social-networks-auto-poster-facebook-twitter-g' ), array( $this, 'metabox_query' ), $this->page, 'normal' );
            } else {
                add_meta_box( $this->page.'_log', __( 'Social Networks Autoposter (SNAP) Log/History', 'social-networks-auto-poster-facebook-twitter-g' ), array( $this, 'metabox_log' ), $this->page, 'normal' );
            }
            $this->showPage_side();
            $this->showPage($this->pluginName.': '. __( 'SNAP Log/Query', 'social-networks-auto-poster-facebook-twitter-g' ), null, $tab);
        }
        public function showPage_about() { 
            add_meta_box( $this->page.'_about', __( 'Social Networks Autoposter (SNAP) Help/Support', 'social-networks-auto-poster-facebook-twitter-g' ), array( $this, 'metabox_about' ), $this->page, 'normal' );                        
            $this->showPage_side();  $this->showPage($this->pluginName.': '. __( 'SNAP Help/Support', 'social-networks-auto-poster-facebook-twitter-g' ));             
        }
        
        public function metabox_accounts($post) { global $nxs_SNAP;  if (!isset($nxs_SNAP)) return; $nxs_SNAP->showAccountsTab(); }
        
        public function metabox_userssites($post) { global $nxs_SNAP;  if (!isset($nxs_SNAP)) return; $nxs_SNAP->showUsersSitesMUTab(); }
        
        public function metabox_emptyaccss($post) { _e( 'Please add at least one account', 'social-networks-auto-poster-facebook-twitter-g' ); echo '&nbsp;-&gt;&nbsp;<a href="'.esc_url(nxs_get_admin_url('admin.php?page=nxssnap')).'">'.__( 'Accounts', 'social-networks-auto-poster-facebook-twitter-g' ).'</a>'; }
        
        public function metabox_query($post) {  global $nxs_SNAP;  if (!isset($nxs_SNAP)) return; $nxs_SNAP->showQueryTab(); }
        public function metabox_settings($post) { global $nxs_SNAP; if (!isset($nxs_SNAP)) return;  
          // if (isset($_POST['nxsMainFromElementAccts']) || isset($_POST['nxsMainFromSupportFld'])) $options = $nxs_SNAP->saveSNAPSettings($nxs_SNAP->nxs_options); //## Save Settings
          $nxs_SNAP->showSettingsTab();
        }        
        public function metabox_log($post) { global $nxs_SNAP;  if (!isset($nxs_SNAP)) return; $nxs_SNAP->showLogHistoryTab();}        
        public function metabox_about($post) { global $nxs_SNAP;  if (!isset($nxs_SNAP)) return; $nxs_SNAP->showAboutTab();}
        
        public function metabox_reposter($post) { 
             $itemsTable = new nxs_ReposterListTable(); $itemsTable->prepare_items();  ?>
           <div class="wrap">
             <div style="background:#ECECEC;border:1px solid #CCC;padding:0 10px;margin-top:5px;border-radius:5px;-moz-border-radius:5px;-webkit-border-radius:5px;margin-bottom: 7px;">
                <p><?php _e('Here you can configure automatic repost of the existing posts to your social network accounts.', 'social-networks-auto-poster-facebook-twitter-g'); ?> <a href="http://nxs.fyi/rpst" target="_blank"><?php _e('Instructions', 'social-networks-auto-poster-facebook-twitter-g'); ?></a></p> 
             </div>
             <div style="padding-top: 8px; padding-bottom: 8px;"> <a id="nxsFltAddButton" href="#" class="NXSButton"><?php _e( 'Add new Reposter Action', 'social-networks-auto-poster-facebook-twitter-g' ); ?></a> </div>
             <form method="get"> <input type="hidden" name="page" value="nxssnap-reposter" />
             <?php
             echo '<input type="hidden" name="my_bulk_action_nonce" value="' . wp_create_nonce( 'my_bulk_action_nonce' ) . '" />';
             $itemsTable->display(); ?>
             </form>
            </div> <div id="nxs_spFltPopup"><span class="nxspButton bClose"><span>X</span></span><div id="nxs_spFltPopupU" style="min-height: 300px;"><?php nxs_rpstPopupCode(); ?></div></div><?php 
            
        }
        
        public function metabox_dashboard($post) { ?> Quick links: <br/>
        
        
          <div align="center"><div class="nxsInfoMsg">Start here <img style="position: relative; top: 8px;" alt="Arrow" src="<?php echo esc_url(NXS_PLURL); ?>img/arrow_r_green_c1.png"></div>
          <a href="admin.php?page=nxssnap-accounts" class="NXSButton" id="nxs_snapConfAccs"><?php _e('Configure Social Networks', 'social-networks-auto-poster-facebook-twitter-g'); ?></a>&nbsp;&nbsp;&nbsp;<a href="#" class="NXSButtonB" id="nxs_snapConfRepstr"><?php _e('Configure Auto-Reposter', 'social-networks-auto-poster-facebook-twitter-g'); ?></a>&nbsp;&nbsp;&nbsp;<a href="#" class="NXSButtonB" id="nxs_snapConfSets"><?php _e('Plugin Settings', 'social-networks-auto-poster-facebook-twitter-g'); ?></a>&nbsp;&nbsp;&nbsp;<a href="#" class="NXSButtonB" id="nxs_snapConfLog"><?php _e('View Log', 'social-networks-auto-poster-facebook-twitter-g'); ?></a></div>           
           <?php 
        }
        
        public function metabox_newPost($post) { $itemsTable = new nxs_QPListTable(); $itemsTable->prepare_items(); ?>
        
        <div class="nxswrap" style="overflow: auto;">
        <ul class="nsx_tabs">
          <li><a class="ab-item" id="nsx_tab1_ttl" href="#nsx_tab1"><span style="font-weight:bold; font-size: 1.2em; color:#2ecc2e; margin: 3px;">&dArr;</span><?php _e('New Quick Post', 'social-networks-auto-poster-facebook-twitter-g') ?><span style="font-weight:bold; font-size: 1.2em; color:#2ecc2e; margin: 3px;">&dArr;</span></a></li>            
          <li><a href="#nsx_tab2"><?php _e('Quick Posts History', 'social-networks-auto-poster-facebook-twitter-g') ?></a></li>
        </ul>
        <div class="nsx_tab_container">
          <div id="nsx_tab1" class="nsx_tab_content"> 
            <?php global $nxs_SNAP;  if (!isset($nxs_SNAP)) return; $networks = (!current_user_can( 'manage_options' ) && current_user_can( 'haveown_snap_accss' ) ) ? $nxs_SNAP->nxs_acctsU : $nxs_SNAP->nxs_accts;  nxs_showNewPostForm($networks, false); ?>        
          </div>
          <div id="nsx_tab2" class="nsx_tab_content"> 
            <div class="wrap">             
              <form method="get"> <input type="hidden" name="page" value="nxssnap-post" /><?php $itemsTable->display() ?></form>        
            </div>        
          </div>
        </div> </div> <?php         
        
        }

        public function metabox_side_getitAddns($post) {
            nxs_addns::showAddnsOffers(); nxs_addns::showAddnsSideBar();
        }

        public function metabox_side_getit($post) {  ?>
        
        <a href="http://nxs.fyi/get-snap" target="_blank">SNAP Pro version</a> upgrade adds the ability to configure more than one account for each social network and some addidional features.<br/><div style="padding-bottom: 10px; padding-top: 7px;" align="center">
<b style="color: #008000">[Limited Time Offer]</b><br/>Get all addons for <b>Free</b> with any yearly <a href="https://post.social/" target="_blank">Post.Social subscription</a>.</div><div align="center"><a href="https://post.social/" target="_blank" class="NXSButton" id="nxs_snapUPG">View Post.Social Subscriptions</a></div>  <?php if(function_exists('nxsDoLic_ajax')) { ?> <br/><div align="center">
            <span style="color: #00FF00; font-weight: bold; font-size: 28px;">--&gt;</span>
            <a style="color: #008000; font-weight: normal; font-size: 13px;" class="showLic" href="#">[<?php  _e('Enter your Activation Key', 'social-networks-auto-poster-facebook-twitter-g'); ?>]</a>
            <span style="color: #00FF00; font-weight: bold; font-size: 28px;">&lt;--</span>
            </div> 
            <?php } 
       
        }
        
        public function metabox_side_supbox($post) { ?>  <?php _e('Most common issues/questions are already answered in the', 'social-networks-auto-poster-facebook-twitter-g'); ?>:<br/><a style="font-weight: normal; font-size: 16px; line-height: 24px;" target="_blank" href="https://www.nextscripts.com/support-faq/"><?php _e('Troubleshooting FAQ', 'social-networks-auto-poster-facebook-twitter-g'); ?></a><br/><br/>
          <?php _e('Can\'t find the answer ins the FAQ? Have troubles/problems/found a bug?', 'social-networks-auto-poster-facebook-twitter-g'); ?>:<br/><a style="font-weight: normal; font-size: 18px; line-height: 24px;" target="_blank" href="https://www.nextscripts.com/support">Open support ticket</a><br/><br/>
          <?php _e('Have questions/suggestions?', 'social-networks-auto-poster-facebook-twitter-g'); ?>:<br/><a style="font-weight:normal;font-size:18px;line-height:24px;" target="_blank" href="https://www.nextscripts.com/contact-us">Contact us</a><br/>
          <?php
        }
        
        public function metabox_side_instrbox($post) { global $nxs_snapAvNts; foreach ($nxs_snapAvNts as $avNt) { $clName = 'nxs_snapClass'.$avNt['code']; $nt = new $clName(); ?>
              <div style="padding-left: 10px; padding-top:5px;"><a style="background-image:url(<?php echo esc_url(nxs_network_asset_url(!empty($avNt['imgcode']) ? $avNt['imgcode'] : $avNt['lcode'])); ?>) !important;" class="nxs_icon16" target="_parent" href="<?php echo esc_url($nt->ntInfo['instrURL']); ?>">  <?php echo $nt->ntInfo['name']; ?> </a></div>
            <?php }
        }
        
        public function metabox_side_info($post) { global $nxs_SNAP;  if (!isset($nxs_SNAP)) return; $options = $nxs_SNAP->nxs_options;  $sMode = $nxs_SNAP->sMode; ?>
            <div align="center"><a target="_blank" href="https://www.nextscripts.com"><img src="<?php echo esc_url(NXS_PLURL); ?>img/SNAP_Logo_2014.png"></a></div> <br/>
              <?php  _e('Plugin Version', 'social-networks-auto-poster-facebook-twitter-g'); ?>: <span style="color:#008000;font-weight: bold;"><?php echo NextScripts_SNAP_Version; ?></span><br/>
              <?php if (defined ('NextScripts_UPG_SNAP_Version') && version_compare(NextScripts_UPG_SNAP_Version, '2.0.0', '<')) {
                  if($sMode['l']=='P') { ?> [Pro]<br/>&nbsp;&nbsp;&nbsp;(One User, Multiple Accounts)&nbsp;&nbsp;<?php } elseif ($sMode['l']=='M'){ ?> [Pro]<br/>&nbsp;&nbsp;&nbsp;(Multiple Users, Multiple Accounts)<?php } ?><br/>
                  <?php if (defined('NXSAPIVER')) echo '<span id="nxsAPIUpd">API</span> Version: <span style="color:#008000;font-weight: bold;">'.NXSAPIVER.'</span>';
              } ?>
              <?php nxs_addns::showAddnsVersions('4'); ?>

            <div id="showLicForm"><span class="nxspButton bClose"><span>X</span></span>
             <div id="nxs_showLicForm" style="min-height: 240px; padding: 10px 20px;">
              <style type="text/css">
                .pgwModal input:not([type=checkbox]):not([type=radio]):not([type=submit]):not([type=button]):not([type=image]),
                #nxs_showLicForm input:not([type=checkbox]):not([type=radio]):not([type=submit]):not([type=button]):not([type=image]) {
                    padding: 6px 10px !important;
                    height: 32px !important;
                    border: 1px solid #ACACAC !important;
                    box-sizing: border-box !important;
                    border-radius: 4px !important;
                    font-size: 13px !important;
                    margin-top: 4px !important;
                    margin-bottom: 4px !important;
                    display: inline-block !important;
                    vertical-align: middle !important;
                    width: 100% !important;
                    max-width: 320px !important;
                }
                .pgwModal .button-primary,
                #nxs_showLicForm .button-primary {
                    background-color: #038bc4 !important;
                    background: -webkit-gradient(linear, left top, left bottom, color-stop(0.05, #038bc4), color-stop(1, #096aa8)) !important;
                    background: -webkit-linear-gradient(top, #038bc4 5%, #096aa8 100%) !important;
                    background: -moz-linear-gradient(top, #038bc4 5%, #096aa8 100%) !important;
                    background: linear-gradient(to bottom, #038bc4 5%, #096aa8 100%) !important;
                    filter: progid:DXImageTransform.Microsoft.gradient(startColorstr='#038bc4', endColorstr='#096aa8') !important;
                    -moz-border-radius: 4px !important;
                    -webkit-border-radius: 4px !important;
                    border-radius: 4px !important;
                    border: 1px solid #077cb8 !important;
                    display: inline-block !important;
                    color: #ffffff !important;
                    font-family: 'Trebuchet MS', sans-serif !important;
                    font-size: 12px !important;
                    font-weight: bold !important;
                    padding: 5px 14px !important;
                    text-decoration: none !important;
                    text-shadow: 1px 1px 0px #095d80 !important;
                    height: auto !important;
                    line-height: normal !important;
                    box-shadow: none !important;
                    cursor: pointer !important;
                    margin-top: 5px !important;
                }
                .pgwModal .button-primary:hover,
                #nxs_showLicForm .button-primary:hover {
                    color: #ffffff !important;
                    background-color: #096aa8 !important;
                    background: -webkit-gradient(linear, left top, left bottom, color-stop(0.05, #096aa8), color-stop(1, #038bc4)) !important;
                    background: -webkit-linear-gradient(top, #096aa8 5%, #038bc4 100%) !important;
                    background: -moz-linear-gradient(top, #096aa8 5%, #038bc4 100%) !important;
                    background: linear-gradient(to bottom, #096aa8 5%, #038bc4 100%) !important;
                    filter: progid:DXImageTransform.Microsoft.gradient(startColorstr='#096aa8', endColorstr='#038bc4') !important;
                }
                #nxs_showLicForm td {
                    padding: 2px 0 !important;
                }
                #nxs_showLicForm h3 {
                    margin: 10px 0 3px 0 !important;
                    font-size: 14px !important;
                }
              </style>
              <?php wp_nonce_field( 'doLic', 'doLic_wpnonce' ); ?>
               <h3 style="margin-top: 0px !important;"><?php  _e('Option 1: Enter your Activation Key manually', 'social-networks-auto-poster-facebook-twitter-g'); ?></h3>
               <span style="font-size: 11px; color: #666;"><?php  _e('You can find your key on this page', 'social-networks-auto-poster-facebook-twitter-g'); ?>: <a href="https://www.nextscripts.net/account/" target="_blank">https://www.nextscripts.net/account/</a></span>
               <table style="width: 100%; table-layout: fixed; margin-top: 5px;">
                 <tr>
                   <td style="width: 110px;"><?php _e('Enter Key', 'social-networks-auto-poster-facebook-twitter-g'); ?>:</td>
                   <td>
                     <input name="eLic" id="eLic" value="<?php echo !empty($options['lk']) ? esc_attr($options['lk']) : ''; ?>" style="width: 100%; max-width: 320px;"/>
                   </td>
                 </tr>
                 <tr>
                   <td></td>
                   <td>
                     <input type="button" class="button-primary" name="eLicDo" onclick="doLic();" value="Activate Key" />
                   </td>
                 </tr>
               </table>
               <div style="margin: 15px 0 10px 0; border-top: 1px solid #ddd; padding-top: 10px;">
                 <h3><?php  _e('Option 2: Connect your nextscripts.net Account', 'social-networks-auto-poster-facebook-twitter-g'); ?></h3>
                 <span style="font-size: 11px; color: #666;"><?php _e('Log in with your WooCommerce store account to automatically activate all purchased addons.', 'social-networks-auto-poster-facebook-twitter-g'); ?></span>
               </div>
               <table style="width: 100%; table-layout: fixed;">
                 <tr>
                   <td style="width: 110px;"><?php _e('Email/User', 'social-networks-auto-poster-facebook-twitter-g'); ?>:</td>
                   <td><input type="text" id="nxs_woo_email" style="width: 100%; max-width: 320px;" /></td>
                 </tr>
                 <tr>
                   <td><?php _e('Password', 'social-networks-auto-poster-facebook-twitter-g'); ?>:</td>
                   <td><input type="password" id="nxs_woo_pass" style="width: 100%; max-width: 320px;" /></td>
                 </tr>
                 <tr>
                   <td></td>
                   <td>
                     <input type="button" class="button-primary" onclick="nxsConnectWooAccount();" value="Connect Account" />
                   </td>
                 </tr>
               </table>
               <br/>
               <img id="enterKeyAPI2xLoadingImg" style="display: none;" src='<?php echo esc_url(NXS_PLURL); ?>img/ajax-loader-sm.gif' />
               <div style="font-size: 11px; color: #666; margin-top: 5px;"><?php _e('Your plugin will be automatically upgraded', 'social-networks-auto-poster-facebook-twitter-g'); ?>.</div>
              </div>
             </div>
         <?php  
        }
    }
}
?>
