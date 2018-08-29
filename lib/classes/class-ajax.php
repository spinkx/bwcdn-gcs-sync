<?php
/**
 * AJAX Handler
 *
 * @since 1.0.0
 */
namespace wpCloud\StatelessMedia {

  if( !class_exists( 'wpCloud\StatelessMedia\Ajax' ) ) {

    final class Ajax {

      /**
       * The list of wp_ajax_{name} actions
       *
       * @var array
       */
      var $actions = array(
        'stateless_process_image',
        'get_images_media_ids',
        'get_other_media_ids',
        'stateless_process_file',
        'stateless_get_current_progresses',
        'stateless_reset_progress',
        'stateless_get_all_fails',
        'gcs_sync_status',
        'gcs_site_enable',
          'gcs_site_disable',
          'update_db_url'
      );

      /**
       * The list of wp_ajax_nopriv_{name} actions
       *
       * @var array
       */
      var $nopriv_actions = array();

      /**
       * Init AJAX actions
       *
       * @author peshkov@UD
       */
      public function __construct(){

        foreach( $this->actions as $action ) {
          add_action( 'wp_ajax_' . $action, array( $this, 'request' ) );
        }

        foreach( $this->nopriv_actions as $action ) {
          add_action( 'wp_ajax_nopriv_' . $action, array( $this, 'request' ) );
        }

      }

      /**
       * Handles AJAX request
       *
       * @author peshkov@UD
       */
      public function request() {

        $response = array(
          'message' => '',
          'html' => '',
        );

        try{

          $action = $_REQUEST[ 'action' ];
            

          /** Determine if the current class has the method to handle request */
          if( is_callable( array( $this, 'action_'. $action ) ) ) {
            $response = call_user_func_array( array( $this, 'action_' . $action ), array( $_REQUEST ) );
          }
          /** Determine if external function exists to handle request */
          elseif ( is_callable( 'action_' . $action ) ) {
            $response = call_user_func_array( $action, array( $_REQUEST ) );
          }
          elseif ( is_callable( $action ) ) {
            $response = call_user_func_array( $action, array( $_REQUEST ) );
          }
          /** Oops! */
          else {
            throw new \Exception( __( 'Incorrect Request' ) );
          }

        } catch( \Exception $e ) {
          wp_send_json_error( $e->getMessage() );
        }

        wp_send_json_success( $response );

      }

        /**
       * Update runnign sync_status
       */
	public function action_gcs_sync_status() {
	  //@error_reporting( 0 );
	  $id = (int) $_REQUEST['site_id'];
	  //wp_cache_delete( "gcs_sync_status_".$id, 'options' );
	  //wp_cache_flush();
//	  $GLOBALS['wp_object_cache']->delete( "gcs_sync_status_".$id, 'options' );
	  $opt_data = get_site_option("gcs_sync_status_".$id, false, false);
      // to check if sync is done once, for post body replace in the_content
      if( ($opt_data["total"] - $opt_data["done"]) < (.5*$opt_data["total"]) ){
          $opt_data["finished"] = true;
          update_site_option("gcs_sync_status_".$id, $opt_data);
      }
	  return "Done/Total:".$opt_data["done"]."/".$opt_data["total"]."<br /> Failed: ".$opt_data["failed"]." <br />Failed Path - ".implode("|", $opt_data["failed_path"]);
	}


        /**
         *  To  disable plugin on various sites
         */
        public function action_gcs_site_disable() {
            //@error_reporting( 0 );
          global $wpdb;
            $id = (int) $_REQUEST['site_id'];
            //wp_cache_delete( "gcs_sync_status_".$id, 'options' );
            //wp_cache_flush();
//	  $GLOBALS['wp_object_cache']->delete( "gcs_sync_status_".$id, 'options' );
          //  $opt_data = get_site_option("gcs_plugin_enabled", false, false);
            $is_sm_mode_enabled = get_blog_option($id, "sm_mode", "disabled");
            if($is_sm_mode_enabled != 'disabled') {
                // site found remove from array
               /* if(($key = array_search($id, $opt_data['sites'])) !== false) {
                    unset($opt_data['sites'][$key]);
                }*/
                // removed array element

                switch_to_blog($id);
                $prefix = $wpdb->get_blog_prefix($id);
                $smenb = $wpdb->get_var("SELECT option_value FROM `" . $prefix . "options` WHERE `option_name` LIKE 'sm' LIMIT 1");
                if ($smenb) {
                  $smenb = maybe_unserialize($smenb);
                  $smenb['mode'] = 'disabled';
                  $smenb = maybe_serialize($smenb);
                  if ($wpdb->query("UPDATE `" . $prefix . "options` SET option_value = '$smenb' WHERE `option_name` LIKE 'sm'")) {
                    if ($wpdb->query("UPDATE `" . $prefix . "options` SET option_value = 'disabled' WHERE `option_name` LIKE 'sm_mode'")) {
                      echo "updated";
                      exit;
                    }
                    echo 'sm_mode not update';
                    exit;
                  }
                  echo 'sm not updated';
                  exit;
                }
                echo 'sm not found';
                exit;
               
            }
            else{
                // already disabled nothing to do
              echo 'already disabled';
              exit;
            }
            //return "Done/Total:".$opt_data["done"]."/".$opt_data["total"]."<br /> Failed: ".$opt_data["failed"]." <br />Failed Path - ".implode("|", $opt_data["failed_path"]);
        }


        /**
       *  To enable plugin on various sites
       */
      public function action_gcs_site_enable() {
        //@error_reporting( 0 );
        global $wpdb;
        $id = (int) $_REQUEST['site_id'];
        //wp_cache_delete( "gcs_sync_status_".$id, 'options' );
        //wp_cache_flush();
//	  $GLOBALS['wp_object_cache']->delete( "gcs_sync_status_".$id, 'options' );
        //$opt_data = get_site_option("gcs_plugin_enabled", false, false);
        $is_sm_mode_enabled = get_blog_option($id, "sm_mode", "disabled");
        if( $is_sm_mode_enabled == 'cdn' ){
          // already enabled nothing to do
          return 'already enabled';
        }
        else {

          switch_to_blog($id);
          $prefix = $wpdb->get_blog_prefix($id);
          $smenb = $wpdb->get_var("SELECT option_value FROM `" . $prefix . "options` WHERE `option_name` LIKE 'sm' LIMIT 1");
          if ($smenb) {
            $smenb = maybe_unserialize($smenb);
            $smenb['mode'] = 'cdn';
            $smenb = maybe_serialize($smenb);
            if ($wpdb->query("UPDATE `" . $prefix . "options` SET option_value = '$smenb' WHERE `option_name` LIKE 'sm'")) {
              if ($wpdb->query("UPDATE `" . $prefix . "options` SET option_value = 'cdn' WHERE `option_name` LIKE 'sm_mode'")) {
                echo "updated";
                exit;
              }
              echo 'sm_mode not update';
              exit;
            }
            echo 'sm not updated';
            exit;
          }
          echo 'sm not found';
          exit;
        }

        //return "Done/Total:".$opt_data["done"]."/".$opt_data["total"]."<br /> Failed: ".$opt_data["failed"]." <br />Failed Path - ".implode("|", $opt_data["failed_path"]);
      }

      /**
       * Regenerate image sizes.
       */
      public function action_stateless_process_image() {
        @error_reporting( 0 );

        $id = (int) $_REQUEST['id'];
        $image = get_post( $id );
        $dellocal = $_REQUEST['dellocal'];
        $is_img_url_changes = $_REQUEST['is_img_url_changes'] ;
        $local_server_deletion = $_REQUEST['local_server_deletion'] ;
        $regenerate_meta = $_REQUEST['regenerate_meta'] ;


        if ( ! $image || 'attachment' != $image->post_type || 'image/' != substr( $image->post_mime_type, 0, 6 ) )
          throw new \Exception( sprintf( __( 'Failed resize: %s is an invalid image ID.', ud_get_stateless_media()->domain ), esc_html( $_REQUEST['id'] ) ) );

        if ( ! ( current_user_can( 'manage_network' ) ||  current_user_can( 'manage_options' ) ) )
          throw new \Exception( __( "Your user account doesn't have permission to resize images", ud_get_stateless_media()->domain ) );

        $fullsizepath = get_attached_file( $image->ID );
       // echo $fullsizepath.'<br/>';
        //vikashedit
        if(  $dellocal === 'yes' && ! file_exists( $fullsizepath ) && (! $regenerate_meta ) ) {
          return sprintf( __( '%1$s (ID %2$s) was not successfully upSync because local files are missing.', ud_get_stateless_media()->domain ), esc_html( get_the_title( $image->ID ) ), $image->ID);
        } else  if( $dellocal === 'no' && file_exists( $fullsizepath ) ) {
          return sprintf( __( '%1$s (ID %2$s) was not successfully DownSync because local files already exist.', ud_get_stateless_media()->domain ), esc_html( get_the_title( $image->ID ) ), $image->ID);
        }

        // If no file found
        if ( false === $fullsizepath || ! file_exists( $fullsizepath ) ) {
          $upload_dir = wp_upload_dir();
          // print_r($upload_dir);
          // Try get it and save

          $result_code = ud_get_stateless_media()->get_client()->get_media( apply_filters( 'wp_stateless_file_name', str_replace( trailingslashit( $upload_dir[ 'basedir' ] ), '', $fullsizepath )), true, $fullsizepath );
           

          if ( $result_code !== 200 ) {
            $this->store_failed_attachment( $image->ID, 'images' );
            throw new \Exception(sprintf(__('Both local and remote files are missing. Unable to resize. (%s)', ud_get_stateless_media()->domain), $image->guid));
          }
        }
        @set_time_limit( 900 );

        $metadata = wp_generate_attachment_metadata( $image->ID, $fullsizepath );
       // print_r($metadata);
        if ( is_wp_error( $metadata ) ) {
          $this->store_failed_attachment( $image->ID, 'images' );
          throw new \Exception($metadata->get_error_message());
        }
        if ( empty( $metadata ) ) {
          $this->store_failed_attachment( $image->ID, 'images' );
          throw new \Exception(__('Unknown failure reason.', ud_get_stateless_media()->domain));
        }



        // If this fails, then it just means that nothing was changed (old value == new value)
        wp_update_attachment_metadata( $image->ID, $metadata );
        //$gcs_del_local = get_blog_option(get_current_blog_id(), "gcs_plugin_delete");
        if ( $dellocal === "yes" && $local_server_deletion ) {
          $this->deleteOnUpSync($image->ID);
        } 

        $this->store_current_progress( 'images', $id );
        $this->maybe_fix_failed_attachment( 'images', $image->ID );

        return sprintf( __( '%1$s (ID %2$s) was successfully resized in %3$s seconds.', ud_get_stateless_media()->domain ), esc_html( get_the_title( $image->ID ) ), $image->ID, timer_stop() );
      }
      public function get_cdn_url() {
        $bucketLink = get_site_option( 'sm_bucket' );
        $rootdir= get_option( 'sm_root_dir' );
        $media_scheme = get_site_option('sm_media_scheme');
        $bucket_prefix = get_site_option('sm_bucket_prefix');
        if( ! strpos($bucketLink,'.') ) {
          $media_scheme = 'https';
          $bucket_prefix = 'storage.googleapis.com';
        }
        if( 'https' ===  $media_scheme ) {
          $bucket_prefix = 'storage.googleapis.com';
        }
        return $media_scheme . '://'.$bucket_prefix. '/' .$bucketLink.'/'.$rootdir  ;
      }

      public function deleteOnUpSync( $attachment_id ) {
          // viksedit deleting local files, as they are cloud uploaded if program execution flow reached till here
          // get basepath to uploads folder
          $upload_dir = wp_upload_dir();
          $base_path = $upload_dir['path'];
          $data = wp_get_attachment_metadata($attachment_id);
          //print_r($data);
          $files = array();
          // adding full size image path to $files array
          $files[] = get_attached_file($attachment_id); // $base_path."/".$data["file"];

          // viksedit path for thumbnail doesnt contain subdirectories so this cause it to not dlete various sizes as paths were
          // wrong but this subdir get dir. of main attachment and we assume other sizes are in same dir. with main attachment
          // so we append it
          $sub_dir = pathinfo($files[0])['dirname'];
          if(isset($data["sizes"])) {
            // add all thumbnail sizes to the $files array
            foreach ($data["sizes"] as $s) {
              $files[] = $sub_dir . "/" . $s["file"];
            }
            // echo "Total Attached files for attachment-id ".$attachment_id." is ".count($files);
            // delete them all
            //print_r($files);

            foreach ($files as $f) {

              if (!unlink(realpath($f))) {
              //  echo("DELETE failed - ".$f);
              }

            }
          }
      }

      public function action_update_db_url() {
        global $wpdb;

        $_REQUEST = json_decode(file_get_contents('php://input'), TRUE);
        $dellocal = $_REQUEST['dellocal'];

        $old_url = $_REQUEST['old_url']?$_REQUEST['old_url']:null;
        $new_url = $_REQUEST['new_url']?$_REQUEST['new_url']:null;

        if( $old_url && $new_url ) {
          $url = $old_url;
          $replaceurl = $new_url;
        } else if ( 'yes' === $dellocal ) {
          $upload_dir = wp_upload_dir();
          $url = $upload_dir['baseurl'] . '/';
          $replaceurl = $this->get_cdn_url();
          $replaceurl = substr($replaceurl, 0, strrpos($replaceurl,'/')).'/' ;
        } else {
          $url = $this->get_cdn_url();
          $upload_dir = wp_upload_dir();
          $replaceurl = $upload_dir['baseurl']. '/';
        }
        if( strlen( $url ) > 10 && strlen( $replaceurl ) > 10 ) {
          $blog_prefix = $wpdb->get_blog_prefix(get_current_blog_id());
          $tables_list = $wpdb->get_results("SHOW TABLES like '$blog_prefix%'");
          $db_name = $wpdb->dbname;
          $data = array();
          foreach ($tables_list as $table) {
            foreach ($table as $value) {

              $columns_list = $wpdb->get_results("SHOW columns from `$value`");
              $where = '';
              foreach ($columns_list as $column) {
                if ($wpdb->query("UPDATE `$db_name`.`$value` SET `$column->Field` = replace(`$column->Field`, '$url', '$replaceurl')")) {
                  $data[] = $wpdb->last_query;
                }
              }

            }
          }
          return $data;
        }
      }

      /**
       * @return string
       * @throws \Exception
       */
      public function action_stateless_process_file() {
        //@error_reporting( 0 );
        $id = (int) $_REQUEST['id'];
        $file = get_post( $id );
        $dellocal = $_REQUEST['dellocal'];
        $is_img_url_changes = $_REQUEST['is_img_url_changes'];
        $local_server_deletion = $_REQUEST['local_server_deletion'];

        if ( ! $file || 'attachment' != $file->post_type )
          throw new \Exception( sprintf( __( 'Attachment not found: %s is an invalid file ID.', ud_get_stateless_media()->domain ), esc_html( $id ) ) );

        if ( ! current_user_can( 'manage_network' ) )
          throw new \Exception( __( "You are not allowed to do this.", ud_get_stateless_media()->domain ) );

        $fullsizepath = get_attached_file( $file->ID );
        //vikashedit
        if( $dellocal === 'yes' && ! file_exists( $fullsizepath ) ) {
          return sprintf( __( '%1$s (ID %2$s) was not successfully upSync because local files are missing.', ud_get_stateless_media()->domain ), esc_html( get_the_title( $file->ID ) ), $file->ID);
        } else  if( $dellocal === 'no' && file_exists( $fullsizepath ) ) {
          return sprintf( __( '%1$s (ID %2$s) was not successfully DownSync because local files already exist.', ud_get_stateless_media()->domain ), esc_html( get_the_title( $file->ID ) ), $file->ID);
        }

        if ( false === $fullsizepath || ! file_exists( $fullsizepath ) ) {
          $upload_dir = wp_upload_dir();

          // Try get it and save
          $result_code = ud_get_stateless_media()->get_client()->get_media( str_replace( trailingslashit( $upload_dir[ 'basedir' ] ), '', $fullsizepath ), true, $fullsizepath );

          if ( $result_code !== 200 ) {
            $this->store_failed_attachment( $file->ID, 'other' );
            throw new \Exception(sprintf(__('File not found (%s)', ud_get_stateless_media()->domain), $file->guid));
          }
        } else {
          $upload_dir = wp_upload_dir();

          if ( !ud_get_stateless_media()->get_client()->media_exists( str_replace( trailingslashit( $upload_dir[ 'basedir' ] ), '', $fullsizepath ) ) ) {

            @set_time_limit( 900 );

            $metadata = wp_generate_attachment_metadata( $file->ID, $fullsizepath );

            if ( is_wp_error( $metadata ) ) {
              $this->store_failed_attachment( $file->ID, 'other' );
              throw new \Exception($metadata->get_error_message());
            }
            if ( empty( $metadata ) ) {
              $this->store_failed_attachment( $file->ID, 'other' );
              throw new \Exception(__('Unknown failure reason.', ud_get_stateless_media()->domain));
            }

            wp_update_attachment_metadata( $file->ID, $metadata );

          }

        }
        if ( $dellocal === "yes" && $local_server_deletion ) {
          $this->deleteOnUpSync($file->ID);
        }
        $this->store_current_progress( 'other', $id );
        $this->maybe_fix_failed_attachment( 'other', $file->ID );

        return sprintf( __( '%1$s (ID %2$s) was successfully synchronised in %3$s seconds.', ud_get_stateless_media()->domain ), esc_html( get_the_title( $file->ID ) ), $file->ID, timer_stop() );
      }

      /**
       * Returns IDs of images media objects
       */
      public function action_get_images_media_ids() {
        global $wpdb;

        if ( ! $images = $wpdb->get_results( "SELECT ID FROM $wpdb->posts WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%' ORDER BY ID DESC" ) ) {
          throw new \Exception( __('No images media objects found.', ud_get_stateless_media()->domain) );
        }

        $continue = false;
        if ( isset( $_REQUEST['continue'] ) ) {
          $continue = (bool) $_REQUEST['continue'];
        }

        return $this->get_non_processed_media_ids( 'images', $images, $continue );
      }

      /**
       * Returns IDs of images media objects
       */
      public function action_get_other_media_ids() {
        global $wpdb;

        if ( ! $files = $wpdb->get_results( "SELECT ID FROM $wpdb->posts WHERE post_type = 'attachment' AND post_mime_type NOT LIKE 'image/%' ORDER BY ID DESC" ) ) {
          throw new \Exception( __('No files found.', ud_get_stateless_media()->domain) );
        }

        $continue = false;
        if ( isset( $_REQUEST['continue'] ) ) {
          $continue = (bool) $_REQUEST['continue'];
        }

        return $this->get_non_processed_media_ids( 'other', $files, $continue );
      }

      /**
       * Returns current progress storage for all modes (to check whether there is something to continue in JS)
       */
      public function action_stateless_get_current_progresses() {
        return array(
          'images'  => $this->retrieve_current_progress( 'images' ),
          'other'   => $this->retrieve_current_progress( 'other' ),
        );
      }

      /**
       * @return array
       */
      public function action_stateless_get_all_fails() {
        return array(
          'images' => $this->get_fails( 'images' ),
          'other'  => $this->get_fails( 'other' )
        );
      }

      /**
       * @param $mode
       */
      private function get_fails( $mode ) {
        if ( $mode !== 'other' ) {
          $mode = 'images';
        }

        return get_option( 'wp_stateless_failed_' . $mode );
      }

      /**
       * Resets the current progress for a specific mode.
       */
      public function action_stateless_reset_progress() {
        $mode = 'images';
        if ( isset( $_REQUEST['mode'] ) && 'other' === $_REQUEST['mode'] ) {
          $mode = 'other';
        }

        $this->reset_current_progress( $mode );

        return true;
      }


      /**
       * @param $mode
       * @param $files
       * @param bool $continue
       * @return array
       */
      private function get_non_processed_media_ids( $mode, $files, $continue = false ) {
        if ( $continue ) {
          $progress = $this->retrieve_current_progress( $mode );
          if ( false !== $progress ) {
            $ids = array();
            foreach ( $files as $file ) {
              $id = (int) $file->ID;
              // only include IDs that have not been processed yet
              if ( $id > $progress[0] || $id < $progress[1] ) {
                $ids[] = $id;
              }
            }
            return $ids;
          }
        }

        $this->reset_current_progress( $mode );

        $ids = array();
        foreach ( $files as $file )
          $ids[] = (int)$file->ID;

        return $ids;
      }

      /**
       * @param $mode
       * @param $id
       */
      private function store_current_progress( $mode, $id ) {
        if ( $mode !== 'other' ) {
          $mode = 'images';
        }

        $first_processed = get_option( 'wp_stateless_' . $mode . '_first_processed' );
        if ( ! $first_processed ) {
          update_option( 'wp_stateless_' . $mode . '_first_processed', $id );
        }
        $last_processed = get_option( 'wp_stateless_' . $mode . '_last_processed' );
        if ( ! $last_processed || $id < (int) $last_processed ) {
          update_option( 'wp_stateless_' . $mode . '_last_processed', $id );
        }
      }

      /**
       * @param $attachment_id
       * @param $mode
       */
      private function store_failed_attachment( $attachment_id, $mode ) {
        if ( $mode !== 'other' ) {
          $mode = 'images';
        }

        $fails = get_option( 'wp_stateless_failed_' . $mode );
        if ( !empty( $fails ) && is_array( $fails ) ) {
          if ( !in_array( $attachment_id, $fails ) ) {
            $fails[] = $attachment_id;
          }
        } else {
          $fails = array( $attachment_id );
        }

        update_option( 'wp_stateless_failed_' . $mode, $fails );
      }

      /**
       * @param $mode
       * @param $attachment_id
       */
      private function maybe_fix_failed_attachment( $mode, $attachment_id ) {
        $fails = get_option( 'wp_stateless_failed_' . $mode );

        if ( !empty( $fails ) && is_array( $fails ) ) {
          if ( in_array( $attachment_id, $fails ) ) {
            foreach (array_keys($fails, $attachment_id) as $key) {
              unset($fails[$key]);
            }
          }
        }

        update_option( 'wp_stateless_failed_' . $mode, $fails );
      }

      /**
       * @param $mode
       * @return array|bool
       */
      private function retrieve_current_progress( $mode ) {
        if ( $mode !== 'other' ) {
          $mode = 'images';
        }

        $first_processed = get_option( 'wp_stateless_' . $mode . '_first_processed' );
        $last_processed = get_option( 'wp_stateless_' . $mode . '_last_processed' );

        if ( ! $first_processed || ! $last_processed ) {
          return false;
        }

        return array( (int) $first_processed, (int) $last_processed );
      }

      /**
       * @param $mode
       */
      private function reset_current_progress( $mode ) {
        if ( $mode !== 'other' ) {
          $mode = 'images';
        }

        delete_option( 'wp_stateless_' . $mode . '_first_processed' );
        delete_option( 'wp_stateless_' . $mode . '_last_processed' );
      }

    }

  }

}
