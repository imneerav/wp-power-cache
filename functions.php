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

if ( ! function_exists( 'wrpc_create_cache_dir' ) ) {
	function wrpc_create_cache_dir() {
		$cacheFolder = isset( $_SERVER['SERVER_NAME'] ) ? md5( $_SERVER['SERVER_NAME'] ) : 'wr-power-cache';
		if ( ! file_exists( WRPC_ROOT_DIR . $cacheFolder ) ) {
			mkdir( WRPC_ROOT_DIR . $cacheFolder );
			WR_Power_Cache::generate_htaccess($cacheFolder);
		}
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

add_action('activate_plugin','wrpc_create_cache_dir');