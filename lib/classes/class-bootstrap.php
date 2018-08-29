<?php
/**
 * Bootstrap
 *
 * @since 0.2.0
 */
namespace wpCloud\StatelessMedia {

    
    if (!class_exists('wpCloud\StatelessMedia\Bootstrap')) {

        final class Bootstrap extends \UsabilityDynamics\WP\Bootstrap_Plugin
        {

            /**
             * Google Storage Client
             * Use $this->get_client()
             *
             * @var \wpCloud\StatelessMedia\GS_CLient
             */
            private $client;

            /**
             * Plugin core version.
             *
             * @static
             * @property $version
             * @type {Object}
             */
            public static $version = '1.7.3';

            /**
             * Singleton Instance Reference.
             *
             * @protected
             * @static
             * @property $instance
             * @type \wpCloud\StatelessMedia\Bootstrap object
             */
            protected static $instance = null;

            /**
             * Instantaite class.
             */
            public function init()
            {
                $opt_data = array();
                $opt_data = get_site_option("gcs_plugin_enabled", false, false);

                if(!isset($opt_data["sites"])) {
                    $opt_data['sites'] = array();

                }
                $current_blog_id = 1;
                if( defined( BLOG_ID_CURRENT_SITE ) ) {
                    $current_blog_id = BLOG_ID_CURRENT_SITE;
                }
                // only proceed if primary site or current_site_id is in sites array
                if( in_array(get_current_blog_id(), $opt_data["sites"]) || get_current_blog_id() == $current_blog_id ) {

                   /* //vikashedit
                    if( get_current_blog_id() == 1 && count($opt_data["sites"]) == 0 ){
                    // master site and no data in DB yet, add master site ie. 1
                    $data['sites'] = array(1);
                    update_site_option("gcs_plugin_enabled", $data);
                    //print_r(get_site_option("gcs_sync_status_" . $blogid, false, false));

                    } else {

                    }*/
                   // echo "init";

                    /**
                     * Register SM metaboxes
                     */
                    add_action('admin_init', array($this, 'register_metaboxes'));
    
                    /**
                     * Add custom actions to media rows
                     */
                    add_filter('media_row_actions', array($this, 'add_custom_row_actions'), 10, 3);
    
                    /**
                     * Handle switch blog properly.
                     */
                    add_action( 'switch_blog', array( $this, 'on_switch_blog' ), 10, 2 );
    
                    /**
                     * Init AJAX jobs
                     */
                    new Ajax();
    
                    /**
                     * Maybe Upgrade current Version
                     */
                    Upgrader::call($this->args['version']);
    
                    /**
                     * Load WP-CLI Commands
                     */
                    if (defined('WP_CLI') && WP_CLI) {
                        include_once($this->path('lib/cli/class-sm-cli-command.php', 'dir'));
                    }
    
                    $this->is_network_detected();
    
                    /**
                     * Define settings and UI.
                     *
                     * Example:
                     *
                     * Get option
                     * $this->get( 'sm.client_id' )
                     *
                     * Manually Update/Add option
                     * $this->set( 'sm.client_id', 'zxcvv12adffse' );
                     */
                    $this->settings = new Settings();

                    add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));

                    /* Initialize plugin only if Mode is not 'disabled'. */
                    if ($this->get('sm.mode') !== 'disabled') {

                        /**
                         * Determine if we have issues with connection to Google Storage Bucket
                         * if SM is not disabled.
                         */
                        $is_connected = $this->is_connected_to_gs();

                        if (is_wp_error($is_connected)) {
                            $this->errors->add($is_connected->get_error_message());
                        }
    
                        /** Temporary fix to WP 4.4 srcset feature **/
                       // add_filter('wp_calculate_image_srcset',  array($this, 'imgsrcset'));
                        add_filter( 'wp_calculate_image_srcset', array($this, 'wp_calculate_image_srcset'), 10, 5 );
    
                        /**
                         * Carry on only if we do not have errors.
                         */
                        if (!$this->has_errors()) {
    
                            if ($this->get('sm.mode') === 'cdn') {
                                add_filter('wp_get_attachment_image_attributes', array($this, 'wp_get_attachment_image_attributes'), 20, 3);
                                add_filter('wp_get_attachment_url', array($this, 'wp_get_attachment_url'), 20, 2);
                                add_filter('attachment_url_to_postid', array($this, 'attachment_url_to_postid'), 20, 2);

                                if ($this->get('sm.body_rewrite') == 'true') {

                                    add_filter('the_content', array($this, 'the_content_filter'));
                                }
                            }
    
                            if ($root_dir = $this->get('sm.root_dir')) {
                                if (trim($root_dir) !== '') {
                                    add_filter('wp_stateless_file_name', array($this, 'handle_root_dir'));
                                }
                            }
    
                            /**
                             * Rewrite Image URLS
                             */
                            add_filter('image_downsize', array($this, 'image_downsize'), 99, 3);
    
                            /**
                             * Extends metadata by adding GS information.
                             */
                            add_filter('wp_get_attachment_metadata', array($this, 'wp_get_attachment_metadata'), 10, 2);

                           // add_action( 'add_post_meta',  array($this, 'add_post_meta'), 10, 3 );
    
                            /**
                             * Add/Edit Media
                             *
                             * Once added or edited we can get into Attachment ID then get all image sizes and sync them with GS
                             */
                            add_filter('wp_generate_attachment_metadata', array($this, 'add_media'), 100, 2);
                            //add_filter('wp_edit_attachment', array($this, 'edit_media_test'), 100, 2);
                            add_filter( 'wp_prepare_attachment_for_js', array($this,'filter_wp_prepare_attachment_for_js'), 100, 3 ); //vikashedit
    
                            if ($this->get('sm.on_fly') == 'true') {
                                /**
                                 * Handle any other on fly generated media
                                 */
                                add_filter('image_make_intermediate_size', array($this, 'handle_on_fly'));
                            }
    
                            if ($this->get('sm.delete_remote') == 'true') {
                                /**
                                 * On physical file deletion we remove any from GS
                                 */
                                add_filter('delete_attachment', array($this, 'remove_media'));
                            }
                            add_filter('manage_sites-network_columns', array($this,'gcs_sync_columns'));
                            add_action('manage_sites_custom_column', array($this,'gcs_sync_column_data'), 10, 2);
                           add_action( 'wpmu_new_blog', array($this,'bwcdn_cs_wpmu_add_new_blog'), 100, 6 );



                    }

                }


            }

            }

            public function add_post_meta( $post_id, $key, $value ){
                global $wpdb;
                $prefix = $wpdb->get_blog_prefix(get_current_blog_id());
                $sm = $wpdb->get_var("SELECT option_value FROM `" . $prefix . "options` WHERE `option_name` LIKE 'sm' LIMIT 1");
                $sm = maybe_unserialize($sm);
                if('sm_cloud' === $key && 'cdn' === $sm['mode'] && 'wordpress' === $_GET['import'] ) {
                    global $wpdb;
                    $sm_cloud_data = $value;

                    if(  ! (  ( isset($sm['bucket']) && $sm['bucket'] ) &&  ( isset($sm['root_dir']) && $sm['root_dir'] ) ) ) {
                        echo "bucket name or root dir data missing";
                        exit;
                    }
                    if( ! isset($sm['media_scheme']) && $sm['media_scheme'] ) {
                        $sm['media_scheme'] = 'https';
                    }
                    if( ! isset($sm['bucket_prefix']) && $sm['bucket_prefix'] ) {
                        $sm['media_scheme'] = 'storage.googleapis.com';
                    }
                    $temp_sm_root_dir = explode('/', $sm['root_dir']);
                    foreach( $sm_cloud_data as $sm_cloud_main_key => $sm_cloud_main_value) {

                        if( ! is_array($sm_cloud_main_value) ) {
                            $str = $sm_cloud_main_value;
                            if( 'mediaLink' === $sm_cloud_main_key  || 'selfLink' === $sm_cloud_main_key ) {
                                $str = urldecode($str);
                            }
                            $temp_arr = explode('/', $str);
                            if( 'id' === $sm_cloud_main_key ) {
                                $temp_arr[0] = $sm['bucket'];
                                $temp_arr[1] = $temp_sm_root_dir[0];
                                $temp_arr[2] = $temp_sm_root_dir[1];
                                $value[ $sm_cloud_main_key ] = implode('/', $temp_arr);
                            } elseif( 'name' === $sm_cloud_main_key ) {
                                $temp_arr[0] = $temp_sm_root_dir[0];
                                $temp_arr[1] = $temp_sm_root_dir[1];
                                $value[ $sm_cloud_main_key ] = implode('/', $temp_arr);
                            }  elseif( 'fileLink' === $sm_cloud_main_key ) {

                                $temp_arr[0] = $sm['media_scheme'].'://';
                                $temp_arr[1] = $sm['bucket_prefix'];
                                $temp_arr[2] = $sm['bucket'];
                                $temp_arr[3] = $temp_sm_root_dir[0];
                                $temp_arr[4] = $temp_sm_root_dir[1];
                                $value[ $sm_cloud_main_key ] = implode('/', $temp_arr);
                            }
                            elseif( 'mediaLink' === $sm_cloud_main_key ) {
                                $temp_arr[7] = $sm['bucket'];
                                $temp_arr[9] = $temp_sm_root_dir[0];
                                $temp_arr[10] = $temp_sm_root_dir[1];
                                $value[ $sm_cloud_main_key ] = implode('/', $temp_arr);
                            }
                            elseif( 'selfLink' === $sm_cloud_main_key ) {
                                $temp_arr[6] = $sm['bucket'];
                                $temp_arr[8] = $temp_sm_root_dir[0];
                                $temp_arr[9] = $temp_sm_root_dir[1];
                                $value[ $sm_cloud_main_key ] = implode('/', $temp_arr);
                            }

                        } else {
                            if( 'object' === $sm_cloud_main_key) {
                                foreach ($sm_cloud_main_value as $sm_cloud_main_internal_key => $sm_cloud_main_internal_value) {
                                    if( ! in_array($sm_cloud_main_internal_key, array('selfLink','mediaLink','fileLink','name','id'))){
                                        continue;
                                    }
                                    $str = $sm_cloud_main_internal_value;
                                    if( 'mediaLink' === $sm_cloud_main_internal_key  || 'selfLink' === $sm_cloud_main_internal_key ) {
                                        $str = urldecode($str);
                                    }
                                    $temp_arr = explode('/', $str);
                                    if( 'id' === $sm_cloud_main_internal_key ) {
                                        $temp_arr[0] = $sm['bucket'];
                                        $temp_arr[1] = $temp_sm_root_dir[0];
                                        $temp_arr[2] = $temp_sm_root_dir[1];
                                        $value[ $sm_cloud_main_key ][ $sm_cloud_main_internal_key ] = implode('/', $temp_arr);
                                    } elseif( 'name' === $sm_cloud_main_internal_key ) {
                                        $temp_arr[0] = $temp_sm_root_dir[0];
                                        $temp_arr[1] = $temp_sm_root_dir[1];
                                        $value[ $sm_cloud_main_key ][ $sm_cloud_main_internal_key ] = implode('/', $temp_arr);
                                    }  elseif( 'fileLink' === $sm_cloud_main_internal_key ) {

                                        $temp_arr[0] = $sm['media_scheme'].'://';
                                        $temp_arr[1] = $sm['bucket_prefix'];
                                        $temp_arr[2] = $sm['bucket'];
                                        $temp_arr[3] = $temp_sm_root_dir[0];
                                        $temp_arr[4] = $temp_sm_root_dir[1];
                                        $value[ $sm_cloud_main_key ][ $sm_cloud_main_internal_key ] = implode('/', $temp_arr);
                                    }
                                    elseif( 'mediaLink' === $sm_cloud_main_internal_key ) {
                                        $temp_arr[7] = $sm['bucket'];
                                        $temp_arr[9] = $temp_sm_root_dir[0];
                                        $temp_arr[10] = $temp_sm_root_dir[1];
                                        $value[ $sm_cloud_main_key ][ $sm_cloud_main_internal_key ] = implode('/', $temp_arr);
                                    }
                                    elseif( 'selfLink' === $sm_cloud_main_internal_key ) {
                                        $temp_arr[6] = $sm['bucket'];
                                        $temp_arr[8] = $temp_sm_root_dir[0];
                                        $temp_arr[9] = $temp_sm_root_dir[1];
                                        $value[ $sm_cloud_main_key ][ $sm_cloud_main_internal_key ] = implode('/', $temp_arr);
                                    }
                                }
                            } else if( 'sizes' === $sm_cloud_main_key) {
                                foreach ($sm_cloud_main_value as $sm_cloud_main_internal_key => $sm_cloud_main_internal_value) {
                                    foreach ($sm_cloud_main_internal_value as $sm_cloud_main_internal_key_size => $sm_cloud_main_internal_value_size) {

                                        $str = $sm_cloud_main_internal_value_size;
                                        if ('mediaLink' === $sm_cloud_main_internal_key_size || 'selfLink' === $sm_cloud_main_internal_key_size) {
                                            $str = urldecode($str);
                                        }
                                        $temp_arr = explode('/', $str);
                                        if ('id' === $sm_cloud_main_internal_key_size) {
                                            $temp_arr[0] = $sm['bucket'];
                                            $temp_arr[1] = $temp_sm_root_dir[0];
                                            $temp_arr[2] = $temp_sm_root_dir[1];
                                            $value[ $sm_cloud_main_key ][ $sm_cloud_main_internal_key ][ $sm_cloud_main_internal_key_size ] = implode('/', $temp_arr);
                                        } elseif ('name' === $sm_cloud_main_internal_key_size) {
                                            $temp_arr[0] = $temp_sm_root_dir[0];
                                            $temp_arr[1] = $temp_sm_root_dir[1];
                                            $value[ $sm_cloud_main_key ][ $sm_cloud_main_internal_key ][ $sm_cloud_main_internal_key_size ] = implode('/', $temp_arr);
                                        } elseif ('fileLink' === $sm_cloud_main_internal_key_size) {

                                            $temp_arr[0] = $sm['media_scheme'].'://';
                                            $temp_arr[1] = $sm['bucket_prefix'];
                                            $temp_arr[2] = $sm['bucket'];
                                            $temp_arr[3] = $temp_sm_root_dir[0];
                                            $temp_arr[4] = $temp_sm_root_dir[1];
                                            $value[ $sm_cloud_main_key ][ $sm_cloud_main_internal_key ][ $sm_cloud_main_internal_key_size ] = implode('/', $temp_arr);
                                        } elseif ('mediaLink' === $sm_cloud_main_internal_key_size) {
                                            $temp_arr[7] = $sm['bucket'];
                                            $temp_arr[9] = $temp_sm_root_dir[0];
                                            $temp_arr[10] = $temp_sm_root_dir[1];
                                            $value[ $sm_cloud_main_key ][ $sm_cloud_main_internal_key ][ $sm_cloud_main_internal_key_size ] = implode('/', $temp_arr);
                                        } elseif ('selfLink' === $sm_cloud_main_internal_key_size) {
                                            $temp_arr[6] = $sm['bucket'];
                                            $temp_arr[8] = $temp_sm_root_dir[0];
                                            $temp_arr[9] = $temp_sm_root_dir[1];
                                            $value[ $sm_cloud_main_key ][ $sm_cloud_main_internal_key ][ $sm_cloud_main_internal_key_size ] = implode('/', $temp_arr);
                                        }
                                    }
                                }
                            }

                        }
                    }

                }

            }



            public function bwcdn_cs_wpmu_add_new_blog( $blog_id, $user_id, $domain, $path, $site_id, $meta ) {
                global $wpdb;
                if( $blog_id > 0 ) {
                    $blog_prefix = $wpdb->get_blog_prefix($blog_id);
                    $table_name = $blog_prefix.'options';
                    $currentBlogid = get_current_blog_id();
                    if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
                    $options = $wpdb->get_results("SELECT option_name, option_value FROM `".$wpdb->base_prefix."options` WHERE option_name in ('sm','sm_bucket','sm_key_json','sm_cache_control','sm_domain_name','sm_mode','sm_media_scheme','sm_bucket_prefix')");


                        switch_to_blog($blog_id );
                        $add_new_site_flag = FALSE;
                        foreach ($options as $key => $option) {
                            if (!update_option($option->option_name, $option->option_value)) {
                                if (add_option($option->option_name, $option->option_value)) {
                                    $add_new_site_flag = TRUE;
                                }
                            } else {
                                $add_new_site_flag = TRUE;
                            }
                        }
                        if ($add_new_site_flag) {
                            $opt_data = get_site_option("gcs_plugin_enabled", FALSE, FALSE);
                            if (is_multisite() && count($opt_data) >= 1) {
                                if (!in_array($blog_id, $opt_data['sites'])) {
                                    $opt_data['sites'][] = $blog_id;
                                }
                                if (count($opt_data["sites"]) > 0) {
                                    update_site_option("gcs_plugin_enabled", $opt_data);
                                }
                            }
                        }
                        switch_to_blog($currentBlogid);
                    }

                }
            }

            public function wp_calculate_image_srcset( $sources, $size_array, $image_src, $image_meta, $attachment_id ){

                $image_src_path = substr($image_src,0,strrpos( $image_src, '/' ) );
                
                foreach ( $sources as $key=>$source ) {
                    $img_url_path = substr( $sources[$key]['url'],0,strrpos( $sources[$key]['url'], '/' ) );
                    $sources[$key]['url'] =  str_replace($img_url_path, $image_src_path, $sources[$key]['url']);
                }
                return $sources;
            }

            /**
             * To add custom columns to sites list for parallel sync progress reporting
             */
            public function gcs_sync_columns( $columns ) {
                $columns["gcs-sync"] = "GCS Sync";
                return $columns;
            }

            public function gcs_sync_column_data( $colname, $site_id ) {

                if ( $colname == 'gcs-sync'){
                        wp_cache_flush();

                    $opt_enabled = get_site_option("gcs_plugin_enabled", false, false);
                    //print_r($opt_enabled);
                    if(!isset($opt_enabled["sites"]))
                        $opt_enabled['sites'] = array();

                    $is_sm_mode_enabled = get_blog_option($site_id, "sm_mode", "disabled");
                   // error_log($site_id ." = ".$is_sm_mode_enabled);
                    // only proceed if primary site or current_site_id is in sites array

                    if($is_sm_mode_enabled == "cdn"){
                        // site is enabled
                        $enabled_html = '<span style="color: #111111; background-color:#00ff00;">Enabled</span>';
                        $disabled_html = '<input type="button" value="Disable Plugin"   id="gcs_site_disable_'.$site_id.'" />';
                    } else {
                        $enabled_html = '<input type="button" value="Enable Plugin"   id="gcs_site_enable_'.$site_id.'" />';
                        $disabled_html = "";
                    }

                        $sync_url = plugins_url("bwcdn-cs-uploaderloader")."/parallelsync.php?blogid=".$site_id."&secret=bw78901603&parallel=8";
                        //file_get_contents($sync_url);
                        $sync_start = plugins_url("bwcdn-cs-uploaderloader")."/sync-start.php?url=".urlencode($sync_url);
                        //echo $sync_start;

                    $activate_url = admin_url( 'admin-ajax.php' )."?action=gcs_site_enable&site_id=".$site_id;
                    $deactivate_url = admin_url( 'admin-ajax.php' )."?action=gcs_site_disable&site_id=".$site_id;
                    $status_url = admin_url( 'admin-ajax.php' )."?action=gcs_sync_status&site_id=".$site_id;
                        echo '
                        <div id="test_'.$site_id.'"></div>
                        <div id="gcs_sync_progress_'.$site_id.'">
                           <div id="gcs_sync_progresso_'.$site_id.'">
                           </div>
                            '.$enabled_html.$disabled_html.'
                            <input type="button" value="Start Sync"   id="gcs_sync_start_'.$site_id.'" style="display:none" />
                        </div>
                        
                        <script type="text/javascript">
    jQuery(document).ready(function(){
        jQuery("#gcs_site_enable_'.$site_id.'").click(function(){
       
            jQuery.ajax({url: "'.$activate_url.'", async: true, success: function(result){
                //alert(result);
               location.reload();
                //jQuery("#test_'.$site_id.'").html(result);
            }});
            
            });
         
            
            jQuery("#gcs_site_disable_'.$site_id.'").click(function(){
       
            jQuery.ajax({url: "'.$deactivate_url.'", async: true, success: function(result){
                //alert(result);
                location.reload();
                //jQuery("#test_'.$site_id.'").html(result);
            }});
            
            });
        
        
                jQuery("#gcs_sync_start_'.$site_id.'").click(function(){
               
                        jQuery.ajax({url: "'.$sync_start.'", async: true, success: function(result){
                            //alert(result);
                            //jQuery("#test_'.$site_id.'").html(result);
                        }});
                    
                  
                       setInterval(function(){
                        // this will run after every 5 seconds
        
                        
                        jQuery.ajax({url: "'.$status_url.'", async: true, success: function(result){
                                   
                                   
                                   jQuery("#gcs_sync_progresso_'.$site_id.'").html(result.data);
                                   //console.log(result.data);
                                   //console.log(json.data);
                                   //alert(result);
                        
                        }});
                    
                }, 2000);
                        
               }); // sync click end
               
        }); // click end 
    
    </script>
    
    ';
    
                         
    
                        //echo $site_id;
                   }
                  //"100%"; //get_post_meta( $cptid, '_my_meta_value_key', true );
            }


            /**
             * Get new blog settings once switched blog.
             * @param $new_blog
             * @param $prev_blog_id
             */
            public function on_switch_blog($new_blog, $prev_blog_id)
            {   global $wpdb;
                $blog_prefix = $wpdb->get_blog_prefix($new_blog);
                $table_name = $blog_prefix.'options';
                if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
                    $this->settings->refresh();
                }
            }

            /**
             * @param $actions
             * @param $post
             * @param $detached
             * @return mixed
             */
            public function add_custom_row_actions($actions, $post, $detached)
            {

                if (!current_user_can('upload_files')) return $actions;

                if ($post && 'attachment' == $post->post_type && 'image/' == substr($post->post_mime_type, 0, 6)) {
                    $actions['sm_sync'] = '<a href="javascript:;" data-id="' . $post->ID . '" data-type="image" class="sm_inline_sync">' . __('Regenerate and Sync with GCS', ud_get_stateless_media()->domain) . '</a>';
                }

                if ($post && 'attachment' == $post->post_type && 'image/' != substr($post->post_mime_type, 0, 6)) {
                    $actions['sm_sync'] = '<a href="javascript:;" data-id="' . $post->ID . '" data-type="other" class="sm_inline_sync">' . __('Sync with GCS', ud_get_stateless_media()->domain) . '</a>';
                }

                return $actions;

            }

            /**
             * Register metaboxes
             */
            public function register_metaboxes()
            {
                add_meta_box(
                    'sm-attachment-metabox',
                    __('Google Cloud Storage', ud_get_stateless_media()->domain),
                    array($this, 'attachment_meta_box_callback'),
                    'attachment',
                    'side',
                    'low'
                );
            }

            /**
             * @param $post
             */
            public function attachment_meta_box_callback($post)
            {
                ob_start();

                $sm_cloud = get_post_meta($post->ID, 'sm_cloud', 1);

                if (is_array($sm_cloud) && !empty($sm_cloud['fileLink'])) { ?>

                    <?php if (!empty($sm_cloud['cacheControl'])) { ?>
                        <div class="misc-pub-cache-control hidden">
                            <?php _e('Cache Control:', ud_get_stateless_media()->domain); ?>
                            <strong><span><?php echo $sm_cloud['cacheControl']; ?></span> </strong>
                        </div>
                    <?php } ?>

                    <div class="misc-pub-gs-file-link" style="margin-bottom: 15px;">
                        <label>
                            <?php _e('Storage Bucket URL:', ud_get_stateless_media()->domain); ?> <a
                                href="<?php echo $sm_cloud['fileLink']; ?>" target="_blank"
                                class="sm-view-link"><?php _e('[view]'); ?></a>
                            <input type="text" class="widefat urlfield" readonly="readonly"
                                   value="<?php echo esc_attr($sm_cloud['fileLink']); ?>"/>
                        </label>
                    </div>

                    <?php

                    if (!empty($sm_cloud['bucket'])) {
                        ?>
                        <div class="misc-pub-gs-bucket" style="margin-bottom: 15px;">
                            <label>
                                <?php _e('Storage Bucket:', ud_get_stateless_media()->domain); ?>
                                <input type="text" class="widefat urlfield" readonly="readonly"
                                       value="gs://<?php echo esc_attr($sm_cloud['bucket']); ?>"/>
                            </label>
                        </div>
                        <?php
                    }

                    if (current_user_can('upload_files')) {
                        if ($post && 'attachment' == $post->post_type && 'image/' == substr($post->post_mime_type, 0, 6)) {
                            ?>
                            <a href="javascript:;" data-type="image" data-id="<?php echo $post->ID; ?>"
                               class="button-secondary sm_inline_sync"><?php _e('Regenerate and Sync with GCS', ud_get_stateless_media()->domain); ?></a>
                            <?php
                        }

                        if ($post && 'attachment' == $post->post_type && 'image/' != substr($post->post_mime_type, 0, 6)) {
                            ?>
                            <a href="javascript:;" data-type="other" data-id="<?php echo $post->ID; ?>"
                               class="button-secondary sm_inline_sync"><?php _e('Sync with GCS', ud_get_stateless_media()->domain); ?></a>
                            <?php
                        }
                    }
                }

                echo apply_filters('sm::attachment::meta', ob_get_clean(), $post->ID);
            }

            /**
             * @param $current_path
             * @return string
             */
            public function handle_root_dir($current_path)
            {
                $root_dir = $this->get('sm.root_dir');
                $root_dir = trim($root_dir);

                if (!empty($root_dir)) {
                    return $root_dir . $current_path;
                }

                return $current_path;
            }

            /**
             * @param $content
             * @return mixed
             */
            public function the_content_filter($content)
            {
                global $post;

              /*
               // earlier - replace only when sm_cloud exists

                 $args = array( 'post_type' => 'attachment', 'posts_per_page' => -1, 'post_status' =>'any', 'post_parent' => $post->ID );
                $attachments = get_posts( $args );
                if ( $attachments ) {
                    foreach ( $attachments as $attachment ) {
                        $sm_cloud = get_post_meta($attachment->ID, 'sm_cloud', 1);
                        if (is_array($sm_cloud) && !empty($sm_cloud['fileLink'])) {
                            if ($upload_data = wp_upload_dir()) {

                                if (!empty($upload_data['baseurl']) && !empty($content)) {
                                    $baseurl = preg_replace('/https?:\/\//', '', $upload_data['baseurl']);
                                    $root_dir = trim($this->get('sm.root_dir'));
                                    $root_dir = !empty($root_dir) ? $root_dir : false;
                                    //            $content = preg_replace( '/(href|src)=(\'|")(https?:\/\/'.str_replace('/', '\/', $baseurl).')\/(.+?)(\.jpg|\.png|\.gif|\.jpeg)(\'|")/i',
                                    //                '$1=$2https://storage.googleapis.com/'.$this->get( 'sm.bucket' ).'/'.($root_dir?$root_dir:'').'$4$5$6', $content);

                                    $content = preg_replace('/(href|src)=(\'|")(https?:\/\/' . str_replace('/', '\/', $baseurl) . ')\/(.+?)(\.jpg|\.png|\.gif|\.jpeg|\.doc|\.docx|\.pdf|\.ppt|\.pptx|\.zip|\.rar|\.mp3|\.mp4|\.m4a|\.mov|\.avi|\.xls|\.xlsx|\.key|\.keynote|\.psd|\.ai|\.fla|\.cdr|\.nef|\.cr2|\.arw|\.mpg|\.3gp|\.3g2|\.midi|\.mid|\.odt|\.pps|\.ppsx|\.ogg|\.wma|\.wav|\.m4v|\.webm|\.ogv|\.wmv|\.flv)(\'|")/i',
                                        '$1=$2http://' . $this->get('sm.bucket') . '/' . ($root_dir ? $root_dir : '') . '$4$5$6', $content);
                                }
                            }
                        }
                    }


                }

*/
                // new method , that replaces every image - sync need to be run first
//                $id = get_current_blog_id();
//                $opt_data = get_site_option("gcs_sync_status_".$id, false, false);
//                if($opt_data["finished"]) {
                    // if sync is done then only replace in post body
                    if ($upload_data = wp_upload_dir()) {
                        // replace VC images with background-image: url( pattern
                        if (!empty($upload_data['baseurl']) && !empty($content)) {
                            $baseurl = preg_replace('/https?:\/\//', '', $upload_data['baseurl']);
                            $root_dir = trim($this->get('sm.root_dir'));
                            $root_dir = !empty($root_dir) ? $root_dir : false;


                            $content = preg_replace('/(href|src|url)(=|\()("|\')?(https?:\/\/' . str_replace('/', '\/', $baseurl) . ')\/(.+?)(\.jpg|\.png|\.gif|\.jpeg|\.doc|\.docx|\.pdf|\.ppt|\.pptx|\.zip|\.rar|\.mp3|\.mp4|\.m4a|\.mov|\.avi|\.xls|\.xlsx|\.key|\.keynote|\.psd|\.ai|\.fla|\.cdr|\.nef|\.cr2|\.arw|\.mpg|\.3gp|\.3g2|\.midi|\.mid|\.odt|\.pps|\.ppsx|\.ogg|\.wma|\.wav|\.m4v|\.webm|\.ogv|\.wmv|\.flv)/i',
                                '$1$2$3http://' . $this->get('sm.bucket') . '/' . ($root_dir ? $root_dir : '') . '$5$6', $content);
                        }
                    }
                //}

                return $content;
            }

            /**
             * Handle images on fly
             *
             * @param $file
             * @return mixed
             */
            public function handle_on_fly($file)
            {
               // echo "handle_on_fly";
                $client = ud_get_stateless_media()->get_client();
                $upload_dir = wp_upload_dir();

                $file_path = str_replace(trailingslashit($upload_dir['basedir']), '', $file);
                $file_info = @getimagesize($file);

                if ($file_info) {
                    $_metadata = array(
                        'width' => $file_info[0],
                        'height' => $file_info[1],
                        'object-id' => 'unknown', // we really don't know it
                        'source-id' => md5($file . ud_get_stateless_media()->get('sm.bucket')),
                        'file-hash' => md5($file)
                    );
                }

                $client->add_media(apply_filters('sm:item:on_fly:before_add', array_filter(array(
                    'name' => $file_path,
                    'absolutePath' => wp_normalize_path($file),
                    'cacheControl' => 'public, max-age=2592000, must-revalidate',
                    'contentDisposition' => null,
                    'metadata' => $_metadata
                ))));
                return $file;
            }

            /**
             * @param $links
             * @param $file
             * @return mixed
             */
            public function plugin_action_links($links, $file)
            {

                if ($file == plugin_basename(dirname(__DIR__) . '/wp-stateless-media.php')) {
                    $settings_link = '<a href="' . '' . '">' . __('Settings', 'ssd') . '</a>';
                    array_unshift($links, $settings_link);
                }

                if ($file == plugin_basename(dirname(__DIR__) . '/wp-stateless.php')) {
                    $settings_link = '<a href="' . '' . '">' . __('Settings', 'ssd') . '</a>';
                    array_unshift($links, $settings_link);
                }

                return $links;
            }

            /**
             * Determines if plugin is loaded via mu-plugins
             * or Network Enabled.
             *
             * @author peshkov@UD
             */
            public function is_network_detected()
            {
                /* Plugin is loaded via mu-plugins. */

                if (strpos(Utility::normalize_path($this->root_path), Utility::normalize_path(WPMU_PLUGIN_DIR)) !== false) {
                    return true;
                }

                if (is_multisite()) {
                    /* Looks through network enabled plugins to see if our one is there. */
                    foreach (wp_get_active_network_plugins() as $path) {
                        if ($this->boot_file == $path) {
                            return true;
                        }
                    }
                }
                return false;
            }

            /**
             *
             * @todo: it should not be loaded everywhere. peshkov@UD
             */
            public function admin_enqueue_scripts($hook)
            {

                wp_enqueue_style('wp-stateless', $this->path('static/styles/wp-stateless.css', 'url'), array(), self::$version);

                switch ($hook) {

                    case 'upload.php':

                        wp_enqueue_script('wp-stateless-uploads-js', $this->path('static/scripts/wp-stateless-uploads.js', 'url'), array('jquery'), self::$version);

                        break;

                    case 'post.php':

                        global $post;

                        if ($post->post_type == 'attachment') {
                            wp_enqueue_script('wp-stateless-uploads-js', $this->path('static/scripts/wp-stateless-uploads.js', 'url'), array('jquery'), self::$version);
                        }

                        break;

                    default:
                        break;
                }

            }

            /**
             * Add Attributes to media HTML
             *
             * @author potanin@UD
             * @param $attr
             * @param $attachment
             * @param $size
             * @return mixed
             */
            public function wp_get_attachment_image_attributes($attr, $attachment, $size)
            {
                //echo "wp_get_attachment_image_attributes";
                $sm_cloud = get_post_meta($attachment->ID, 'sm_cloud', true);
                if (is_array($sm_cloud) && !empty($sm_cloud['name'])) {
                    $attr['class'] = $attr['class'] . ' wp-stateless-item';
                    $attr['data-image-size'] = is_array($size) ? implode('x', $size) : $size;
                    $attr['data-stateless-media-bucket'] = isset($sm_cloud['bucket']) ? $sm_cloud['bucket'] : false;
                    $attr['data-stateless-media-name'] = $sm_cloud['name'];
                }

                return $attr;

            }

            /**
             * Adds filter link to Media Library table.
             *
             * @param $views
             * @return mixed
             */
            public function views_upload($views)
            {
                $views['stateless'] = '<a href="#">' . __('Stateless Media') . '</a>';
                return $views;
            }

            /**
             * Replace media URL
             *
             * @param bool $false
             * @param integer $id
             * @param string $size
             * @return mixed $false
             */
            public function image_downsize($false = false, $id, $size)
            {
               // echo "image_downsize";

                if (!isset($this->client) || !$this->client || is_wp_error($this->client)) {
                    return $false;
                }

                /**
                 * Check if enabled
                 */
                if ($this->get('sm.mode') !== 'cdn') {
                    return $false;
                }

                /** Start determine remote file */
                $img_url = wp_get_attachment_url($id);
                $meta = wp_get_attachment_metadata($id);
               // print_r($meta);
                $width = $height = 0;
                $is_intermediate = false;

                //** try for a new style intermediate size */
                if ($intermediate = image_get_intermediate_size($id, $size)) {
                    $img_url = !empty($intermediate['gs_link']) ? $intermediate['gs_link'] : $intermediate['url'];
                    $width = $intermediate['width'];
                    $height = $intermediate['height'];
                    $is_intermediate = true;
                }
                //die( '<pre>' . print_r( $intermediate, true ) . '</pre>' );
                if (!$width && !$height && isset($meta['width'], $meta['height'])) {

                    //** any other type: use the real image */
                    $width = $meta['width'];
                    $height = $meta['height'];
                }


                if ($img_url) {

                    //** we have the actual image size, but might need to further constrain it if content_width is narrower */
                    list($width, $height) = image_constrain_size_for_editor($width, $height, $size);

                    return array($img_url, $width, $height, $is_intermediate);
                }


                /**
                 * All other cases work as usually
                 */
                return $false;

            }

            /**
             * Extends metadata by adding GS information.
             * Note: must not be called directly. It's used only on hook
             *
             * @action wp_get_attachment_metadata
             * @param $metadata
             * @param $attachment_id
             * @return $metadata
             */
            public function wp_get_attachment_metadata($metadata, $attachment_id)
            {
              //  echo "wp_get_attachment_metadata";
                /* Determine if the media file has GS data at all. */
                $sm_cloud = get_post_meta($attachment_id, 'sm_cloud', true);
               // print_r($sm_cloud);
                if (is_array($sm_cloud) && !empty($sm_cloud['fileLink'])) {
                    $metadata['gs_link'] = $sm_cloud['fileLink'];
                    $metadata['gs_name'] = isset($sm_cloud['name']) ? $sm_cloud['name'] : false;
                    $metadata['gs_bucket'] = isset($sm_cloud['bucket']) ? $sm_cloud['bucket'] : false;
                    if (!empty($metadata['sizes']) && is_array($metadata['sizes'])) {
                        foreach ($metadata['sizes'] as $k => $v) {
                            if (!empty($sm_cloud['sizes'][$k]['name'])) {
                                $metadata['sizes'][$k]['gs_name'] = $sm_cloud['sizes'][$k]['name'];
                                $metadata['sizes'][$k]['gs_link'] = $sm_cloud['sizes'][$k]['fileLink'];
                            }
                        }
                    }
                }
                return $metadata;
            }

            //vikashedit
            /**
             * add file size in response.
             * Note: must not be called directly. It's used only on hook
             *
             * @action filter_wp_prepare_attachment_for_js
             * @param $response
             * @param $attachment
             * @param $meta
             * @return $response
             */
            public function filter_wp_prepare_attachment_for_js( $response, $attachment, $meta ) {

                  if( ! ( $response["filesizeInBytes"] &&  $response["filesizeHumanReadable"] ) ) {
                      stream_context_set_default(
                          array(
                              'http' => array(
                                  'method' => 'HEAD'
                              )
                          )
                      );
                      $header = get_headers($response['url'], 1);
                      $content_length =0;
                      if( isset($header["x-goog-stored-content-length"]) && $header["x-goog-stored-content-length"] > 0 ) {
                          $content_length = $header["x-goog-stored-content-length"];
                      } else if( isset($header["Content-Length"]) && $header["Content-Length"] > 0 ) {
                          $content_length =  $header["Content-Length"];
                      }
                      if( $content_length > 0 ) {
                          $response["filesizeInBytes"] = $content_length;
                          $response["filesizeHumanReadable"] = size_format($content_length);
                      } else {
                          $sm_cloud = get_post_meta($attachment->ID, 'sm_cloud', true);
                          $response["filesizeInBytes"] = $sm_cloud['object']['size'];
                          $response["filesizeHumanReadable"] = size_format($sm_cloud['object']['size']);
                      }

                  }

                  return $response;
            }

            /**
             * Returns client object
             * or WP_Error on failure.
             *
             * @author peshkov@UD
             * @return object $this->client. \wpCloud\StatelessMedia\GS_Client or \WP_Error
             */
            public function get_client()
            {

                if (null === $this->client) {

                    $key_json = get_site_option('sm_key_json');

                    if (empty($key_json)) {
                        $key_json = $this->get('sm.key_json');
                    }

                    $bucket = get_site_option('sm_bucket');

                    if (empty($bucket)) {
                        $bucket = $this->get('sm.bucket');
                    }
                   // print_r($bucket);
                  //  print_r($key_json);
                    /* Try to initialize GS Client */
                    $this->client = GS_Client::get_instance(array(
                        'bucket' => $bucket,
                        'key_json' => $key_json
                    ));

                }

                return $this->client;

            }

            /**
             * Determines if we can connect to Google Storage Bucket.
             *
             * @author peshkov@UD
             */
            public function is_connected_to_gs()
            {

                //$trnst = get_transient( 'sm::is_connected_to_gs' );
                
                if (empty($trnst) || false === $trnst || !isset($trnst['hash']) || $trnst['hash'] != md5(serialize($this->get('sm')))) {
                    $trnst = array(
                        'success' => 'true',
                        'error' => '',
                        'hash' => md5(serialize($this->get('sm'))),
                    );
                    $client = $this->get_client();
                    if (is_wp_error($client)) {
                        $trnst['success'] = 'false';
                        $trnst['error'] = $client->get_error_message();
                    } else {

                        if (!$client->is_connected()) {
                            $trnst['success'] = 'false';
                            $trnst['error'] = sprintf(__('Could not connect to Google Storage bucket. Please, be sure that bucket with name <b>%s</b> exists.', $this->domain), $this->get('sm.bucket'));
                        }
                    }
                    set_transient('sm::is_connected_to_gs', $trnst, 24 * HOUR_IN_SECONDS);
                }

                if (isset($trnst['success']) && $trnst['success'] == 'false') {
                    return new \WP_Error('error', (!empty($trnst['error']) ? $trnst['error'] : __('There is an Error on connection to Google Storage.', $this->domain)));
                }

                return true;
            }

            /**
             * Flush all plugin transients
             *
             */
            public function flush_transients()
            {
                delete_transient('sm::is_connected_to_gs');
            }

            /**
             * Plugin Activation
             *
             */
            public function activate()
            {
                //vikashedit
                /**
                 * fire when plugin activate and defult auto enable
                 */
                /*$opt_data = get_site_option("gcs_plugin_enabled", false, false);
                if ( is_multisite() && count($opt_data["sites"]) <= 1) {
                    $siteArr = @get_sites(array('public' => 1));
                    foreach ( $siteArr as $currentSite ) {
                        $opt_data['sites'][] = $currentSite->blog_id;
                    }
                    if( count($opt_data["sites"]) > 0)  {
                        update_site_option("gcs_plugin_enabled", $opt_data);
                    }
                }*/

            }

            /**
             * Plugin Deactivation
             *
             */
            public function deactivate()
            {
            }

            /**
             * Filter for wp_get_attachment_url();
             *
             * @param string $url
             * @param string $post_id
             * @return mixed|null|string
             */
            public function wp_get_attachment_url($url = '', $post_id = '')
            {
                //echo "wp_get_attachment_url";
                $sm_cloud = get_post_meta($post_id, 'sm_cloud', 1);
                if (is_array($sm_cloud) && !empty($sm_cloud['fileLink'])) {
                    // viksedit removedd https , as we'll addd it later
                    //return strpos($sm_cloud['fileLink'], 'https://') === false ? ('https:' . $sm_cloud['fileLink']) : $sm_cloud['fileLink'];
                    $media_scheme = get_site_option('sm_media_scheme');
                    if( "https" == $media_scheme) {
                       // $bucket_prefix = get_site_option('sm_bucket_prefix');
                       // $bucket = get_site_option('sm_bucket');
                      // $sm_cloud['fileLink'] = str_replace('http://','',$sm_cloud['fileLink']);
                        //$sm_cloud['fileLink'] = str_replace('https://','',$sm_cloud['fileLink']);
                       // if( FALSE === strpos($sm_cloud['fileLink'],$bucket)  ) {
                           //$sm_cloud['fileLink'] = str_replace('cdn1.brandwiki.info', $bucket, $sm_cloud['fileLink']);
                       // }
                       // return strpos($sm_cloud['fileLink'], 'https://') === false ? ('https://' .  $sm_cloud['fileLink']) : $sm_cloud['fileLink'];
                        return $sm_cloud['fileLink'];
                    }
                    else{
                        return $sm_cloud['fileLink'];
                    }
                }
                return $url;
            }

            /**
             * Filter for attachment_url_to_postid()
             *
             * @param int|false $post_id originally found post ID (or false if not found)
             * @param string $url the URL to find the post ID for
             * @return int|false found post ID from cloud storage URL
             */
            public function attachment_url_to_postid($post_id, $url)
            {
                global $wpdb;
               // echo "attachment_url_to_postid";
                if (!$post_id) {
                    $query = "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'sm_cloud' AND meta_value LIKE '%s'";

                    $post_id = $wpdb->get_var($wpdb->prepare($query, '%' . $url . '%'));
                }

                return $post_id;
            }

            /**
             * Change Upload BaseURL when CDN Used.
             *
             * @param $data
             * @return mixed
             */
            public function upload_dir($data)
            {
               // echo "upload_dir";
                //$data[ 'baseurl' ] = '//storage.googleapis.com/' . ( $this->get( 'sm.bucket' ) );
                $data['baseurl'] = '//' . $this->get('sm.bucket');
                $data['url'] = $data['baseurl'] . $data['subdir'];

                return $data;

            }

            /**
             * Determine if Utility class contains missed function
             * in other case, just return NULL to prevent ERRORS
             *
             * @author peshkov@UD
             * @param $name
             * @param $arguments
             * @return mixed|null
             */
            public function __call($name, $arguments)
            {
                if (is_callable(array("wpCloud\\StatelessMedia\\Utility", $name))) {
                    return call_user_func_array(array("wpCloud\\StatelessMedia\\Utility", $name), $arguments);
                } else {
                    return NULL;
                }
            }



        }

    }




}

