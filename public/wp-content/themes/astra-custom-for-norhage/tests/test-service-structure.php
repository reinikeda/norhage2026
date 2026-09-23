<?php
/**
 * CLI tests: service landing structure.
 *
 * Blog posts keep their date. Services do not.
 *
 * Run: php public/wp-content/themes/astra-custom-for-norhage/tests/test-service-structure.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/' );
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $string ) {
		return trim( strip_tags( (string) $string ) );
	}
}

if ( ! function_exists( 'wp_html_excerpt' ) ) {
	function wp_html_excerpt( $str, $count, $more = null ) {
		$str = (string) $str;
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $str, 'UTF-8' ) <= (int) $count ) {
			return $str;
		}
		if ( ! function_exists( 'mb_strlen' ) && strlen( $str ) <= (int) $count ) {
			return $str;
		}
		$cut = function_exists( 'mb_substr' ) ? mb_substr( $str, 0, (int) $count, 'UTF-8' ) : substr( $str, 0, (int) $count );
		return $cut . (string) $more;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return (string) $url;
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data ) {
		return json_encode( $data );
	}
}

require_once dirname( __DIR__ ) . '/inc/service-structure.php';

$failures = 0;

function nh_service_assert( $label, $ok ) {
	global $failures;
	if ( $ok ) {
		echo "ok  {$label}\n";
		return;
	}
	$failures++;
	echo "FAIL  {$label}\n";
}

nh_service_assert( 'service post type hides author and date', nh_service_post_type_hides_meta( 'service' ) );
nh_service_assert( 'blog posts keep their date', ! nh_service_post_type_hides_meta( 'post' ) );
nh_service_assert( 'pages are not treated as services', ! nh_service_post_type_hides_meta( 'page' ) );

nh_service_assert(
	'separator-only archive title is empty',
	nh_service_document_title_is_empty( '– Norhage', 'Norhage' )
);
nh_service_assert(
	'entity separator title is empty',
	nh_service_document_title_is_empty( '&#8211; Norhage', 'Norhage' )
);
nh_service_assert(
	'site name alone is empty',
	nh_service_document_title_is_empty( 'Norhage', 'Norhage' )
);
nh_service_assert(
	'real service title is kept',
	! nh_service_document_title_is_empty( 'Services – Norhage', 'Norhage' )
);
nh_service_assert(
	'singular service title is kept',
	! nh_service_document_title_is_empty( 'Greenhouse Installation Services – Norhage', 'Norhage' )
);

$template_desc = 'Explore Norhage’s expert engineering services – Greenhouse Installation Services';
nh_service_assert(
	'yoast template around the title is thin',
	nh_service_metadesc_is_thin( $template_desc, 'Greenhouse Installation Services' )
);
nh_service_assert(
	'dangling archive description is thin',
	nh_service_metadesc_is_thin( 'Explore Norhage’s expert engineering services –', 'Services' )
);
$custom = 'Norhage installs greenhouses on prepared sites across the EU, including foundations, glazing, and handover. Greenhouse Installation Services include a written scope before work starts on site today.';
nh_service_assert(
	'hand-written description is kept',
	! nh_service_metadesc_is_thin( $custom, 'Greenhouse Installation Services' )
);
nh_service_assert(
	'thin description is replaced with on-page text',
	nh_service_replacement_metadesc( $template_desc, 'Greenhouse Installation Services', 'Elevate your property.' ) === 'Elevate your property.'
);
nh_service_assert(
	'custom description is not replaced',
	nh_service_replacement_metadesc( $custom, 'Greenhouse Installation Services', 'Short.' ) === $custom
);

$content = '<h2 class="wp-block-heading">Streamline Your Project</h2><p class="wp-block-paragraph">Elevate your property’s potential with our professional <strong>Greenhouse Installation Services</strong>.</p>';
nh_service_assert(
	'teaser uses the first paragraph, not the heading',
	nh_service_teaser( '', $content, 200 ) === 'Elevate your property’s potential with our professional Greenhouse Installation Services.'
);
nh_service_assert(
	'manual excerpt wins over the first paragraph',
	nh_service_teaser( 'Manual summary', $content, 200 ) === 'Manual summary'
);

$html = $content
	. '<h2 class="wp-block-heading">Why Partner with Our Experts?</h2><ul class="wp-block-list"><li><strong>Precision:</strong> Exact alignment.</li></ul>'
	. '<h2>Why Partner with Our Experts?</h2><p>Duplicate heading.</p>';
$sectioned = nh_service_sectionize_html( $html );

nh_service_assert( 'three h2 sections are created', substr_count( $sectioned['html'], 'class="nh-service-section"' ) === 3 );
nh_service_assert( 'contents lists three headings', count( $sectioned['toc'] ) === 3 );
nh_service_assert( 'strong text is preserved', strpos( $sectioned['html'], '<strong>Greenhouse Installation Services</strong>' ) !== false );
nh_service_assert( 'list markup is preserved', strpos( $sectioned['html'], '<li><strong>Precision:</strong>' ) !== false );
nh_service_assert(
	'duplicate heading ids get a suffix',
	$sectioned['toc'][1]['id'] !== $sectioned['toc'][2]['id'] && strpos( $sectioned['toc'][2]['id'], $sectioned['toc'][1]['id'] ) === 0
);
nh_service_assert(
	'each section points at its heading',
	strpos( $sectioned['html'], 'aria-labelledby="' . $sectioned['toc'][0]['id'] . '"' ) !== false
	&& strpos( $sectioned['html'], 'id="' . $sectioned['toc'][0]['id'] . '"' ) !== false
);
nh_service_assert( 'section html has no publish date', strpos( $sectioned['html'], 'datePublished' ) === false );

$wrapped = nh_service_sectionize_html( '<div class="wp-block-group"><h2>One</h2><p>Body</p><h2>Two</h2><p>More</p></div>' );
nh_service_assert( 'grouped headings are still sectioned', count( $wrapped['toc'] ) === 2 );
nh_service_assert( 'group wrapper stays around the sections', strpos( $wrapped['html'], 'wp-block-group' ) !== false );

$toc = nh_service_toc_markup( $sectioned['toc'], 'On this page' );
nh_service_assert( 'contents nav uses the heading text', strpos( $toc, 'Why Partner with Our Experts?' ) !== false );
nh_service_assert( 'a single heading does not get a contents nav', nh_service_toc_markup( array( $sectioned['toc'][0] ), 'On this page' ) === '' );

$band = nh_service_band_markup(
	array(
		'title'      => 'Greenhouse Installation Services',
		'url'        => 'https://norhage.eu/services/greenhouse-installation-services/',
		'anchor'     => 'service-greenhouse-installation-services',
		'teaser'     => 'Elevate your property.',
		'image_html' => '<img src="greenhouse.webp" alt="Greenhouse Installation Services" />',
		'cta'        => 'View Service',
	),
	0
);
nh_service_assert( 'archive band is a service article with an h2', strpos( $band, '<h2' ) !== false && strpos( $band, 'nh-service-band' ) !== false );
nh_service_assert( 'archive band links the service name', strpos( $band, '>Greenhouse Installation Services</a>' ) !== false );
nh_service_assert( 'archive band has an in-page anchor', strpos( $band, 'id="service-greenhouse-installation-services"' ) !== false );
nh_service_assert( 'archive band keeps the image alt', strpos( $band, 'alt="Greenhouse Installation Services"' ) !== false );
nh_service_assert( 'archive band has no date', stripos( $band, 'datePublished' ) === false && stripos( $band, 'posted-on' ) === false && stripos( $band, '<time' ) === false );
nh_service_assert( 'archive band has no author', stripos( $band, 'author' ) === false );

$nav = nh_service_archive_nav_markup(
	array(
		array( 'anchor' => 'service-a', 'title' => 'Greenhouse Installation Services' ),
		array( 'anchor' => 'service-b', 'title' => 'Decking & Patio Installation Services' ),
	),
	'Services'
);
nh_service_assert( 'archive nav is an ordered list of service names', strpos( $nav, '<ol>' ) !== false && strpos( $nav, 'Decking &amp; Patio Installation Services' ) !== false );

$contact = nh_service_contact_panel_markup( array(
	'heading'      => 'Contact',
	'heading_id'   => 'nh-services-contact-title',
	'phone_label'  => 'Phone:',
	'phone'        => '+49 176 65 10 6609',
	'phone_href'   => 'tel:+4917665106609',
	'email_label'  => 'Email:',
	'email'        => 'info@norhage.eu',
	'email_href'   => 'mailto:info@norhage.eu',
	'cta'          => 'Contact',
	'cta_href'     => 'https://norhage.eu/contacts/',
) );
nh_service_assert( 'contact section is a heading plus phone and email', strpos( $contact, '<h2' ) !== false && strpos( $contact, 'tel:+4917665106609' ) !== false && strpos( $contact, 'mailto:info@norhage.eu' ) !== false );

$graph = nh_service_jsonld_graph(
	array(
		'title'       => 'Greenhouse Installation Services',
		'url'         => 'https://norhage.eu/services/greenhouse-installation-services/',
		'description' => 'Elevate your property.',
		'image'       => 'https://norhage.eu/wp-content/uploads/greenhouse.webp',
	),
	array(
		'id'         => 'https://norhage.eu/#organization',
		'name'       => 'Norhage',
		'url'        => 'https://norhage.eu/',
		'areaServed' => 'EU',
	)
);
$encoded = wp_json_encode( $graph );
nh_service_assert( 'single schema is a Service', isset( $graph['@type'] ) && $graph['@type'] === 'Service' );
nh_service_assert( 'single schema has no date or author', strpos( $encoded, 'datePublished' ) === false && strpos( $encoded, 'author' ) === false );
nh_service_assert( 'single schema points at the organization', $graph['provider']['@id'] === 'https://norhage.eu/#organization' );

$list = nh_service_itemlist_jsonld(
	'Services',
	'https://norhage.eu/services/',
	array(
		array( 'title' => 'Greenhouse Installation Services', 'url' => 'https://norhage.eu/services/greenhouse-installation-services/', 'description' => 'Elevate.' ),
		array( 'title' => 'Decking & Patio Installation Services', 'url' => 'https://norhage.eu/services/decking-patio-installation-services/' ),
	)
);
nh_service_assert( 'archive schema is an ItemList of Services', $list['@type'] === 'ItemList' && $list['itemListElement'][0]['item']['@type'] === 'Service' );
nh_service_assert( 'archive schema positions start at 1', $list['itemListElement'][0]['position'] === 1 && $list['itemListElement'][1]['position'] === 2 );
nh_service_assert( 'archive schema counts the services', $list['numberOfItems'] === 2 );
nh_service_assert( 'archive schema has no dates', strpos( wp_json_encode( $list ), 'datePublished' ) === false );

$order = nh_service_orderby();
nh_service_assert( 'service order is menu order, then newest', $order['menu_order'] === 'ASC' && $order['date'] === 'DESC' );

$reordered = nh_service_menu_order_map( array( 10, 20, 30, 40, 50 ), array( 30, 10, 20 ) );
nh_service_assert(
	'dragging the first page leaves later services in place',
	$reordered === array( 30 => 0, 10 => 1, 20 => 2, 40 => 3, 50 => 4 )
);
$second_page = nh_service_menu_order_map( array( 10, 20, 30, 40, 50 ), array( 50, 40 ) );
nh_service_assert(
	'dragging a later page does not move the earlier services',
	$second_page === array( 10 => 0, 20 => 1, 30 => 2, 50 => 3, 40 => 4 )
);
$untouched = nh_service_menu_order_map( array( 10, 20, 20 ), array() );
nh_service_assert( 'an empty drag keeps the current order once', $untouched === array( 10 => 0, 20 => 1 ) );

echo $failures === 0 ? "All service structure tests passed\n" : "{$failures} failed\n";
exit( $failures === 0 ? 0 : 1 );
