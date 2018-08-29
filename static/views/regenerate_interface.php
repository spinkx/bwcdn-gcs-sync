<?php
  /**
   * Stateless Sync Interface
   */
  global $wpdb;
  if( is_buacket_connected() ) {
  if ( wp_script_is( 'jquery-ui-widget', 'registered' ) )
    wp_enqueue_script( 'jquery-ui-progressbar', ud_get_stateless_media()->path('static/scripts/jquery-ui/jquery.ui.progressbar.min.js', 'url'), array( 'jquery-ui-core', 'jquery-ui-widget' ), '1.8.6' );
  else
    wp_enqueue_script( 'jquery-ui-progressbar', ud_get_stateless_media()->path( 'static/scripts/jquery-ui/jquery.ui.progressbar.min.1.7.2.js', 'url' ), array( 'jquery-ui-core' ), '1.7.2' );

  wp_enqueue_script( 'wp-stateless-angular', 'https://ajax.googleapis.com/ajax/libs/angularjs/1.5.0/angular.min.js', array(), '1.5.0', true );
  wp_enqueue_script( 'wp-stateless', ud_get_stateless_media()->path( 'static/scripts/wp-stateless.js', 'url'  ), array( 'jquery-ui-core' ), ud_get_stateless_media()->version, true );

  wp_enqueue_style( 'jquery-ui-regenthumbs', ud_get_stateless_media()->path( 'static/scripts/jquery-ui/redmond/jquery-ui-1.7.2.custom.css', 'url' ), array(), '1.7.2' );
?>

<div id="message" class="error fade" ng-show="error"><p>{{error}}</p></div>

<div class="wrap" ng-app="wpStatelessApp" ng-controller="wpStatelessTools" ng-init="init()">

  <h1><?php _e('Stateless Images Synchronisation', ud_get_stateless_media()->domain); ?></h1>

  <noscript><p><em><?php _e( 'You must enable Javascript in order to proceed!', ud_get_stateless_media()->domain ); ?></em></p></noscript>

  <form id="go" ng-submit="processStart($event)">

    <div>
<hr />

      <h2><?php _e( 'Action', ud_get_stateless_media()->domain ); ?></h2>
      <!-- viksedit  parallelsync link in admin  --->
      <h3>Parallel Sync to GCS</h3>
      <p>Add value for parallel threads and then press on link to open in new tab and it will continue processing images , do not close that tab as it might not be able to complete  </p>
      <?php $url = plugins_url("bwcdn-cs-uploaderloader")."/parallelsync.php?blogid=".get_current_blog_id()."&secret=bw78901603&parallel=" ?>
    
      <br />
      <a id="reflectedlink" target="_blank" href="<?php echo $url; ?>">Click to Process GCS (Fill parallel value first)</a>
      
     

      <br />
<label for="parallelrequests">No. of images to process in paralles ideal range (2-8)</label><input id="parallelrequests" value="4"/>



<input type="button" id="goparallel" value="Set Value for Parallel !" />


<script type="text/javascript">
    var originalurl = "<?php echo $url; ?>";
    var mybutton= document.getElementById('goparallel');
    var input= document.getElementById('parallelrequests');
    var link = document.getElementById('reflectedlink');
    mybutton.onclick= function() {
        
        link.href = originalurl + input.value;
        link.inerHTML = link.href;
        this.value = "Now click on link to Start !";
        setTimeout(function(){
	
	document.getElementById('goparallel').value = "Set Value for Parallel !";

	},1000);
    };
</script>
<br />

<hr/>
<h3>One by One Sync to GCS</h3>


      <div class="option">
        <label>
          <input ng-disabled="isRunning || isLoading" type="radio" name="action" value="regenerate_images" ng-model="action" />
          <?php _e( 'Regenerate all stateless images and synchronize Google Storage with local server', ud_get_stateless_media()->domain ); ?>
        </label>
      </div>

      <div class="option">
        <label>
          <input ng-disabled="isRunning || isLoading" type="radio" name="action" value="sync_non_images" ng-model="action" />
          <?php _e( 'Synchronize non-images files between Google Storage and local server', ud_get_stateless_media()->domain ); ?>
        </label>
      </div>

    </div>

    <div ng-if="action == 'regenerate_images' && progresses.images || action == 'sync_non_images' && progresses.other">

      <h2><?php _e( 'Method', ud_get_stateless_media()->domain ); ?></h2>

      <div class="option">
        <label>
          <input ng-disabled="isRunning || isLoading" type="radio" name="method" value="start" ng-model="$parent.method" />
          <?php _e( 'Start a new process', ud_get_stateless_media()->domain ); ?>
          <span class="notice notice-warning" style="margin-left:20px;">
            <?php _e( '<strong>Warning:</strong> This will make it impossible to continue the last process.', ud_get_stateless_media()->domain ); ?>
          </span>
        </label>
      </div>

      <div class="option">
        <label>
          <input ng-disabled="isRunning || isLoading" type="radio" name="method" value="continue" ng-model="$parent.method" />
          <?php _e( 'Continue the last process', ud_get_stateless_media()->domain ); ?>
        </label>
      </div>

    </div>

    <div ng-if="(action == 'regenerate_images' && fails.images) || (action == 'sync_non_images' && fails.other)">

      <h2><?php _e( 'Fix errors', ud_get_stateless_media()->domain ); ?></h2>

      <div class="option">
        <label>
          <input ng-disabled="isRunning || isLoading" type="checkbox" name="method" ng-true-value="'fix'" ng-model="$parent.method" />
          <?php _e( 'Try to fix previously failed items', ud_get_stateless_media()->domain ); ?>
          <span class="notice notice-warning" style="margin-left:20px;">
            <?php _e( '<strong>Warning:</strong> This will make it impossible to continue the last process.', ud_get_stateless_media()->domain ); ?>
          </span>
        </label>
      </div>

    </div>

    <div>

      <h2><?php _e( 'Bulk Size', ud_get_stateless_media()->domain ); ?></h2>

      <div class="option">
        <label>
          <input ng-disabled="isRunning || isLoading" type="number" name="bulk_size" ng-model="bulk_size" />
          <?php _e( 'How many items to process at once', ud_get_stateless_media()->domain ); ?>
        </label>
      </div>

    </div>

      <div>

          <h4><?php _e( 'Database Url Changes', ud_get_stateless_media()->domain ); ?></h4>

          <div class="option">
              <label>
                  <input ng-disabled="isRunning || isLoading" type="checkbox"  value="1" name="is_img_url_changes"  ng-checked="is_img_url_changes==1" ng-click="toggleSelectionIsImgUrlChanges($event)"/>
                  <?php _e( 'Image url changes in db', ud_get_stateless_media()->domain ); ?>
              </label>
          </div>

      </div>

      <div>

          <h4><?php _e( 'Local Server File Deletion', ud_get_stateless_media()->domain ); ?></h4>

          <div class="option">
              <label>
                  <input ng-disabled="isRunning || isLoading" type="checkbox"  value="1" name="local_server_deletion"  ng-checked="local_server_deletion==1" ng-click="toggleSelectionLocalServerDeleteion($event)"/>
                  <?php _e( 'Local server file deletion', ud_get_stateless_media()->domain ); ?>
              </label>
          </div>

      </div>

    <div class="status" ng-show="status"><?php _e( 'Status:', ud_get_stateless_media()->domain ); ?> {{status}}</div>

    <div ng-show="isRunning" id="regenthumbs-bar" style="position:relative;height:25px;">
      <div id="regenthumbs-bar-percent" style="position:absolute;left:50%;top:50%;width:300px;margin-left:-150px;height:25px;margin-top:-9px;font-weight:bold;text-align:center;"></div>
    </div>

    <ol ng-show="log.length" id="regenthumbs-debuglist">
      <li ng-repeat="l in log">{{l.message}}</li>
    </ol>

    <div class="buttons">
      <button ng-disabled="isRunning || isLoading" type="submit" class="button-primary"  ng-click="del_local='yes'"><?php _e( 'UpSync (may take a while)' ); ?></button>
      <button class="button-primary downsync" ng-disabled="isRunning || isLoading"  ng-click="del_local='no'"><?php _e( 'DownSync (may take a while)' ); ?></button>
      <button ng-disabled="isRunning || isLoading"  class="button-primary regenrate_meta"  ng-click="del_local='yes'; regenerate_meta=1"><?php _e( 'Regenerate Metadata (may take a while)' ); ?></button>
      <div ng-disabled="!isRunning" ng-click="processStop($event)" class="button-secondary"><?php _e( 'Stop' ); ?></div>
      <div ng-disabled="!log.length" ng-click="log=[]" class="button-secondary"><?php _e( 'Clear Log' ); ?></div>
    </div>

      <h4><?php _e( 'Replace URL', ud_get_stateless_media()->domain ); ?></h4>

      <div class="option">
          <label>
              <?php _e( 'OLD URL', ud_get_stateless_media()->domain ); ?>
              <input ng-disabled="isRunning || isLoading" type="textbox"  name="old_url" ng-model="old_url" size="50" />
          </label>
      </div>

      <div class="option">
          <label>
              <?php _e( 'NEW URL', ud_get_stateless_media()->domain ); ?>
              <input ng-disabled="isRunning || isLoading" type="textbox"  name="new_url" ng-model="new_url" size="50" />
          </label>
      </div>

  <div class="buttons">
      <button class="button-primary change_url" ng-disabled="isRunning || isLoading" type="button" ng-click="updateDBUrl()"><?php _e( 'Replace URL' ); ?></button>
  </div>

  </form>

</div>
<?php } ?>
<script>
    jQuery(document).ready(function(){
       jQuery('.ud-admin-notice').removeClass('fade');
    });
</script>