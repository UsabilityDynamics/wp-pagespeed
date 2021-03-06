<?php
/**
 * UsabilityDynamics\PageSpeed Bootstrap
 *
 * @verison 0.1.0
 * @author potanin@UD
 * @namespace UsabilityDynamics\PageSpeed
 */
namespace UsabilityDynamics\PageSpeed {

	use UsabilityDynamics\Settings;
	use zz\Html\HTMLMinify;

	if( !class_exists( 'UsabilityDynamics\PageSpeed\Bootstrap' ) ) {

		/**
		 * Bootstrap PageSpeed
		 *
		 * @class Bootstrap
		 * @author potanin@UD
		 * @version 0.0.1
		 */
		class Bootstrap {

			/**
			 * PageSpeed core version.
			 *
			 * @static
			 * @property $version
			 * @type {Object}
			 */
			public static $version = '0.2.0';

			/**
			 * Textdomain String
			 *
			 * @public
			 * @property text_domain
			 * @var string
			 */
			public static $text_domain = 'wp-pagespeed';

			/**
			 * Settings Instance.
			 *
			 * @property $_settings
			 * @type {Object}
			 */
			private $_settings;

			/**
			 * Singleton Instance Reference.
			 *
			 * @public
			 * @static
			 * @property $instance
			 * @type {Object}
			 */
			public static $instance = false;

			/**
			 * Constructor.
			 *
			 * UsabilityDynamics components should be avialable.
			 * - class_exists( '\UsabilityDynamics\API' );
			 * - class_exists( '\UsabilityDynamics\Utility' );
			 *
			 * @for Loader
			 * @method __construct
			 */
			public function __construct() {
				global $wp_pagespeed;

				// Return Singleton Instance
				if( self::$instance ) {
					return self::$instance;
				}

				/** If we currently have a wp_veener object, we should copy it */
				if( isset( $wp_pagespeed ) && is_object( $wp_pagespeed ) && count( get_object_vars( $wp_pagespeed ) ) ) {
					foreach( array_keys( get_object_vars( $wp_pagespeed ) ) as $key ) {
						$this->{$key} = $wp_pagespeed->{$key};
					}
				}

				// Set the singleton instance
				if( !isset( $wp_pagespeed ) ) {
					$wp_pagespeed = self::$instance = & $this;
				}

				// Check if being called too early, such as during Unit Testing.
				if( !function_exists( 'did_action' ) ) {
					return $this;
				}
				
				// Make sure we're not too late to init this
				if( did_action( 'init' ) ) {
					_doing_it_wrong( 'UsabilityDynamics\PageSpeed\Bootstrap::__construct', 'PageSpeed should not be initialized before "init" filter.', '0.6.1' );
				}

				// Initialize Settings.
				if( class_exists( '\UsabilityDynamics\Settings' ) ) {
					$this->_settings = new Settings();
				}

				// Load options.
				$this->set(array(
					"core" => array(
						"enabled" => get_option( "wp-pagespeed:core.enabled" )
					),
					"minify" => array(
						"enabled" => get_option( "wp-pagespeed:minify.enabled" ),
						"level" => get_option( "wp-pagespeed:minify.level" )
					)
				));

				// Initialize plugin.
				add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ), 20 );

			}

			/**
			 *
			 */
			public function plugins_loaded() {

				add_action( 'template_redirect', array( $this, 'template_redirect' ) );
				add_action( 'admin_init', array( $this, 'admin_init' ) );

			}

			public function setting_callback_function() {
				echo '<p>Intro text for our settings section</p>';

			}
			
			public function setting_section_callback_function() {
				echo '<label><input name="core.enabled" type="checkbox" value="1" class="code" ' . checked( 1, get_option( 'core.enabled' ), false ) . ' /> Explanation text</label>';
			}

			/**
			 *
			 */
			public function admin_init() {

				register_setting( 'reading', 'core.enabled' );
				register_setting( 'reading', 'minify.enabled' );

				add_settings_section( 'wp-pagespeed-section', __( 'PageSpeed Settings', 'wp-pagespeed' ), array( $this, 'setting_section_callback_function' ), 'reading' );

				add_settings_field( 'core.enabled', __( 'Enabled', 'wp-pagespeed' ), array( $this, 'setting_callback_function' ), 'reading', 'wp-pagespeed-section' );

				$this->send_headers();

			}

			/**
			 *
			 */
			public function template_redirect() {

				ob_start( array( $this, 'ob_start' ) );

				$this->send_headers();

			}

			/**
			 * Write Response headers.
			 *
			 * @uahot potanin@UD
			 */
			public function send_headers() {

				if( headers_sent() ) {
					return;
				}

				$_filters = apply_filters( 'wp_pagespeed:filters', [
					'recompress_images',
					'rewrite_javascript',
					'combine_javascript',
					//'outline_javascript',
					'rewrite_css',
					'combine_css',
					'inline_google_font_css',
					//'rewrite_domains',
					'rewrite_images',
					'resize_images',
					'lazyload_images',
					//'collapse_whitespace',
					//'inline_javascript',
					'remove_comments',
					'resize_rendered_image_dimensions'
				], $this );

				if( defined( 'WP_PAGESPEED' ) && !WP_PAGESPEED ) {
					header( 'PageSpeed: off' );
				}

				// Apply default filters.
				if( defined( 'WP_PAGESPEED' ) && is_bool( WP_PAGESPEED ) ) {
					header( 'PageSpeed: on' );
					header( 'PageSpeedFilters:' . join( ',', $_filters ) );
				}

				// Use filters defined in constant.
				if( defined( 'WP_PAGESPEED' ) && is_string( WP_PAGESPEED ) ) {
					header( 'PageSpeed: on' );
					header( 'PageSpeedFilters:' . WP_PAGESPEED );
				}

				if( $this->get( 'core.enabled', false ) ) {
					header( 'PageSpeed: on' );
					header( 'PageSpeedFilters:' . join( ',', $_filters ) );
				}

			}

			/**
			 * Handle Caching and Minification
			 *
			 * @todo Add logging.
			 *
			 * @mehod cache
			 * @author potanin@UD
			 *
			 * @param $buffer
			 *
			 * @return mixed|void
			 */
			public function ob_start( &$buffer ) {
				global $post, $wp_query;

				// @note thro exception to abort rest of ob_start from a filter.
				try {
					$buffer = apply_filters( 'wp-pagespeed:ob_start', $buffer, $this );
				} catch( \Exception $e ) {
					return $buffer;
				}

				// Media Domain Sharding.
				if( $this->get( 'minify.enabled' ) ) {

					$buffer = HTMLMinify::minify( $buffer, array(
						'optimizationLevel' => 'OPTIMIZATION_ADVANCED',
						'emptyElementAddWhitespaceBeforeSlash' => true,
						'emptyElementAddSlash' => false,
						'removeComment' => true,
						// 'excludeComment' => 'nocache',
						// 'removeDuplicateAttribute' => false
					));

				}

				// Never cached logged in users.
				if( function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) {
					return $buffer;
				}

				// Ignore CRON requests.
				if( isset( $_GET[ 'doing_wp_cron' ] ) && $_GET[ 'doing_wp_cron' ] ) {
					return $buffer;
				}

				// Do not cache search results.
				if( is_search() ) {
					return $buffer;
				}

				// Ignore 404 pages.
				if( is_404() ) {
					return $buffer;
				}

				// Bail on Media and Assets.
				if( is_attachment() ) {
					return $buffer;
				}

				// Bypass non-get requests.
				if( $_SERVER[ 'REQUEST_METHOD' ] !== 'GET' ) {
					return $buffer;
				}

				// Always bypass AJAX and CRON Requests.
				if( ( defined( 'DOING_CRON' ) && DOING_CRON ) && ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
					return $buffer;
				}

				return $buffer;

			}

			/**
			 * Get Setting.
			 *
			 *    // Get Setting
			 *    PageSpeed::get( 'my_key' )
			 *
			 * @method get
			 *
			 * @for Flawless
			 * @author potanin@UD
			 * @since 0.1.1
			 *
			 * @param null $key
			 * @param null $default
			 *
			 * @return null
			 */
			public static function get( $key = null, $default = null ) {
				return self::$instance->_settings ? self::$instance->_settings->get( $key, $default ) : null;
			}

			/**
			 * Set Setting.
			 *
			 * @usage
			 *
			 *    // Set Setting
			 *    PageSpeed::set( 'my_key', 'my-value' )
			 *
			 * @method get
			 * @for Flawless
			 *
			 * @author potanin@UD
			 * @since 0.1.1
			 *
			 * @param $key
			 * @param null $value
			 *
			 * @return null
			 */
			public static function set( $key, $value = null ) {
				return self::$instance->_settings ? self::$instance->_settings->set( $key, $value ) : null;
			}

			/**
			 * Get the PageSpeed Singleton
			 *
			 * Concept based on the CodeIgniter get_instance() concept.
			 *
			 * @example
			 *
			 *      var settings = PageSpeed::get_instance()->Settings;
			 *      var api = PageSpeed::$instance()->API;
			 *
			 * @static
			 * @return object
			 *
			 * @method get_instance
			 * @for PageSpeed
			 */
			public static function &get_instance() {
				return self::$instance;
			}

		}

	}

}