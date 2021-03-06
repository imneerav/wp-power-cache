<?php
/**
 * Plugin Name: WP Power Cache By WpRetro
 * Author: WpRetro
 * Author URI: http://wpretro.com/about
 * Description: A simple page/post cache plugin
 * Plugin URI: http://wpretro.com/plugins/Wp-Power-Cache
 * Version: 0.0.1
 * Text Domain: wp-power-cache
 */
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once 'functions.php';
require_once 'wr-power-cache-admin.php';

if ( ! class_exists( 'WR_Power_Cache' ) ) :
	class WR_Power_Cache {
		private $cacheFolder = 'wr-power-cache';
		private $isFront, $isAdmin = false;
		private $currentCacheFile = '';
		protected static $instance, $startTime, $endTime = '';
		private $cacheStatus = true, $isDev, $isDebug = true;
		private $actions = array(
			'clear_cache' => [
				'switch_theme',
				'wp_insert_comment',
				'comment_post',
				'edit_comment',
				'wp_set_comment_status',
				'permalink_structure_changed',
				'wp_trash_post',
				'publish_post',
				'edit_post',
				'delete_post',
				'pre_post_update',
				'transition_post_status',
				'trackback_post',
				'pingback_post',
				'wp_update_nav_menu',
				'edit_category_form',
				'edited_category',
				'activated_plugin',
				'deactivated_plugin',
				'delete_category',
			]
		);

		public function __construct() {

			$settings = (array) get_option( 'wp_power_cache_settings', '' );

			if ( isset( $settings["cache_status_flag"] ) && $settings["cache_status_flag"] != '') {
				$cacheStatus = esc_attr( $settings["cache_status_flag"] );
			}

			$this->cacheStatus      = ( isset( $cacheStatus ) && $cacheStatus == 1 ) ? true : false;

			if(!$this->cacheStatus) return;

			define( 'WRPC_ROOT_DIR', str_replace( '\\', '/', dirname( __FILE__ ) ) . '/' );
			$this->cacheFolder = isset( $_SERVER['SERVER_NAME'] ) ? md5( $_SERVER['SERVER_NAME'] ) : $this->cacheFolder;

			if ( $this->isFront && ! file_exists( WRPC_ROOT_DIR . $this->cacheFolder ) ) {
				wrpc_create_cache_dir();
			}
			$this->add_filters();
			$this->add_actions();

			if ( isset( $settings["developer_flag"] ) ) {
				$is_dev = esc_attr( $settings["developer_flag"] );
			}
			$debug_flag = esc_attr( $settings["debug_flag"] );
			$lazy_flag = esc_attr( $settings["lazy_loading_flag_settings"] );

			$this->isDev            = ( isset( $is_dev ) && $is_dev == 1 ) ? true : false;
			$this->isDebug          = ( isset( $debug_flag ) && $debug_flag == 1 ) ? true : false;
			$this->isLazy          = ( isset( $lazy_flag ) && $lazy_flag == 1 ) ? true : false;
			if($this->isLazy) {
				require_once 'wr-lazy-load.php';
			}
		}

		public function get_post_action_handler( $q ) {
			$this->currentCacheFile = $this->get_query_parameters( $q );
			$this->front_or_admin();

			if ( $this->isFront && $this->validate_cache_file() ) {
				if ( ! file_exists( WRPC_ROOT_DIR . $this->cacheFolder ) ) {
					mkdir( WRPC_ROOT_DIR . $this->cacheFolder, 0777, true );
				}
				ob_start( array( 'self', 'end_post_action_handler' ) );
			}
		}

		public function save_post_action_handler( $post_id, $post ) {
			$this->create_cache( $post['post_content'], $post['post_name'] );
		}

		public function load_cache_action_handler() {
			$this->front_or_admin();
			if ( $this->isFront && $this->cache_exists() ) {
				print file_get_contents( $this->cache_file_path() );
				$this->end_page_load_time();
				die();
			}
		}

		public function clear_cache_file( $post_id, $post = null, $otherObj = null ) {
			//Only post id is passed
			if ( $post_id != null && is_string( $post ) ) {
				$post                   = get_post( $post_id );
				$this->currentCacheFile = $post->post_name;
				@unlink( $this->cache_file_path() );
			} //If Comment is added / updated
			else if ( is_object( $post ) && $post instanceof WP_Comment ) {
				$post_id                = $post->comment_post_ID;
				$comment_post           = get_post( $post_id );
				$this->currentCacheFile = $comment_post->post_name;
				@unlink( $this->cache_file_path() );
			} //If Term is updated
			else if ( is_object( $otherObj ) && $otherObj instanceof WP_Term ) {
				wrpc_recursive_dir_delete( WRPC_ROOT_DIR . $this->cacheFolder );
			} //When Publish event occur
			else if ( is_object( $otherObj ) && $otherObj instanceof WP_Post ) {
				$this->currentCacheFile = $otherObj->post_name;
				if ( file_exists( $this->cache_file_path() ) ) {
					@unlink( $this->cache_file_path() );
				}
			} //If Post is added / updated
			elseif ( is_object( $post ) && $post instanceof WP_Post ) {
				$this->currentCacheFile = $post->post_name;
				@unlink( $this->cache_file_path() );
			} elseif ( is_array( $post ) && isset( $post['post_name'] ) && $post['post_name'] != "" ) {
				$this->currentCacheFile = $post['post_name'];
				if ( file_exists( $this->cache_file_path() ) ) {
					@unlink( $this->cache_file_path() );
				}
			} else {
				if($this->currentCacheFile!='') {
					@unlink( $this->cache_file_path() );
				}
				else {
					/*pre( $this->currentCacheFile );
					pre( $this->cache_file_path() );
					pre( func_get_args(), true );*/
					wrpc_recursive_dir_delete( WRPC_ROOT_DIR . $this->cacheFolder );
				}
			}
		}

		public function start_page_load_time() {
			$time            = microtime();
			$time            = explode( ' ', $time );
			self::$startTime = $time[1] + $time[0];
		}

		public function end_page_load_time() {
			$time          = microtime();
			$time          = explode( ' ', $time );
			self::$endTime = $time[1] + $time[0];
			$finish        = round( ( self::$endTime - self::$startTime ), 4 );

			if ( $this->isDev ) {
				$this->wrpc_print_loading_time( $finish );
			}
			if ( $this->isDebug ) {
				echo '<!--';
				_e( 'Your dynamic page generated in ' . $finish . ' seconds.' );
				echo '-->';
				echo '<!-- ';
				_e( 'This cached page generated by WP-Power-Cache plugin on ' );
				echo date( "d-m-Y @ H:i:s", time() ) . ' -->';
			}
		}

		public function end_post_action_handler( $buffer ) {
			$this->end_page_load_time();
			if ( is_404() ) {
				return $buffer;
			}

			return $this->create_cache( $buffer );
		}

		private function validate_cache_file() {
			$ext = pathinfo( $this->currentCacheFile, PATHINFO_EXTENSION );

			return ( in_array( $ext, $this->invalid_file_extensions() ) ) ? false : true;
		}

		private function invalid_file_extensions() {
			$invalid_ext = array(
				'image/gif'                     => 'gif',
				'image/jpeg'                    => 'jpeg',
				'image/jpg'                     => 'jpg',
				'image/png'                     => 'png',
				'application/x-shockwave-flash' => 'swf',
				'image/psd'                     => 'psd',
				'image/bmp'                     => 'bmp',
				'image/tiff'                    => 'tiff',
				'image/tiff'                    => 'tiff',
				'image/jp2'                     => 'jp2',
				'image/iff'                     => 'iff',
				'image/vnd.wap.wbmp'            => 'bmp',
				'image/xbm'                     => 'xbm',
				'image/vnd.microsoft.icon'      => 'ico'
			);

			$invalid_ext = apply_filters( 'WP_Power_Cache_Invalid_Ext', $invalid_ext );

			return $invalid_ext;
		}

		private function get_query_parameters( $q ) {
			if ( isset( $q->query_vars['page_id'] ) ) {
				return $q->query_vars['page_id'];
			} else if ( isset( $q->request ) && $q->request != '' ) {
				$request = explode( "/", $q->request );
				$file    = end( $request );
				array_pop( $request );
				$this->cacheFolder .= '/' . implode( "/", $request );

				return $file;
			} else {
				return ( $q->query_string == '' ) ? 'index' : '';
			}
		}

		private function add_filters() {

		}

		private function add_actions() {
			add_action( 'registered_taxonomy', array( $this, 'start_page_load_time' ) );
			add_action( 'parse_request', array( $this, 'get_post_action_handler' ), 10, 3 );
			add_action( 'pre_get_posts', array( $this, 'load_cache_action_handler' ), 10, 3 );
			foreach ( $this->actions['clear_cache'] as $action ) {
				add_action( $action, array( $this, 'clear_cache_file' ), 10, 3 );
			}
		}

		private function front_or_admin() {
			if ( is_admin_bar_showing() || is_user_logged_in() ) {
				$this->isAdmin = true;
			} else {
				$this->isFront = true;
			}
		}

		private function cache_exists() {
			if ( file_exists( $this->cache_file_path() ) ) {
				return $this->cache_file_path();
			} else {
				return false;
			}
		}

		private function cache_file_path() {
			return WRPC_ROOT_DIR . $this->cacheFolder . '/' . $this->currentCacheFile . '.html';
		}

		private function create_cache( $buffer, $file_name = '' ) {

			if ( $file_name != "" ) {
				$this->currentCacheFile = $file_name;
				@unlink( $this->cache_file_path() );
			}
			if ( $this->isFront && ! $this->cache_exists() ) {
				$buffer = $this->sanitize_output( $buffer );
				file_put_contents( $this->cache_file_path(), $buffer );
			}

			return $buffer;
		}

		private function sanitize_output( $buffer ) {
			$search  = array(
				'/\>[^\S ]+/s',     // strip whitespaces after tags, except space
				'/[^\S ]+\</s',     // strip whitespaces before tags, except space
				'/(\s)+/s',         // shorten multiple whitespace sequences
				'/<!--(.|\s)*?-->/' // Remove HTML comments
			);
			$replace = array(
				'>',
				'<',
				'\\1',
				''
			);
			$buffer  = preg_replace( $search, $replace, $buffer );

			return $buffer;
		}

		private function wrpc_print_loading_time( $time ) {
			echo '<div style="position: fixed;top: 0px;background-color: dimgrey;color: #FFF;text-align: center;width: 100%;padding: 12px 0px;z-index:999999999">';
			_e( 'Page executed in ' . $time . ' seconds. by WP Power Cache' );
			echo '</div>';
		}

		static public function clear_all_cache() {
			$catch_folder = isset( $_SERVER['SERVER_NAME'] ) ? md5( $_SERVER['SERVER_NAME'] ) : 'wr-power-cache';
			wrpc_recursive_dir_delete( WRPC_ROOT_DIR . $catch_folder );
		}

		static public function generate_htaccess( $folder ) {
			$content = "# BEGIN WP Power Cache
			Options -Indexes
			<IfModule mod_mime.c>
			  <FilesMatch '\.html\.gz$'>
				ForceType text/html
				FileETag None
			  </FilesMatch>
			  AddEncoding gzip .gz
			  AddType text/html .gz
			</IfModule>
			<IfModule mod_deflate.c>
			  SetEnvIfNoCase Request_URI \.gz$ no-gzip
			</IfModule>
			<IfModule mod_headers.c>
			  Header set Vary \"Accept-Encoding, Cookie\"
			  Header set Cache-Control 'max-age=3, must-revalidate'
			</IfModule>
			<IfModule mod_expires.c>
			  ExpiresActive On
			  ExpiresByType text/html A3
			</IfModule>
			Options -Indexes
			# END WP Power Cache";

			file_put_contents( WRPC_ROOT_DIR . $folder . '/.htaccess', $content );
		}

		static public function run() {
			if ( self::$instance == null ) {
				self::$instance = new self();
			}

			return self::$instance;
		}
	}

	WR_Power_Cache::run();
endif;
?>