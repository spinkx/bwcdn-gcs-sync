<?php
/**
 * GS API Client
 *
 * @since 0.2.0
 * @author peshkov@UD
 */
namespace wpCloud\StatelessMedia {

  use Google_Client;
  use Google_Service_Storage;
  use WP_Error;
  use Exception;
  use Google_Service_Storage_ObjectAccessControl;
  use Google_Auth_AssertionCredentials;

  if( !class_exists( 'wpCloud\StatelessMedia\GS_Client' ) ) {

    final class GS_Client {

      /**
       * Singleton object
       *
       * @var \wpCloud\StatelessMedia\GS_Client
       */
      private static $instance;

      /**
       * Google Client manager
       *
       * @var \Google_Client $client
       */
      public $client;

      /**
       * Google Storage Service manager
       *
       * @var \Google_Service_Storage $service
       */
      public $service;

      /**
       * Google Storage Bucket
       *
       * @var
       */
      private $bucket;

      /**
       * Constructor.
       * Must not be called directly.
       *
       * @param $args
       * @author peshkov@UD
       */
      protected function __construct( $args ) {
        global $current_blog;
        //print_r($args);
        $this->bucket = $args[ 'bucket' ];
       //$this->bucket = get_site_option('sm_bucket'); //vikashedit
        $this->key_json = json_decode($args['key_json'], 1);
      //$this->key_json = json_decode(get_site_option('sm_key_json'), 1); //vikashedit
       // print_r($this->key_json);

        /* Initialize our client */
        $this->client = new Google_Client();

        $this->client->setAuthConfig($this->key_json);

        if( isset( $current_blog ) && isset( $current_blog->domain ) ) {
          $this->client->setApplicationName( $current_blog->domain );
        } else {
          $this->client->setApplicationName( urlencode( str_replace( array( 'http://', 'https://' ), '', get_bloginfo( 'url' ) ) ) );
        }

        $this->client->setScopes(['https://www.googleapis.com/auth/devstorage.full_control']);

        /* Now, Initialize our Google Storage Service */
        $this->service = new Google_Service_Storage( $this->client );
      }

        /**
         * GZIPs a file on disk (appending .gz to the name)
         *
         * From http://stackoverflow.com/questions/6073397/how-do-you-create-a-gz-file-using-php
         * Based on function by Kioob at:
         * http://www.php.net/manual/en/function.gzwrite.php#34955
         *
         * @param string $source Path to file that should be compressed
         * @param string $dest Path to destination file
         * @param integer $level GZIP compression level (default: 9)
         * @return string New filename (without .gz appended) if success, or false if operation fails
         */
        public function gzCompressFile($source, $dest, $level = 5){
            $mode = 'wb' . $level;
            $error = false;
            if ($fp_out = gzopen($dest, $mode)) {
                if ($fp_in = fopen($source,'rb')) {
                    while (!feof($fp_in))
                        gzwrite($fp_out, fread($fp_in, 1024 * 512));
                    fclose($fp_in);
                } else {
                    $error = true;
                }
                gzclose($fp_out);
            } else {
                $error = true;
            }
            if ($error)
                return false;
            else
                return $dest;
        }


        /**
       * Add/Update Media Object to Bucket
       *
       * @author peshkov@UD
       * @param array $args
       * @return bool
       */
      public function add_media( $args = array() ) {
        //viksedit
        $paths = wp_upload_dir();
        $upload_dir = $paths["basedir"];
        $dir = $upload_dir."/temp/";

        if(! file_exists($dir)){
          wp_mkdir_p($dir);
        }

        /* viksedit gzip and upload with proper encoding */
        $source = $args["absolutePath"];
        $source_filename = basename($source);


        $dest = $dir.$source_filename;

        try {

          extract( $args = wp_parse_args( $args, array(
            'name' => false,
            'absolutePath' => false,
            'mimeType' => 'image/jpeg',
            'metadata' => array(),
          ) ) );


          /* Be sure file exists. */
          if( !file_exists( $args['absolutePath'] ) ) {
            return new \WP_Error( 'sm_error', __( 'Unable to locate file on disk', ud_get_stateless_media()->domain ) );
          }



            if($this->gzCompressFile($source, $dest)){
                // file compressed succesfully
                $args["absolutePath"] = $dest;
                $args["contentEncoding"] = "gzip";
            }



          /* Set default name if parameter was not passed. */
          if( empty( $name ) ) {
            $name = basename( $args['name'] );
          }

          $name = apply_filters( 'wp_stateless_file_name', $name );

          // If media exists we just return it
          if ( $media = $this->media_exists( $name ) ) {
            return get_object_vars( $media );
          }

          $media = new \Google_Service_Storage_StorageObject();
          $media->setName($name);
          $media->setMetadata($args['metadata']);

          if (isset($args['cacheControl'])) {
            $media->setCacheControl($args['cacheControl']);
          }

          if (isset($args['contentEncoding'])) {
            $media->setContentEncoding($args['contentEncoding']);
          }

          if (isset($args['contentDisposition'])) {
            $media->getContentDisposition($args['contentDisposition']);
          }

          /* Upload Media file to Google storage */
          $media = $this->service->objects->insert($this->bucket, $media, array_filter(array(
              'data' => file_get_contents($args['absolutePath']),
              'uploadType' => 'media',
              'mimeType' => $args['mimeType'],
              'predefinedAcl' => 'bucketOwnerFullControl',
          )));

          /* Make Media Public READ for all on success */
          if (is_object($media)) {
            $acl = new Google_Service_Storage_ObjectAccessControl();
            $acl->setEntity('allUsers');
            $acl->setRole('READER');

            $this->service->objectAccessControls->insert($this->bucket, $name, $acl);


          }



        } catch( Exception $e ) {
          return new WP_Error( 'sm_error', $e->getMessage() );
        } finally{
          // remove the temp gzipped file
          @unlink(realpath($dest));
        }
        //print_r($media);
        return get_object_vars( $media );


      }

      /**
       * get or save media file
       * @param $path
       * @param bool $save
       * @param bool $save_path
       * @return bool|\Google_Service_Storage_StorageObject|int
       */
      public function get_media( $path, $save = false, $save_path = false ) {
        try {
          $media = $this->service->objects->get($this->bucket, $path);
          //print_r($media);
        } catch ( \Exception $e ) {
         // print_r($e->getMessage());
          return false;
        }

        if ( empty( $media->id ) ) return false;

        if ( $save && $save_path ) {
         
          if ( !file_exists( $_dir = dirname( $save_path ) ) ) {
            $response = wp_mkdir_p( $_dir );

          }
          return $this->client->getHttpClient()->get($media->getMediaLink(), ['save_to' => $save_path] )->getStatusCode();
        }

        return $media;
      }

      /**
       * Check if media exists
       * @param $path
       * @return bool
       */
      public function media_exists( $path ) {
        try {
          $media = $this->service->objects->get($this->bucket, $path);
        } catch ( \Exception $e ) {
          return false;
        }

        if ( empty( $media->id ) ) return false;
        return $media;
      }
      
      /**
       * Fired for every file remove action
       *
       * @author peshkov@UD
       * @param string $name
       * @return bool
       */
      public function remove_media( $name ) {
        try {
          $this->service->objects->delete( $this->bucket, $name );
        } catch( Exception $e ) {
          return new WP_Error( 'sm_error', $e->getMessage() );
        }
        return true;
      }

      /**
       * Tests connection to Google Storage
       * by trying to get passed bucket's data.
       *
       * @author peshkov@UD
       */
      public function is_connected() {

        try {
          $bucket = $this->service->buckets->get( $this->bucket );
        } catch( Exception $e ) {
          return false;
        }
        return true;
      }

      /**
       * Determine if instance already exists and Return Instance
       *
       * @param array $args
       *
       * $args
       * @param string client_id
       * @param string service_account_name
       * @param string key_file_path
       *
       * @author peshkov@UD
       * @return \wpCloud\StatelessMedia\GS_Client
       */
      public static function get_instance( $args ) {
        if( null === self::$instance ) {

          try {

            if( empty( $args[ 'bucket' ] ) ) {
              throw new Exception( __( '<b>Bucket</b> parameter must be provided.' ) );
            }

            if( empty( $args[ 'key_json' ] ) || !$json = json_decode( $args[ 'key_json' ]) || !property_exists($json, 'private_key') ){
              throw new Exception( __( '<b>Service Account JSON</b> is invalid.' ) );
            }

            self::$instance = new self( $args );
          } catch( Exception $e ) {
            return new WP_Error( 'sm_error', $e->getMessage() );
          }
        }
        return self::$instance;
      }

    }

  }

}
