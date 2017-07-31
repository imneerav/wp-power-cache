<?php
/**
 * @package: WpRetro Power Cache
 * Author: WpRetro
 * Description: A simple page/post cache plugin
 * Plugin URI: http://wpretro.com/plugins/Power-Cache
 * Version: 0.0.1
 */
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$active_tab = isset( $_GET[ 'tab' ] ) ? $_GET[ 'tab' ] : 'debug_options';
?>
<div class="wrap">
	<h2><?php _e('WP Power Cache Settings', 'wp-power-cache'); ?></h2>
	<h2 class="nav-tab-wrapper">
            <a href="?page=setting-power-cache&tab=debug_options" class="nav-tab <?php echo $active_tab == 'debug_options' ? 'nav-tab-active' : ''; ?>">Debug</a>
            <!--<a href="?page=setting-power-cache&tab=other_options" class="nav-tab <?php //echo $active_tab == 'other_options' ? 'nav-tab-active' : ''; ?>">Other Options</a>-->
    </h2>
    <form action="options.php" method="POST">
        <?php settings_fields('wp-power-cache-group'); ?>
        <?php do_settings_sections('setting-power-cache'); ?>
        <input type="submit" name="save_settings" id="submit" class="button button-primary" value="Save Changes" style="float: left;margin-right: 5px;">
    </form>
	<form action="" method="POST">
		<input type="submit" value="Clear All Cache" class="button-primary" name="clear_all_cache" id="clear_all_cache">
	</form>
</div>
<script type="text/javascript">
  jQuery(document).ready(function($)
  {
    $('#clear_all_cache').click(function(e)
    {
      e.preventDefault();
      if(confirm("This action will delete all generated cache, Are you sure ?")) {
        jQuery.ajax({
          type:'POST',
          data:{action:'clear_all_cache'},
          url: '<?php echo admin_url( 'admin-ajax.php' )?>',
          success: function(response) {
            if(response.success == true) {
                $(".notice-success").show();
            }
          }
        });
      }
    });
  });
</script>