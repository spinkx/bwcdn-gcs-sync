<?php
/**
 * Helper Functions List
 *
 * Can be called via Singleton. Since Singleton uses magic method __call().
 * Example:
 *
 * Add Media to GS storage:
 * ud_get_stateless_media()->add_media( false, $post_id );
 *
 * @class Utility
 */
namespace wpCloud\StatelessMedia {

  if( !class_exists( 'wpCloud\StatelessMedia\Utility' ) ) {

    class Utility {

      /**
       * wp_normalize_path was added in 3.9.0
       *
       * @param $path
       * @return mixed|string
       *
       */
      public static function normalize_path( $path ) {

        if( function_exists( 'wp_normalize_path' ) ) {
          return wp_normalize_path( $path );
        }

        $path = str_replace( '\\', '/', $path );
        $path = preg_replace( '|/+|','/', $path );
        return $path;

      }

      /**
       * Get Media Item Content Disposition
       *
       * @param null $attachment_id
       * @param array $metadata
       * @param array $data
       * @return string
       */
      public static function getContentDisposition( $attachment_id = null, $metadata = array(), $data = array() ) {
        // return 'Content-Disposition: attachment; filename=some-file.sql';

        return apply_filters( 'sm:item:contentDisposition', null, array( 'attachment_id' => $attachment_id, 'mime_type' => get_post_mime_type( $attachment_id ), 'metadata' => $metadata, 'data' => $data ) );

      }

      /**
       * @param null $attachment_id
       * @param array $metadata
       * @param array $data
       * @return string
       */
      public static function getCacheControl( $attachment_id = null, $metadata = array(), $data = array() ) {

        if( !$attachment_id ) {
          return apply_filters( 'sm:item:cacheControl', 'private, no-cache, no-store', $attachment_id, array( 'attachment_id' => null, 'mime_type' => null, 'metadata' => $metadata, 'data' => $data ) );
        }

        $_mime_type = get_post_mime_type( $attachment_id );

        // Treat images as public.
        if( strpos( $_mime_type, 'image/' ) !== false ) {
          return apply_filters( 'sm:item:cacheControl', 'public, max-age=2592000, must-revalidate', array( 'attachment_id' => $attachment_id, 'mime_type' => null, 'metadata' => $metadata, 'data' => $data ) );
        }

        // Treat images as public.
        if( strpos( $_mime_type, 'sql' ) !== false ) {
          return apply_filters( 'sm:item:cacheControl', 'private, no-cache, no-store', array( 'attachment_id' => $attachment_id, 'mime_type' => null, 'metadata' => $metadata, 'data' => $data ) );
        }

        return apply_filters( 'sm:item:cacheControl', 'public, max-age=30, no-store, must-revalidate', array( 'attachment_id' => $attachment_id, 'mime_type' => null, 'metadata' => $metadata, 'data' => $data ) );

      }


      /**
       * Add/Update Media to Bucket
       * Fired for every action with image add or update
       *
       * @action wp_generate_attachment_metadata
       * @author peshkov@UD
       * @param $metadata
       * @param $attachment_id
       * @return bool|string
       */
      public static function add_media( $metadata, $attachment_id ) {

        /* Get metadata in case if method is called directly. */
        if( current_filter() !== 'wp_generate_attachment_metadata' ) {
          $metadata = wp_get_attachment_metadata( $attachment_id );
        }

        $client = ud_get_stateless_media()->get_client();
       // error_log(print_r($client, true));
        if( !is_wp_error( $client ) ) {

          // Make non-images uploadable.
          if( empty( $metadata['file'] ) && $attachment_id ) {
            $upload_dir = wp_upload_dir();
            $metadata = array( "file" => str_replace( trailingslashit( $upload_dir[ 'basedir' ] ), '', get_attached_file( $attachment_id ) ) );
          }

          $file = wp_normalize_path( $metadata[ 'file' ] );

          //$bucketLink = 'https://storage.googleapis.com/' . ud_get_stateless_media()->get( 'sm.bucket' );
          $bucketLink = 'http://' . ud_get_stateless_media()->get( 'sm.bucket' );
          //vikashedit
          $bucketLink = get_site_option( 'sm_bucket' );

          $media_scheme = get_site_option('sm_media_scheme');
          $bucket_prefix = get_site_option('sm_bucket_prefix');
          if( ! strpos($bucketLink, '.' ) ) {
            $media_scheme = 'https';
            $bucket_prefix = 'storage.googleapis.com';
          }
          if( 'https' ===  $media_scheme ) {
            $bucket_prefix = 'storage.googleapis.com';
          }
          $bucketLink = $media_scheme . '://' . $bucket_prefix . '/' . $bucketLink;
          $fileLink = $bucketLink . '/' . ( !empty($media['name']) ? $media['name'] : $file );
          $_metadata = array(
            "width" => isset( $metadata[ 'width' ] ) ? $metadata[ 'width' ] : null,
            "height" => isset( $metadata[ 'height' ] )  ? $metadata[ 'height' ] : null,
            'object-id' => $attachment_id,
            'source-id' => md5( $attachment_id.ud_get_stateless_media()->get( 'sm.bucket' ) ),
            'file-hash' => md5( $metadata[ 'file' ] )
          );

          /* Add default image */

          $media = $client->add_media( $_mediaOptions = array_filter( array(
            'name' => $file,
            'absolutePath' => wp_normalize_path( get_attached_file( $attachment_id ) ),
            'cacheControl' => $_cacheControl = self::getCacheControl( $attachment_id, $metadata, null ),
            'contentDisposition' => $_contentDisposition = self::getContentDisposition( $attachment_id, $metadata, null ),
            'mimeType' => get_post_mime_type( $attachment_id ),
            'metadata' => $_metadata
          ) ));
          //error_log(print_r($media, true));
          // Break if we have errors.
          // @note Errors could be due to key being invalid or now having sufficient permissions in which case should notify user.
          if( is_wp_error( $media ) ) {            
            return $metadata;
          }

          /* Add Google Storage metadata to our attachment */
          $fileLink = $bucketLink . '/' . ( !empty($media['name']) ? $media['name'] : $file );

          $cloud_meta = array(
            'id' => $media[ 'id' ],
            'name' => !empty($media['name']) ? $media['name'] : $file,
            'fileLink' => $fileLink,
            'storageClass' => $media[ 'storageClass' ],
            'mediaLink' => $media[ 'mediaLink' ],
            'selfLink' => $media[ 'selfLink' ],
            /*'bucket' => ud_get_stateless_media()->get( 'sm.bucket' ),*/ //vikashedit
            'bucket' => $bucketLink,
            'object' => $media,
            'sizes' => array(),
          );


          if( isset( $_cacheControl ) && $_cacheControl ) {
            //update_post_meta( $attachment_id, 'sm_cloud:cacheControl', $_cacheControl );
            $cloud_meta[ 'cacheControl' ] = $_cacheControl;
          }

          if( isset( $_contentDisposition ) && $_contentDisposition ) {
            //update_post_meta( $attachment_id, 'sm_cloud:contentDisposition', $_contentDisposition );
            $cloud_meta[ 'contentDisposition' ] = $_contentDisposition;
          }

          if( empty( $metadata[ 'sizes' ] ) ) {
            // @note This could happen if WordPress does not have any wp_get_image_editor(), e.g. Imagemagic not installed.
          }

          /* Now we go through all available image sizes and upload them to Google Storage */
          if( !empty( $metadata[ 'sizes' ] ) && is_array( $metadata[ 'sizes' ] ) ) {

            $path = wp_normalize_path( dirname( get_attached_file( $attachment_id ) ) );
            $mediaPath = wp_normalize_path( trim( str_replace( basename( $metadata[ 'file' ] ), '', $metadata[ 'file' ] ), '\/\\' ) );

            foreach( (array) $metadata[ 'sizes' ] as $image_size => $data ) {

              $absolutePath = wp_normalize_path( $path . '/' . $data[ 'file' ] );

              /* Add 'image size' image */
              $media = $client->add_media( array(
                'name' => $file_path = $mediaPath . '/' . $data[ 'file' ],
                'absolutePath' => $absolutePath,
                'cacheControl' => $_cacheControl,
                'contentDisposition' => $_contentDisposition,
                'mimeType' => $data[ 'mime-type' ],
                'metadata' => array_merge( $_metadata, array(
                  'width' => $data['width'],
                  'height' => $data['height'],
                  'child-of' => $attachment_id,
                  'file-hash' => md5( $data[ 'file' ] )
                ))
              ));

              /* Break if we have errors. */
              if( !is_wp_error( $media ) ) {

                $fileLink = $bucketLink . '/' . (!empty($media['name']) ? $media['name'] : $file_path);

                // @note We don't add storageClass because it's same as parent...
                $cloud_meta[ 'sizes' ][ $image_size ] = array(
                  'id' => $mediaPath . '/' . $media[ 'id' ],
                  'name' => !empty($media['name']) ? $media['name'] : $file_path,
                  'fileLink' => $fileLink,
                  'mediaLink' => $media[ 'mediaLink' ],
                  'selfLink' => $media[ 'selfLink' ]
                );

              }

            }

          }

         //$cloud_meta['fileLink'] =  'https://storage.googleapis.com/cdn1.brandwiki.info/brandwiki.info/brandwikicitrus/';

        update_post_meta( $attachment_id, 'sm_cloud', $cloud_meta );

          // viksedit - to add sizes to _wp_attachment_metadata when uploading
          // as add_media forgets to update 'sizes' in metadata and so thumbnails were not
          // getting deleted
         wp_update_attachment_metadata($attachment_id, $metadata);

          // viksedit - code to delete various sizes after succesful sync
          //$attachment_id = $image->ID;
          //print_r(wp_get_attachment_image_src( $attachment_id ));

          //error_log(WP_STATELESS_MEDIA_DELETE_LOCAL_ON_UPLOAD."---".$attachment_id."--");
          // viksedit check if delete from local is allowed , else skip delete code
          //Code move by Vikash
          $dellocal = isset( $_REQUEST['dellocal'] )?$_REQUEST['dellocal']:"yes";
          $local_server_deletion = isset( $_REQUEST['local_server_deletion'] )?$_REQUEST['local_server_deletion'] : 1 ;
          if ( $dellocal === "yes" && isset( $local_server_deletion ) &&  $local_server_deletion ) {
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
            if (isset($data["sizes"])) {
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

        }

        return $metadata;
      }

      /**
       * Remove Media from Bucket by post ID
       * Fired on calling function wp_delete_attachment()
       *
       * @todo: add error logging. peshkov@UD
       * @see wp_delete_attachment()
       * @action delete_attachment
       * @author peshkov@UD
       * @param $post_id
       */
      public static function remove_media( $post_id ) {
        /* Get attahcment's metadata */
        $metadata = wp_get_attachment_metadata( $post_id );

        /* Be sure we have the same bucket in settings and have GS object's name before proceed. */
        $bucket_name = 'https://storage.googleapis.com/'.ud_get_stateless_media()->get( 'sm.bucket' );
        if(
          isset( $metadata[ 'gs_name' ] ) &&
          isset( $metadata[ 'gs_bucket' ] ) &&
          $metadata[ 'gs_bucket' ] ==  $bucket_name
        ) {

          $client = ud_get_stateless_media()->get_client();
          if( !is_wp_error( $client ) ) {

            /* Remove default image */
            $client->remove_media( $metadata[ 'gs_name' ] );

            /* Now, go through all sizes and remove 'image sizes' images from Bucket too. */
            if( !empty( $metadata[ 'sizes' ] ) && is_array( $metadata[ 'sizes' ] ) ) {
              foreach( $metadata[ 'sizes' ] as $k => $v ) {
                if( !empty( $v[ 'gs_name' ] ) ) {
                  $client->remove_media( $v[ 'gs_name' ] );
                }
              }
            }

          }

        }

      }

    }

  }

}
