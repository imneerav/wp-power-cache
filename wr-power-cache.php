<?php
/**
 * Plugin Name: WP Power Cache By WpRetro
 * Author: WpRetro
 * Description: A simple page/post cache plugin
 * Plugin URI: http://wpretro.com/plugins/Power-Cache
 * Version: 0.0.1
 * Text Domain: wp-power-cache
 */
// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

require_once 'functions.php';
require_once 'wr-lazy-load.php';

if ( ! class_exists( 'WR_Power_Cache' ) ) :
	class WR_Power_Cache {
		private $cacheFolder = 'wr-power-cache';
		private $isFront, $isAdmin = false;
		private $currentCacheFile = '';
		protected static $instance, $startTime, $endTime = '';
		private $isDev = true;
		private $actions = array(
			'clear_cache' => [
				'switch_theme',
				'comment_post',
				'permalink_structure_changed',
				'save_post', /* Test all below */
				'edit_category_form',
				'edited_category',
				'wp_insert_comment',
				'activated_plugin',
				'deactivated_plugin',
			]
		);

		public function __construct() {
			define( 'WRPC_ROOT_DIR', str_replace( '\\', '/', dirname( __FILE__ ) ) . '/' );
			$this->cacheFolder = isset( $_SERVER['SERVER_NAME'] ) ? md5( $_SERVER['SERVER_NAME'] ) : $this->cacheFolder;
			if ( ! file_exists( WRPC_ROOT_DIR . $this->cacheFolder ) ) {
				mkdir( WRPC_ROOT_DIR . $this->cacheFolder );
				$this->generate_htaccess();
			}
			$this->add_filters();
			$this->add_actions();
		}
		
		public function generate_htaccess() {
			$content = "
			# BEGIN WP Power Cache
			Options -Indexes
			<IfModule mod_mime.c>
			  <FilesMatch "\.html\.gz$">
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
			
			file_put_contents( WRPC_ROOT_DIR . $this->cacheFolder . '/.htaccess', $content );
		}

		public function get_post_action_handler( $q ) {
			$this->currentCacheFile = $this->get_query_parameters( $q );
			if ( $this->validCatchFile() ) {
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
			$this->FrontOrAdmin();
			if ( $this->isFront && $this->cache_exists() ) {
				print file_get_contents( $this->cache_file_path() );
				$this->end_page_load_time();
				die();
			}
		}

		public function clear_cache_file( $post ) {
			if ( isset( $post ) && isset( $post['post_name'] ) && $post['post_name'] != "" ) {
				$this->currentCacheFile = $post['post_name'];
				@unlink( $this->cache_file_path() );
			} else {
				wrpc_recursive_dir_delete( WRPC_ROOT_DIR . $this->cacheFolder );
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
				wrpc_print_loading_time( $finish );
			} else {
				echo '<!-- Page executed in ' . $finish . ' seconds. by WP Power Cache -->';
			}

		}

		public function end_post_action_handler( $buffer ) {
			$this->end_page_load_time();
			if ( is_404() ) {
				return $buffer;
			}

			return $this->create_cache( $buffer );
		}

		private function validCatchFile() {
			$ext = pathinfo( $this->currentCacheFile, PATHINFO_EXTENSION );

			return ( in_array( $ext, $this->invalid_file_extensions() ) ) ? false : true;
		}

		private function invalid_file_extensions() {
			return array(
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
			add_action( 'pre_post_update', array( $this, 'save_post_action_handler' ), 10, 3 );
			add_action( 'parse_request', array( $this, 'get_post_action_handler' ), 10, 3 );
			add_action( 'pre_get_posts', array( $this, 'load_cache_action_handler' ), 10, 3 );
			add_action( 'admin_menu', array( $this, 'power_cache_menu' ), 10, 3);
			add_action( 'admin_init', array( $this, 'power_cache_init' ), 10, 3);
			add_action( 'admin_notices', array($this,'wp_power_cache_admin_notice'));
			add_filter( 'plugin_action_links_'.plugin_basename(__FILE__), array($this,'add_wp_power_cache_settings_link'));

			foreach ( $this->actions['clear_cache'] as $action ) {
				add_action( $action, array( $this, 'clear_cache_file' ), 10, 3 );
			}
		}

		private function FrontOrAdmin() {
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
		
		/* Add Menu for WP Power Cache page */
		public function power_cache_menu() {
			add_menu_page('WP Power Cache', 'WP Power Cache', 'manage_options', 'setting-power-cache', array(&$this, 'power_cache_setting_page'),'dashicons-controls-skipforward');
		}
		
		/* Add Settings */
		public function power_cache_init() {
			register_setting( 'wp-power-cache-group', 'wp_power_cache_settings' );
			
			$active_tab = isset( $_GET[ 'tab' ] ) ? $_GET[ 'tab' ] : 'debug_options';
			
			if( $active_tab == 'debug_options' ) {
				
				add_settings_section( 'developer_flag', __( 'Developer Mode', 'wp-power-cache' ), array(&$this,'developer_flag_callback'), 'setting-power-cache' );
				add_settings_field( 'developer_flag_settings', __( 'Enable Developer Mode', 'wp-power-cache' ), array(&$this,'developer_flag_settings_callback'), 'setting-power-cache', 'developer_flag' );
			
				add_settings_section( 'debug_flag', __( 'Debug', 'wp-power-cache' ), array(&$this,'debug_callback'), 'setting-power-cache' );
				add_settings_field( 'debug_settings', __( 'Debugging', 'wp-power-cache' ), array(&$this,'debug_field_callback'), 'setting-power-cache', 'debug_flag' );
			
			}
			if( $active_tab == 'other_options' ) {
				add_settings_section( 'others', __( 'Coming Soon.', 'wp-power-cache' ), array(&$this,'others'), 'setting-power-cache' );
			}
		}
		
		public function developer_flag_callback() {}
		
		public function developer_flag_settings_callback() {
			$settings 	= 	(array) get_option( 'wp_power_cache_settings' );
			$field 		= 	"developer_flag";
			$is_dev 	= 	esc_attr( $settings[$field] );
			$checked 	= 	'';
			if(isset($is_dev) && $is_dev == 1) {
				$checked = 'checked';
			}
			echo '<input type="checkbox" name="wp_power_cache_settings[developer_flag]" value="1"' . $checked . '/>';
			_e( '&nbsp;&nbsp;Check it to show Page Loading Time in header area after caching.', 'wp-power-cache' );
		}
		
		public function debug_callback() {}
		
		public function debug_field_callback() {
	
			$settings 	= 	(array) get_option( 'wp_power_cache_settings' );
			$field 		= 	"debug_flag";
			$is_debug 	= 	esc_attr( $settings[$field] );
			$checked 	= 	'';
			if(isset($is_debug) && $is_debug == 1) {
				$checked = 'checked';
			}
			echo '<input type="checkbox" name="wp_power_cache_settings[debug_flag]" value="1"' . $checked . '/>';
			_e( '&nbsp;&nbsp;Display comments at the end of every page like below :', 'wp-power-cache' );
			_e( '<pre>&lt;!-- Your dynamic page generated in 0.0128 seconds. -->
&lt;!-- This cached page generated by WP-Power-Cache plugin on ' . date( "d-m-Y H:i:s", time() ) . ' --></pre>', 'wp-power-cache' );
		}
		
		public function others() {
			
		}
		
		public function power_cache_setting_page() {
			require_once 'power-cache-setting-page.php';
		}
		
		public function wp_power_cache_admin_notice() {
			if (isset($_GET['settings-updated'])) :
				settings_errors();
			endif;
		}
		
		public function add_wp_power_cache_settings_link( $links ) {
			$settings_link = '<a href="admin.php?page=setting-power-cache">' . __( 'Settings' ) . '</a>';
			array_push( $links, $settings_link );
			return $links;
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