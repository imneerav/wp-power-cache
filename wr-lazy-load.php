<?php
/**
 * @package: WpRetro Power Cache
 * Author: WpRetro
 * Description: A simple page/post cache plugin
 * Plugin URI: http://wpretro.com/plugins/Power-Cache
 * Version: 0.0.1
 */

if ( ! class_exists( 'WR_LazyLoad_Images' ) ) :

	class WR_LazyLoad_Images {

		const version = '0.0.1';
		protected static $enabled = true;

		static function add_filters() {
			add_filter( 'the_content', array( __CLASS__, 'enable_img_placeholders' ), 99 );
			add_filter( 'post_thumbnail_html', array( __CLASS__, 'enable_img_placeholders' ), 11 );
			add_filter( 'get_avatar', array( __CLASS__, 'enable_img_placeholders' ), 11 );
		}

		static function enque_scripts() {
			wp_enqueue_script( 'wpcom-lazy-load-images', self::get_plugin_url( 'assets/js/lazy-load.js' ), array( 'jquery' ), self::version, true );
		}

		static function enable_img_placeholders( $content ) {
			if ( ! self::is_enabled() ) {
				return $content;
			}

			if ( is_feed() || is_preview() ) {
				return $content;
			}

			if ( false !== strpos( $content, 'data-lazy-src' ) ) {
				return $content;
			}

			$content = preg_replace_callback( '#<(img)([^>]+?)(>(.*?)</\\1>|[\/]?>)#si', array(
				__CLASS__,
				'process_image'
			), $content );

			return $content;
		}

		static function process_image( $matches ) {
			$placeholder_image = self::get_plugin_url( 'assets/img/trans.gif' );

			$old_attributes_str = $matches[2];
			$old_attributes     = wp_kses_hair( $old_attributes_str, wp_allowed_protocols() );
			$old_attributes['class']['value'] .= ' lazy ';

			if ( empty( $old_attributes['src'] ) ) {
				return $matches[0];
			}

			$image_src = $old_attributes['src']['value'];
			$new_attributes = $old_attributes;
			unset( $new_attributes['src'], $new_attributes['data-original'] );

			$new_attributes_str = self::build_attributes_string( $new_attributes );

			return sprintf( '<img src="%1$s" data-original="%2$s" %3$s><noscript>%4$s</noscript>', esc_url( $placeholder_image ), esc_url( $image_src ), $new_attributes_str, $matches[0] );
		}

		private static function build_attributes_string( $attributes ) {
			$string = array();
			foreach ( $attributes as $name => $attribute ) {
				$value = $attribute['value'];
				if ( '' === $value ) {
					$string[] = sprintf( '%s', $name );
				} else {
					$string[] = sprintf( '%s="%s"', $name, esc_attr( $value ) );
				}
			}

			return implode( ' ', $string );
		}

		static function is_enabled() {
			return self::$enabled;
		}

		static function get_plugin_url( $path = '' ) {
			return plugins_url( ltrim( $path, '/' ), __FILE__ );
		}

		static function run() {
			if ( is_admin() ) {
				return;
			}

			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enque_scripts' ) );
			add_action( 'wp_head', array( __CLASS__, 'add_filters' ), 9999 );
		}

		static function Lazy_JS_Call() {
			print '<script>jQuery(function() {jQuery("img.lazy").lazyload({threshold : 200,effect : "fadeIn"});});</script>';
		}
	}
	add_action('wp_print_footer_scripts',array( 'WR_LazyLoad_Images','Lazy_JS_Call'));
	add_action( 'init', array( 'WR_LazyLoad_Images', 'run' ) );

endif;
