<?php
/**
 * CLI sync
 *
 * @since 1.0.0
 */

/**
 * Regenerate image sizes via wp-cli
 *
 * We issue following comand
 *
 * wp --path=/Users/vivek/Sites/wpms/ --url=http://localhost/wpms --user=1 eval-file "/Users/vivek/Sites/wpms/wp-content/plugins/bwcdn-cs-uploaderloader/lib/classes/clisync.php"  23
 *
 * --url= blog root url, --user=id of user , superadmin=1, as stateless sync allows only superadmin
 * evali-file absolute path of clisync file , followed by args which get
 * transferred to $args array within code, first param will be name of action to perform,
 * followed by other inputs required by that function ie $args[1] and onwards
 *
 *
 */

namespace wpCloud\StatelessMedia {

    /*
    $ref = $_SERVER['HTTP_REFERER'];
    $refData = parse_url($ref);
    if($refData["path"] != "/parallelsync.php"){
        echo "forbidden access from elsewhere or direct ";
        die();
    }
    **/
    if( isset($_GET["secret"]) && $_GET["secret"] != "bw78901603"){
    	die("code missing");
    }


    // viksedit to load wordpress core
    $parse_uri = explode( 'wp-content', $_SERVER['SCRIPT_FILENAME'] );
    require_once( $parse_uri[0] . 'wp-load.php' );
    // viksedit added because of a fatal error of wp_generate_attachment_metadata undefined
    require_once ($parse_uri[0] .'wp-admin/includes/image.php');

    function process_image($blogid, $image_id) {
	switch_to_blog($blogid);
       // echo "process_image";
        $id = $image_id;
        $image = get_post($id);

        //var_dump($image);
	//echo "here";

        if (!$image || 'attachment' != $image->post_type || 'image/' != substr($image->post_mime_type, 0, 6))
            echo(sprintf(__('Failed resize: %s is an invalid image ID.', ud_get_stateless_media()->domain), esc_html($id)));

        /** disabled for url access
        if (!current_user_can('manage_network'))
            echo(__("Your user account doesn't have permission to resize images", ud_get_stateless_media()->domain));
       ***/
        // echo "inside func ---".$image_id;

        $fullsizepath = get_attached_file($image->ID);

      //  echo $fullsizepath;
        // If no file found on lcoal disk then skip as we assume it's on GCS
        if (false === $fullsizepath || !file_exists($fullsizepath)) {

            wp_cache_flush();
            wp_cache_delete( "gcs_sync_status_".$blogid, 'options' );
            $opt_data = get_site_option("gcs_sync_status_".$blogid, false, false);
            $opt_data["done"] = (int)$opt_data["done"] + 1;
            update_site_option("gcs_sync_status_".$blogid , $opt_data );
            print_r(get_site_option("gcs_sync_status_".$blogid, false, false));

            return "Skipped $image_id as not found on Local";
            $upload_dir = wp_upload_dir();

            // Try get it and save
            $result_code = ud_get_stateless_media()->get_client()->get_media(apply_filters('wp_stateless_file_name', str_replace(trailingslashit($upload_dir['basedir']), '', $fullsizepath)), true, $fullsizepath);

            if ($result_code !== 200) {
                //          $this->store_failed_attachment( $image->ID, 'images' );
                echo "could not save file ";

                wp_cache_flush();
                wp_cache_delete( "gcs_sync_status_".$blogid, 'options' );
                $opt_data = get_site_option("gcs_sync_status_".$blogid, false, false);
                $opt_data["failed"] = (int)$opt_data["failed"] + 1;
                $opt_data["failed_path"][] = $fullsizepath;
                update_site_option("gcs_sync_status_".$blogid , $opt_data );
                print_r(get_site_option("gcs_sync_status_".$blogid, false, false));
                
                throw new \Exception(sprintf(__('Both local and remote files are missing. Unable to resize. (%s)', ud_get_stateless_media()->domain), $image->guid));
            }
        }

        @set_time_limit(900);



        $metadata = wp_generate_attachment_metadata($image->ID, $fullsizepath);



        if (is_wp_error($metadata)) {
//        $this->store_failed_attachment( $image->ID, 'images' );
            throw new \Exception($metadata->get_error_message());
        }
        if (empty($metadata)) {
//        $this->store_failed_attachment( $image->ID, 'images' );
            throw new \Exception(__('Unknown failure reason.', ud_get_stateless_media()->domain));
        }


        // If this fails, then it just means that nothing was changed (old value == new value)
        wp_update_attachment_metadata($image->ID, $metadata);
        
        wp_cache_flush();
        wp_cache_delete( "gcs_sync_status_".$blogid, 'options' );
	$opt_data = get_site_option("gcs_sync_status_".$blogid, false, false);
        $opt_data["done"] = (int)$opt_data["done"] + 1;
        update_site_option("gcs_sync_status_".$blogid , $opt_data );
        print_r(get_site_option("gcs_sync_status_".$blogid, false, false));

//    $this->store_current_progress( 'images', $id );
//    $this->maybe_fix_failed_attachment( 'images', $image->ID );

        return sprintf(__('%1$s (ID %2$s) was successfully resized.', ud_get_stateless_media()->domain), esc_html(get_the_title($image->ID)), $image->ID);


    }

    function process_image2($blogid, $image_id) {
        switch_to_blog($blogid);
        $id = $image_id;
        $image = get_post($id);

        //var_dump($image);
        //echo "here";

        if (!$image || 'attachment' != $image->post_type || 'image/' != substr($image->post_mime_type, 0, 6))
            echo(sprintf(__('Failed resize: %s is an invalid image ID.', ud_get_stateless_media()->domain), esc_html($id)));

        /** disabled for url access
        if (!current_user_can('manage_network'))
        echo(__("Your user account doesn't have permission to resize images", ud_get_stateless_media()->domain));
         ***/
        // echo "inside func ---".$image_id;

        $fullsizepath = get_attached_file($image->ID);


        //$upload_dir = wp_upload_dir();
       // return json_encode($upload_dir);
        // If no file found on lcoal disk then skip as we assume it's on GCS
        if ( false === $fullsizepath || !file_exists( $fullsizepath ) ) {

            wp_cache_flush();
            wp_cache_delete( "gcs_sync_status_".$blogid, 'options' );
            $opt_data = get_site_option("gcs_sync_status_".$blogid, false, false);
            $opt_data["done"] = (int)$opt_data["done"] + 1;
            update_site_option("gcs_sync_status_".$blogid , $opt_data );
            //print_r(get_site_option("gcs_sync_status_".$blogid, false, false));

            //return "Skipped $image_id as not found on Local";
            //echo $fullsizepath;
            $smCloud = get_post_meta($image->ID, 'sm_cloud');
            if($smCloud && is_serialized($smCloud)) {
               $smCloud = unserialize($smCloud);
            }
            $upload_dir = wp_upload_dir();
            //return print_r( $smCloud );
            if( isset( $smCloud[0]['sizes'] ) && is_array( $smCloud[0]['sizes'] ) ) {
               foreach ( $smCloud[0]['sizes'] as $key => $size ) {
                   echo $key;
                  /*$result_code = downloadFile($size['fileLink'], $fullsizepath );
                  // $result_code = ud_get_stateless_media()->get_client()->get_media(apply_filters('wp_stateless_file_name', str_replace(trailingslashit(dirname($size['mediaLink'])), '', $size['fileLink'])), true,trailingslashit($upload_dir['basedir']));
                   if ( ! $result_code ) {
                       //          $this->store_failed_attachment( $image->ID, 'images' );
                       echo "could not save file ";

                       wp_cache_flush();
                       wp_cache_delete( "gcs_sync_status_".$blogid, 'options' );
                       $opt_data = get_site_option("gcs_sync_status_".$blogid, false, false);
                       $opt_data["failed"] = (int)$opt_data["failed"] + 1;
                       $opt_data["failed_path"][] =$size['fileLink'];
                       update_site_option("gcs_sync_status_".$blogid , $opt_data );
                       print_r(get_site_option("gcs_sync_status_".$blogid, false, false));
                       throw new \Exception(sprintf(__('Both local and remote files are missing. Unable to resize. (%s)', ud_get_stateless_media()->domain), $image->guid));
                   }*/
               }
            }

           // return $smCloud[0]['fileLink'];
            // Try get it and save



        }
        return;
        @set_time_limit(900);



        /*$metadata = wp_generate_attachment_metadata($image->ID, $fullsizepath);



        if (is_wp_error($metadata)) {
//        $this->store_failed_attachment( $image->ID, 'images' );
            throw new \Exception($metadata->get_error_message());
        }
        if (empty($metadata)) {
//        $this->store_failed_attachment( $image->ID, 'images' );
            throw new \Exception(__('Unknown failure reason.', ud_get_stateless_media()->domain));
        }


        // If this fails, then it just means that nothing was changed (old value == new value)
        wp_update_attachment_metadata($image->ID, $metadata);*/

        wp_cache_flush();
        wp_cache_delete( "gcs_sync_status_".$blogid, 'options' );
        $opt_data = get_site_option("gcs_sync_status_".$blogid, false, false);
        $opt_data["done"] = (int)$opt_data["done"] + 1;
        update_site_option("gcs_sync_status_".$blogid , $opt_data );
       // print_r(get_site_option("gcs_sync_status_".$blogid, false, false));

//    $this->store_current_progress( 'images', $id );
//    $this->maybe_fix_failed_attachment( 'images', $image->ID );

        return sprintf(__('%1$s (ID %2$s) was successfully resized.', ud_get_stateless_media()->domain), esc_html(get_the_title($image->ID)), $image->ID);


    }

    function downloadFile($url, $path)
    {
        try {
            $newfname = $path;
            $file = fopen($url, 'rb');
            if ($file) {
                $newf = fopen($newfname, 'wb');
                if ($newf) {
                    while (!feof($file)) {
                        fwrite($newf, fread($file, 1024 * 8), 1024 * 8);
                    }
                }
            }
            if ($file) {
                fclose($file);
            }
            if ($newf) {
                fclose($newf);
            }
            return true;
        }catch(\Exception $e) {
            return false;
        }
    }


    /**
     * Returns IDs of images media objects
     */
    function get_images_media_ids($blogid) {
    
        global $wpdb;
	switch_to_blog($blogid);
        if ( ! $images = $wpdb->get_results( "SELECT ID FROM $wpdb->posts WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%' ORDER BY ID DESC" ) ) {
            throw new \Exception( __('No images media objects found.', ud_get_stateless_media()->domain) );
        }

//        $continue = false;
//        if ( isset( $_REQUEST['continue'] ) ) {
//            $continue = (bool) $_REQUEST['continue'];
//        }

        return $images;
    }

    /**
     * Returns IDs of images media objects
     */
    function get_other_media_ids() {
        global $wpdb;

        if ( ! $files = $wpdb->get_results( "SELECT ID FROM $wpdb->posts WHERE post_type = 'attachment' AND post_mime_type NOT LIKE 'image/%' ORDER BY ID DESC" ) ) {
            throw new \Exception( __('No files found.', ud_get_stateless_media()->domain) );
        }


        return $files;
    }

    //echo $args[0];
    //$action = $args[0];
    $action= isset( $_GET["action"] )?$_GET["action"]:'';
    $imgid = isset( $_GET["imgid"] )?$_GET["imgid"]:''; // or $args[1]
    $blogid = isset( $_GET["blogid"] )?$_GET["blogid"]:'';
    switch($action){
        case 'process_all_images':
            $images = get_images_media_ids();
            foreach($images as $img){

            }
            break;
        case 'process_image':
            //echo "process single";
            echo process_image($blogid, $imgid)."\n\r";
            break;

        case 'all_image_ids':
            $images = get_images_media_ids($blogid);
            $out = array();
           foreach($images as $img){
                $out[] = (int)$img->ID;
           }
            echo serialize($out)."\n\r";
            break;
        case 'process_image2':
            //echo "process single";
            echo process_image2($blogid, $imgid)."\n\r";
            break;
        default:
            echo "\n\r Cannot recognize command acceptable commands are %^&#@&@(*&#"; // \n\r *process_all_images \n\r *all_media_ids \n\r";
    }



} //end namespace
