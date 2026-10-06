<?php
/**
 * CLI tests: product feature box admin icons.
 *
 * Run: php public/wp-content/themes/astra-custom-for-norhage/tests/test-feature-box-admin.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/' );
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		unset( $hook, $callback, $priority, $accepted_args );
	}
}

require_once dirname( __DIR__ ) . '/inc/feature-box-admin.php';

$failures = 0;

function nh_feature_assert( $label, $ok ) {
	global $failures;

	if ( $ok ) {
		echo "ok  {$label}\n";
		return;
	}

	$failures++;
	echo "FAIL  {$label}\n";
}

$icon = nh_feature_admin_icon_html( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"></svg>' );

nh_feature_assert( 'admin icon sets a width', false !== strpos( $icon, 'width="18"' ) );
nh_feature_assert( 'admin icon sets a height', false !== strpos( $icon, 'height="18"' ) );
nh_feature_assert( 'admin icon is not sized twice', 1 === substr_count( nh_feature_admin_icon_html( $icon ), 'width="' ) );

$css = file_get_contents( dirname( __DIR__ ) . '/assets/css/feature-box-admin.css' );
$admin = file_get_contents( dirname( __DIR__ ) . '/inc/feature-box-admin.php' );
$front_css = file_get_contents( dirname( __DIR__ ) . '/assets/css/product-page.css' );
$front = file_get_contents( dirname( __DIR__ ) . '/inc/feature-box.php' );

nh_feature_assert( 'admin stylesheet caps the icon', false !== strpos( $css, 'width: 18px' ) && false !== strpos( $css, 'flex: 0 0 20px' ) );
nh_feature_assert( 'product editor loads the admin stylesheet', false !== strpos( $admin, '/assets/css/feature-box-admin.css' ) );
nh_feature_assert( 'drag label is valid php', false !== strpos( $admin, 'title="<?php esc_attr_e( \'Drag to reorder\', \'nh-theme\' ); ?>"' ) );
nh_feature_assert( 'storefront feature icons stay untouched', false === strpos( $front, 'nh_feature_admin_icon_html' ) );
nh_feature_assert( 'storefront icon size stays in the product css', false !== strpos( $front_css, '.nhf-box .nhf-icon svg' ) );

if ( $failures > 0 ) {
	echo "{$failures} failed\n";
	exit( 1 );
}

echo "all passed\n";
