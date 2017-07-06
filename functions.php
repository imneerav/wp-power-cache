<?php
/**
 * @package: WpRetro Power Cache
 * Author: WpRetro
 * Description: A simple page/post cache plugin
 * Plugin URI: http://wpretro.com/plugins/Power-Cache
 * Version: 0.0.1
 */
if ( ! function_exists( 'pre' ) ) {
	function pre( $p, $d = false ) {
		echo "<pre>";
		print_r( $p );
		echo "</pre>";
		if ( $d ) {
			die();
		}
	}
}

if ( ! function_exists( 'wrpc_start_loading_time' ) ) {
	function wrpc_start_loading_time() {
		$time  = microtime();
		$time  = explode( ' ', $time );
		$start = $time[1] + $time[0];
		define( 'WRPC_START_TIME', $start );
	}
}
if ( ! function_exists( 'wrpc_end_loading_time' ) ) {
	function wrpc_end_loading_time() {
		$time   = microtime();
		$time   = explode( ' ', $time );
		$finish = $time[1] + $time[0];
		$finish = round( ( $finish - WRPC_START_TIME ), 4 );
		wrpc_print_loading_time( $finish );
	}
}

if ( ! function_exists( 'wrpc_print_loading_time' ) ) {
	function wrpc_print_loading_time( $time ) {
		echo '<div style="position: fixed;top: 0px;background-color: dimgrey;color: #FFF;text-align: center;width: 100%;padding: 12px 0px;">Page executed in ' . $time . ' seconds. by WP Power Cache</div>';
	}
}

if ( ! function_exists( 'wrpc_recursive_dir_delete' ) ) {
	function wrpc_recursive_dir_delete( $path ) {
		foreach ( glob( $path . "/*" ) as $file ) {
			if ( is_dir( $file ) ) {
				wrpc_recursive_dir_delete( $file );
				@rmdir( $file );
			} else {
				@unlink( $file );
			}
		}
	}
}

if ( ! function_exists( 'wrpc_lazy_img_add_placeholders' ) ) {
	function wrpc_lazy_img_add_placeholders( $content ) {
		return WR_LazyLoad_Images::add_image_placeholders( $content );
	}
}