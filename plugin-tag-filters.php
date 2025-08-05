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
	global $ptf_plugin_result_tags;

	if ( ! empty( $res->plugins ) && is_array( $res->plugins ) ) {
		if ( ! is_array( $ptf_plugin_result_tags ) ) {
			$ptf_plugin_result_tags = array();
		}

		foreach ( $res->plugins as $plugin ) {
			$slug = $plugin['slug'];
			if ( $plugin['tags'] && is_array( $plugin['tags'] ) ) {
				foreach ( $plugin['tags'] as $tag ) {
					$ptf_plugin_result_tags[ $tag ][] = $slug;
				}
			} else {
				$ptf_plugin_result_tags['untagged'][] = $slug;
			}
		}

		ksort( $ptf_plugin_result_tags );
	}

	return $res;
}

/**
 * Add the filtering links to the DOM above the table.
 *
 * @return void
 */
function install_plugins_table_header() {
	global $ptf_plugin_result_tags;

	if ( empty( $ptf_plugin_result_tags ) || ! is_array( $ptf_plugin_result_tags ) ) {
		return;
	}

	?>
	<ul class="plugin-table-tag-filters">
		<?php
		foreach ( $ptf_plugin_result_tags as $tag => $plugin_slugs ) {
			if ( ( 'untagged' === $tag ) || count( $plugin_slugs ) > 1 ) {
				printf(
					'<li><a data-slugs="%3$s" href="javascript:;">%1$s (%2$d)</a></li>',
					esc_html( $tag ),
					count( $plugin_slugs ),
					esc_attr( wp_json_encode( $plugin_slugs ) )
				);
			}
		}
		?>
	</ul>
	<?php
}

function filter_manage_plugins_columns( $columns ) {
    $columns = array_merge(
		array_slice( $columns, 0, 2 ),
		[ 'tags' => __( 'Tags' ) ],
		array_slice( $columns, 2 )
	);

    return $columns;
}
add_action( 'manage_plugins_columns', __NAMESPACE__ . '\filter_manage_plugins_columns' );
add_action( 'manage_plugins-network_columns', __NAMESPACE__ . '\filter_manage_plugins_columns' );

/**
 * Display a column with the plugin's known tags.
 *
 * @param string $column_name Name of the column.
 * @param string $plugin_file Path to the plugin file relative to the plugins directory.
 *
 * @return void
 */
function action_manage_plugins_custom_column( $column_name, $plugin_file ) {
	if ( 'tags' === $column_name ) {
		$tags = get_plugin_tags( $plugin_file );
		if ( $tags && is_array( $tags ) ) {
			$tags = array_map( __NAMESPACE__ . '\linkify_tag', $tags );
			echo implode( ', ', $tags );
		}
	}
}
add_action( 'manage_plugins_custom_column', __NAMESPACE__ . '\action_manage_plugins_custom_column', 10, 2 );

function linkify_tag( $tag ) {
	return sprintf(
		'<a href="%2$s">%1$s</a>',
		esc_html( $tag ),
		esc_url( add_query_arg( 'tag', $tag ) )
	);
}

/**
 * Check for plugin tags if there's a readme.txt file.
 *
 * @link https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/
 *
 * @param string $plugin The plugin file -- for example, `akismet/akismet.php`.
 *
 * @return mixed Either an array of tags, or something false-y.
 */
function get_plugin_tags( $plugin ) {
	$readme_file = WP_PLUGIN_DIR . '/' . dirname( $plugin ) . '/readme.txt';

	if ( file_exists( $readme_file ) ) {
		$readme_headers = get_file_data(
			$readme_file,
			array(
				'tags' => 'Tags',
			),
			'plugin'
		);

		if ( $readme_headers['tags'] ) {
			$tags = explode( ',', $readme_headers['tags'] );
			return array_map( 'trim', $tags );
		}
		return false;
	}

	// @todo: Add something to parse the `keywords` out of a `package.json` if it exists?

	return null;
}
