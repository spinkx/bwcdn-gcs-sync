<?php
/**
 * Settings management and UI
 *
 * @since 0.2.0
 */
namespace wpCloud\StatelessMedia {
use \wpCloud\StatelessMedia\Bootstrap;
  if( !class_exists( 'wpCloud\StatelessMedia\Settings' ) ) {

    final class Settings extends \UsabilityDynamics\Settings {

      /**
       * @var false|null|string
       */
      private $regenerate_ui = null;

      /**
       * Overriden construct
       */
      public function __construct() {

        add_action('admin_menu', array( $this, 'admin_menu' ));

        /* Add 'Settings' link for SM plugin on plugins page. */
        $_basename = plugin_basename( ud_get_stateless_media()->boot_file );

        add_filter( "plugin_action_links_" . $_basename, function( $links ) {
          $settings_link = '<a href="options-media.php#stateless-media">' . __( 'Settings', ud_get_stateless_media()->domain ) . '</a>';
          array_unshift($links, $settings_link);
          return $links;
        });

        parent::__construct( array(
          'store'       => 'options',
          'format'      => 'json',
          'data'        => array(
            'sm' => array(
              'mode' => get_option( 'sm_mode', 'cdn' ),
              'bucket' => get_option( 'sm_bucket' ),
              'root_dir' => get_option( 'sm_root_dir' ),
              'key_json' => get_option( 'sm_key_json' ),
              // viksedit hardcoded body rewrite url, on the fly/plugin generated urls,
              // delete from gcs when locally deleted
                'body_rewrite' => True, //get_option( 'sm_body_rewrite' ),
                'on_fly' => True, //get_option( 'sm_on_fly' ),
                'delete_remote' => True, //get_option( 'sm_delete_remote' ),
                //Vikashedit add extra options
                 'media_scheme' => get_option( 'sm_media_scheme' ), //get_option( 'sm_media_scheme' ),
                 'bucket_prefix' => get_option( 'sm_bucket_prefix' ), //get_option( 'sm_media_scheme' ),
              'domain_name' => get_option( 'sm_domain_name' ) //get_option( 'sm_media_scheme' ),
            )
          )
        ));

        /* Use constant value for mode, if set. */
        if( defined( 'WP_STATELESS_MEDIA_MODE' ) ) {
          $this->set( 'sm.mode', WP_STATELESS_MEDIA_MODE );
        }

        /* Use constant value for Bucket, if set. */
        if( defined( 'WP_STATELESS_MEDIA_BUCKET' ) ) {
          $this->set( 'sm.bucket', WP_STATELESS_MEDIA_BUCKET );
        }

        /* Use constant value for Root Dir, if set. */
        if( defined( 'WP_STATELESS_MEDIA_ROOT_DIR' ) || true ) {
          // viksedit - change root_dir on per site basis based on burrent blog id
          //$blogs = get_blog_details(get_current_blog_id());

          
          if( is_multisite() ) {
            global $wpdb;
            $blog_domain = get_site_option('sm_domain_name');
            $path = $wpdb->get_var("SELECT path FROM `$wpdb->blogs` WHERE blog_id = ". get_current_blog_id());
            $path = str_replace('/','',$path);
            if(!$path) {
              $blog_title = BLOG_ID_CURRENT_SITE;
            } else {
              $blog_title = $path;
            }
            $this->set('sm.root_dir', $blog_domain . "/" . $blog_title . "/" );
          } else {
            $blog_title = get_option('sm_domain_name');
            $this->set('sm.root_dir', $blog_title. "/" );
          }
          //$this->set( 'sm.root_dir', WP_STATELESS_MEDIA_ROOT_DIR );
        }


        if( defined( 'WP_STATELESS_JSON_KEY' ) ) {
          // viksedit - set JSON Key

          $this->set( 'sm.key_json', WP_STATELESS_JSON_KEY );

        }
        //$this->set( 'sm.media_scheme', 'https' );
        //$this->set( 'sm.bucket_prefix', 'storage.googleapis.com/' );

        /**
         * Manage specific Network Settings
         */
        if( ud_get_stateless_media()->is_network_detected() || !is_multisite() ) {

          add_filter( 'wpmu_options', array( $this, 'register_network_settings' ) );
          add_action( 'update_wpmu_options', array( $this, 'save_network_settings' ) );



        }

        /** Register options */
        add_action( 'admin_init', array( $this, 'register_settings' ) );
      }


	public function refresh() {
           $this->set('sm', array(
	          'mode' => get_option( 'sm_mode', 'cdn' ),
	          'bucket' => get_option( 'sm_bucket' ),
	          'root_dir' => get_option( 'sm_root_dir' ),
	          'key_json' => get_option( 'sm_key_json' ),
	          'body_rewrite' => true,
	          'on_fly' => true,
	          'delete_remote' => true,
              'media_scheme' => get_option( 'sm_media_scheme' ), //get_option( 'sm_media_scheme' ),
              'bucket_prefix' => get_option( 'sm_bucket_prefix' ), //get_option( 'bucket_prefix' ),
             'bucket_prefix' => get_option( 'sm_bucket_prefix' ) //get_option( 'bucket_prefix' ),
	       ));
      }
      

      /**
       * Add menu options
       */
      public function admin_menu() {
        // viksedit

        if( is_multisite() ) {
          $this->regenerate_ui = add_options_page(__('Stateless Images Synchronisation', ud_get_stateless_media()->domain), __('Stateless Sync', ud_get_stateless_media()->domain), 'manage_network', 'stateless-regenerate', array($this, 'regenerate_interface'));
         /* $this->regenerate_ui = add_options_page(__('Stateless Css Js Synchronisation', ud_get_stateless_media()->domain), __('Stateless Css Js Sync', ud_get_stateless_media()->domain), 'manage_network', 'stateless-cssjs', array($this, 'bwcdn_cs_cssjs_interface'));*/
        } else {
             add_options_page(__('Stateless Media Settings', ud_get_stateless_media()->domain), __('Stateless Media Settings', ud_get_stateless_media()->domain), 'manage_options', 'stateless-setting', array($this, 'register_single_site_settings'));
          $this->regenerate_ui = add_options_page(__('Stateless Images Synchronisation', ud_get_stateless_media()->domain), __('Stateless Sync', ud_get_stateless_media()->domain), 'manage_options', 'stateless-regenerate', array($this, 'regenerate_interface'));
        }

      }




      /**
       * Draw interface
       */
      public function regenerate_interface() {
      
        include ud_get_stateless_media()->path( '/static/views/regenerate_interface.php', 'dir' );
      }

      public function bwcdn_cs_cssjs_interface() {

        include ud_get_stateless_media()->path( '/static/views/cssjs_interface.php', 'dir' );
      }

      /**
       * Handles saving network SM data.
       *
       * @action update_wpmu_options
       * @author peshkov@UD
       */
      public function save_network_settings() {
        global $wpdb;
        $settings  = $_POST['sm'];
        if( ! isset( $settings['mode'] ) ) {
          $settings['mode'] = 'cdn';
        }
        $siteArr = array();

        foreach ( $settings as $name => $value ) {
            update_site_option('sm_' . $name, stripslashes($value));
            $this->set( 'sm.'.$name, stripslashes($value) );

        }
        if( is_multisite() ) {
          $siteArr = $wpdb->get_results('SELECT blog_id FROM `wp_blogs` WHERE public = 1', ARRAY_A);
        } else {
          $this->refresh();
        }
        $blogid = get_current_blog_id();
        if( count( $siteArr ) > 0 ) {
          $data = array();
          $options = $wpdb->get_results("SELECT option_name, option_value FROM `$wpdb->options` WHERE option_name in ('sm','sm_bucket','sm_key_json','sm_cache_control','sm_domain_name','sm_mode','sm_media_scheme','sm_bucket_prefix')");

          foreach ( $siteArr as $value ) {
            switch_to_blog( $value['blog_id'] );
              foreach ( $options as $key => $option ) {
                if( ! update_option($option->option_name, $option->option_value) ) {
                   add_option($option->option_name, $option->option_value);
                }
              }
          }
          switch_to_blog( $blogid);
        }

        
      }

      public function register_single_site_settings() {
        $this->save_network_settings();
        ?>
          <form method="post" action="options-general.php?page=stateless-setting" >
            <?php $this->register_network_settings(); ?>
            <p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes"></p>
          </form>
      <?php }
      /**
       * Registers Network Settings in case plugin is Network Enabled.
       *
       * @action wpmu_options
       * @author peshkov@UD
       */
      public function register_network_settings() {
        $sm_media_scheme = get_site_option( 'sm_media_scheme' );

        if( ! $sm_media_scheme ) {
          $sm_media_scheme = "https";
        }
        $bucket_prefix = get_site_option( 'sm_bucket_prefix', 'storage.googleapis.com' );
        $bucket = get_site_option( 'sm_bucket' );
        $sm_domain_name = get_site_option( 'sm_domain_name' );
        ?>
        <h3><?php _e( 'Stateless Media Settings', ud_get_stateless_media()->domain ); ?></h3>
        <p><?php $this->section_callback(); ?></p>
        <?php if( is_multisite() ) { ?>
          <div class="key_type"><label><b><?php _e('Domain Name', ud_get_stateless_media()->domain) ?></b></label>
            <input type="text" name="sm[domain_name]" value="<?php echo $sm_domain_name;?>" size="50"/>
          </div>
        <?php } else {
          if( ! $sm_domain_name ) {
            $url = get_site_url();
            $sm_domain_name = parse_url($url,PHP_URL_HOST);
          }

          ?>
          <input type="hidden" name="sm[domain_name]" value="<?php echo $sm_domain_name;?>" size="50"/>
        <?php } ?>
        <div class="key_type"><label><b><?php _e('Media Scheme', ud_get_stateless_media()->domain) ?></b></label>
            <select name="sm[media_scheme]" id="sm_media_scheme">
              <option value="https" <?php echo ( "https" == $sm_media_scheme )?'selected':''?> >HTTPS</option>
              <option value="http" <?php echo ( "http" == $sm_media_scheme)?'selected':''?>>HTTP</option>
            </select>
        </div>
        <div class="key_type"><label><b><?php _e('Bucket Prefix', ud_get_stateless_media()->domain) ?></b></label>
            <input type="text" name="sm[bucket_prefix]" value="<?php echo $bucket_prefix;?>" size="50"/>
        </div>
        <div class="key_type"><label for="sm_bucket"><b><?php _e( 'Bucket', ud_get_stateless_media()->domain )?></b></label><input id="sm_bucket" class="regular-text"  type="text" name="sm[bucket]" value="<?php echo esc_attr( $bucket ) ?>" /></div>
        <div class="key_type"><label><b><?php _e('Service Account JSON', ud_get_stateless_media()->domain) ?></b></label>
          <div class="_key_type _sm_key_json">
            <textarea id="sm_key_json" class="field regular-textarea sm_key_json" type="text" name="sm[key_json]"><?php echo get_site_option( 'sm_key_json' ); ?></textarea>
          </div>
        </div>
      <?php
      }

      /**
       * Adds options
       */
      public function register_settings() {

        //** Register Setting */
       // register_setting( 'media', 'sm', array( $this, 'register_network_settings' ) );

        //** Add Section */
       // add_settings_section( 'sm', __( 'Stateless Media', ud_get_stateless_media()->domain ),array( $this, 'register_network_settings' ), 'media' );

        //** Add Fields */
        //add_settings_field( 'sm.mode',  __( 'Mode', ud_get_stateless_media()->domain ),  array( $this, 'sm_fields_mode_callback' ), 'media',  'sm'  );

        //** Add Fields */
        //add_settings_field( 'sm.credentials', __( 'Credentials', ud_get_stateless_media()->domain ), array( $this, 'sm_fields_credentials_callback' ), 'media', 'sm' );
        //add_settings_field( 'sm.advanced', __( 'Advanced', ud_get_stateless_media()->domain ), array( $this, 'sm_fields_advanced_callback' ), 'media', 'sm' );

        if( defined( 'WP_DEBUG' ) && WP_DEBUG === true || WP_DEBUG === 'true' ) {
          //add_settings_field( 'sm.debug', __( 'Debug', ud_get_stateless_media()->domain ), array( $this, 'sm_fields_debug_callback' ), 'media', 'sm' );
        }

      }

      /**
       * Before save filter
       * Used to sync options with options table
       *
       * @param type $input
       * @return type
       */
      public function validate( $input ) {

        if ( !empty( $input ) && is_array( $input ) ) {

          $_has_updates = false;
          foreach( $input as $_field => $_value ) {
            if ( update_option( "sm_{$_field}", $_value ) ) {
              $_has_updates = true;
            }
          }

          if ( $_has_updates ) {
            /* Reset all plugin's transients. */
            ud_get_stateless_media()->flush_transients();
          }

        }

        return $input;
      }

      /**
       * Advanced Media Options
       *
       * @author potanin@UD
       */
      public function sm_fields_advanced_callback() {

        $inputs = array();

        $inputs[] = '<input type="hidden" name="sm[body_rewrite]" value="false" />';

        $inputs[] = '<label for="sm_body_rewrite"><input id="sm_body_rewrite" type="checkbox" name="sm[body_rewrite]" value="true" '. checked( 'true', $this->get( 'sm.body_rewrite' ), false ) .'/>'.__( 'Body content media URL rewrite.', ud_get_stateless_media()->domain ).'</label>';

        $inputs[] = '<input type="hidden" name="sm[on_fly]" value="false" />';

        $inputs[] = '<label for="sm_on_fly"><input id="sm_on_fly" type="checkbox" name="sm[on_fly]" value="true" '. checked( 'true', $this->get( 'sm.on_fly' ), false ) .'/>'.__( 'Upload on-fly generated (by third-party scripts) images to GCS.', ud_get_stateless_media()->domain ).'<small> '.__( '(This option may slow down file upload processes)', ud_get_stateless_media()->domain ).'</small></label>';

        $inputs[] = '<input type="hidden" name="sm[delete_remote]" value="false" />';

        $inputs[] = '<label for="sm_delete_remote"><input id="sm_delete_remote" type="checkbox" name="sm[delete_remote]" value="true" '. checked( 'true', $this->get( 'sm.delete_remote' ), false ) .'/>'.__( 'Delete media from GCS when media is deleted from the site.', ud_get_stateless_media()->domain ).'<small> '.__( '(This option may slow down media deletion process)', ud_get_stateless_media()->domain ).'</small></label>';

        echo '<section class="wp-stateless-media-options wp-stateless-media-advanced-options"><p>' . implode( "</p>\n<p>", (array) apply_filters( 'sm::settings::advanced', $inputs ) ) . '</p></section>';

      }

      /**
       * Debug output
       * @author potanin@UD
       */
      public function sm_fields_debug_callback() {

        echo( '<pre style="width:600px;overflow:scroll;">' . print_r( json_decode($this->get()), true ) . '</pre>' );

      }

      /**
       * Render Credential Inputs
       *
       */
      public function sm_fields_credentials_callback() {

        $inputs = array( '<section class="wp-stateless-media-options wp-stateless-credentials-options">' );

        if( !defined( 'WP_STATELESS_MEDIA_BUCKET' ) ) {
          $inputs[ ] = '<p><label for="sm_bucket">'.__( 'Bucket', ud_get_stateless_media()->domain ).'</label><div><input id="sm_bucket" class="regular-text" type="text" name="sm[bucket]" value="'. esc_attr( $this->get( 'sm.bucket' ) ) .'" /></div></p>';
        } else {
          $inputs[ ] = '<p><label for="sm_bucket">'.__( 'Bucket', ud_get_stateless_media()->domain ).'</label><div><input id="sm_bucket" class="regular-text" readonly="readonly" type="text" name="sm[bucket]" value="'. esc_attr( $this->get( 'sm.bucket' ) ) .'" /></div></p>';
        }

        if( !defined( 'WP_STATELESS_MEDIA_ROOT_DIR' ) ) {
          $inputs[ ] = '<p><label for="sm_bucket">'.__( 'Root Directory', ud_get_stateless_media()->domain ).'<small> '.__('(With trailing slash!)', ud_get_stateless_media()->domain).'</small></label><div><input id="sm_bucket" class="regular-text" type="text" name="sm[root_dir]" value="'. esc_attr( $this->get( 'sm.root_dir' ) ) .'" /></div></p>';
        } else {
          $inputs[ ] = '<p><label for="sm_bucket">'.__( 'Root Directory', ud_get_stateless_media()->domain ).'<small> '.__('(With trailing slash!)', ud_get_stateless_media()->domain).'</small></label><div><input id="sm_bucket" class="regular-text" readonly="readonly" type="text" name="sm[root_dir]" value="'. esc_attr( $this->get( 'sm.root_dir' ) ) .'" /></div></p>';
        }

        if( ud_get_stateless_media()->is_network_detected() ) {

          if( is_super_admin() ) {

            $kjsn_readonly = get_site_option( 'sm_key_json' ) || defined( 'WP_STATELESS_MEDIA_KEY_FILE_PATH' ) ? 'readonly="readonly"' : '';

            $inputs[] = '<div class="key_type"><label>'.__('Service Account JSON', ud_get_stateless_media()->domain).'</label>';
            $inputs[] = '<div class="_key_type _sm_key_json">';
            $inputs[] = '<textarea '.$kjsn_readonly.' id="sm_key_json" class="field regular-textarea sm_key_json" type="text" name="sm[key_json]" >'. esc_attr( $this->get( 'sm.key_json' ) ) .'</textarea>';
            $inputs[] = '</div>';
            $inputs[] = '</div>';

            if( $kjsn_readonly ) {
              $inputs[ ] = '<p class="description">' . sprintf( __( 'The account name can not be changed because it is set via <a href="%s">Network Settings.</a>' ), network_admin_url( 'settings.php' ) ) . '</p>';
            }

          }

        } else {

          $inputs[] = '<div class="key_type"><label>'.__('Service Account JSON', ud_get_stateless_media()->domain).'</label>';
            $inputs[] = '<div class="_key_type _sm_key_json">';
              $inputs[] = '<textarea id="sm_key_json" class="field regular-textarea sm_key_json" type="text" name="sm[key_json]" >'. esc_attr( $this->get( 'sm.key_json' ) ) .'</textarea>';
            $inputs[] = '</div>';
          $inputs[] = '</div>';
        }

        $inputs[] = '</section>';

        echo implode( "\n", (array) apply_filters( 'sm::settings::credentials', $inputs ) );

      }

      /**
       * Render inputs
       */
      public function sm_fields_mode_callback() {

        $inputs = array(
          '<p class="sm-mode"><label for="sm_mode_disabled"><input id="sm_mode_disabled" '. checked( 'disabled', $this->get( 'sm.mode' ), false ) .' type="radio" name="sm[mode]" value="disabled" />'.__( 'Disabled', ud_get_stateless_media()->domain ).''
          . '<small class="description">'.__('Disable Stateless Media.', ud_get_stateless_media()->domain).'</small></label></p>',
          '<p class="sm-mode"><label for="sm_mode_backup"><input id="sm_mode_backup" '. checked( 'backup', $this->get( 'sm.mode' ), false ) .' type="radio" name="sm[mode]" value="backup" />'.__( 'Backup', ud_get_stateless_media()->domain ).''
          . '<small class="description">'.__('Push media files to Google Storage but keep using local ones.', ud_get_stateless_media()->domain).'</small></label></p>',
          '<p class="sm-mode"><label for="sm_mode_cdn"><input id="sm_mode_cdn" '. checked( 'cdn', $this->get( 'sm.mode' ), false ) .' type="radio" name="sm[mode]" value="cdn" />'.__( 'CDN', ud_get_stateless_media()->domain ).''
          . '<small class="description">'.__('Push media files to Google Storage and use them directly from there.', ud_get_stateless_media()->domain).'</small></label></p>'
        );

        echo implode( "\n", (array)apply_filters( 'sm::settings::mode', $inputs ) );

      }

      /**
       * Description callback
       */
      public function section_callback() {
        echo '<p id="stateless-media">' . __( 'Google Storage credentials and settings.', ud_get_stateless_media()->domain ) . '</p>';

        //Imagick is installed
        if( !extension_loaded('imagick') || !class_exists("Imagick" ) )  {
          echo '<p id="stateless-media">' . __( 'Be advised, Imagick does not seem to be installed, thumbnails will not be generated not uploaded..', ud_get_stateless_media()->domain ) . '</p>';
        }

        // Check GD library.
        if ( !extension_loaded('gd') || !function_exists('gd_info') ) {
          echo '<p id="stateless-media">' . __( 'Be advised, GD does not seem to be installed, thumbnails will not be generated not uploaded..', ud_get_stateless_media()->domain ) . '</p>';
        }

      }

      /**
       * Wrapper for setting value.
       * @param string $key
       * @param bool $value
       * @param bool $bypass_validation
       * @return \UsabilityDynamics\Settings
       */
      public function set( $key = '', $value = false, $bypass_validation = false ) {

        if (  $value !== false ) {
          update_option( str_replace( '.', '_', $key ), $value );
        }

        return parent::set( $key, $value, $bypass_validation );

      }

    }


  }

}