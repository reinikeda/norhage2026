<?php
/**
 * Wishlist rules, PDF, and translation catalogs.
 *
 * Run: php public/wp-content/plugins/nh-wishlist/tests/test-nh-wishlist.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/' );
}

require_once dirname( __DIR__ ) . '/includes/lists.php';
require_once dirname( __DIR__ ) . '/includes/pdf.php';
require_once dirname( __DIR__ ) . '/includes/email-html.php';

$failures = 0;

function nh_wl_assert( $label, $ok ) {
	global $failures;
	if ( $ok ) {
		echo "OK $label\n";
		return;
	}
	$failures++;
	fwrite( STDERR, "FAIL $label\n" );
}

$simple = nh_wl_make_item(
	array(
		'product_id' => 10,
		'quantity'   => 2,
		'is_custom'  => false,
		'dimensions' => array(
			'width_mm'  => 500,
			'length_mm' => 500,
		),
	)
);
nh_wl_assert( 'simple product drops stray dimensions', 0 === $simple['dimensions']['width_mm'] && 0 === $simple['dimensions']['length_mm'] );
nh_wl_assert( 'simple product does not need customize', ! nh_wl_needs_customize( array( 'is_variable' => false, 'is_custom' => false, 'variation_id' => 0, 'dimensions' => $simple['dimensions'] ) ) );

$cut = nh_wl_make_item(
	array(
		'product_id' => 11,
		'is_custom'  => true,
		'dimensions' => array(
			'unit'      => 'mm',
			'type'      => 'planar',
			'width_mm'  => 1200,
			'length_mm' => 800,
		),
		'added'      => 10,
	)
);
nh_wl_assert( 'custom size is kept', 1200 === $cut['dimensions']['width_mm'] && 800 === $cut['dimensions']['length_mm'] );
nh_wl_assert( 'complete custom cut can go to the basket', ! nh_wl_needs_customize( array( 'is_custom' => true, 'dimensions' => $cut['dimensions'] ) ) );
nh_wl_assert( 'missing dimensions need customize', nh_wl_needs_customize( array( 'is_custom' => true, 'dimensions' => array( 'unit' => 'mm', 'type' => 'planar' ) ) ) );
nh_wl_assert( 'width alone is not a complete cut', nh_wl_needs_customize( array( 'is_custom' => true, 'dimensions' => array( 'unit' => 'mm', 'width_mm' => 1000 ) ) ) );
nh_wl_assert(
	'variable without a variation needs customize',
	nh_wl_needs_customize(
		array(
			'is_variable'  => true,
			'is_custom'    => true,
			'variation_id' => 0,
			'dimensions'   => $cut['dimensions'],
		)
	)
);
nh_wl_assert(
	'selected variation with dimensions is ready',
	! nh_wl_needs_customize(
		array(
			'is_variable'  => true,
			'is_custom'    => true,
			'variation_id' => 55,
			'dimensions'   => $cut['dimensions'],
		)
	)
);
nh_wl_assert(
	'selected variation still needs a length',
	nh_wl_item_needs_customize(
		array(
			'variation_id' => 55,
			'dimensions'   => array( 'width_mm' => 10 ),
		),
		array(
			'is_variable' => true,
			'is_custom'   => true,
			'unit'        => 'mm',
			'type'        => 'planar',
		)
	)
);

$linear = array(
	'unit'     => 'm',
	'type'     => 'linear',
	'length_m' => '2,50',
);
nh_wl_assert( 'comma metres parse', '2.5' === nh_wl_format_metres( '2,50' ) );
nh_wl_assert( 'linear length is complete', nh_wl_dimensions_complete( $linear ) );
nh_wl_assert( '2.50 and 2.5 share a key', nh_wl_item_key( 1, 0, array( 'length_m' => '2.50', 'unit' => 'm', 'type' => 'linear' ), array() ) === nh_wl_item_key( 1, 0, array( 'length_m' => '2.5', 'unit' => 'm', 'type' => 'linear' ), array() ) );

$variation = nh_wl_make_item(
	array(
		'product_id'   => 11,
		'variation_id' => 55,
		'is_custom'    => true,
		'attributes'   => array(
			'attribute_pa_thickness' => '16mm',
		),
		'dimensions'   => $cut['dimensions'],
	)
);
nh_wl_assert( 'variation attribute is stored', isset( $variation['attributes']['attribute_pa_thickness'] ) );
nh_wl_assert( 'different variation is a different line', $variation['key'] !== $cut['key'] );

$state = nh_wl_add_item( nh_wl_empty_state(), 'default', $cut );
nh_wl_assert( 'custom cut saved on default', $state['ok'] && 1200 === $state['state']['lists'][0]['items'][0]['dimensions']['width_mm'] );
$again = nh_wl_add_item( $state['state'], 'default', array_merge( $cut, array( 'quantity' => 4 ) ) );
nh_wl_assert( 'same size updates quantity', $again['ok'] && 1 === count( $again['state']['lists'][0]['items'] ) && 4 === $again['state']['lists'][0]['items'][0]['quantity'] );

$created = nh_wl_create_list( $again['state'], 'Terrace' );
nh_wl_assert( 'second list', $created['ok'] && 'Terrace' === nh_wl_find_list( $created['state'], $created['list_id'] )['name'] );
$duplicate = nh_wl_create_list( $created['state'], 'terrace' );
nh_wl_assert( 'duplicate name rejected', ! $duplicate['ok'] && 'duplicate_name' === $duplicate['error'] );
$empty_name = nh_wl_create_list( $created['state'], '   ' );
nh_wl_assert( 'empty name rejected', 'empty_name' === $empty_name['error'] );
$only = nh_wl_delete_list( nh_wl_empty_state(), 'default' );
nh_wl_assert( 'only list stays', ! $only['ok'] && 'last_list' === $only['error'] );
$removed = nh_wl_delete_list( $created['state'], $created['list_id'] );
nh_wl_assert( 'extra list can be deleted', $removed['ok'] && 1 === count( $removed['state']['lists'] ) );

$guest = nh_wl_add_item( nh_wl_empty_state(), 'default', $variation );
$merged = nh_wl_merge_states( $state['state'], $guest['state'] );
$default_items = nh_wl_find_list( $merged, 'default' )['items'];
nh_wl_assert( 'login merge keeps both cuts', 2 === count( $default_items ) );

$cookie = nh_wl_decode_cookie( nh_wl_encode_cookie( $guest['state'] ) );
nh_wl_assert( 'cookie keeps variation and size', 55 === $cookie['lists'][0]['items'][0]['variation_id'] && 800 === $cookie['lists'][0]['items'][0]['dimensions']['length_mm'] );
nh_wl_assert( 'small wishlist fits in a cookie', nh_wl_cookie_fits( nh_wl_encode_cookie( $guest['state'] ) ) );
nh_wl_assert( 'broken cookie falls back to default', 'default' === nh_wl_decode_cookie( '@@@' )['active'] );

$flags = array(
	'is_custom' => true,
	'unit'      => 'mm',
	'type'      => 'planar',
);
$post = nh_wl_post_fields_for_cart( $cut, $flags );
nh_wl_assert( 'cart post carries width', isset( $post['nh_width_mm'] ) && '1200' === $post['nh_width_mm'] && '1' === $post['nh_custom_cutting'] );
$plain_post = nh_wl_post_fields_for_cart( $simple, array( 'is_custom' => false ) );
nh_wl_assert( 'plain product does not send a cut flag', ! isset( $plain_post['nh_custom_cutting'] ) );
nh_wl_assert(
	'sheet price is area plus fee',
	12.0 === nh_wl_custom_unit_price(
		10,
		2,
		array(
			'unit'      => 'mm',
			'type'      => 'planar',
			'width_mm'  => 1000,
			'length_mm' => 1000,
		)
	)
);
nh_wl_assert( 'linear price is length plus fee', 21.0 === nh_wl_custom_unit_price( 8, 1, $linear ) );

$html = nh_wl_email_html(
	array(
		'site_name'     => 'Norhage',
		'heading'       => 'Wishlist',
		'list_name'     => 'Default',
		'comment'       => "Please cut <carefully>",
		'comment_label' => 'Comment:',
		'open_label'    => 'Open product',
		'items'         => array(
			array(
				'name'      => 'Polycarbonate 16mm',
				'url'       => 'https://example.test/product',
				'qty_label' => 'Quantity: 2',
				'lines'     => array(
					array(
						'label' => 'Width',
						'value' => '1200 mm',
					),
				),
				'notice'    => 'This product must be customized before it can be added to the basket.',
			),
		),
	)
);
nh_wl_assert( 'email contains the comment', false !== strpos( $html, 'Please cut &lt;carefully&gt;' ) );
nh_wl_assert( 'email contains the product', false !== strpos( $html, 'Polycarbonate 16mm' ) );
nh_wl_assert( 'email contains the customize notice', false !== strpos( $html, 'must be customized' ) );

$font = dirname( __DIR__ ) . '/assets/fonts/LiberationSans-Regular.ttf';
$info = nh_wl_pdf_font_info( $font );
nh_wl_assert( 'font maps Lithuanian a', $info && nh_wl_pdf_glyph( $info, 0x105 ) > 0 );
nh_wl_assert( 'font maps German a', $info && nh_wl_pdf_glyph( $info, 0xE4 ) > 0 );
nh_wl_assert( 'font maps Nordic o', $info && nh_wl_pdf_glyph( $info, 0xF8 ) > 0 );
$pdf = nh_wl_pdf_render(
	array(
		'title'    => 'Wunschliste',
		'subtitle' => 'Terasos sąrašas',
		'meta'     => '2026-09-24',
		'footer'   => 'Norhage',
		'blocks'   => array(
			array(
				'heading' => 'Polikarbonatas ąčę',
				'lines'   => array( 'Plotis: 1200 mm', 'Ilgis: 800 mm' ),
			),
		),
	),
	$font
);
nh_wl_assert( 'pdf header', 0 === strpos( $pdf, '%PDF-1.4' ) );
nh_wl_assert( 'pdf embeds the font', false !== strpos( $pdf, '/FontFile2' ) );
nh_wl_assert( 'pdf maps a Lithuanian character', false !== strpos( $pdf, '0105' ) );
nh_wl_assert( 'pdf is a real file', strlen( $pdf ) > 10000 );

$header = file_get_contents( dirname( __DIR__, 3 ) . '/themes/astra-custom-for-norhage/template-parts/headers/header-main.php' );
nh_wl_assert( 'header has a wishlist link', false !== strpos( $header, 'nh_wl_header_link' ) );

$plugin = dirname( __DIR__ );
$code   = '';
$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $plugin ) );
foreach ( $iterator as $file ) {
	$path = $file->getPathname();
	if ( substr( $path, -4 ) !== '.php' || false !== strpos( $path, '/tests/' ) ) {
		continue;
	}
	$code .= file_get_contents( $path );
}
preg_match_all( "/(?:__|_e|esc_html__|esc_html_e|esc_attr__|esc_attr_e)\\(\\s*'((?:\\\\'|[^'])*)'\\s*,\\s*'nh-wishlist'/", $code, $matches );
$msgids = array_values( array_unique( $matches[1] ) );
nh_wl_assert( 'frontend strings exist', count( $msgids ) > 40 );

$locales = array( 'de_DE', 'da_DK', 'sv_SE', 'nb_NO', 'fi', 'lt_LT' );
foreach ( $locales as $locale ) {
	$po = $plugin . '/languages/nh-wishlist-' . $locale . '.po';
	$mo = $plugin . '/languages/nh-wishlist-' . $locale . '.mo';
	nh_wl_assert( $locale . ' mo exists', is_file( $mo ) && filesize( $mo ) > 200 );
	$catalog = is_file( $po ) ? file_get_contents( $po ) : '';
	$missing = array();
	foreach ( $msgids as $msgid ) {
		$needle = 'msgid "' . str_replace( array( '\\', '"' ), array( '\\\\', '\\"' ), $msgid ) . '"';
		if ( false === strpos( $catalog, $needle ) ) {
			$missing[] = $msgid;
		}
	}
	nh_wl_assert( $locale . ' translates every string', $missing === array() );
	if ( $missing ) {
		fwrite( STDERR, $locale . ' missing: ' . implode( ' | ', $missing ) . "\n" );
	}
}

if ( $failures ) {
	fwrite( STDERR, "$failures failed\n" );
	exit( 1 );
}
echo "All wishlist tests passed\n";
