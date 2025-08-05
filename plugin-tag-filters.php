<?php
/**
 * Plugin Name:       Plugin Tag Filters
 * Description:       A plugin to customize the Plugins interface enabling tag-based filtering.
 * Version:           0.1.0
 * Requires at least: 6.7
 * Requires PHP:      7.4
 * Author:            George Stephanis
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       plugin-tag-filters
 * Domain Path:       /languages
 */

namespace PluginTagFilters;

/**
 * Runs on `init` action.  Sets up other hooks and integrations.
 *
 * @return void
 */
function on_init() {
	add_action( 'install_plugins_table_header', __NAMESPACE__ . '\install_plugins_table_header' );
	add_action( 'load-plugin-install.php', __NAMESPACE__ . '\add_styles_to_admin' );
}
add_action ( 'init', __NAMESPACE__ . '\on_init' );

/**
 * Add in our additional styles to the wp-admin stylesheets.
 */
function add_styles_to_admin() {
	$asset_file = include( plugin_dir_path( __FILE__ ) . 'build/index.asset.php');

	wp_enqueue_script(
		'plugin-tag-filters',
		plugins_url( 'build/index.js', __FILE__ ),
		$asset_file['dependencies'],
		$asset_file['version'],
		true
	);

	wp_enqueue_style(
		'plugin-tag-filters',
		plugins_url( 'build/index.css', __FILE__ ),
		array(),
		$asset_file['version']
	);
	wp_style_add_data( 'plugin-tag-filters', 'rtl', 'replace' );
}

/**
 * Add the filtering links to the DOM above the table.
 *
 * @return void
 */
function install_plugins_table_header() {
	?>
	<ul class="plugin-table-tag-filters">
		<li><a href="javascript:;">Tag One (32)</a></li>
		<li><a href="javascript:;">Tag Two (23)</a></li>
		<li><a href="javascript:;">Tag Three (14)</a></li>
		<li><a href="javascript:;">Tag Four (6)</a></li>
	</ul>
	<?php
}