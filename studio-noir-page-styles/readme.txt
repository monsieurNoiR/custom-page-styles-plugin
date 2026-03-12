=== Studio Noir Custom Page Styles ===
Contributors: studionoir
Tags: css, custom css, page styles, reusable css, file upload
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage custom CSS for each page/post with unlimited style selection, file uploads, and reusability features.

== Description ==

Tired of copy-pasting the same CSS across multiple pages? Studio Noir Custom Page Styles lets you write a style once and reuse it on any page — no duplicates, no maintenance headaches.

= Key Features =

* Write custom CSS directly in the page/post editor
* **Upload CSS and JavaScript files**
* **Select unlimited existing styles with drag & drop reordering**
* Choose header or footer loading for JavaScript files
* Automatically generates CSS files for better performance
* Choose which post types to enable
* Secure and WordPress coding standards compliant

= Perfect For =

* Adding unique designs to landing pages
* Customizing individual blog posts with external libraries
* Managing page-specific styles without bloating your main stylesheet
* Reusing common style patterns across multiple pages
* Adding JavaScript effects and animations to specific pages

= How It Works =

1. Edit any page or post
2. Find the "Custom Page Styles" meta box
3. Upload CSS/JS files, select existing styles, or write custom CSS
4. Reorder styles by drag & drop to control load order
5. Publish and see your styles in action!

= Load Order =

Styles are loaded in the following order for maximum flexibility:

1. Selected styles (base templates)
2. Uploaded files (libraries and frameworks)
3. Direct CSS (final adjustments and overrides)

== Support Development ==

If you find this plugin helpful, consider supporting its development:

☕ [Buy me a coffee on Ko-fi](https://ko-fi.com/studio_noir)

Your support helps me continue creating free, open-source WordPress plugins!

== Installation ==

= Automatic Installation =

1. Go to Plugins > Add New
2. Search for "Studio Noir Custom Page Styles"
3. Click "Install Now"
4. Activate the plugin

= Manual Installation =

1. Download the plugin zip file
2. Go to Plugins > Add New > Upload Plugin
3. Choose the downloaded file and click "Install Now"
4. Activate the plugin

= After Activation =

1. Go to Settings > Custom Page Styles
2. Select which post types you want to enable
3. Edit any page/post and find the "Custom Page Styles" meta box
4. Start adding custom styles!

== Frequently Asked Questions ==

= Can I use this on custom post types? =

Yes! Go to Settings > Custom Page Styles and select which post types you want to enable.

= Will this work with my theme? =

Yes, Studio Noir Custom Page Styles works with any WordPress theme.

= How many styles can I apply to one page? =

Unlimited! You can select as many existing styles as you need, upload multiple CSS/JS files, and add custom CSS - all on the same page.

= Can I control the order styles are loaded? =

Yes! Use drag & drop to reorder selected styles. The order you set determines the load order.

= What file types can I upload? =

CSS (.css) and JavaScript (.js) files only. Maximum file size is 5MB per file.

= Where should JavaScript files be loaded? =

You can choose header or footer for each JS file. Footer is recommended for most cases (default).

= Where are the uploaded files stored? =

Uploaded files are stored in `/wp-content/uploads/sn-cps-styles/{post_id}/` to keep files organized by post.

= Does this affect site performance? =

No, CSS is generated as separate files and only loaded on the relevant pages, which is better for performance than inline styles.

= Can I use this with page builders? =

Yes, Studio Noir Custom Page Styles works alongside page builders like Elementor, Gutenberg, and the Classic Editor.

= What if the meta box doesn't appear? =

1. Make sure the plugin is activated
2. Check that the post type is enabled in Settings > Custom Page Styles
3. Click "Screen Options" at the top of the editor and make sure "Custom Page Styles" is checked

== Screenshots ==

1. Custom Page Styles meta box with file upload interface
2. Drag & drop reordering of selected styles
3. Settings page to choose enabled post types

== Changelog ==

= 2.0.0 =
* NEW: Style Library — manage reusable CSS as independent entries (Custom Post Type)
* NEW: "Save to Library" button — register a page's CSS as a named reusable style
* NEW: "Sync to Library" button — overwrite or fork an existing Library entry
* NEW: Automatic migration from v1.x data to Style Library entries
* NEW: Migration error notice with Retry / Dismiss actions
* NEW: Trash warning when a page with unregistered CSS is moved to trash
* IMPROVED: Style selector now uses Library entry names instead of page titles
* IMPROVED: Library entry deletion cleans up CSS files, uploaded files, and page references
* IMPROVED: Uninstall routine now removes all v2.0 data (CPT, meta keys, options)

= 1.1.1 =
* SECURITY: Enhanced path traversal protection in ajax_remove_file()
* SECURITY: Added MIME type validation using finfo_file() in ajax_upload_file()
* FEATURE: Automatic filename conflict resolution (adds -1, -2, etc.)
* IMPROVED: File upload security with comprehensive validation

= 1.1.0 =
* NEW: Unlimited style selection (previously limited to 2)
* NEW: Drag & drop reordering for selected styles (ACF-style sortable UI)
* NEW: File upload feature for CSS and JavaScript files
* NEW: Choose header or footer loading for JavaScript files
* NEW: Files organized in post-specific directories
* IMPROVED: CSS load order optimization (selected → uploaded → direct CSS)
* IMPROVED: Security enhancements for file uploads (type validation, size limit)
* IMPROVED: Better UI with visual feedback and file management

= 1.0.2 =
* IMPROVED: Style loading priority optimization
* Set `wp_enqueue_scripts` hook priority to 20
* Custom CSS now reliably overrides theme styles

= 1.0.1 =
* SECURITY: Enhanced CSS sanitization (WP_Error support)
* SECURITY: Additional dangerous pattern detection
* SECURITY: File size limit added (1MB for CSS)
* IMPROVED: Better error handling with Transient API
* IMPROVED: Path traversal attack prevention

= 1.0.0 =
* Initial release
* Custom CSS per page/post
* Reuse existing styles feature
* Post type selection
* Automatic CSS file generation
* Security: SQL injection prevention
* Security: CSS sanitization
* Security: File path validation

== Upgrade Notice ==

= 2.0.0 =
Major update! Introduces the Style Library — a dedicated Custom Post Type for managing reusable styles. Existing CSS is automatically migrated on first activation. Fully backward compatible with v1.x data.

= 1.1.1 =
Important security update! Enhanced file upload validation with MIME type checking and improved path traversal protection. Recommended for all users.

= 1.1.0 =
Major update! Unlimited style selection with drag & drop, file upload support for CSS/JS, and improved load order control. Fully backward compatible with v1.0.x.

= 1.0.2 =
Improved style loading priority for better theme override capability.

= 1.0.1 =
Important security update with enhanced CSS sanitization and file validation.

= 1.0.0 =
Initial release of Studio Noir Custom Page Styles.
