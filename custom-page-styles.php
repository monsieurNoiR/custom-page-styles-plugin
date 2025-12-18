<?php
/**
 * Plugin Name: Custom Page Styles Manager
 * Plugin URI: https://example.com/custom-page-styles
 * Description: ページ固有のカスタムスタイルシート管理機能を提供します。各ページにカスタムCSSを記述し、過去のスタイルシートを再利用できます。
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: custom-page-styles
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 *
 * @package CustomPageStyles
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class
 */
class Custom_Page_Styles_Manager {

	/**
	 * Plugin version
	 *
	 * @var string
	 */
	const VERSION = '1.0.0';

	/**
	 * Meta key for custom CSS content
	 *
	 * @var string
	 */
	const META_KEY_CSS = '_custom_page_styles_css';

	/**
	 * Meta key for selected stylesheet
	 *
	 * @var string
	 */
	const META_KEY_SELECTED = '_custom_page_styles_selected';

	/**
	 * Option key for enabled post types
	 *
	 * @var string
	 */
	const OPTION_ENABLED_POST_TYPES = 'custom_page_styles_enabled_post_types';

	/**
	 * Directory name for storing CSS files
	 *
	 * @var string
	 */
	const CSS_DIR_NAME = 'custom-page-styles';

	/**
	 * Singleton instance
	 *
	 * @var Custom_Page_Styles_Manager
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @return Custom_Page_Styles_Manager
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		// Admin hooks
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_meta_box' ), 10, 2 );

		// Frontend hooks
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_custom_styles' ) );

		// Activation hook
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
	}

	/**
	 * Plugin activation
	 */
	public function activate() {
		// Set default enabled post types
		if ( false === get_option( self::OPTION_ENABLED_POST_TYPES ) ) {
			update_option( self::OPTION_ENABLED_POST_TYPES, array( 'post', 'page' ) );
		}

		// Create CSS directory
		$this->create_css_directory();
	}

	/**
	 * Create CSS directory if it doesn't exist
	 *
	 * @return bool True on success, false on failure
	 */
	private function create_css_directory() {
		$upload_dir = wp_upload_dir();
		$css_dir    = trailingslashit( $upload_dir['basedir'] ) . self::CSS_DIR_NAME;

		if ( ! file_exists( $css_dir ) ) {
			if ( ! wp_mkdir_p( $css_dir ) ) {
				return false;
			}

			$filesystem = $this->get_filesystem();

			if ( $filesystem ) {
				// Add index.php for security
				$index_file = trailingslashit( $css_dir ) . 'index.php';
				$filesystem->put_contents( $index_file, '<?php // Silence is golden', FS_CHMOD_FILE );

				// Add .htaccess to prevent PHP execution
				$htaccess_file = trailingslashit( $css_dir ) . '.htaccess';
				$htaccess_content = "# Prevent PHP execution in uploads directory\n";
				$htaccess_content .= "<Files *.php>\n";
				$htaccess_content .= "deny from all\n";
				$htaccess_content .= "</Files>\n";
				$filesystem->put_contents( $htaccess_file, $htaccess_content, FS_CHMOD_FILE );
			}
		}

		return true;
	}

	/**
	 * Get WP_Filesystem instance
	 *
	 * @return WP_Filesystem_Base|false
	 */
	private function get_filesystem() {
		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		return $wp_filesystem;
	}

	/**
	 * Add settings page to admin menu
	 */
	public function add_settings_page() {
		add_options_page(
			__( 'Custom Page Styles Settings', 'custom-page-styles' ),
			__( 'Custom Page Styles', 'custom-page-styles' ),
			'manage_options',
			'custom-page-styles',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register plugin settings
	 */
	public function register_settings() {
		register_setting(
			'custom_page_styles_settings',
			self::OPTION_ENABLED_POST_TYPES,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_post_types' ),
				'default'           => array( 'post', 'page' ),
			)
		);

		add_settings_section(
			'custom_page_styles_main',
			__( 'Post Type Settings', 'custom-page-styles' ),
			array( $this, 'render_settings_section' ),
			'custom-page-styles'
		);

		add_settings_field(
			'enabled_post_types',
			__( 'Enable for Post Types', 'custom-page-styles' ),
			array( $this, 'render_post_types_field' ),
			'custom-page-styles',
			'custom_page_styles_main'
		);
	}

	/**
	 * Sanitize post types array
	 *
	 * @param array $post_types Post types array
	 * @return array Sanitized post types
	 */
	public function sanitize_post_types( $post_types ) {
		if ( ! is_array( $post_types ) ) {
			return array();
		}

		$all_post_types = get_post_types( array(), 'names' );
		$sanitized      = array();

		foreach ( $post_types as $post_type ) {
			$post_type = sanitize_key( $post_type );
			if ( in_array( $post_type, $all_post_types, true ) ) {
				$sanitized[] = $post_type;
			}
		}

		return $sanitized;
	}

	/**
	 * Render settings section
	 */
	public function render_settings_section() {
		echo '<p>' . esc_html__( 'Select the post types where you want to enable custom page styles functionality.', 'custom-page-styles' ) . '</p>';
	}

	/**
	 * Render post types selection field
	 */
	public function render_post_types_field() {
		$enabled_post_types = get_option( self::OPTION_ENABLED_POST_TYPES, array( 'post', 'page' ) );
		$all_post_types     = get_post_types( array( 'public' => true ), 'objects' );

		foreach ( $all_post_types as $post_type ) {
			$checked = in_array( $post_type->name, $enabled_post_types, true ) ? 'checked="checked"' : '';
			printf(
				'<label><input type="checkbox" name="%s[]" value="%s" %s /> %s</label><br>',
				esc_attr( self::OPTION_ENABLED_POST_TYPES ),
				esc_attr( $post_type->name ),
				$checked,
				esc_html( $post_type->label )
			);
		}
	}

	/**
	 * Render settings page
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- WordPress handles nonce verification for settings page
		if ( isset( $_GET['settings-updated'] ) ) {
			add_settings_error(
				'custom_page_styles_messages',
				'custom_page_styles_message',
				__( 'Settings saved.', 'custom-page-styles' ),
				'updated'
			);
		}

		settings_errors( 'custom_page_styles_messages' );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'custom_page_styles_settings' );
				do_settings_sections( 'custom-page-styles' );
				submit_button( __( 'Save Settings', 'custom-page-styles' ) );
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Add meta box to enabled post types
	 */
	public function add_meta_box() {
		$enabled_post_types = get_option( self::OPTION_ENABLED_POST_TYPES, array( 'post', 'page' ) );

		foreach ( $enabled_post_types as $post_type ) {
			add_meta_box(
				'custom_page_styles_meta_box',
				__( 'Custom Page Styles', 'custom-page-styles' ),
				array( $this, 'render_meta_box' ),
				$post_type,
				'normal',
				'default'
			);
		}

		// Add admin styles
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );
	}

	/**
	 * Enqueue admin styles
	 */
	public function enqueue_admin_styles( $hook ) {
		// Only load on post edit pages
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}

		// Inline CSS for better UX
		$custom_css = "
			.custom-page-styles-meta-box textarea#custom_page_styles_css {
				font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', 'Consolas', 'source-code-pro', monospace;
				font-size: 13px;
				line-height: 1.5;
				background-color: #f5f5f5;
				border: 1px solid #ddd;
				padding: 10px;
			}
			.custom-page-styles-meta-box .description {
				font-style: italic;
				color: #666;
			}
		";

		wp_add_inline_style( 'wp-admin', $custom_css );
	}

	/**
	 * Render meta box content
	 *
	 * @param WP_Post $post Post object
	 */
	public function render_meta_box( $post ) {
		// Add nonce field
		wp_nonce_field( 'custom_page_styles_save', 'custom_page_styles_nonce' );

		// Get current values
		$custom_css      = get_post_meta( $post->ID, self::META_KEY_CSS, true );
		$selected_style  = get_post_meta( $post->ID, self::META_KEY_SELECTED, true );

		// Get all posts with custom styles
		$available_styles = $this->get_available_styles( $post->ID );

		?>
		<div class="custom-page-styles-meta-box">
			<p>
				<label for="custom_page_styles_css">
					<strong><?php esc_html_e( 'Custom CSS for this page:', 'custom-page-styles' ); ?></strong>
				</label>
			</p>
			<p>
				<textarea
					id="custom_page_styles_css"
					name="custom_page_styles_css"
					rows="10"
					style="width: 100%; font-family: monospace;"
					placeholder="<?php esc_attr_e( 'Enter your custom CSS here...', 'custom-page-styles' ); ?>"
				><?php echo esc_textarea( $custom_css ); ?></textarea>
			</p>

			<hr style="margin: 20px 0;">

			<p>
				<label for="custom_page_styles_selected">
					<strong><?php esc_html_e( 'Or select an existing stylesheet:', 'custom-page-styles' ); ?></strong>
				</label>
			</p>
			<p>
				<select id="custom_page_styles_selected" name="custom_page_styles_selected" style="width: 100%;">
					<option value=""><?php esc_html_e( '-- None --', 'custom-page-styles' ); ?></option>
					<?php foreach ( $available_styles as $style_post_id => $style_title ) : ?>
						<option value="<?php echo esc_attr( $style_post_id ); ?>" <?php selected( $selected_style, $style_post_id ); ?>>
							<?php echo esc_html( $style_title ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</p>

			<p class="description">
				<?php esc_html_e( 'You can apply up to 2 stylesheets: one custom CSS written above, and one selected from existing styles.', 'custom-page-styles' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Get available stylesheets from other posts
	 *
	 * @param int $current_post_id Current post ID to exclude
	 * @return array Array of post_id => title
	 */
	private function get_available_styles( $current_post_id ) {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT DISTINCT p.ID, p.post_title, p.post_type
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			WHERE pm.meta_key = %s
			AND pm.meta_value != ''
			AND p.post_status = 'publish'
			AND p.ID != %d
			ORDER BY p.post_modified DESC",
			self::META_KEY_CSS,
			$current_post_id
		);

		$results = $wpdb->get_results( $query );
		$styles  = array();

		foreach ( $results as $row ) {
			$post_type_obj = get_post_type_object( $row->post_type );
			$post_type_label = $post_type_obj ? $post_type_obj->labels->singular_name : $row->post_type;

			$styles[ $row->ID ] = sprintf(
				'%s (ID: %d, %s)',
				$row->post_title,
				$row->ID,
				$post_type_label
			);
		}

		return $styles;
	}

	/**
	 * Save meta box data
	 *
	 * @param int     $post_id Post ID
	 * @param WP_Post $post    Post object
	 */
	public function save_meta_box( $post_id, $post ) {
		// Verify nonce
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verification doesn't require sanitization
		if ( ! isset( $_POST['custom_page_styles_nonce'] ) ||
		     ! wp_verify_nonce( wp_unslash( $_POST['custom_page_styles_nonce'] ), 'custom_page_styles_save' ) ) {
			return;
		}

		// Check autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check permissions
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Check if this post type is enabled
		$enabled_post_types = get_option( self::OPTION_ENABLED_POST_TYPES, array( 'post', 'page' ) );
		if ( ! in_array( $post->post_type, $enabled_post_types, true ) ) {
			return;
		}

		// Save custom CSS
		if ( isset( $_POST['custom_page_styles_css'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized in sanitize_css() method
			$custom_css = $this->sanitize_css( wp_unslash( $_POST['custom_page_styles_css'] ) );

			if ( ! empty( $custom_css ) ) {
				update_post_meta( $post_id, self::META_KEY_CSS, $custom_css );

				// Generate CSS file
				$this->generate_css_file( $post_id, $custom_css );
			} else {
				// Delete meta and file if CSS is empty
				delete_post_meta( $post_id, self::META_KEY_CSS );
				$this->delete_css_file( $post_id );
			}
		}

		// Save selected stylesheet
		if ( isset( $_POST['custom_page_styles_selected'] ) ) {
			$selected_id = absint( $_POST['custom_page_styles_selected'] );

			if ( $selected_id > 0 ) {
				// Verify the selected post exists and has custom CSS
				$selected_css = get_post_meta( $selected_id, self::META_KEY_CSS, true );
				if ( ! empty( $selected_css ) ) {
					update_post_meta( $post_id, self::META_KEY_SELECTED, $selected_id );
				} else {
					delete_post_meta( $post_id, self::META_KEY_SELECTED );
				}
			} else {
				delete_post_meta( $post_id, self::META_KEY_SELECTED );
			}
		}
	}

	/**
	 * Sanitize CSS input
	 *
	 * @param string $css Raw CSS input
	 * @return string Sanitized CSS
	 */
	private function sanitize_css( $css ) {
		// Remove all tags
		$css = wp_strip_all_tags( $css );

		// Basic CSS validation - check for balanced braces
		$open_braces  = substr_count( $css, '{' );
		$close_braces = substr_count( $css, '}' );

		if ( $open_braces !== $close_braces ) {
			add_settings_error(
				'custom_page_styles_messages',
				'css_validation_error',
				__( 'CSS validation error: Unbalanced braces detected.', 'custom-page-styles' ),
				'error'
			);
			return '';
		}

		// Remove potentially dangerous CSS properties
		$dangerous_patterns = array(
			'/@import/i',
			'/javascript:/i',
			'/expression\s*\(/i',
			'/behavior\s*:/i',
			'/-moz-binding/i',
		);

		foreach ( $dangerous_patterns as $pattern ) {
			$css = preg_replace( $pattern, '', $css );
		}

		return trim( $css );
	}

	/**
	 * Generate CSS file for a post
	 *
	 * @param int    $post_id Post ID
	 * @param string $css     CSS content
	 * @return bool True on success, false on failure
	 */
	private function generate_css_file( $post_id, $css ) {
		if ( empty( $css ) ) {
			return false;
		}

		// Validate post ID (prevent path traversal)
		$post_id = absint( $post_id );
		if ( $post_id <= 0 ) {
			return false;
		}

		$upload_dir = wp_upload_dir();
		$css_dir    = trailingslashit( $upload_dir['basedir'] ) . self::CSS_DIR_NAME;

		// Create directory if it doesn't exist
		if ( ! $this->create_css_directory() ) {
			add_settings_error(
				'custom_page_styles_messages',
				'css_dir_error',
				__( 'Failed to create CSS directory.', 'custom-page-styles' ),
				'error'
			);
			return false;
		}

		// Build filename with validated post ID
		$filename = 'post-styles-' . $post_id . '.css';
		$css_file = trailingslashit( $css_dir ) . $filename;

		// Verify the file path is within the expected directory (prevent path traversal)
		$real_css_dir = realpath( $css_dir );
		$real_css_file = realpath( dirname( $css_file ) ) . '/' . basename( $css_file );

		if ( false === $real_css_dir || false === strpos( $real_css_file, $real_css_dir ) ) {
			add_settings_error(
				'custom_page_styles_messages',
				'path_traversal_error',
				__( 'Invalid file path detected.', 'custom-page-styles' ),
				'error'
			);
			return false;
		}

		$filesystem = $this->get_filesystem();

		if ( ! $filesystem ) {
			add_settings_error(
				'custom_page_styles_messages',
				'filesystem_error',
				__( 'Failed to initialize filesystem.', 'custom-page-styles' ),
				'error'
			);
			return false;
		}

		// Add header comment to CSS file
		$css_content = sprintf(
			"/**\n * Custom Page Styles for Post ID: %d\n * Generated: %s\n */\n\n%s",
			$post_id,
			current_time( 'mysql' ),
			$css
		);

		if ( ! $filesystem->put_contents( $css_file, $css_content, FS_CHMOD_FILE ) ) {
			add_settings_error(
				'custom_page_styles_messages',
				'css_write_error',
				__( 'Failed to write CSS file.', 'custom-page-styles' ),
				'error'
			);
			return false;
		}

		return true;
	}

	/**
	 * Delete CSS file for a post
	 *
	 * @param int $post_id Post ID
	 * @return bool True on success, false on failure
	 */
	private function delete_css_file( $post_id ) {
		// Validate post ID (prevent path traversal)
		$post_id = absint( $post_id );
		if ( $post_id <= 0 ) {
			return false;
		}

		$upload_dir = wp_upload_dir();
		$css_dir    = trailingslashit( $upload_dir['basedir'] ) . self::CSS_DIR_NAME;
		$filename   = 'post-styles-' . $post_id . '.css';
		$css_file   = trailingslashit( $css_dir ) . $filename;

		// Verify the file path is within the expected directory (prevent path traversal)
		if ( file_exists( $css_file ) ) {
			$real_css_dir  = realpath( $css_dir );
			$real_css_file = realpath( $css_file );

			if ( false === $real_css_dir || false === $real_css_file || false === strpos( $real_css_file, $real_css_dir ) ) {
				return false;
			}

			$filesystem = $this->get_filesystem();
			if ( $filesystem ) {
				return $filesystem->delete( $css_file );
			}
		}

		return false;
	}

	/**
	 * Enqueue custom styles on frontend
	 */
	public function enqueue_custom_styles() {
		if ( ! is_singular() ) {
			return;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return;
		}

		$upload_dir = wp_upload_dir();
		$css_dir    = trailingslashit( $upload_dir['basedir'] ) . self::CSS_DIR_NAME;
		$css_url    = trailingslashit( $upload_dir['baseurl'] ) . self::CSS_DIR_NAME;

		// Enqueue current post's custom CSS
		$this->enqueue_post_style( $post_id, $css_dir, $css_url, 'custom-page-styles-' );

		// Enqueue selected stylesheet
		$selected_id = absint( get_post_meta( $post_id, self::META_KEY_SELECTED, true ) );
		if ( $selected_id > 0 ) {
			$this->enqueue_post_style( $selected_id, $css_dir, $css_url, 'custom-page-styles-selected-' );
		}
	}

	/**
	 * Enqueue a single post's stylesheet
	 *
	 * @param int    $post_id Post ID
	 * @param string $css_dir CSS directory path
	 * @param string $css_url CSS directory URL
	 * @param string $handle_prefix Handle prefix for wp_enqueue_style
	 */
	private function enqueue_post_style( $post_id, $css_dir, $css_url, $handle_prefix ) {
		// Validate post ID
		$post_id = absint( $post_id );
		if ( $post_id <= 0 ) {
			return;
		}

		$filename = 'post-styles-' . $post_id . '.css';
		$css_file = trailingslashit( $css_dir ) . $filename;

		// Check if file exists
		if ( ! file_exists( $css_file ) ) {
			return;
		}

		// Verify the file path is within the expected directory (prevent path traversal)
		$real_css_dir  = realpath( $css_dir );
		$real_css_file = realpath( $css_file );

		if ( false === $real_css_dir || false === $real_css_file || false === strpos( $real_css_file, $real_css_dir ) ) {
			return;
		}

		// Enqueue the stylesheet
		wp_enqueue_style(
			$handle_prefix . $post_id,
			trailingslashit( $css_url ) . $filename,
			array(),
			filemtime( $css_file )
		);
	}
}

// Initialize the plugin
Custom_Page_Styles_Manager::get_instance();
