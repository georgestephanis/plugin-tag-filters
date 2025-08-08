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
 *
 * @package PluginTagFilters
 */

namespace PluginTagFilters;

/**
 * Runs on `init` action.  Sets up other hooks and integrations.
 *
 * @return void
 */
function on_init() {
	add_action( 'install_plugins_table_header', __NAMESPACE__ . '\install_plugins_table_header' );
	add_action( 'load-plugin-install.php', __NAMESPACE__ . '\add_plugin_install_screen_assets' );
	add_action( 'load-plugins.php', __NAMESPACE__ . '\add_plugin_screen_assets' );
	add_filter( 'plugins_api_result', __NAMESPACE__ . '\filter_plugins_api_result' );

	add_action( 'manage_plugins_columns', __NAMESPACE__ . '\filter_manage_plugins_columns' );
	add_action( 'manage_plugins-network_columns', __NAMESPACE__ . '\filter_manage_plugins_columns' );
	add_action( 'manage_plugins_custom_column', __NAMESPACE__ . '\action_manage_plugins_custom_column', 10, 2 );
	add_action( 'views_plugins', __NAMESPACE__ . '\views_plugins' );
	add_filter( 'plugins_list', __NAMESPACE__ . '\filter_plugins_list' );
}
add_action( 'init', __NAMESPACE__ . '\on_init' );

/**
 * Add in our additional styles to the plugin-install.php screen
 *
 * @return void
 */
function add_plugin_install_screen_assets() {
	$asset_file = include plugin_dir_path( __FILE__ ) . 'build/index.asset.php';

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
 * Add in our additional styles to the plugins.php screen
 *
 * @return void
 */
function add_plugin_screen_assets() {
	$asset_file = include plugin_dir_path( __FILE__ ) . 'build/index.asset.php';

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
 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
 *
 * @param object|WP_Error $res    Response object or WP_Error.
 */
function filter_plugins_api_result( $res ) {
	global $ptf_plugin_result_tags;

	if ( ! empty( $res->plugins ) && is_array( $res->plugins ) ) {
		$tag_filter = null;
		// phpcs:ignore: WordPress.Security.NonceVerification
		if ( isset( $_GET['tag_filter'] ) ) {
			// phpcs:ignore: WordPress.Security.NonceVerification
			$tag_filter = normalize_tag( $_GET['tag_filter'] );
		}

		if ( ! is_array( $ptf_plugin_result_tags ) ) {
			$ptf_plugin_result_tags = array();
		}

		foreach ( $res->plugins as $i => $plugin ) {
			$slug = $plugin['slug'];
			if ( $plugin['tags'] && is_array( $plugin['tags'] ) ) {
				$tags = $plugin['tags'];
				foreach ( $tags as $tag ) {
					$ptf_plugin_result_tags[ $tag ][] = $slug;
				}
				if ( $tag_filter ) {
					$tags_normalized = array_map( __NAMESPACE__ . '\normalize_tag', $tags );
					if ( ! in_array( $tag_filter, $tags_normalized, true ) ) {
						unset( $res->plugins[ $i ] );
					}
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
			$active = null;
			// phpcs:ignore: WordPress.Security.NonceVerification
			if ( ! empty( $_GET['tag_filter'] ) ) {
				// phpcs:ignore: WordPress.Security.NonceVerification
				$active = $tag === $_GET['tag_filter'] ? 'active' : '';
			}

			if ( ( 'untagged' === $tag ) || count( $plugin_slugs ) > 1 ) {
				printf(
					'<li><a data-slugs="%3$s" href="%4$s" class="%5$s">%1$s (%2$d)</a></li>',
					esc_html( $tag ),
					count( $plugin_slugs ),
					esc_attr( wp_json_encode( $plugin_slugs ) ),
					esc_url( add_query_arg( 'tag_filter', $tag ) ),
					esc_attr( $active )
				);
			}
		}
		?>
	</ul>
	<?php
}

/**
 * Add in a Tags column to the plugins list table.
 *
 * @param array[] $columns The columns to be displayed.
 *
 * @return array[]
 */
function filter_manage_plugins_columns( $columns ) {
	$columns = array_merge(
		array_slice( $columns, 0, 2 ),
		array( 'tags' => __( 'Tags', 'plugin-tag-filters' ) ),
		array_slice( $columns, 2 )
	);

	return $columns;
}

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
			echo implode( ', ', $tags ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}
}

/**
 * Add in a tagged view, if we're filtering by tag.
 *
 * @param string[] $views An array of available list table views.
 */
function views_plugins( $views ) {
	// phpcs:ignore: WordPress.Security.NonceVerification
	if ( isset( $_GET['plugin_status'], $_GET['tag'] ) && 'tagged' === $_GET['plugin_status'] ) {
		// phpcs:ignore: WordPress.Security.NonceVerification
		$tag = $_GET['tag'];

		$views['all'] = str_replace( ' class="current" aria-current="page"', '', $views['all'] );

		$views['tagged'] = sprintf(
			'<a href="%2$s" class="current" aria-current="page">Tagged <span class="count">(%1$s)</span></a>',
			esc_html( $tag ),
			esc_url(
				add_query_arg(
					array(
						'plugin_status' => 'tagged',
						'tag'           => $tag,
					),
					admin_url( 'plugins.php' )
				)
			)
		);
	}
	return $views;
}

/**
 * Generate the link for the installed plugins tag list.  Handle active class as needed.
 *
 * @param string $tag The tag name.
 *
 * @return string The html link for the tag.
 */
function linkify_tag( $tag ) {
	$class = '';

	// phpcs:ignore: WordPress.Security.NonceVerification
	if ( isset( $_GET['tag'] ) && ( normalize_tag( $_GET['tag'] ) === normalize_tag( $tag ) ) ) {
		$class = 'active';
	}

	$url = null;
	if ( 'active' === $class ) {
		$url = remove_query_arg( array( 'plugin_status', 'tag' ) );
	} else {
		$url = add_query_arg(
			array(
				'plugin_status' => 'tagged',
				'tag'           => $tag,
			),
			admin_url( 'plugins.php' )
		);
	}

	return sprintf(
		'<a href="%2$s" class="%3$s">%1$s</a>',
		esc_html( $tag ),
		esc_url( $url ),
		esc_attr( $class )
	);
}

/**
 * Normalize plugin tags to account for variances in spelling or usage before comparison.
 *
 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
 *
 * @param string $tag The tag that we are distilling down to a common format.
 *
 * @return string
 */
function normalize_tag( $tag ) {
	$raw = $tag;
	$tag = strtolower( preg_replace( '/[\W]/', '', $raw ) );

	switch ( $tag ) {
		case 'searchengine':
		case 'searchengineoptimization':
			$tag = 'seo';
			break;
		case 'firewall':
		case 'malware':
		case 'login':
		case '2fa':
		case 'twofactor':
			$tag = 'security';
			break;
		case 'cache':
		case 'caching':
		case 'speed':
		case 'optimization':
		case 'minify':
			$tag = 'performance';
			break;
		case 'woo':
		case 'woocommerce':
			$tag = 'woo';
			break;
		case 'shop':
		case 'cart':
		case 'store':
			$tag = 'ecommerce';
			break;
		case 'schedule':
		case 'booking':
		case 'appointment':
		case 'calendar':
		case 'events':
			$tag = 'event';
			break;
	}

	/**
	 * Give plugins a chance to review the normalization we've done, and apply their own overrides.
	 */
	apply_filters( 'ptf_normalize_tag', $tag, $raw );

	return $tag;
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

	$package_json = WP_PLUGIN_DIR . '/' . dirname( $plugin ) . '/package.json';
	if ( file_exists( $package_json ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$package_headers = json_decode( file_get_contents( $package_json ) );

		if ( ! empty( $package_headers->keywords ) ) {
			return array_map( 'trim', (array) $package_headers->keywords );
		}
	}

	return null;
}

/**
 * Filters the array of plugins for the list table.
 *
 * @param array[] $plugins An array of arrays of plugin data, keyed by context.
 */
function filter_plugins_list( $plugins ) {
	global $status;
	// phpcs:ignore: WordPress.Security.NonceVerification
	if ( isset( $_GET['plugin_status'], $_GET['tag'] ) && 'tagged' === $_GET['plugin_status'] ) {
		$status            = 'tagged'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$tag               = normalize_tag( $_GET['tag'] ); // phpcs:ignore: WordPress.Security.NonceVerification
		$plugins['tagged'] = array();
		foreach ( $plugins['all'] as $plugin => $properties ) {
			$tags = get_plugin_tags( $plugin );
			if ( $tags ) {
				$tags = array_map( __NAMESPACE__ . '\normalize_tag', $tags );
				if ( in_array( $tag, $tags, true ) ) {
					$plugins['tagged'][ $plugin ] = $properties;
				}
			}
		}
	}
	return $plugins;
}
