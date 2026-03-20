<?php
/**
 * Plugin Name: Studio Noir Custom Page Styles
 * Plugin URI: https://github.com/monsieurNoiR/custom-page-styles-plugin
 * Description: Manage custom CSS for each page/post with a Style Library for reusable styles, file uploads, and drag & drop ordering.
 * Version: 2.0.1
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Author: Masaki Kobayashi (studioNoiR)
 * Author URI: https://github.com/monsieurNoiR
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: studio-noir-page-styles
 * Domain Path: /languages
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
class SN_CPS_Manager {

	const VERSION = '2.0.1';

	/** Meta key for custom CSS content */
	const SN_CPS_META_KEY_CSS = '_sn_cps_css';

	/** Meta key for selected stylesheet IDs (legacy v1.x) */
	const SN_CPS_META_KEY_SELECTED = '_sn_cps_selected';

	/** Meta key for Library entry IDs applied to a page (v2.0+) */
	const SN_CPS_META_KEY_LIBRARY_IDS = '_sn_cps_library_ids';

	/** Meta key linking a page to its Library entry (v2.0+) */
	const SN_CPS_META_KEY_LINKED_LIBRARY = '_sn_cps_linked_library_id';

	/** Meta key for uploaded files */
	const SN_CPS_META_KEY_UPLOADED = '_sn_cps_uploaded_files';

	/** Option key for enabled post types */
	const SN_CPS_OPTION_ENABLED_POST_TYPES = 'sn_cps_enabled_post_types';

	/** Directory name for storing CSS files */
	const SN_CPS_CSS_DIR_NAME = 'sn-cps-styles';

	/** Custom post type for Style Library */
	const SN_CPS_STYLE_POST_TYPE = 'sn_cps_style';

	/** DB schema version */
	const SN_CPS_DB_VERSION = '2.0';

	/** Option key for DB version */
	const SN_CPS_OPTION_DB_VERSION = 'sn_cps_db_version';

	/** @var SN_CPS_Manager */
	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->init_hooks();
	}

	private function init_hooks() {
		// CPT registration
		add_action( 'init', array( $this, 'register_style_post_type' ) );

		// Admin hooks
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'maybe_run_migration' ) );
		add_action( 'admin_notices', array( $this, 'render_migration_notice' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_meta_box' ), 10, 2 );

		// Deletion / trash hooks
		add_action( 'before_delete_post', array( $this, 'cleanup_on_delete' ) );
		add_action( 'wp_trash_post', array( $this, 'notify_trash_unregistered_css' ) );

		// AJAX hooks
		add_action( 'wp_ajax_sn_cps_upload_file', array( $this, 'ajax_upload_file' ) );
		add_action( 'wp_ajax_sn_cps_remove_file', array( $this, 'ajax_remove_file' ) );
		add_action( 'wp_ajax_sn_cps_save_to_library', array( $this, 'ajax_save_to_library' ) );
		add_action( 'wp_ajax_sn_cps_sync_to_library', array( $this, 'ajax_sync_to_library' ) );
		add_action( 'wp_ajax_sn_cps_retry_migration', array( $this, 'ajax_retry_migration' ) );
		add_action( 'wp_ajax_sn_cps_dismiss_migration_notice', array( $this, 'ajax_dismiss_migration_notice' ) );

		// Frontend hooks
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_custom_styles' ), 20 );

		// Activation hook
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
	}

	// =========================================================================
	// ACTIVATION & SETUP
	// =========================================================================

	public function activate() {
		if ( false === get_option( self::SN_CPS_OPTION_ENABLED_POST_TYPES ) ) {
			update_option( self::SN_CPS_OPTION_ENABLED_POST_TYPES, array( 'post', 'page' ) );
		}
		$this->create_css_directory();
	}

	private function create_css_directory() {
		$upload_dir = wp_upload_dir();
		$css_dir    = trailingslashit( $upload_dir['basedir'] ) . self::SN_CPS_CSS_DIR_NAME;

		if ( ! file_exists( $css_dir ) ) {
			if ( ! wp_mkdir_p( $css_dir ) ) {
				return false;
			}

			$filesystem = $this->get_filesystem();
			if ( $filesystem ) {
				$filesystem->put_contents( trailingslashit( $css_dir ) . 'index.php', '<?php // Silence is golden', FS_CHMOD_FILE );
				$htaccess  = "# Prevent PHP execution in uploads directory\n";
				$htaccess .= "<Files *.php>\n";
				$htaccess .= "deny from all\n";
				$htaccess .= "</Files>\n";
				$filesystem->put_contents( trailingslashit( $css_dir ) . '.htaccess', $htaccess, FS_CHMOD_FILE );
			}
		}

		return true;
	}

	private function get_filesystem() {
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}
		return $wp_filesystem;
	}

	// =========================================================================
	// STYLE LIBRARY CPT
	// =========================================================================

	public function register_style_post_type() {
		register_post_type(
			self::SN_CPS_STYLE_POST_TYPE,
			array(
				'labels'             => array(
					'name'               => __( 'Style Library', 'studio-noir-page-styles' ),
					'singular_name'      => __( 'Style', 'studio-noir-page-styles' ),
					'add_new'            => __( 'Add New Style', 'studio-noir-page-styles' ),
					'add_new_item'       => __( 'Add New Style', 'studio-noir-page-styles' ),
					'edit_item'          => __( 'Edit Style', 'studio-noir-page-styles' ),
					'new_item'           => __( 'New Style', 'studio-noir-page-styles' ),
					'search_items'       => __( 'Search Styles', 'studio-noir-page-styles' ),
					'not_found'          => __( 'No styles found.', 'studio-noir-page-styles' ),
					'not_found_in_trash' => __( 'No styles found in trash.', 'studio-noir-page-styles' ),
					'menu_name'          => __( 'Style Library', 'studio-noir-page-styles' ),
				),
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_in_nav_menus'  => false,
				'show_in_admin_bar'  => false,
				'show_in_rest'       => false,
				'supports'           => array( 'title' ),
				'has_archive'        => false,
				'rewrite'            => false,
				'query_var'          => false,
				'capability_type'    => 'post',
				'map_meta_cap'       => true,
				'menu_icon'          => 'dashicons-art',
				'menu_position'      => 25,
			)
		);
	}

	// =========================================================================
	// SETTINGS PAGE
	// =========================================================================

	public function add_settings_page() {
		add_options_page(
			__( 'Custom Page Styles Settings', 'studio-noir-page-styles' ),
			__( 'Custom Page Styles', 'studio-noir-page-styles' ),
			'manage_options',
			'sn-cps-settings',
			array( $this, 'render_settings_page' )
		);
	}

	public function register_settings() {
		register_setting(
			'sn_cps_settings',
			self::SN_CPS_OPTION_ENABLED_POST_TYPES,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_post_types' ),
				'default'           => array( 'post', 'page' ),
			)
		);

		add_settings_section( 'sn_cps_main', __( 'Post Type Settings', 'studio-noir-page-styles' ), array( $this, 'render_settings_section' ), 'sn-cps-settings' );
		add_settings_field( 'enabled_post_types', __( 'Enable for Post Types', 'studio-noir-page-styles' ), array( $this, 'render_post_types_field' ), 'sn-cps-settings', 'sn_cps_main' );
	}

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

	public function render_settings_section() {
		echo '<p>' . esc_html__( 'Select the post types where you want to enable custom page styles functionality.', 'studio-noir-page-styles' ) . '</p>';
	}

	public function render_post_types_field() {
		$enabled_post_types = get_option( self::SN_CPS_OPTION_ENABLED_POST_TYPES, array( 'post', 'page' ) );
		$all_post_types     = get_post_types( array( 'public' => true ), 'objects' );

		foreach ( $all_post_types as $post_type ) {
			$checked = in_array( $post_type->name, $enabled_post_types, true ) ? 'checked="checked"' : '';
			printf(
				'<label><input type="checkbox" name="%s[]" value="%s" %s /> %s</label><br>',
				esc_attr( self::SN_CPS_OPTION_ENABLED_POST_TYPES ),
				esc_attr( $post_type->name ),
				esc_attr( $checked ),
				esc_html( $post_type->label )
			);
		}
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['settings-updated'] ) ) {
			add_settings_error( 'sn_cps_messages', 'sn_cps_message', __( 'Settings saved.', 'studio-noir-page-styles' ), 'updated' );
		}
		settings_errors( 'sn_cps_messages' );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'sn_cps_settings' );
				do_settings_sections( 'sn-cps-settings' );
				submit_button( __( 'Save Settings', 'studio-noir-page-styles' ) );
				?>
			</form>
		</div>
		<?php
	}

	// =========================================================================
	// META BOXES
	// =========================================================================

	public function add_meta_box() {
		$enabled_post_types = get_option( self::SN_CPS_OPTION_ENABLED_POST_TYPES, array( 'post', 'page' ) );

		foreach ( $enabled_post_types as $post_type ) {
			add_meta_box( 'sn_cps_meta_box', __( 'Custom Page Styles', 'studio-noir-page-styles' ), array( $this, 'render_meta_box' ), $post_type, 'normal', 'default' );
		}

		// Library entry meta box
		add_meta_box( 'sn_cps_library_entry_box', __( 'Style Content', 'studio-noir-page-styles' ), array( $this, 'render_library_entry_meta_box' ), self::SN_CPS_STYLE_POST_TYPE, 'normal', 'high' );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );
	}

	public function enqueue_admin_styles( $hook ) {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}
		wp_enqueue_script( 'jquery-ui-sortable' );

		$custom_css = "
			.sn-cps-meta-box textarea#sn_cps_css {
				font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', 'Consolas', 'source-code-pro', monospace;
				font-size: 13px;
				line-height: 1.5;
				background-color: #f5f5f5;
				border: 1px solid #ddd;
				padding: 10px;
			}
			.sn-cps-meta-box .description { font-style: italic; color: #666; }
			.sn-cps-library-actions { margin-top: 8px; padding: 8px 0; border-top: 1px solid #e0e0e0; }
			.sn-cps-sync-dialog { background: #fff; border: 1px solid #c3c4c7; padding: 15px; margin-top: 10px; border-left: 4px solid #2271b1; }
			.sn-cps-sync-dialog label { display: block; margin-bottom: 8px; }
			.sn-cps-sync-dialog .sn-cps-new-name-wrap { margin-left: 22px; margin-top: 6px; }
		";
		wp_add_inline_style( 'wp-admin', $custom_css );
	}

	/**
	 * Render meta box for regular pages/posts
	 */
	public function render_meta_box( $post ) {
		$error_message = get_transient( 'sn_cps_error_' . $post->ID );
		if ( $error_message ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $error_message ) . '</p></div>';
			delete_transient( 'sn_cps_error_' . $post->ID );
		}

		wp_nonce_field( 'sn_cps_save', 'sn_cps_nonce' );

		$custom_css     = get_post_meta( $post->ID, self::SN_CPS_META_KEY_CSS, true );
		$uploaded_files = get_post_meta( $post->ID, self::SN_CPS_META_KEY_UPLOADED, true );
		if ( ! is_array( $uploaded_files ) ) {
			$uploaded_files = array();
		}

		// Get selected Library IDs (v2.0). Backward compat: fall back to legacy _sn_cps_selected.
		$selected_library_ids = get_post_meta( $post->ID, self::SN_CPS_META_KEY_LIBRARY_IDS, true );
		if ( ! is_array( $selected_library_ids ) ) {
			$selected_library_ids = array();
		}

		$available_styles = $this->get_available_styles( $post->ID );

		$upload_dir      = wp_upload_dir();
		$post_upload_dir = trailingslashit( $upload_dir['basedir'] ) . self::SN_CPS_CSS_DIR_NAME . '/' . $post->ID;

		// Library link info for Save/Sync button
		$linked_library_id   = absint( get_post_meta( $post->ID, self::SN_CPS_META_KEY_LINKED_LIBRARY, true ) );
		$linked_library_post = $linked_library_id > 0 ? get_post( $linked_library_id ) : null;
		$is_linked           = $linked_library_post && 'publish' === get_post_status( $linked_library_id ) && self::SN_CPS_STYLE_POST_TYPE === get_post_type( $linked_library_id );
		?>
		<div class="sn-cps-meta-box">

			<!-- File Upload Section -->
			<p><strong><?php esc_html_e( 'Upload CSS/JS Files:', 'studio-noir-page-styles' ); ?></strong></p>
			<div class="sn-cps-file-upload" style="margin-bottom: 20px;">
				<input type="file" id="sn_cps_file_input" accept=".css,.js" style="display: none;">
				<button type="button" id="sn_cps_upload_btn" class="button"><?php esc_html_e( 'Choose File', 'studio-noir-page-styles' ); ?></button>
				<span id="sn_cps_file_name" style="margin-left: 10px; color: #666;"></span>
				<button type="button" id="sn_cps_add_file_btn" class="button button-primary" style="margin-left: 10px;" disabled><?php esc_html_e( 'Add File', 'studio-noir-page-styles' ); ?></button>
			</div>

			<?php if ( ! empty( $uploaded_files ) ) : ?>
				<p><strong><?php esc_html_e( 'Uploaded Files:', 'studio-noir-page-styles' ); ?></strong></p>
				<ul id="sn_cps_uploaded_list" style="list-style: none; padding: 0;">
					<?php foreach ( $uploaded_files as $index => $file_info ) : ?>
						<?php
						$file_path = trailingslashit( $post_upload_dir ) . $file_info['filename'];
						if ( ! file_exists( $file_path ) ) {
							continue;
						}
						?>
						<li class="sn-cps-file-item" style="background: #f6f7f7; padding: 10px; margin-bottom: 5px; border-left: 3px solid <?php echo 'js' === $file_info['type'] ? '#f0ad4e' : '#5bc0de'; ?>;">
							<span class="dashicons dashicons-media-<?php echo 'js' === $file_info['type'] ? 'code' : 'document'; ?>" style="color: #787c82; margin-right: 8px;"></span>
							<span class="sn-cps-file-name"><?php echo esc_html( $file_info['filename'] ); ?></span>
							<span style="margin-left: 10px; color: #666; font-size: 12px;">
								(<?php echo esc_html( strtoupper( $file_info['type'] ) );
								if ( 'js' === $file_info['type'] ) : ?> - <?php echo esc_html( ucfirst( $file_info['load_in'] ) ); endif; ?>)
							</span>
							<?php if ( 'js' === $file_info['type'] ) : ?>
								<select name="sn_cps_uploaded_files[<?php echo esc_attr( $index ); ?>][load_in]" style="margin-left: 10px;">
									<option value="header" <?php selected( $file_info['load_in'], 'header' ); ?>>Header</option>
									<option value="footer" <?php selected( $file_info['load_in'], 'footer' ); ?>>Footer</option>
								</select>
							<?php endif; ?>
							<button type="button" class="sn-cps-remove-file button-link-delete" data-index="<?php echo esc_attr( $index ); ?>" style="float: right; color: #d63638; text-decoration: none;"><?php esc_html_e( 'Remove', 'studio-noir-page-styles' ); ?></button>
							<input type="hidden" name="sn_cps_uploaded_files[<?php echo esc_attr( $index ); ?>][filename]" value="<?php echo esc_attr( $file_info['filename'] ); ?>">
							<input type="hidden" name="sn_cps_uploaded_files[<?php echo esc_attr( $index ); ?>][type]" value="<?php echo esc_attr( $file_info['type'] ); ?>">
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p class="sn-cps-no-files" style="color: #787c82; font-style: italic;"><?php esc_html_e( 'No files uploaded.', 'studio-noir-page-styles' ); ?></p>
			<?php endif; ?>
			<p class="description"><?php esc_html_e( 'Upload CSS or JavaScript files. JS files can be loaded in header or footer.', 'studio-noir-page-styles' ); ?></p>

			<hr style="margin: 20px 0;">

			<!-- Custom CSS Section -->
			<p><label for="sn_cps_css"><strong><?php esc_html_e( 'Custom CSS for this page:', 'studio-noir-page-styles' ); ?></strong></label></p>
			<p>
				<textarea id="sn_cps_css" name="sn_cps_css" rows="10" style="width: 100%; font-family: monospace;" placeholder="<?php esc_attr_e( 'Enter your custom CSS here...', 'studio-noir-page-styles' ); ?>"><?php echo esc_textarea( $custom_css ); ?></textarea>
			</p>

			<!-- Save to Library / Sync to Library -->
			<div class="sn-cps-library-actions">
				<?php if ( $is_linked ) : ?>
					<span style="color: #666; font-size: 12px;"><?php esc_html_e( 'Library:', 'studio-noir-page-styles' ); ?> <strong><?php echo esc_html( $linked_library_post->post_title ); ?></strong></span>
					<button type="button" id="sn_cps_sync_library_btn" class="button" style="margin-left: 10px;"
						data-library-id="<?php echo esc_attr( $linked_library_id ); ?>"
						data-library-name="<?php echo esc_attr( $linked_library_post->post_title ); ?>">
						<?php esc_html_e( 'Sync to Library', 'studio-noir-page-styles' ); ?>
					</button>
				<?php else : ?>
					<button type="button" id="sn_cps_save_library_btn" class="button"><?php esc_html_e( 'Save to Library', 'studio-noir-page-styles' ); ?></button>
					<span style="color: #787c82; font-size: 12px; margin-left: 8px;"><?php esc_html_e( 'Register this CSS as a reusable style.', 'studio-noir-page-styles' ); ?></span>
				<?php endif; ?>
			</div>

			<!-- Sync Dialog (hidden by default) -->
			<div id="sn_cps_sync_dialog" class="sn-cps-sync-dialog" style="display: none;">
				<p><strong><?php esc_html_e( 'Sync to Library', 'studio-noir-page-styles' ); ?></strong></p>
				<label><input type="radio" name="sn_cps_sync_mode" value="overwrite" checked> <?php esc_html_e( 'Overwrite existing style:', 'studio-noir-page-styles' ); ?> <strong id="sn_cps_sync_lib_name"></strong></label>
				<label><input type="radio" name="sn_cps_sync_mode" value="new"> <?php esc_html_e( 'Save as new style', 'studio-noir-page-styles' ); ?></label>
				<div id="sn_cps_new_name_wrap" class="sn-cps-new-name-wrap" style="display: none;">
					<input type="text" id="sn_cps_new_style_name" placeholder="<?php esc_attr_e( 'Style name', 'studio-noir-page-styles' ); ?>" style="width: 300px;">
				</div>
				<div style="margin-top: 12px;">
					<button type="button" id="sn_cps_sync_cancel_btn" class="button"><?php esc_html_e( 'Cancel', 'studio-noir-page-styles' ); ?></button>
					<button type="button" id="sn_cps_sync_confirm_btn" class="button button-primary" style="margin-left: 5px;"><?php esc_html_e( 'Sync', 'studio-noir-page-styles' ); ?></button>
				</div>
			</div>

			<hr style="margin: 20px 0;">

			<!-- Add Existing Stylesheets -->
			<p><strong><?php esc_html_e( 'Add styles from Library:', 'studio-noir-page-styles' ); ?></strong></p>
			<div class="sn-cps-add-style" style="margin-bottom: 15px;">
				<select id="sn_cps_available_styles" style="width: 70%; max-width: 400px;">
					<option value=""><?php esc_html_e( '-- Select a style to add --', 'studio-noir-page-styles' ); ?></option>
					<?php foreach ( $available_styles as $lib_id => $lib_title ) : ?>
						<?php if ( ! in_array( $lib_id, $selected_library_ids, true ) ) : ?>
							<option value="<?php echo esc_attr( $lib_id ); ?>"><?php echo esc_html( $lib_title ); ?></option>
						<?php endif; ?>
					<?php endforeach; ?>
				</select>
				<button type="button" id="sn_cps_add_style_btn" class="button" style="margin-left: 5px;"><?php esc_html_e( '+ Add', 'studio-noir-page-styles' ); ?></button>
			</div>

			<p><strong><?php esc_html_e( 'Selected styles (order = load order):', 'studio-noir-page-styles' ); ?></strong></p>
			<ul id="sn_cps_selected_list" class="sn-cps-sortable" style="list-style: none; padding: 0;">
				<?php foreach ( $selected_library_ids as $lib_id ) : ?>
					<?php if ( isset( $available_styles[ $lib_id ] ) ) : ?>
						<li class="sn-cps-style-item" data-style-id="<?php echo esc_attr( $lib_id ); ?>" style="background: #f6f7f7; padding: 10px; margin-bottom: 5px; border-left: 3px solid #2271b1; cursor: move;">
							<span class="dashicons dashicons-menu" style="color: #787c82; margin-right: 8px;"></span>
							<span class="sn-cps-style-title"><?php echo esc_html( $available_styles[ $lib_id ] ); ?></span>
							<button type="button" class="sn-cps-remove-style button-link-delete" style="float: right; color: #d63638; text-decoration: none;"><?php esc_html_e( 'Remove', 'studio-noir-page-styles' ); ?></button>
							<input type="hidden" name="sn_cps_library_ids[]" value="<?php echo esc_attr( $lib_id ); ?>">
						</li>
					<?php endif; ?>
				<?php endforeach; ?>
			</ul>
			<?php if ( empty( $selected_library_ids ) ) : ?>
				<p class="sn-cps-empty-message" style="color: #787c82; font-style: italic;"><?php esc_html_e( 'No styles selected. Add styles from the dropdown above.', 'studio-noir-page-styles' ); ?></p>
			<?php endif; ?>
			<p class="description" style="margin-top: 15px;"><?php esc_html_e( 'Select styles from the Library. Drag and drop to reorder.', 'studio-noir-page-styles' ); ?></p>

		</div>

		<style>
			.sn-cps-sortable .ui-sortable-helper { opacity: 0.6; box-shadow: 0 2px 8px rgba(0,0,0,0.15); }
			.sn-cps-sortable .ui-sortable-placeholder { background: #e5e5e5; border: 2px dashed #2271b1; visibility: visible !important; height: 50px; margin-bottom: 5px; }
			.sn-cps-style-item:hover { background: #f0f0f1; }
		</style>

		<script>
		jQuery(document).ready(function($) {
			var selectedFile = null;

			$('#sn_cps_upload_btn').on('click', function() { $('#sn_cps_file_input').click(); });

			$('#sn_cps_file_input').on('change', function(e) {
				var file = e.target.files[0];
				if (file) {
					var ext = file.name.split('.').pop().toLowerCase();
					if (ext === 'css' || ext === 'js') {
						selectedFile = file;
						$('#sn_cps_file_name').text(file.name);
						$('#sn_cps_add_file_btn').prop('disabled', false);
					} else {
						alert('<?php esc_html_e( 'Please select a CSS or JS file.', 'studio-noir-page-styles' ); ?>');
						selectedFile = null;
						$('#sn_cps_file_name').text('');
						$('#sn_cps_add_file_btn').prop('disabled', true);
					}
				}
			});

			$('#sn_cps_add_file_btn').on('click', function() {
				if (!selectedFile) return;
				var formData = new FormData();
				formData.append('action', 'sn_cps_upload_file');
				formData.append('post_id', <?php echo absint( $post->ID ); ?>);
				formData.append('file', selectedFile);
				formData.append('nonce', '<?php echo esc_js( wp_create_nonce( 'sn_cps_upload_file' ) ); ?>');
				$.ajax({
					url: ajaxurl, type: 'POST', data: formData, processData: false, contentType: false,
					success: function(response) {
						if (response.success) { location.reload(); }
						else { alert(response.data || '<?php esc_html_e( 'Upload failed.', 'studio-noir-page-styles' ); ?>'); }
					},
					error: function() { alert('<?php esc_html_e( 'Upload failed.', 'studio-noir-page-styles' ); ?>'); }
				});
			});

			$(document).on('click', '.sn-cps-remove-file', function() {
				var $item = $(this).closest('.sn-cps-file-item');
				var filename = $item.find('.sn-cps-file-name').text();
				if (!confirm('<?php esc_html_e( 'Remove this file?', 'studio-noir-page-styles' ); ?>')) return;
				$.ajax({
					url: ajaxurl, type: 'POST',
					data: { action: 'sn_cps_remove_file', post_id: <?php echo absint( $post->ID ); ?>, filename: filename, nonce: '<?php echo esc_js( wp_create_nonce( 'sn_cps_remove_file' ) ); ?>' },
					success: function(response) {
						if (response.success) { location.reload(); }
						else { alert(response.data || '<?php esc_html_e( 'Remove failed.', 'studio-noir-page-styles' ); ?>'); }
					}
				});
			});

			$('#sn_cps_selected_list').sortable({ placeholder: 'ui-sortable-placeholder', cursor: 'move', opacity: 0.6 });

			$('#sn_cps_add_style_btn').on('click', function() {
				var $select = $('#sn_cps_available_styles');
				var styleId = $select.val();
				var styleTitle = $select.find('option:selected').text();
				if (!styleId) return;
				$('.sn-cps-empty-message').hide();
				var $item = $('<li>', { 'class': 'sn-cps-style-item', 'data-style-id': styleId, 'style': 'background: #f6f7f7; padding: 10px; margin-bottom: 5px; border-left: 3px solid #2271b1; cursor: move;' });
				$item.html('<span class="dashicons dashicons-menu" style="color: #787c82; margin-right: 8px;"></span><span class="sn-cps-style-title"></span><button type="button" class="sn-cps-remove-style button-link-delete" style="float: right; color: #d63638; text-decoration: none;"><?php esc_html_e( 'Remove', 'studio-noir-page-styles' ); ?></button><input type="hidden" name="sn_cps_library_ids[]" value="">');
				$item.find('.sn-cps-style-title').text(styleTitle);
				$item.find('input').val(styleId);
				$('#sn_cps_selected_list').append($item);
				$select.find('option:selected').remove();
				$select.val('');
			});

			$(document).on('click', '.sn-cps-remove-style', function() {
				var $item = $(this).closest('.sn-cps-style-item');
				var styleId = $item.data('style-id');
				var styleTitle = $item.find('.sn-cps-style-title').text();
				$('#sn_cps_available_styles').append($('<option>', { value: styleId, text: styleTitle }));
				$item.remove();
				if ($('#sn_cps_selected_list li').length === 0) { $('.sn-cps-empty-message').show(); }
			});

			// Save to Library
			$('#sn_cps_save_library_btn').on('click', function() {
				var defaultName = $('#post_title').val() || '';
				var styleName = prompt('<?php esc_html_e( 'Enter a name for this style:', 'studio-noir-page-styles' ); ?>', defaultName);
				if (styleName === null || styleName.trim() === '') return;
				var $btn = $(this).prop('disabled', true);
				$.ajax({
					url: ajaxurl, type: 'POST',
					data: { action: 'sn_cps_save_to_library', post_id: <?php echo absint( $post->ID ); ?>, style_name: styleName.trim(), css: $('#sn_cps_css').val(), nonce: '<?php echo esc_js( wp_create_nonce( 'sn_cps_save_to_library' ) ); ?>' },
					success: function(response) {
						if (response.success) { location.reload(); }
						else { alert(response.data || '<?php esc_html_e( 'Failed to save to Library.', 'studio-noir-page-styles' ); ?>'); $btn.prop('disabled', false); }
					},
					error: function() { alert('<?php esc_html_e( 'Failed to save to Library.', 'studio-noir-page-styles' ); ?>'); $btn.prop('disabled', false); }
				});
			});

			// Sync to Library - show dialog
			$('#sn_cps_sync_library_btn').on('click', function() {
				$('#sn_cps_sync_lib_name').text($(this).data('library-name'));
				$('#sn_cps_sync_dialog').show();
			});

			$('input[name="sn_cps_sync_mode"]').on('change', function() {
				$('#sn_cps_new_name_wrap').toggle($(this).val() === 'new');
			});

			$('#sn_cps_sync_cancel_btn').on('click', function() {
				$('#sn_cps_sync_dialog').hide();
				$('input[name="sn_cps_sync_mode"][value="overwrite"]').prop('checked', true);
				$('#sn_cps_new_name_wrap').hide();
				$('#sn_cps_new_style_name').val('');
			});

			$('#sn_cps_sync_confirm_btn').on('click', function() {
				var mode = $('input[name="sn_cps_sync_mode"]:checked').val();
				var libraryId = $('#sn_cps_sync_library_btn').data('library-id');
				var styleName = mode === 'new' ? $('#sn_cps_new_style_name').val().trim() : '';
				if (mode === 'new' && !styleName) { alert('<?php esc_html_e( 'Please enter a style name.', 'studio-noir-page-styles' ); ?>'); return; }
				var $btn = $(this).prop('disabled', true);
				$.ajax({
					url: ajaxurl, type: 'POST',
					data: { action: 'sn_cps_sync_to_library', post_id: <?php echo absint( $post->ID ); ?>, library_id: libraryId, mode: mode, style_name: styleName, css: $('#sn_cps_css').val(), nonce: '<?php echo esc_js( wp_create_nonce( 'sn_cps_sync_to_library' ) ); ?>' },
					success: function(response) {
						if (response.success) { location.reload(); }
						else { alert(response.data || '<?php esc_html_e( 'Sync failed.', 'studio-noir-page-styles' ); ?>'); $btn.prop('disabled', false); }
					},
					error: function() { alert('<?php esc_html_e( 'Sync failed.', 'studio-noir-page-styles' ); ?>'); $btn.prop('disabled', false); }
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Render meta box for Library entries
	 */
	public function render_library_entry_meta_box( $post ) {
		$error_message = get_transient( 'sn_cps_error_' . $post->ID );
		if ( $error_message ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $error_message ) . '</p></div>';
			delete_transient( 'sn_cps_error_' . $post->ID );
		}

		wp_nonce_field( 'sn_cps_save', 'sn_cps_nonce' );

		$custom_css     = get_post_meta( $post->ID, self::SN_CPS_META_KEY_CSS, true );
		$uploaded_files = get_post_meta( $post->ID, self::SN_CPS_META_KEY_UPLOADED, true );
		if ( ! is_array( $uploaded_files ) ) {
			$uploaded_files = array();
		}

		$upload_dir      = wp_upload_dir();
		$post_upload_dir = trailingslashit( $upload_dir['basedir'] ) . self::SN_CPS_CSS_DIR_NAME . '/' . $post->ID;
		?>
		<div class="sn-cps-meta-box">
			<p><strong><?php esc_html_e( 'Upload CSS/JS Files:', 'studio-noir-page-styles' ); ?></strong></p>
			<div class="sn-cps-file-upload" style="margin-bottom: 20px;">
				<input type="file" id="sn_cps_file_input" accept=".css,.js" style="display: none;">
				<button type="button" id="sn_cps_upload_btn" class="button"><?php esc_html_e( 'Choose File', 'studio-noir-page-styles' ); ?></button>
				<span id="sn_cps_file_name" style="margin-left: 10px; color: #666;"></span>
				<button type="button" id="sn_cps_add_file_btn" class="button button-primary" style="margin-left: 10px;" disabled><?php esc_html_e( 'Add File', 'studio-noir-page-styles' ); ?></button>
			</div>

			<?php if ( ! empty( $uploaded_files ) ) : ?>
				<p><strong><?php esc_html_e( 'Uploaded Files:', 'studio-noir-page-styles' ); ?></strong></p>
				<ul id="sn_cps_uploaded_list" style="list-style: none; padding: 0;">
					<?php foreach ( $uploaded_files as $index => $file_info ) : ?>
						<?php
						$file_path = trailingslashit( $post_upload_dir ) . $file_info['filename'];
						if ( ! file_exists( $file_path ) ) {
							continue;
						}
						?>
						<li class="sn-cps-file-item" style="background: #f6f7f7; padding: 10px; margin-bottom: 5px; border-left: 3px solid <?php echo 'js' === $file_info['type'] ? '#f0ad4e' : '#5bc0de'; ?>;">
							<span class="sn-cps-file-name"><?php echo esc_html( $file_info['filename'] ); ?></span>
							<span style="margin-left: 10px; color: #666; font-size: 12px;">(<?php echo esc_html( strtoupper( $file_info['type'] ) ); ?>)</span>
							<?php if ( 'js' === $file_info['type'] ) : ?>
								<select name="sn_cps_uploaded_files[<?php echo esc_attr( $index ); ?>][load_in]" style="margin-left: 10px;">
									<option value="header" <?php selected( $file_info['load_in'], 'header' ); ?>>Header</option>
									<option value="footer" <?php selected( $file_info['load_in'], 'footer' ); ?>>Footer</option>
								</select>
							<?php endif; ?>
							<button type="button" class="sn-cps-remove-file button-link-delete" data-index="<?php echo esc_attr( $index ); ?>" style="float: right; color: #d63638;"><?php esc_html_e( 'Remove', 'studio-noir-page-styles' ); ?></button>
							<input type="hidden" name="sn_cps_uploaded_files[<?php echo esc_attr( $index ); ?>][filename]" value="<?php echo esc_attr( $file_info['filename'] ); ?>">
							<input type="hidden" name="sn_cps_uploaded_files[<?php echo esc_attr( $index ); ?>][type]" value="<?php echo esc_attr( $file_info['type'] ); ?>">
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p style="color: #787c82; font-style: italic;"><?php esc_html_e( 'No files uploaded.', 'studio-noir-page-styles' ); ?></p>
			<?php endif; ?>

			<hr style="margin: 20px 0;">

			<p><label for="sn_cps_css"><strong><?php esc_html_e( 'CSS for this style:', 'studio-noir-page-styles' ); ?></strong></label></p>
			<p>
				<textarea id="sn_cps_css" name="sn_cps_css" rows="15" style="width: 100%; font-family: monospace;" placeholder="<?php esc_attr_e( 'Enter your CSS here...', 'studio-noir-page-styles' ); ?>"><?php echo esc_textarea( $custom_css ); ?></textarea>
			</p>
		</div>
		<script>
		jQuery(document).ready(function($) {
			var selectedFile = null;
			$('#sn_cps_upload_btn').on('click', function() { $('#sn_cps_file_input').click(); });
			$('#sn_cps_file_input').on('change', function(e) {
				var file = e.target.files[0];
				if (file) {
					var ext = file.name.split('.').pop().toLowerCase();
					if (ext === 'css' || ext === 'js') {
						selectedFile = file;
						$('#sn_cps_file_name').text(file.name);
						$('#sn_cps_add_file_btn').prop('disabled', false);
					} else {
						alert('<?php esc_html_e( 'Please select a CSS or JS file.', 'studio-noir-page-styles' ); ?>');
						selectedFile = null; $('#sn_cps_file_name').text(''); $('#sn_cps_add_file_btn').prop('disabled', true);
					}
				}
			});
			$('#sn_cps_add_file_btn').on('click', function() {
				if (!selectedFile) return;
				var formData = new FormData();
				formData.append('action', 'sn_cps_upload_file');
				formData.append('post_id', <?php echo absint( $post->ID ); ?>);
				formData.append('file', selectedFile);
				formData.append('nonce', '<?php echo esc_js( wp_create_nonce( 'sn_cps_upload_file' ) ); ?>');
				$.ajax({ url: ajaxurl, type: 'POST', data: formData, processData: false, contentType: false,
					success: function(r) { if (r.success) location.reload(); else alert(r.data || '<?php esc_html_e( 'Upload failed.', 'studio-noir-page-styles' ); ?>'); },
					error: function() { alert('<?php esc_html_e( 'Upload failed.', 'studio-noir-page-styles' ); ?>'); }
				});
			});
			$(document).on('click', '.sn-cps-remove-file', function() {
				var filename = $(this).closest('.sn-cps-file-item').find('.sn-cps-file-name').text();
				if (!confirm('<?php esc_html_e( 'Remove this file?', 'studio-noir-page-styles' ); ?>')) return;
				$.ajax({ url: ajaxurl, type: 'POST',
					data: { action: 'sn_cps_remove_file', post_id: <?php echo absint( $post->ID ); ?>, filename: filename, nonce: '<?php echo esc_js( wp_create_nonce( 'sn_cps_remove_file' ) ); ?>' },
					success: function(r) { if (r.success) location.reload(); else alert(r.data || '<?php esc_html_e( 'Remove failed.', 'studio-noir-page-styles' ); ?>'); }
				});
			});
		});
		</script>
		<?php
	}

	// =========================================================================
	// DATA LAYER
	// =========================================================================

	/**
	 * Get available styles from the Library (v2.0)
	 *
	 * @param int $current_post_id Unused — kept for API compatibility.
	 * @return array Array of library_entry_id => style_name
	 */
	private function get_available_styles( $current_post_id ) {
		$styles  = array();
		$entries = get_posts(
			array(
				'post_type'      => self::SN_CPS_STYLE_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		foreach ( $entries as $entry ) {
			$css = get_post_meta( $entry->ID, self::SN_CPS_META_KEY_CSS, true );
			if ( ! empty( $css ) ) {
				$styles[ $entry->ID ] = $entry->post_title;
			}
		}

		return $styles;
	}

	/**
	 * Save meta box data
	 */
	public function save_meta_box( $post_id, $post ) {
		if ( ! isset( $_POST['sn_cps_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sn_cps_nonce'] ) ), 'sn_cps_save' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Handle Library entry saving
		if ( self::SN_CPS_STYLE_POST_TYPE === $post->post_type ) {
			$this->save_library_entry_meta( $post_id );
			return;
		}

		// Check if this post type is enabled
		$enabled_post_types = get_option( self::SN_CPS_OPTION_ENABLED_POST_TYPES, array( 'post', 'page' ) );
		if ( ! in_array( $post->post_type, $enabled_post_types, true ) ) {
			return;
		}

		// Save custom CSS
		if ( isset( $_POST['sn_cps_css'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$sanitized_css = $this->sanitize_css( wp_unslash( $_POST['sn_cps_css'] ) );

			if ( is_wp_error( $sanitized_css ) ) {
				set_transient( 'sn_cps_error_' . $post_id, $sanitized_css->get_error_message(), 45 );
				return;
			}

			if ( ! empty( $sanitized_css ) ) {
				update_post_meta( $post_id, self::SN_CPS_META_KEY_CSS, $sanitized_css );
				$this->generate_css_file( $post_id, $sanitized_css );
			} else {
				delete_post_meta( $post_id, self::SN_CPS_META_KEY_CSS );
				$this->delete_css_file( $post_id );
			}
		}

		// Save selected Library IDs (v2.0)
		if ( isset( $_POST['sn_cps_library_ids'] ) && is_array( $_POST['sn_cps_library_ids'] ) ) {
			$library_ids = array();
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			foreach ( $_POST['sn_cps_library_ids'] as $lib_id ) {
				$lib_id    = absint( $lib_id );
				$lib_post  = $lib_id > 0 ? get_post( $lib_id ) : null;
				if ( $lib_post && self::SN_CPS_STYLE_POST_TYPE === $lib_post->post_type && 'publish' === $lib_post->post_status ) {
					$library_ids[] = $lib_id;
				}
			}
			if ( ! empty( $library_ids ) ) {
				update_post_meta( $post_id, self::SN_CPS_META_KEY_LIBRARY_IDS, $library_ids );
			} else {
				delete_post_meta( $post_id, self::SN_CPS_META_KEY_LIBRARY_IDS );
			}
		} else {
			delete_post_meta( $post_id, self::SN_CPS_META_KEY_LIBRARY_IDS );
		}

		// Save uploaded files settings
		if ( isset( $_POST['sn_cps_uploaded_files'] ) && is_array( $_POST['sn_cps_uploaded_files'] ) ) {
			$uploaded_files = array();
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			foreach ( $_POST['sn_cps_uploaded_files'] as $file_data ) {
				if ( isset( $file_data['filename'], $file_data['type'] ) ) {
					$uploaded_files[] = array(
						'filename' => sanitize_file_name( wp_unslash( $file_data['filename'] ) ),
						'type'     => in_array( $file_data['type'], array( 'css', 'js' ), true ) ? $file_data['type'] : 'css',
						'load_in'  => isset( $file_data['load_in'] ) && 'header' === $file_data['load_in'] ? 'header' : 'footer',
					);
				}
			}
			if ( ! empty( $uploaded_files ) ) {
				update_post_meta( $post_id, self::SN_CPS_META_KEY_UPLOADED, $uploaded_files );
			}
		}
	}

	/**
	 * Save CSS and uploaded files for a Library entry
	 */
	private function save_library_entry_meta( $post_id ) {
		if ( isset( $_POST['sn_cps_css'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$sanitized_css = $this->sanitize_css( wp_unslash( $_POST['sn_cps_css'] ) );

			if ( is_wp_error( $sanitized_css ) ) {
				set_transient( 'sn_cps_error_' . $post_id, $sanitized_css->get_error_message(), 45 );
				return;
			}

			if ( ! empty( $sanitized_css ) ) {
				update_post_meta( $post_id, self::SN_CPS_META_KEY_CSS, $sanitized_css );
				$this->generate_css_file( $post_id, $sanitized_css );
			} else {
				delete_post_meta( $post_id, self::SN_CPS_META_KEY_CSS );
				$this->delete_css_file( $post_id );
			}
		}

		if ( isset( $_POST['sn_cps_uploaded_files'] ) && is_array( $_POST['sn_cps_uploaded_files'] ) ) {
			$uploaded_files = array();
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			foreach ( $_POST['sn_cps_uploaded_files'] as $file_data ) {
				if ( isset( $file_data['filename'], $file_data['type'] ) ) {
					$uploaded_files[] = array(
						'filename' => sanitize_file_name( wp_unslash( $file_data['filename'] ) ),
						'type'     => in_array( $file_data['type'], array( 'css', 'js' ), true ) ? $file_data['type'] : 'css',
						'load_in'  => isset( $file_data['load_in'] ) && 'header' === $file_data['load_in'] ? 'header' : 'footer',
					);
				}
			}
			if ( ! empty( $uploaded_files ) ) {
				update_post_meta( $post_id, self::SN_CPS_META_KEY_UPLOADED, $uploaded_files );
			}
		}
	}

	// =========================================================================
	// CSS SANITIZATION & FILE OPERATIONS
	// =========================================================================

	private function sanitize_css( $css ) {
		if ( empty( $css ) ) {
			return '';
		}
		$css = wp_strip_all_tags( $css );
		$css = stripslashes( $css );

		if ( strlen( $css ) > 1048576 ) {
			return new WP_Error( 'css_too_large', __( 'CSS code is too large. Maximum size is 1MB.', 'studio-noir-page-styles' ) );
		}

		$dangerous_patterns = array(
			'/@\s*import/i', '/javascript\s*:/i', '/expression\s*\(/i', '/behavior\s*:/i',
			'/-moz-binding/i', '/data\s*:\s*text\s*\/\s*html/i', '/vbscript\s*:/i',
			'/<script/i', '/onclick/i', '/onerror/i', '/onload/i', '/onmouseover/i', '/onfocus/i',
		);
		foreach ( $dangerous_patterns as $pattern ) {
			if ( preg_match( $pattern, $css ) ) {
				return new WP_Error( 'dangerous_css', __( 'CSS contains potentially dangerous code.', 'studio-noir-page-styles' ) );
			}
		}

		$open_braces  = substr_count( $css, '{' );
		$close_braces = substr_count( $css, '}' );
		if ( $open_braces !== $close_braces ) {
			return new WP_Error( 'invalid_css', __( 'CSS validation error: Unbalanced braces detected.', 'studio-noir-page-styles' ) );
		}

		return trim( $css );
	}

	private function generate_css_file( $post_id, $css ) {
		if ( empty( $css ) ) {
			return false;
		}
		$post_id = absint( $post_id );
		if ( $post_id <= 0 ) {
			return false;
		}

		$upload_dir = wp_upload_dir();
		$css_dir    = trailingslashit( $upload_dir['basedir'] ) . self::SN_CPS_CSS_DIR_NAME;

		if ( ! $this->create_css_directory() ) {
			add_settings_error( 'sn_cps_messages', 'css_dir_error', __( 'Failed to create CSS directory.', 'studio-noir-page-styles' ), 'error' );
			return false;
		}

		$filename     = 'post-styles-' . $post_id . '.css';
		$css_file     = trailingslashit( $css_dir ) . $filename;
		$css_dir_real = realpath( $css_dir );

		if ( ! $css_dir_real ) {
			add_settings_error( 'sn_cps_messages', 'invalid_directory', __( 'CSS directory does not exist.', 'studio-noir-page-styles' ), 'error' );
			return false;
		}

		$css_file_dir      = dirname( $css_file );
		$css_file_dir_real = realpath( $css_file_dir );
		if ( ! $css_file_dir_real || strpos( $css_file_dir_real, $css_dir_real ) !== 0 ) {
			add_settings_error( 'sn_cps_messages', 'path_traversal_error', __( 'Invalid file path detected.', 'studio-noir-page-styles' ), 'error' );
			return false;
		}

		$filesystem = $this->get_filesystem();
		if ( ! $filesystem ) {
			add_settings_error( 'sn_cps_messages', 'filesystem_error', __( 'Failed to initialize filesystem.', 'studio-noir-page-styles' ), 'error' );
			return false;
		}

		$css_content = sprintf( "/**\n * Custom Page Styles for Post ID: %d\n * Generated: %s\n */\n\n%s", $post_id, current_time( 'mysql' ), $css );

		if ( ! $filesystem->put_contents( $css_file, $css_content, FS_CHMOD_FILE ) ) {
			add_settings_error( 'sn_cps_messages', 'css_write_error', __( 'Failed to write CSS file.', 'studio-noir-page-styles' ), 'error' );
			return false;
		}

		return true;
	}

	private function delete_css_file( $post_id ) {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 ) {
			return false;
		}

		$upload_dir = wp_upload_dir();
		$css_dir    = trailingslashit( $upload_dir['basedir'] ) . self::SN_CPS_CSS_DIR_NAME;
		$css_file   = trailingslashit( $css_dir ) . 'post-styles-' . $post_id . '.css';

		if ( file_exists( $css_file ) ) {
			$real_css_dir  = realpath( $css_dir );
			$real_css_file = realpath( $css_file );

			if ( false === $real_css_dir || false === $real_css_file || strpos( $real_css_file, $real_css_dir ) !== 0 ) {
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
	 * Delete an upload directory for a post or Library entry
	 */
	private function delete_upload_directory( $entry_id ) {
		$entry_id = absint( $entry_id );
		if ( $entry_id <= 0 ) {
			return false;
		}

		$upload_dir = wp_upload_dir();
		$base_dir   = trailingslashit( $upload_dir['basedir'] ) . self::SN_CPS_CSS_DIR_NAME;
		$dir        = $base_dir . '/' . $entry_id;

		if ( ! is_dir( $dir ) ) {
			return true;
		}

		$real_base = realpath( $base_dir );
		$real_dir  = realpath( $dir );

		if ( false === $real_base || false === $real_dir || strpos( $real_dir, $real_base ) !== 0 ) {
			return false;
		}

		$filesystem = $this->get_filesystem();
		if ( ! $filesystem ) {
			return false;
		}

		return $filesystem->delete( $dir, true );
	}

	/**
	 * Copy uploaded files from one post/entry to another
	 */
	private function copy_uploaded_files( $from_id, $to_id ) {
		$from_id = absint( $from_id );
		$to_id   = absint( $to_id );
		if ( $from_id <= 0 || $to_id <= 0 ) {
			return;
		}

		$upload_dir = wp_upload_dir();
		$base_dir   = trailingslashit( $upload_dir['basedir'] ) . self::SN_CPS_CSS_DIR_NAME;
		$from_dir   = $base_dir . '/' . $from_id;
		$to_dir     = $base_dir . '/' . $to_id;

		if ( ! is_dir( $from_dir ) ) {
			return;
		}

		$real_base = realpath( $base_dir );
		$real_from = realpath( $from_dir );
		if ( ! $real_base || ! $real_from || strpos( $real_from, $real_base ) !== 0 ) {
			return;
		}

		if ( ! file_exists( $to_dir ) ) {
			wp_mkdir_p( $to_dir );
		}

		$filesystem = $this->get_filesystem();
		if ( ! $filesystem ) {
			return;
		}

		$files = $filesystem->dirlist( $from_dir );
		if ( is_array( $files ) ) {
			foreach ( $files as $file => $info ) {
				if ( 'f' === $info['type'] ) {
					$filesystem->copy( trailingslashit( $from_dir ) . $file, trailingslashit( $to_dir ) . $file );
				}
			}
		}
	}

	// =========================================================================
	// DELETION & TRASH HOOKS
	// =========================================================================

	/**
	 * Clean up files when a post or Library entry is permanently deleted.
	 * Fires on before_delete_post (permanent deletion only, not trash).
	 */
	public function cleanup_on_delete( $post_id ) {
		$post_type = get_post_type( $post_id );

		if ( self::SN_CPS_STYLE_POST_TYPE === $post_type ) {
			// Library entry: delete CSS file and upload directory
			$this->delete_css_file( $post_id );
			$this->delete_upload_directory( $post_id );

			// Remove this Library ID from all pages that reference it
			$this->remove_library_id_from_pages( $post_id );

			// Remove linked_library_id from pages that point to this entry
			$linked_pages = get_posts(
				array(
					'post_type'      => 'any',
					'post_status'    => 'any',
					'meta_key'       => self::SN_CPS_META_KEY_LINKED_LIBRARY,
					'meta_value'     => $post_id,
					'posts_per_page' => -1,
					'fields'         => 'ids',
				)
			);
			foreach ( $linked_pages as $page_id ) {
				delete_post_meta( $page_id, self::SN_CPS_META_KEY_LINKED_LIBRARY );
			}
			return;
		}

		// Regular page/post
		$enabled_post_types = get_option( self::SN_CPS_OPTION_ENABLED_POST_TYPES, array( 'post', 'page' ) );
		if ( ! in_array( $post_type, $enabled_post_types, true ) ) {
			return;
		}

		// Delete page-specific CSS file and upload directory
		// Library entries linked to this page are NOT deleted (they're independent entities)
		$this->delete_css_file( $post_id );
		$this->delete_upload_directory( $post_id );
	}

	/**
	 * Remove a Library entry ID from all pages' _sn_cps_library_ids arrays.
	 */
	private function remove_library_id_from_pages( $library_id ) {
		$library_id = absint( $library_id );
		if ( $library_id <= 0 ) {
			return;
		}

		global $wpdb;

		$post_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s",
				self::SN_CPS_META_KEY_LIBRARY_IDS
			)
		);

		foreach ( $post_ids as $page_id ) {
			$page_id     = absint( $page_id );
			$library_ids = get_post_meta( $page_id, self::SN_CPS_META_KEY_LIBRARY_IDS, true );
			if ( ! is_array( $library_ids ) ) {
				continue;
			}
			$key = array_search( $library_id, $library_ids, true );
			if ( false !== $key ) {
				unset( $library_ids[ $key ] );
				$library_ids = array_values( $library_ids );
				if ( empty( $library_ids ) ) {
					delete_post_meta( $page_id, self::SN_CPS_META_KEY_LIBRARY_IDS );
				} else {
					update_post_meta( $page_id, self::SN_CPS_META_KEY_LIBRARY_IDS, $library_ids );
				}
			}
		}
	}

	/**
	 * Warn when a page with unregistered CSS is moved to trash.
	 */
	public function notify_trash_unregistered_css( $post_id ) {
		$post_type          = get_post_type( $post_id );
		$enabled_post_types = get_option( self::SN_CPS_OPTION_ENABLED_POST_TYPES, array( 'post', 'page' ) );

		if ( ! in_array( $post_type, $enabled_post_types, true ) ) {
			return;
		}

		$css = get_post_meta( $post_id, self::SN_CPS_META_KEY_CSS, true );
		if ( empty( $css ) ) {
			return;
		}

		$linked_library_id = absint( get_post_meta( $post_id, self::SN_CPS_META_KEY_LINKED_LIBRARY, true ) );
		if ( $linked_library_id > 0 ) {
			return; // Already in Library
		}

		$post = get_post( $post_id );
		set_transient(
			'sn_cps_trash_warning_' . get_current_user_id(),
			array( 'post_title' => $post ? $post->post_title : '' ),
			60
		);
	}

	// =========================================================================
	// FRONTEND ENQUEUE
	// =========================================================================

	public function enqueue_custom_styles() {
		if ( ! is_singular() ) {
			return;
		}
		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return;
		}

		$upload_dir = wp_upload_dir();
		$css_dir    = trailingslashit( $upload_dir['basedir'] ) . self::SN_CPS_CSS_DIR_NAME;
		$css_url    = trailingslashit( $upload_dir['baseurl'] ) . self::SN_CPS_CSS_DIR_NAME;

		// 1. Library entries (v2.0). Backward compat: fall back to legacy _sn_cps_selected.
		$library_ids = get_post_meta( $post_id, self::SN_CPS_META_KEY_LIBRARY_IDS, true );
		if ( ! is_array( $library_ids ) || empty( $library_ids ) ) {
			$old_selected = get_post_meta( $post_id, self::SN_CPS_META_KEY_SELECTED, true );
			if ( ! empty( $old_selected ) ) {
				$library_ids = is_array( $old_selected ) ? $old_selected : array( $old_selected );
			}
		}

		if ( ! empty( $library_ids ) ) {
			foreach ( $library_ids as $index => $lib_id ) {
				$lib_id = absint( $lib_id );
				if ( $lib_id > 0 ) {
					$this->enqueue_post_style( $lib_id, $css_dir, $css_url, 'sn-cps-lib-' . $index . '-' );
					$this->enqueue_uploaded_files_for_post( $lib_id, $css_dir, $css_url );
				}
			}
		}

		// 2. Current page's own uploaded files
		$this->enqueue_uploaded_files_for_post( $post_id, $css_dir, $css_url );

		// 3. Current page's own CSS (last, for overrides)
		$this->enqueue_post_style( $post_id, $css_dir, $css_url, 'sn-cps-' );
	}

	private function enqueue_post_style( $post_id, $css_dir, $css_url, $handle_prefix ) {
		$post_id  = absint( $post_id );
		if ( $post_id <= 0 ) {
			return;
		}
		$filename = 'post-styles-' . $post_id . '.css';
		$css_file = trailingslashit( $css_dir ) . $filename;

		if ( ! file_exists( $css_file ) ) {
			return;
		}

		$real_css_dir  = realpath( $css_dir );
		$real_css_file = realpath( $css_file );
		if ( false === $real_css_dir || false === $real_css_file || strpos( $real_css_file, $real_css_dir ) !== 0 ) {
			return;
		}

		wp_enqueue_style( $handle_prefix . $post_id, trailingslashit( $css_url ) . $filename, array(), filemtime( $css_file ) );
	}

	/**
	 * Enqueue uploaded files for a given post ID or Library entry ID.
	 */
	private function enqueue_uploaded_files_for_post( $entry_id, $css_dir, $css_url ) {
		$entry_id       = absint( $entry_id );
		$uploaded_files = get_post_meta( $entry_id, self::SN_CPS_META_KEY_UPLOADED, true );
		if ( ! is_array( $uploaded_files ) || empty( $uploaded_files ) ) {
			return;
		}

		$entry_upload_dir = trailingslashit( $css_dir ) . $entry_id;
		$entry_upload_url = trailingslashit( $css_url ) . $entry_id;

		foreach ( $uploaded_files as $index => $file_info ) {
			$safe_filename = sanitize_file_name( $file_info['filename'] );
			$file_path     = trailingslashit( $entry_upload_dir ) . $safe_filename;
			$file_url      = trailingslashit( $entry_upload_url ) . $safe_filename;

			if ( ! file_exists( $file_path ) ) {
				continue;
			}

			$handle = 'sn-cps-uploaded-' . $entry_id . '-' . $index;

			if ( 'css' === $file_info['type'] ) {
				wp_enqueue_style( $handle, $file_url, array(), filemtime( $file_path ) );
			} elseif ( 'js' === $file_info['type'] ) {
				$in_footer = isset( $file_info['load_in'] ) && 'footer' === $file_info['load_in'];
				wp_enqueue_script( $handle, $file_url, array(), filemtime( $file_path ), $in_footer );
			}
		}
	}

	// =========================================================================
	// AJAX: FILE UPLOAD / REMOVE
	// =========================================================================

	public function ajax_upload_file() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'sn_cps_upload_file' ) ) {
			wp_send_json_error( __( 'Invalid nonce', 'studio-noir-page-styles' ) );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( $post_id <= 0 ) {
			wp_send_json_error( __( 'Invalid post ID', 'studio-noir-page-styles' ) );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( __( 'Permission denied', 'studio-noir-page-styles' ) );
		}
		if ( ! isset( $_FILES['file'] ) ) {
			wp_send_json_error( __( 'No file uploaded', 'studio-noir-page-styles' ) );
		}

		$file     = $_FILES['file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$filename = sanitize_file_name( $file['name'] );
		$ext      = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

		if ( ! in_array( $ext, array( 'css', 'js' ), true ) ) {
			wp_send_json_error( __( 'Only CSS and JS files are allowed', 'studio-noir-page-styles' ) );
		}

		$finfo     = finfo_open( FILEINFO_MIME_TYPE );
		$mime_type = finfo_file( $finfo, $file['tmp_name'] );
		finfo_close( $finfo );

		$allowed_mimes = array( 'text/css', 'text/plain', 'text/javascript', 'application/javascript', 'application/x-javascript' );
		if ( ! in_array( $mime_type, $allowed_mimes, true ) ) {
			wp_send_json_error( __( 'Invalid file content type', 'studio-noir-page-styles' ) );
		}

		if ( $file['size'] > 5242880 ) {
			wp_send_json_error( __( 'File size must be less than 5MB', 'studio-noir-page-styles' ) );
		}

		$upload_dir      = wp_upload_dir();
		$post_upload_dir = trailingslashit( $upload_dir['basedir'] ) . self::SN_CPS_CSS_DIR_NAME . '/' . $post_id;

		if ( ! file_exists( $post_upload_dir ) ) {
			if ( ! wp_mkdir_p( $post_upload_dir ) ) {
				wp_send_json_error( __( 'Failed to create upload directory', 'studio-noir-page-styles' ) );
			}
		}

		$target_file = trailingslashit( $post_upload_dir ) . $filename;
		if ( file_exists( $target_file ) ) {
			$file_info = pathinfo( $filename );
			$counter   = 1;
			do {
				$new_filename = $file_info['filename'] . '-' . $counter . '.' . $file_info['extension'];
				$target_file  = trailingslashit( $post_upload_dir ) . $new_filename;
				$counter++;
			} while ( file_exists( $target_file ) && $counter < 100 );

			if ( $counter >= 100 ) {
				wp_send_json_error( __( 'Too many files with the same name', 'studio-noir-page-styles' ) );
			}
			$filename = $new_filename;
		}

		if ( ! move_uploaded_file( $file['tmp_name'], $target_file ) ) {
			wp_send_json_error( __( 'Failed to save file', 'studio-noir-page-styles' ) );
		}

		$uploaded_files   = get_post_meta( $post_id, self::SN_CPS_META_KEY_UPLOADED, true );
		$uploaded_files   = is_array( $uploaded_files ) ? $uploaded_files : array();
		$uploaded_files[] = array( 'filename' => $filename, 'type' => $ext, 'load_in' => 'js' === $ext ? 'footer' : 'header' );
		update_post_meta( $post_id, self::SN_CPS_META_KEY_UPLOADED, $uploaded_files );

		wp_send_json_success( __( 'File uploaded successfully', 'studio-noir-page-styles' ) );
	}

	public function ajax_remove_file() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'sn_cps_remove_file' ) ) {
			wp_send_json_error( __( 'Invalid nonce', 'studio-noir-page-styles' ) );
		}

		$post_id  = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$filename = isset( $_POST['filename'] ) ? sanitize_file_name( wp_unslash( $_POST['filename'] ) ) : '';

		if ( $post_id <= 0 ) {
			wp_send_json_error( __( 'Invalid post ID', 'studio-noir-page-styles' ) );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( __( 'Permission denied', 'studio-noir-page-styles' ) );
		}
		if ( empty( $filename ) ) {
			wp_send_json_error( __( 'Invalid filename', 'studio-noir-page-styles' ) );
		}

		$upload_dir      = wp_upload_dir();
		$post_upload_dir = trailingslashit( $upload_dir['basedir'] ) . self::SN_CPS_CSS_DIR_NAME . '/' . $post_id;
		$file_path       = trailingslashit( $post_upload_dir ) . $filename;

		if ( file_exists( $file_path ) ) {
			$real_upload_dir = realpath( $post_upload_dir );
			$real_file_path  = realpath( $file_path );
			if ( false === $real_upload_dir || false === $real_file_path || strpos( $real_file_path, $real_upload_dir ) !== 0 ) {
				wp_send_json_error( __( 'Invalid file path detected', 'studio-noir-page-styles' ) );
				return;
			}
			$filesystem = $this->get_filesystem();
			if ( $filesystem ) {
				$filesystem->delete( $file_path );
			}
		}

		$uploaded_files = get_post_meta( $post_id, self::SN_CPS_META_KEY_UPLOADED, true );
		if ( is_array( $uploaded_files ) ) {
			$uploaded_files = array_values( array_filter( $uploaded_files, function( $f ) use ( $filename ) {
				return $f['filename'] !== $filename;
			} ) );
			if ( empty( $uploaded_files ) ) {
				delete_post_meta( $post_id, self::SN_CPS_META_KEY_UPLOADED );
			} else {
				update_post_meta( $post_id, self::SN_CPS_META_KEY_UPLOADED, $uploaded_files );
			}
		}

		wp_send_json_success( __( 'File removed successfully', 'studio-noir-page-styles' ) );
	}

	// =========================================================================
	// AJAX: SAVE / SYNC TO LIBRARY
	// =========================================================================

	public function ajax_save_to_library() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'sn_cps_save_to_library' ) ) {
			wp_send_json_error( __( 'Invalid nonce', 'studio-noir-page-styles' ) );
		}

		$post_id    = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$style_name = isset( $_POST['style_name'] ) ? sanitize_text_field( wp_unslash( $_POST['style_name'] ) ) : '';

		if ( $post_id <= 0 ) {
			wp_send_json_error( __( 'Invalid post ID', 'studio-noir-page-styles' ) );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( __( 'Permission denied', 'studio-noir-page-styles' ) );
		}
		if ( empty( $style_name ) ) {
			wp_send_json_error( __( 'Style name is required', 'studio-noir-page-styles' ) );
		}

		$library_entry_id = wp_insert_post(
			array(
				'post_type'   => self::SN_CPS_STYLE_POST_TYPE,
				'post_title'  => $style_name,
				'post_status' => 'publish',
			)
		);

		if ( is_wp_error( $library_entry_id ) ) {
			wp_send_json_error( __( 'Failed to create Library entry', 'studio-noir-page-styles' ) );
		}

		// Use CSS from the AJAX request (current textarea value) if provided; fall back to post meta.
		if ( isset( $_POST['css'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$css = $this->sanitize_css( wp_unslash( $_POST['css'] ) );
			if ( is_wp_error( $css ) ) {
				wp_send_json_error( $css->get_error_message() );
			}
		} else {
			$css = get_post_meta( $post_id, self::SN_CPS_META_KEY_CSS, true );
		}

		if ( ! empty( $css ) ) {
			// Save to the post itself so the frontend also reflects the current CSS.
			update_post_meta( $post_id, self::SN_CPS_META_KEY_CSS, $css );
			$this->generate_css_file( $post_id, $css );
			// Save to the Library entry.
			update_post_meta( $library_entry_id, self::SN_CPS_META_KEY_CSS, $css );
			$this->generate_css_file( $library_entry_id, $css );
		}

		$uploaded_files = get_post_meta( $post_id, self::SN_CPS_META_KEY_UPLOADED, true );
		if ( is_array( $uploaded_files ) && ! empty( $uploaded_files ) ) {
			update_post_meta( $library_entry_id, self::SN_CPS_META_KEY_UPLOADED, $uploaded_files );
			$this->copy_uploaded_files( $post_id, $library_entry_id );
		}

		update_post_meta( $post_id, self::SN_CPS_META_KEY_LINKED_LIBRARY, $library_entry_id );

		wp_send_json_success(
			array(
				'library_id'   => $library_entry_id,
				'library_name' => $style_name,
				'message'      => __( 'Saved to Library successfully', 'studio-noir-page-styles' ),
			)
		);
	}

	public function ajax_sync_to_library() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'sn_cps_sync_to_library' ) ) {
			wp_send_json_error( __( 'Invalid nonce', 'studio-noir-page-styles' ) );
		}

		$post_id    = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$library_id = isset( $_POST['library_id'] ) ? absint( $_POST['library_id'] ) : 0;
		$mode       = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : '';

		if ( $post_id <= 0 ) {
			wp_send_json_error( __( 'Invalid post ID', 'studio-noir-page-styles' ) );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( __( 'Permission denied', 'studio-noir-page-styles' ) );
		}
		if ( ! in_array( $mode, array( 'overwrite', 'new' ), true ) ) {
			wp_send_json_error( __( 'Invalid mode', 'studio-noir-page-styles' ) );
		}

		// Use CSS from the AJAX request (current textarea value) if provided; fall back to post meta.
		if ( isset( $_POST['css'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$css = $this->sanitize_css( wp_unslash( $_POST['css'] ) );
			if ( is_wp_error( $css ) ) {
				wp_send_json_error( $css->get_error_message() );
			}
		} else {
			$css = get_post_meta( $post_id, self::SN_CPS_META_KEY_CSS, true );
		}

		$uploaded_files = get_post_meta( $post_id, self::SN_CPS_META_KEY_UPLOADED, true );

		// Save the current CSS to the post itself so the frontend reflects it immediately.
		if ( ! empty( $css ) ) {
			update_post_meta( $post_id, self::SN_CPS_META_KEY_CSS, $css );
			$this->generate_css_file( $post_id, $css );
		}

		if ( 'overwrite' === $mode ) {
			$library_post = get_post( $library_id );
			if ( ! $library_post || self::SN_CPS_STYLE_POST_TYPE !== $library_post->post_type ) {
				wp_send_json_error( __( 'Library entry not found', 'studio-noir-page-styles' ) );
			}
			if ( ! current_user_can( 'edit_post', $library_id ) ) {
				wp_send_json_error( __( 'Permission denied', 'studio-noir-page-styles' ) );
			}

			if ( ! empty( $css ) ) {
				update_post_meta( $library_id, self::SN_CPS_META_KEY_CSS, $css );
				$this->generate_css_file( $library_id, $css );
			} else {
				delete_post_meta( $library_id, self::SN_CPS_META_KEY_CSS );
				$this->delete_css_file( $library_id );
			}

			if ( is_array( $uploaded_files ) && ! empty( $uploaded_files ) ) {
				update_post_meta( $library_id, self::SN_CPS_META_KEY_UPLOADED, $uploaded_files );
				$this->copy_uploaded_files( $post_id, $library_id );
			}

			wp_send_json_success( array( 'library_id' => $library_id, 'message' => __( 'Library entry updated successfully', 'studio-noir-page-styles' ) ) );

		} else { // new
			$style_name = isset( $_POST['style_name'] ) ? sanitize_text_field( wp_unslash( $_POST['style_name'] ) ) : '';
			if ( empty( $style_name ) ) {
				wp_send_json_error( __( 'Style name is required', 'studio-noir-page-styles' ) );
			}

			$new_id = wp_insert_post( array( 'post_type' => self::SN_CPS_STYLE_POST_TYPE, 'post_title' => $style_name, 'post_status' => 'publish' ) );
			if ( is_wp_error( $new_id ) ) {
				wp_send_json_error( __( 'Failed to create Library entry', 'studio-noir-page-styles' ) );
			}

			if ( ! empty( $css ) ) {
				update_post_meta( $new_id, self::SN_CPS_META_KEY_CSS, $css );
				$this->generate_css_file( $new_id, $css );
			}
			if ( is_array( $uploaded_files ) && ! empty( $uploaded_files ) ) {
				update_post_meta( $new_id, self::SN_CPS_META_KEY_UPLOADED, $uploaded_files );
				$this->copy_uploaded_files( $post_id, $new_id );
			}

			update_post_meta( $post_id, self::SN_CPS_META_KEY_LINKED_LIBRARY, $new_id );
			wp_send_json_success( array( 'library_id' => $new_id, 'library_name' => $style_name, 'message' => __( 'Saved as new Library entry', 'studio-noir-page-styles' ) ) );
		}
	}

	// =========================================================================
	// MIGRATION (v1.x → v2.0)
	// =========================================================================

	public function maybe_run_migration() {
		$current_db_version = get_option( self::SN_CPS_OPTION_DB_VERSION, '1.0' );
		if ( version_compare( $current_db_version, self::SN_CPS_DB_VERSION, '<' ) ) {
			$this->run_migration_v2();
		}
	}

	public function run_migration_v2() {
		$errors        = array();
		$migration_map = array();

		$enabled_post_types = get_option( self::SN_CPS_OPTION_ENABLED_POST_TYPES, array( 'post', 'page' ) );
		$source_post_types  = array_diff( $enabled_post_types, array( self::SN_CPS_STYLE_POST_TYPE ) );

		if ( empty( $source_post_types ) ) {
			update_option( self::SN_CPS_OPTION_DB_VERSION, self::SN_CPS_DB_VERSION );
			return;
		}

		// Step 2-3: Migrate posts with CSS
		$posts_with_css = get_posts(
			array(
				'post_type'      => $source_post_types,
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'meta_key'       => self::SN_CPS_META_KEY_CSS,
				'posts_per_page' => -1,
			)
		);

		foreach ( $posts_with_css as $post ) {
			// Skip if already migrated
			$existing_link = get_post_meta( $post->ID, self::SN_CPS_META_KEY_LINKED_LIBRARY, true );
			if ( ! empty( $existing_link ) ) {
				$migration_map[ $post->ID ] = absint( $existing_link );
				continue;
			}

			$this->migrate_single_post( $post, $migration_map, $errors );
		}

		// Step 4-5: Update _sn_cps_selected references to Library IDs
		$pages_with_selected = get_posts(
			array(
				'post_type'      => $source_post_types,
				'post_status'    => 'any',
				'meta_key'       => self::SN_CPS_META_KEY_SELECTED,
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ( $pages_with_selected as $page_id ) {
			$old_selected = get_post_meta( $page_id, self::SN_CPS_META_KEY_SELECTED, true );
			if ( ! is_array( $old_selected ) ) {
				$old_selected = ! empty( $old_selected ) ? array( $old_selected ) : array();
			}

			$new_library_ids = array();
			foreach ( $old_selected as $old_post_id ) {
				$old_post_id = absint( $old_post_id );
				if ( isset( $migration_map[ $old_post_id ] ) ) {
					$new_library_ids[] = $migration_map[ $old_post_id ];
				}
			}

			if ( ! empty( $new_library_ids ) ) {
				update_post_meta( $page_id, self::SN_CPS_META_KEY_LIBRARY_IDS, $new_library_ids );
			}
			// Old _sn_cps_selected is kept for rollback purposes
		}

		// Step 6-7: Update DB version and save errors
		if ( empty( $errors ) ) {
			update_option( self::SN_CPS_OPTION_DB_VERSION, self::SN_CPS_DB_VERSION );
			delete_option( 'sn_cps_migration_errors' );
		} else {
			update_option( 'sn_cps_migration_errors', $errors );
		}
	}

	/**
	 * Migrate a single post to v2.0 Library entry.
	 *
	 * @param WP_Post $post          Source post.
	 * @param array   $migration_map Map of old post ID => new Library entry ID (passed by reference).
	 * @param array   $errors        Error log (passed by reference).
	 */
	private function migrate_single_post( $post, &$migration_map, &$errors ) {
		$post_id = $post->ID;
		$css     = get_post_meta( $post_id, self::SN_CPS_META_KEY_CSS, true );
		if ( empty( $css ) ) {
			return;
		}

		$library_entry_id = wp_insert_post(
			array(
				'post_type'   => self::SN_CPS_STYLE_POST_TYPE,
				/* translators: %s: Original post title */
				'post_title'  => sprintf( __( '%s (migrated)', 'studio-noir-page-styles' ), $post->post_title ),
				'post_status' => 'publish',
			)
		);

		if ( is_wp_error( $library_entry_id ) ) {
			$errors[ $post_id ] = sprintf(
				/* translators: %s: Error message */
				__( 'wp_insert_post failed: %s', 'studio-noir-page-styles' ),
				$library_entry_id->get_error_message()
			);
			return;
		}

		update_post_meta( $library_entry_id, self::SN_CPS_META_KEY_CSS, $css );

		if ( ! $this->generate_css_file( $library_entry_id, $css ) ) {
			$errors[ $post_id ] = __( 'CSS file generation failed', 'studio-noir-page-styles' );
			// Continue — metadata migration can still succeed
		}

		$uploaded_files = get_post_meta( $post_id, self::SN_CPS_META_KEY_UPLOADED, true );
		if ( is_array( $uploaded_files ) && ! empty( $uploaded_files ) ) {
			update_post_meta( $library_entry_id, self::SN_CPS_META_KEY_UPLOADED, $uploaded_files );
			$this->copy_uploaded_files( $post_id, $library_entry_id );
		}

		update_post_meta( $post_id, self::SN_CPS_META_KEY_LINKED_LIBRARY, $library_entry_id );
		$migration_map[ $post_id ] = $library_entry_id;
	}

	// =========================================================================
	// MIGRATION NOTICE
	// =========================================================================

	public function render_migration_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Trash warning for pages with unregistered CSS
		$trash_warning = get_transient( 'sn_cps_trash_warning_' . get_current_user_id() );
		if ( $trash_warning ) {
			delete_transient( 'sn_cps_trash_warning_' . get_current_user_id() );
			$post_title = isset( $trash_warning['post_title'] ) ? $trash_warning['post_title'] : '';
			?>
			<div class="notice notice-warning is-dismissible">
				<p>
					<?php
					printf(
						/* translators: %s: Post title */
						esc_html__( 'Custom Page Styles: "%s" was moved to trash, but its CSS has not been saved to the Style Library. Restore the post and use "Save to Library" if you need to reuse this CSS.', 'studio-noir-page-styles' ),
						esc_html( $post_title )
					);
					?>
				</p>
			</div>
			<?php
		}

		// Migration error notice
		$errors = get_option( 'sn_cps_migration_errors', array() );
		if ( empty( $errors ) ) {
			return;
		}

		$error_count = count( $errors );
		?>
		<div class="notice notice-warning sn-cps-migration-notice">
			<p>
				<strong>
					<?php
					printf(
						/* translators: %d: Number of errors */
						esc_html__( 'Custom Page Styles: Migration completed with %d error(s).', 'studio-noir-page-styles' ),
						$error_count
					);
					?>
				</strong>
			</p>
			<p><?php esc_html_e( 'The following posts could not be migrated:', 'studio-noir-page-styles' ); ?></p>
			<ul style="list-style: disc; margin-left: 20px;">
				<?php foreach ( $errors as $post_id => $reason ) : ?>
					<?php
					$err_post  = get_post( absint( $post_id ) );
					$err_title = $err_post ? $err_post->post_title : __( '(deleted)', 'studio-noir-page-styles' );
					?>
					<li>
						<?php
						printf(
							/* translators: 1: Post ID, 2: Post title, 3: Error reason */
							esc_html__( 'Post #%1$d "%2$s": %3$s', 'studio-noir-page-styles' ),
							absint( $post_id ),
							esc_html( $err_title ),
							esc_html( $reason )
						);
						?>
					</li>
				<?php endforeach; ?>
			</ul>
			<p>
				<button type="button" class="button" id="sn_cps_retry_migration_btn"><?php esc_html_e( 'Retry failed posts', 'studio-noir-page-styles' ); ?></button>
				<button type="button" class="button" id="sn_cps_dismiss_migration_btn" style="margin-left: 5px;"><?php esc_html_e( 'Dismiss', 'studio-noir-page-styles' ); ?></button>
			</p>
			<p>
				<?php
				printf(
					/* translators: %s: Anchor tag */
					esc_html__( 'If the issue persists, please visit the %s.', 'studio-noir-page-styles' ),
					'<a href="https://wordpress.org/support/plugin/studio-noir-page-styles/" target="_blank">' . esc_html__( 'support forum', 'studio-noir-page-styles' ) . '</a>'
				);
				?>
			</p>
		</div>
		<script>
		jQuery(document).ready(function($) {
			$('#sn_cps_retry_migration_btn').on('click', function() {
				var $btn = $(this).prop('disabled', true).text('<?php esc_html_e( 'Retrying...', 'studio-noir-page-styles' ); ?>');
				$.ajax({
					url: ajaxurl, type: 'POST',
					data: { action: 'sn_cps_retry_migration', nonce: '<?php echo esc_js( wp_create_nonce( 'sn_cps_retry_migration' ) ); ?>' },
					success: function(r) {
						if (r.success) { location.reload(); }
						else { alert(r.data || '<?php esc_html_e( 'Retry failed.', 'studio-noir-page-styles' ); ?>'); $btn.prop('disabled', false).text('<?php esc_html_e( 'Retry failed posts', 'studio-noir-page-styles' ); ?>'); }
					},
					error: function() { alert('<?php esc_html_e( 'Retry failed.', 'studio-noir-page-styles' ); ?>'); $btn.prop('disabled', false).text('<?php esc_html_e( 'Retry failed posts', 'studio-noir-page-styles' ); ?>'); }
				});
			});
			$('#sn_cps_dismiss_migration_btn').on('click', function() {
				$.ajax({
					url: ajaxurl, type: 'POST',
					data: { action: 'sn_cps_dismiss_migration_notice', nonce: '<?php echo esc_js( wp_create_nonce( 'sn_cps_dismiss_migration_notice' ) ); ?>' },
					success: function() { $('.sn-cps-migration-notice').remove(); }
				});
			});
		});
		</script>
		<?php
	}

	public function ajax_retry_migration() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'sn_cps_retry_migration' ) ) {
			wp_send_json_error( __( 'Invalid nonce', 'studio-noir-page-styles' ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied', 'studio-noir-page-styles' ) );
		}

		$errors = get_option( 'sn_cps_migration_errors', array() );
		if ( empty( $errors ) ) {
			wp_send_json_success( __( 'No failed posts to retry.', 'studio-noir-page-styles' ) );
		}

		$remaining_errors = array();
		$migration_map    = array();

		foreach ( array_keys( $errors ) as $post_id ) {
			$post = get_post( absint( $post_id ) );
			if ( ! $post ) {
				continue; // Post deleted — skip silently
			}
			$new_errors = array();
			$this->migrate_single_post( $post, $migration_map, $new_errors );
			if ( ! empty( $new_errors ) ) {
				$remaining_errors[ $post_id ] = $new_errors[ $post_id ];
			}
		}

		// Update references for newly migrated posts
		if ( ! empty( $migration_map ) ) {
			$enabled_post_types = get_option( self::SN_CPS_OPTION_ENABLED_POST_TYPES, array( 'post', 'page' ) );
			$source_post_types  = array_diff( $enabled_post_types, array( self::SN_CPS_STYLE_POST_TYPE ) );

			$pages_with_selected = get_posts( array( 'post_type' => $source_post_types, 'post_status' => 'any', 'meta_key' => self::SN_CPS_META_KEY_SELECTED, 'posts_per_page' => -1, 'fields' => 'ids' ) );
			foreach ( $pages_with_selected as $page_id ) {
				$old_selected     = get_post_meta( $page_id, self::SN_CPS_META_KEY_SELECTED, true );
				$existing_lib_ids = get_post_meta( $page_id, self::SN_CPS_META_KEY_LIBRARY_IDS, true );
				if ( ! is_array( $old_selected ) ) {
					continue;
				}
				if ( ! is_array( $existing_lib_ids ) ) {
					$existing_lib_ids = array();
				}
				$new_ids = $existing_lib_ids;
				foreach ( $old_selected as $old_id ) {
					$old_id = absint( $old_id );
					if ( isset( $migration_map[ $old_id ] ) && ! in_array( $migration_map[ $old_id ], $new_ids, true ) ) {
						$new_ids[] = $migration_map[ $old_id ];
					}
				}
				if ( $new_ids !== $existing_lib_ids ) {
					update_post_meta( $page_id, self::SN_CPS_META_KEY_LIBRARY_IDS, $new_ids );
				}
			}
		}

		if ( empty( $remaining_errors ) ) {
			delete_option( 'sn_cps_migration_errors' );
			update_option( self::SN_CPS_OPTION_DB_VERSION, self::SN_CPS_DB_VERSION );
			wp_send_json_success( __( 'All posts migrated successfully.', 'studio-noir-page-styles' ) );
		} else {
			update_option( 'sn_cps_migration_errors', $remaining_errors );
			wp_send_json_error(
				sprintf(
					/* translators: %d: Number of remaining errors */
					__( '%d posts still failed to migrate.', 'studio-noir-page-styles' ),
					count( $remaining_errors )
				)
			);
		}
	}

	public function ajax_dismiss_migration_notice() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'sn_cps_dismiss_migration_notice' ) ) {
			wp_send_json_error( __( 'Invalid nonce', 'studio-noir-page-styles' ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied', 'studio-noir-page-styles' ) );
		}
		delete_option( 'sn_cps_migration_errors' );
		wp_send_json_success();
	}
}

// Initialize the plugin
SN_CPS_Manager::get_instance();
