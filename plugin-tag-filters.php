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
	add_filter( 'plugins_api_result', __NAMESPACE__ . '\filter_plugins_api_result', 10, 3 );
}
add_action ( 'init', __NAMESPACE__ . '\on_init' );

/**
 * Add in our additional styles to the wp-admin stylesheets.
 *
 * @return void
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
 * Store the data we care about from the plugins api result, for later reference.
 *
 * Ideally we would just access the data on WP_Plugin_Install_List_Table->items -- if it's exposed when we want it.
 *
 * @param object|WP_Error $res    Response object or WP_Error.
 * @param string          $action The type of information being requested from the Plugin Installation API.
 * @param object          $args   Plugin API arguments.
 */
function filter_plugins_api_result( $res, $action, $args ) {
	$tags = array();

	foreach ( $res->plugins as $plugin ) {
		$slug = $plugin['slug'];
		if ( $plugin['tags'] && is_array( $plugin['tags'] ) ) {
			foreach ( $plugin['tags'] as $tag ) {
				$tags[ $tag ][] = $slug;
			}
		} else {
			$tags['untagged'][] = $slug;
		}
	}

	ksort( $tags );

	$GLOBALS['ptf_plugin_result_tags'] = $tags;

	return $res;
}

/**
 * Add the filtering links to the DOM above the table.
 *
 * @return void
 */
function install_plugins_table_header() {
	global $ptf_plugin_result_tags;
	?>
	<ul class="plugin-table-tag-filters">
		<?php
		foreach ( $ptf_plugin_result_tags as $tag => $plugin_slugs ) {
			if ( ( 'untagged' === $tag ) || count( $plugin_slugs ) > 1 ) {
				printf( '<li><a href="javascript:;">%1$s (%2$d)</a></li>', esc_html( $tag ), count( $plugin_slugs ) );
			}
		}
		?>
	</ul>
	<?php
}