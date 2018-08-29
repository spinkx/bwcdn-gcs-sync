<?php
/**
 * Plugin Name: BWCDN GCS Sync
 * Description: Google Cloud Storage Uploader and Syncing engine
 * Author: Vikash, Brandwiki
 * Version: 1.1
 * Text Domain: bwcdn-cs-uploader
 * Author URI: https://www.brandwiki.today
 *
 */

if( !function_exists( 'ud_get_stateless_media' ) ) {

  /**
   * Returns Stateless Media Instance
   *
   * @author Usability Dynamics, Inc.
   * @since 0.2.0
   * @param bool $key
   * @param null $default
   * @return
   */
  function ud_get_stateless_media( $key = false, $default = null ) {
    $instance = \wpCloud\StatelessMedia\Bootstrap::get_instance();
    return $key ? $instance->get( $key, $default ) : $instance;
  }

}

if( !function_exists( 'ud_check_stateless_media' ) ) {
  /**
   * Determines if plugin can be initialized.
   *
   * @author Usability Dynamics, Inc.
   * @since 0.2.0
   */
  function ud_check_stateless_media() {
    global $_ud_stateless_media_error;
    try {
      //** Be sure composer.json exists */
      $file = dirname( __FILE__ ) . '/composer.json';
      if( !file_exists( $file ) ) {
        throw new Exception( __( 'Distributive is broken. composer.json is missed. Try to remove and upload plugin again.', 'stateless-media' ) );
      }
      $data = json_decode( file_get_contents( $file ), true );
      //** Be sure PHP version is correct. */
      if( !empty( $data[ 'require' ][ 'php' ] ) ) {
        preg_match( '/^([><=]*)([0-9\.]*)$/', $data[ 'require' ][ 'php' ], $matches );
        if( !empty( $matches[1] ) && !empty( $matches[2] ) ) {
          if( !version_compare( PHP_VERSION, $matches[2], $matches[1] ) ) {
            throw new Exception( sprintf( __( 'Plugin requires PHP %s or higher. Your current PHP version is %s', 'stateless-media' ), $matches[2], PHP_VERSION ) );
          }
        }
      }
      //** Be sure vendor autoloader exists */
      if ( file_exists( dirname( __FILE__ ) . '/vendor/autoload.php' ) ) {
        require_once ( dirname( __FILE__ ) . '/vendor/autoload.php' );
      } else {
        throw new Exception( sprintf( __( 'Distributive is broken. %s file is missed. Try to remove and upload plugin again.', 'stateless-media' ), dirname( __FILE__ ) . '/vendor/autoload.php' ) );
      }
      //** Be sure our Bootstrap class exists */
      if( !class_exists( '\wpCloud\StatelessMedia\Bootstrap' ) ) {
        throw new Exception( __( 'Distributive is broken. Plugin loader is not available. Try to remove and upload plugin again.', 'stateless-media' ) );
      }
    } catch( Exception $e ) {
      $_ud_stateless_media_error = $e->getMessage();
      return false;
    }
    return true;
  }


}

if( !function_exists( 'ud_stateless_media_message' ) ) {
  /**
   * Renders admin notes in case there are errors on plugin init
   *
   * @author Usability Dynamics, Inc.
   * @since 1.0.0
   */
  function ud_stateless_media_message() {
    global $_ud_stateless_media_error;
    if( !empty( $_ud_stateless_media_error ) ) {
      $message = sprintf( __( '<p><b>%s</b> can not be initialized. %s</p>', 'stateless-media' ), 'Stateless Media', $_ud_stateless_media_error );
      echo '<div class="error fade" style="padding:11px;">' . $message . '</div>';
    }
  }
  add_action( 'admin_notices', 'ud_stateless_media_message' );
}

if( ud_check_stateless_media() ) {
  //** Initialize. */
    
    ud_get_stateless_media();
    
}

function is_buacket_connected() {
  $instance = \wpCloud\StatelessMedia\Bootstrap::get_instance();
  $is_connected = $instance->is_connected_to_gs();
  if (is_wp_error($is_connected)) {
     return false;
  }
  return true;
}


function vc_remove_wp_ver_css_js( $src ) {
    if ( strpos( $src, 'ver=' . get_bloginfo( 'version' ) ) ) {
      $src = remove_query_arg('ver', $src);
    }

    if ( strpos( $src, 'http://bwdev.local/brandwiki/test14/'  ) ) {
      // $src = str_replace( 'http://bwdev.local/brandwiki/test14/', $src );
    }



    return $src;
 }
  add_filter( 'style_loader_src', 'vc_remove_wp_ver_css_js', 9999 );
  add_filter( 'script_loader_src', 'vc_remove_wp_ver_css_js', 9999 );
