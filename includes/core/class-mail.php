<?php
namespace um\core;


// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;


if ( ! class_exists( 'um\core\Mail' ) ) {


	/**
	 * Class Mail
	 * @package um\core
	 */
	class Mail {


		/**
		 * @var array
		 */
		var $email_templates = array();


		/**
		 * @var array
		 */
		var $path_by_slug = array();


		/**
		 * Mail constructor.
		 */
		function __construct() {
			//mandrill compatibility
			add_filter( 'mandrill_nl2br', array( &$this, 'mandrill_nl2br' ) );
			add_action( 'plugins_loaded', array( &$this, 'init_paths' ), 99 );
		}


		/**
		 * Mandrill compatibility
		 *
		 * @param $nl2br
		 * @param string $message
		 * @return bool
		 */
		function mandrill_nl2br( $nl2br, $message = '' ) {
			// text emails
			if ( ! UM()->options()->get( 'email_html' ) ) {
				$nl2br = true;
			}

			return $nl2br;
		}


		/**
		 * Init paths for email notifications
		 */
		function init_paths() {
			/**
			 * UM hook
			 *
			 * @type filter
			 * @title um_email_templates_path_by_slug
			 * @description Extend email templates path
			 * @input_vars
			 * [{"var":"$paths","type":"array","desc":"Email slug -> Template Path"}]
			 * @change_log
			 * ["Since: 2.0"]
			 * @usage
			 * <?php add_filter( 'um_email_templates_path_by_slug', 'function_name', 10, 1 ); ?>
			 * @example
			 * <?php
			 * add_filter( 'um_email_templates_path_by_slug', 'my_email_templates_path_by_slug', 10, 1 );
			 * function my_email_templates_path_by_slug( $paths ) {
			 *     // your code here
			 *     return $paths;
			 * }
			 * ?>
			 */
			$this->path_by_slug = apply_filters( 'um_email_templates_path_by_slug', $this->path_by_slug );
		}


		/**
		 * Prepare email template to send
		 *
		 * @param $slug
		 * @param $args
		 * @return mixed|string
		 */
		function prepare_template( $slug, $args = array() ) {
			ob_start();

			$module = $template_path = '';
			if ( ! empty( $args['module'] ) ) {
				$module = $args['module'];
			}
			if ( ! empty( $args['template_path'] ) ) {
				$template_path = $args['template_path'];
			}

			if ( UM()->options()->get( 'email_html' ) ) {

				/**
				 * UM hook
				 *
				 * @type filter
				 * @title um_email_template_html_formatting
				 * @description Change email notification template header
				 * @input_vars
				 * [{"var":"$header","type":"string","desc":"Email notification header. '<html>' by default"},
				 * {"var":"$slug","type":"string","desc":"Template Key"},
				 * {"var":"$args","type":"array","desc":"Template settings"}]
				 * @change_log
				 * ["Since: 2.0"]
				 * @usage
				 * <?php add_filter( 'um_email_template_html_formatting', 'function_name', 10, 3 ); ?>
				 * @example
				 * <?php
				 * add_filter( 'um_email_template_html_formatting', 'my_email_template_html_formatting', 10, 3 );
				 * function my_email_template_html_formatting( $header, $slug, $args ) {
				 *     // your code here
				 *     return $header;
				 * }
				 * ?>
				 */
				echo apply_filters( 'um_email_template_html_formatting', '<html>', $slug, $args );

				/**
				 * UM hook
				 *
				 * @type action
				 * @title um_before_email_template_body
				 * @description Action before email template body display
				 * @input_vars
				 * [{"var":"$slug","type":"string","desc":"Email template slug"},
				 * {"var":"$args","type":"array","desc":"Email template arguments"}]
				 * @change_log
				 * ["Since: 2.0"]
				 * @usage add_action( 'um_before_email_template_body', 'function_name', 10, 2 );
				 * @example
				 * <?php
				 * add_action( 'um_before_email_template_body', 'my_before_email_template_body', 10, 2 );
				 * function my_before_email_template_body( $slug, $args ) {
				 *     // your code here
				 * }
				 * ?>
				 */
				do_action( 'um_before_email_template_body', $slug, $args );

				/**
				 * UM hook
				 *
				 * @type filter
				 * @title um_email_template_body_attrs
				 * @description Change email notification template body additional attributes
				 * @input_vars
				 * [{"var":"$body_atts","type":"string","desc":"Email notification body attributes"},
				 * {"var":"$slug","type":"string","desc":"Template Key"},
				 * {"var":"$args","type":"array","desc":"Template settings"}]
				 * @change_log
				 * ["Since: 2.0"]
				 * @usage
				 * <?php add_filter( 'um_email_template_body_attrs', 'function_name', 10, 3 ); ?>
				 * @example
				 * <?php
				 * add_filter( 'um_email_template_body_attrs', 'my_email_template_body_attrs', 10, 3 );
				 * function my_email_template_body_attrs( $body_atts, $slug, $args ) {
				 *     // your code here
				 *     return $body_atts;
				 * }
				 * ?>
				 */
				$body_attrs = apply_filters( 'um_email_template_body_attrs', 'style="background: #f2f2f2;-webkit-font-smoothing: antialiased;-moz-osx-font-smoothing: grayscale;"', $slug, $args );
				?>


				<body <?php echo $body_attrs ?>>

				<?php um_get_template( "email/{$slug}.php", $args, $module, $template_path ); ?>
				<?php /*echo $this->get_email_template( $slug, $args );*/ ?>

				</body>
				</html>

			<?php } else {

				//strip tags in plain text email
				//important don't use HTML in plain text emails!
				//$raw_email_template = $this->get_email_template( $slug, $args );
				$raw_email_template = um_get_template_html( "email/{$slug}.php", $args, $module, $template_path );
				$plain_email_template = strip_tags( $raw_email_template );
				if ( $plain_email_template !== $raw_email_template ) {
					$plain_email_template = preg_replace( array('/&nbsp;/mi', '/^\s+/mi'), array( ' ', '' ), $plain_email_template );
				}

				echo $plain_email_template;

			}

			$message = ob_get_clean();


			/**
			 * UM hook
			 *
			 * @type filter
			 * @title um_email_send_message_content
			 * @description Change email notification message content
			 * @input_vars
			 * [{"var":"$message","type":"string","desc":"Message Content"},
			 * {"var":"$template","type":"string","desc":"Template Key"},
			 * {"var":"$args","type":"string","desc":"Notification Arguments"}]
			 * @change_log
			 * ["Since: 2.0"]
			 * @usage
			 * <?php add_filter( 'um_email_send_message_content', 'function_name', 10, 3 ); ?>
			 * @example
			 * <?php
			 * add_filter( 'um_email_send_message_content', 'my_email_send_message_content', 10, 3 );
			 * function my_email_send_message_content( $message, $template, $args ) {
			 *     // your code here
			 *     return $message;
			 * }
			 * ?>
			 */
			$message = apply_filters( 'um_email_send_message_content', $message, $slug, $args );

			add_filter( 'um_template_tags_patterns_hook', array( &$this, 'add_placeholder' ), 10, 1 );
			add_filter( 'um_template_tags_replaces_hook', array( &$this, 'add_replace_placeholder' ), 10, 1 );

			// Convert tags in email template
			return um_convert_tags( $message, $args );
		}


		/**
		 * Send Email function
		 *
		 * @param string $email
		 * @param null $template
		 * @param array $args
		 */
		function send( $email, $template, $args = array() ) {

			if ( ! is_email( $email ) ) {
				return;
			}

			if ( UM()->options()->get( $template . '_on' ) != 1 ) {
				return;
			}

			do_action( 'um_before_email_notification_sending', $email, $template, $args );

			$this->attachments = array();
			$this->headers = 'From: '. stripslashes( UM()->options()->get( 'mail_from' ) ) .' <'. UM()->options()->get( 'mail_from_addr' ) .'>' . "\r\n";

			add_filter( 'um_template_tags_patterns_hook', array( UM()->mail(), 'add_placeholder' ), 10, 1 );
			add_filter( 'um_template_tags_replaces_hook', array( UM()->mail(), 'add_replace_placeholder' ), 10, 1 );

			$subject = apply_filters( 'um_email_send_subject', UM()->options()->get( $template . '_sub' ), $template );

			$subject = wp_unslash( um_convert_tags( $subject , $args ) );

			$this->subject = html_entity_decode( $subject, ENT_QUOTES, 'UTF-8' );

			$this->message = $this->prepare_template( $template, $args );

			if ( UM()->options()->get( 'email_html' ) ) {
				$this->headers .= "Content-Type: text/html\r\n";
			} else {
				$this->headers .= "Content-Type: text/plain\r\n";
			}

			// Send mail
			wp_mail( $email, $this->subject, $this->message, $this->headers, $this->attachments );

			do_action( 'um_after_email_notification_sending', $email, $template, $args );
		}


		/**
		 * UM Placeholders for site url, admin email, submit registration
		 *
		 * @param $placeholders
		 *
		 * @return array
		 */
		function add_placeholder( $placeholders ) {
			$placeholders[] = '{user_profile_link}';
			$placeholders[] = '{site_url}';
			$placeholders[] = '{admin_email}';
			$placeholders[] = '{submitted_registration}';
			$placeholders[] = '{login_url}';
			$placeholders[] = '{password}';
			$placeholders[] = '{account_activation_link}';
			return $placeholders;
		}


		/**
		 * UM Replace Placeholders for site url, admin email, submit registration
		 *
		 * @param $replace_placeholders
		 *
		 * @return array
		 */
		function add_replace_placeholder( $replace_placeholders ) {
			$replace_placeholders[] = um_user_profile_url();
			$replace_placeholders[] = get_bloginfo( 'url' );
			$replace_placeholders[] = um_admin_email();
			$replace_placeholders[] = um_user_submitted_registration_formatted();
			$replace_placeholders[] = um_get_predefined_page_url( 'login' );
			$replace_placeholders[] = esc_html__( 'Your set password', 'ultimate-member' );
			$replace_placeholders[] = um_user( 'account_activation_link' );
			return $replace_placeholders;
		}









		/**
		 * @deprecated 3.0
		 *
		 * @param $template_name
		 *
		 * @return mixed|void
		 */
		function get_template_filename( $template_name ) {
			_deprecated_function( __METHOD__, '3.0' );

			/**
			 * UM hook
			 *
			 * @type filter
			 * @title um_change_email_template_file
			 * @description Change email notification template path
			 * @input_vars
			 * [{"var":"$template_name","type":"string","desc":"Template Name"}]
			 * @change_log
			 * ["Since: 2.0"]
			 * @usage
			 * <?php add_filter( 'um_change_email_template_file', 'function_name', 10, 1 ); ?>
			 * @example
			 * <?php
			 * add_filter( 'um_change_email_template_file', 'my_change_email_template_file', 10, 1 );
			 * function my_change_email_template_file( $template, $template_name ) {
			 *     // your code here
			 *     return $template;
			 * }
			 * ?>
			 */
			return apply_filters( 'um_change_email_template_file', $template_name );
		}


		/**
		 * @deprecated 3.0
		 *
		 * Check blog ID on multisite, return '' if single site
		 *
		 * @return string
		 */
		function get_blog_id() {
			_deprecated_function( __METHOD__, '3.0' );

			$blog_id = '';
			if ( is_multisite() ) {
				$blog_id = '/' . get_current_blog_id();
			}

			return $blog_id;
		}


		/**
		 * Locate a template and return the path for inclusion.
		 *
		 * @deprecated 3.0
		 *
		 * @access public
		 * @param string $template_name
		 * @return string
		 */
		function locate_template( $template_name ) {
			_deprecated_function( __METHOD__, '3.0' );

			// check if there is template at theme folder
			$blog_id = $this->get_blog_id();

			//get template file from current blog ID folder
			$template = locate_template( array(
				trailingslashit( 'ultimate-member/email' . $blog_id ) . $template_name . '.php'
			) );

			//if there isn't template at theme folder for current blog ID get template file from theme folder
			if ( is_multisite() && ! $template ) {
				$template = locate_template( array(
					trailingslashit( 'ultimate-member/email' ) . $template_name . '.php'
				) );
			}

			//if there isn't template at theme folder get template file from plugin dir
			if ( ! $template ) {
				$path = ! empty( $this->path_by_slug[ $template_name ] ) ? $this->path_by_slug[ $template_name ] : um_path . 'templates/email';
				$template = trailingslashit( $path ) . $template_name . '.php';
			}

			// Return what we found.
			/**
			 * UM hook
			 *
			 * @type filter
			 * @title um_locate_email_template
			 * @description Change email notification template path
			 * @input_vars
			 * [{"var":"$template","type":"string","desc":"Template Path"},
			 * {"var":"$template_name","type":"string","desc":"Template Name"}]
			 * @change_log
			 * ["Since: 2.0"]
			 * @usage
			 * <?php add_filter( 'um_locate_email_template', 'function_name', 10, 2 ); ?>
			 * @example
			 * <?php
			 * add_filter( 'um_locate_email_template', 'my_locate_email_template', 10, 2 );
			 * function my_email_template_body_attrs( $template, $template_name ) {
			 *     // your code here
			 *     return $template;
			 * }
			 * ?>
			 */
			return apply_filters( 'um_locate_email_template', $template, $template_name );
		}


		/**
		 * @deprecated 3.0
		 *
		 * @param $slug
		 * @param $args
		 * @return bool|string
		 */
		function get_email_template( $slug, $args = array() ) {
			_deprecated_function( __METHOD__, '3.0', 'um_get_template' );

			$located = $this->locate_template( $slug );

			/**
			 * UM hook
			 *
			 * @type filter
			 * @title um_email_template_path
			 * @description Change email template location
			 * @input_vars
			 * [{"var":"$located","type":"string","desc":"Template Location"},
			 * {"var":"$slug","type":"string","desc":"Template Key"},
			 * {"var":"$args","type":"array","desc":"Template settings"}]
			 * @change_log
			 * ["Since: 2.0"]
			 * @usage
			 * <?php add_filter( 'um_email_template_path', 'function_name', 10, 3 ); ?>
			 * @example
			 * <?php
			 * add_filter( 'um_email_template_path', 'my_email_send_subject', 10, 3 );
			 * function my_email_send_subject( $located, $slug, $args ) {
			 *     // your code here
			 *     return $located;
			 * }
			 * ?>
			 */
			$located = apply_filters( 'um_email_template_path', $located, $slug, $args );

			if ( ! file_exists( $located ) ) {
				_doing_it_wrong( __FUNCTION__, sprintf( '<code>%s</code> does not exist.', $located ), '2.1' );
				return false;
			}

			ob_start();
			/**
			 * UM hook
			 *
			 * @type action
			 * @title um_before_email_template_part
			 * @description Action before email template loading
			 * @input_vars
			 * [{"var":"$slug","type":"string","desc":"Email template slug"},
			 * {"var":"$located","type":"string","desc":"Email template location"},
			 * {"var":"$args","type":"array","desc":"Email template arguments"}]
			 * @change_log
			 * ["Since: 2.0"]
			 * @usage add_action( 'um_before_email_template_part', 'function_name', 10, 3 );
			 * @example
			 * <?php
			 * add_action( 'um_before_email_template_part', 'my_before_email_template_part', 10, 3 );
			 * function my_before_email_template_part( $slug, $located, $args ) {
			 *     // your code here
			 * }
			 * ?>
			 */
			do_action( 'um_before_email_template_part', $slug, $located, $args );

			include( $located );
			/**
			 * UM hook
			 *
			 * @type action
			 * @title um_after_email_template_part
			 * @description Action after email template loading
			 * @input_vars
			 * [{"var":"$slug","type":"string","desc":"Email template slug"},
			 * {"var":"$located","type":"string","desc":"Email template location"},
			 * {"var":"$args","type":"array","desc":"Email template arguments"}]
			 * @change_log
			 * ["Since: 2.0"]
			 * @usage add_action( 'um_after_email_template_part', 'function_name', 10, 3 );
			 * @example
			 * <?php
			 * add_action( 'um_after_email_template_part', 'my_after_email_template_part', 10, 3 );
			 * function my_after_email_template_part( $slug, $located, $args ) {
			 *     // your code here
			 * }
			 * ?>
			 */
			do_action( 'um_after_email_template_part', $slug, $located, $args );

			return ob_get_clean();
		}


		/**
		 * Method returns expected path for template
		 *
		 * @deprecated 3.0
		 *
		 * @access public
		 *
		 * @param string $location
		 * @param string $template_name
		 *
		 * @return string
		 */
		function get_template_file( $location, $template_name ) {
			_deprecated_function( __METHOD__, '3.0' );
			$template_path = '';
			$template_name_file = $this->get_template_filename( $template_name );

			switch( $location ) {
				case 'theme':
					//save email template in blog ID folder if we use multisite
					$blog_id = $this->get_blog_id();

					$template_path = trailingslashit( get_stylesheet_directory() . '/ultimate-member/email' . $blog_id ). $template_name_file . '.php';
					break;
				case 'plugin':
					$path = ! empty( $this->path_by_slug[ $template_name ] ) ? $this->path_by_slug[ $template_name ] : um_path . 'templates/email';
					$template_path = trailingslashit( $path ) . $template_name . '.php';
					break;
			}

			return $template_path;
		}


		/**
		 * Locate a template and return the path for inclusion.
		 *
		 * @deprecated 3.0
		 *
		 * @access public
		 * @param string $template_name
		 * @return string
		 */
		function template_in_theme( $template_name ) {
			_deprecated_function( __METHOD__, '3.0' );
			$template_name_file = $this->get_template_filename( $template_name );

			$blog_id = $this->get_blog_id();

			// check if there is template at theme blog ID folder
			$template = locate_template( array(
				trailingslashit( 'ultimate-member/email' . $blog_id ) . $template_name_file . '.php'
			) );

			// Return what we found.
			if ( get_template_directory() === get_stylesheet_directory() ) {
				return ! $template ? false : true;
			} else {
				return strstr( $template, get_stylesheet_directory() );
			}
		}


		/**
		 * Ajax copy template to the theme
		 *
		 * @deprecated 3.0
		 *
		 * @param string $template
		 * @return bool
		 */
		function copy_email_template( $template ) {
			_deprecated_function( __METHOD__, '3.0' );

			$in_theme = $this->template_in_theme( $template );
			if ( $in_theme ) {
				return false;
			}

			$plugin_template_path = $this->get_template_file( 'plugin', $template );
			$theme_template_path = $this->get_template_file( 'theme', $template );

			$temp_path = str_replace( trailingslashit( get_stylesheet_directory() ), '', $theme_template_path );
			$temp_path = str_replace( '/', DIRECTORY_SEPARATOR, $temp_path );
			$folders = explode( DIRECTORY_SEPARATOR, $temp_path );
			$folders = array_splice( $folders, 0, count( $folders ) - 1 );
			$cur_folder = '';
			$theme_dir = trailingslashit( get_stylesheet_directory() );

			foreach ( $folders as $folder ) {
				$prev_dir = $cur_folder;
				$cur_folder .= $folder . DIRECTORY_SEPARATOR;
				if ( ! is_dir( $theme_dir . $cur_folder ) && wp_is_writable( $theme_dir . $prev_dir ) ) {
					mkdir( $theme_dir . $cur_folder, 0777 );
				}
			}

			if ( file_exists( $plugin_template_path ) && copy( $plugin_template_path, $theme_template_path ) ) {
				return true;
			} else {
				return false;
			}
		}
	}
}
