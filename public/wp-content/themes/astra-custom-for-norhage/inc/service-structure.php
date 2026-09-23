<?php
/**
 * Service landing markup and crawler structure.
 *
 * Pure helpers: no WordPress hooks. Blog posts are out of scope here —
 * dates stay on the post type `post`.
 *
 * @package Astra_Custom_For_Norhage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Services are not articles. Hide author and date for this post type only.
 *
 * @param string $post_type Post type slug.
 * @return bool
 */
function nh_service_post_type_hides_meta( $post_type ) {
	return $post_type === 'service';
}

/**
 * Yoast sometimes stores an archive title that is only the separator plus the site name.
 *
 * @param string $title     Document title.
 * @param string $site_name Site name.
 * @return bool
 */
function nh_service_document_title_is_empty( $title, $site_name ) {
	$plain = html_entity_decode( (string) $title, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$plain = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $plain ) ) );
	if ( $plain === '' ) {
		return true;
	}

	$site = trim( (string) $site_name );
	if ( $site !== '' && strcasecmp( $plain, $site ) === 0 ) {
		return true;
	}

	$stripped = trim( (string) preg_replace( '/^[–—\-\|]\s*/u', '', $plain ) );
	if ( $site !== '' && strcasecmp( $stripped, $site ) === 0 && $stripped !== $plain ) {
		return true;
	}

	return (bool) preg_match( '/^[–—\-\|]\s*$/u', $plain );
}

/**
 * A meta description that is empty, a dangling separator, or a short template around the subject.
 *
 * Hand-written descriptions that add a real sentence around the title are kept.
 *
 * @param string $desc    Current meta description.
 * @param string $subject Page title or archive label.
 * @return bool
 */
function nh_service_metadesc_is_thin( $desc, $subject = '' ) {
	$desc = html_entity_decode( (string) $desc, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$desc = trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $desc ) ) );
	if ( $desc === '' ) {
		return true;
	}

	if ( function_exists( 'nh_seo_is_boilerplate_metadesc' ) && nh_seo_is_boilerplate_metadesc( $desc ) ) {
		return true;
	}

	if ( preg_match( '/[–—\-:|]\s*$/u', $desc ) ) {
		return true;
	}

	$subject = trim( wp_strip_all_tags( (string) $subject ) );
	if ( $subject !== '' && nh_service_stripos( $desc, $subject ) !== false ) {
		$rest = str_ireplace( $subject, '', $desc );
		$rest = trim( $rest, " \t\n\r\0\x0B–—-|:." );
		if ( nh_service_strlen( $rest ) < 90 ) {
			return true;
		}
	}

	return false;
}

/**
 * Replace a thin description. Keep the current text when the replacement is empty.
 *
 * @param string $current     Current description.
 * @param string $subject     Title used to detect templates.
 * @param string $replacement Description built from on-page text.
 * @return string
 */
function nh_service_replacement_metadesc( $current, $subject, $replacement ) {
	if ( ! nh_service_metadesc_is_thin( $current, $subject ) ) {
		return (string) $current;
	}

	$replacement = trim( (string) $replacement );
	return $replacement !== '' ? $replacement : (string) $current;
}

/**
 * Visible card text: manual excerpt, otherwise the first paragraph.
 *
 * @param string $excerpt Manual excerpt.
 * @param string $content Post content HTML.
 * @param int    $limit   Character limit.
 * @return string
 */
function nh_service_teaser( $excerpt, $content, $limit = 200 ) {
	$manual = trim( wp_strip_all_tags( (string) $excerpt ) );
	if ( $manual !== '' ) {
		return nh_service_trim_text( $manual, $limit );
	}

	if ( preg_match( '/<p\b[^>]*>(.*?)<\/p>/is', (string) $content, $match ) ) {
		$text = trim( wp_strip_all_tags( $match[1] ) );
		if ( $text !== '' ) {
			return nh_service_trim_text( $text, $limit );
		}
	}

	return nh_service_plain_summary( '', $content, $limit );
}

/**
 * Plain summary for meta descriptions and Service schema.
 *
 * @param string $excerpt Manual excerpt.
 * @param string $content Post content HTML.
 * @param int    $limit   Character limit.
 * @return string
 */
function nh_service_plain_summary( $excerpt, $content, $limit = 155 ) {
	$text = trim( wp_strip_all_tags( (string) $excerpt ) );
	if ( $text === '' ) {
		$raw  = function_exists( 'strip_shortcodes' ) ? strip_shortcodes( (string) $content ) : (string) $content;
		$text = trim( wp_strip_all_tags( $raw ) );
	}

	$text = trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	return nh_service_trim_text( $text, $limit );
}

/**
 * Split service HTML into sections at each h2 and build a table of contents.
 *
 * @param string $html Post content HTML.
 * @return array{html:string,toc:array<int,array{id:string,text:string,level:int}>}
 */
function nh_service_sectionize_html( $html ) {
	$html = (string) $html;
	$empty = array(
		'html' => $html,
		'toc'  => array(),
	);

	if ( trim( $html ) === '' || stripos( $html, '<h2' ) === false || stripos( $html, 'nh-service-section' ) !== false ) {
		return $empty;
	}

	$dom  = new DOMDocument();
	$prev = libxml_use_internal_errors( true );
	// Let DOMDocument supply <html><body>. A string wrapper would break on </div> in the content.
	$dom->loadHTML( '<?xml encoding="utf-8"?>' . $html );
	libxml_clear_errors();
	libxml_use_internal_errors( $prev );

	$body = $dom->getElementsByTagName( 'body' )->item( 0 );
	$root = $body instanceof DOMElement ? $body : null;
	if ( ! $root ) {
		return $empty;
	}

	$parent = nh_service_heading_parent( $root );
	if ( ! $parent ) {
		return $empty;
	}

	$used      = array();
	$toc       = array();
	$intro     = array();
	$container = $dom->createElement( 'div' );
	$container->setAttribute( 'class', 'nh-service-sections' );
	$current = null;

	$children = array();
	foreach ( $parent->childNodes as $child ) {
		$children[] = $child;
	}

	foreach ( $children as $child ) {
		$is_h2 = $child->nodeType === XML_ELEMENT_NODE && strtolower( $child->nodeName ) === 'h2';
		if ( $is_h2 ) {
			$text = trim( preg_replace( '/\s+/u', ' ', $child->textContent ) );
			$id   = nh_service_unique_anchor( $text, $used, $child->getAttribute( 'id' ) );
			$child->setAttribute( 'id', $id );
			$toc[] = array(
				'id'    => $id,
				'text'  => $text,
				'level' => 2,
			);

			$current = $dom->createElement( 'section' );
			$current->setAttribute( 'class', 'nh-service-section' );
			$current->setAttribute( 'aria-labelledby', $id );
			$container->appendChild( $current );
			$current->appendChild( $child );
			continue;
		}

		if ( $current ) {
			$current->appendChild( $child );
		} else {
			$intro[] = $child;
		}
	}

	if ( ! $container->hasChildNodes() ) {
		return $empty;
	}

	nh_service_stamp_subheading_ids( $container, $used );

	while ( $parent->firstChild ) {
		$parent->removeChild( $parent->firstChild );
	}
	foreach ( $intro as $node ) {
		$parent->appendChild( $node );
	}
	$parent->appendChild( $container );

	$out = '';
	foreach ( $root->childNodes as $child ) {
		$out .= $dom->saveHTML( $child );
	}

	return array(
		'html' => $out,
		'toc'  => $toc,
	);
}

/**
 * In-page contents. Omitted when a page has fewer than two sections.
 *
 * @param array<int,array{id:string,text:string}> $toc    Headings.
 * @param string                                  $label Visible label.
 * @return string
 */
function nh_service_toc_markup( array $toc, $label ) {
	if ( count( $toc ) < 2 ) {
		return '';
	}

	$html = '<nav class="nh-service__toc" aria-label="' . esc_attr( $label ) . '">';
	$html .= '<p class="nh-service__toc-label">' . esc_html( $label ) . '</p><ol>';
	foreach ( $toc as $item ) {
		if ( empty( $item['id'] ) || ! isset( $item['text'] ) ) {
			continue;
		}
		$html .= '<li><a href="#' . esc_attr( $item['id'] ) . '">' . esc_html( $item['text'] ) . '</a></li>';
	}
	$html .= '</ol></nav>';

	return $html;
}

/**
 * Jump list for the services archive.
 *
 * @param array<int,array{anchor:string,title:string}> $services Services.
 * @param string                                       $label    Nav label.
 * @return string
 */
function nh_service_archive_nav_markup( array $services, $label ) {
	if ( count( $services ) < 2 ) {
		return '';
	}

	$html = '<nav class="nh-services__nav" aria-label="' . esc_attr( $label ) . '"><ol>';
	foreach ( $services as $service ) {
		if ( empty( $service['anchor'] ) || empty( $service['title'] ) ) {
			continue;
		}
		$html .= '<li><a href="#' . esc_attr( $service['anchor'] ) . '">' . esc_html( $service['title'] ) . '</a></li>';
	}
	$html .= '</ol></nav>';

	return $html;
}

/**
 * One service on the archive: not a blog card (no author, no date).
 *
 * @param array<string,mixed> $service title, url, anchor, teaser, image_html, cta.
 * @param int                 $index   Zero-based position.
 * @return string
 */
function nh_service_band_markup( array $service, $index = 0 ) {
	$title  = isset( $service['title'] ) ? (string) $service['title'] : '';
	$url    = isset( $service['url'] ) ? (string) $service['url'] : '';
	$anchor = isset( $service['anchor'] ) ? (string) $service['anchor'] : '';
	$teaser = isset( $service['teaser'] ) ? (string) $service['teaser'] : '';
	$image  = isset( $service['image_html'] ) ? (string) $service['image_html'] : '';
	$cta    = isset( $service['cta'] ) ? (string) $service['cta'] : 'View Service';

	$classes = 'nh-service-band';
	if ( $image === '' ) {
		$classes .= ' nh-service-band--no-media';
	}

	$html = '<article class="' . esc_attr( $classes ) . '"';
	if ( $anchor !== '' ) {
		$html .= ' id="' . esc_attr( $anchor ) . '"';
	}
	$html .= '>';

	if ( $image !== '' ) {
		$html .= '<div class="nh-service-band__media">' . $image . '</div>';
	}

	$html .= '<div class="nh-service-band__body">';
	$html .= '<p class="nh-service-band__index" aria-hidden="true">' . esc_html( sprintf( '%02d', (int) $index + 1 ) ) . '</p>';
	$html .= '<h2 class="nh-service-band__title"><a href="' . esc_url( $url ) . '">' . esc_html( $title ) . '</a></h2>';
	if ( $teaser !== '' ) {
		$html .= '<p class="nh-service-band__teaser">' . esc_html( $teaser ) . '</p>';
	}
	$html .= '<a class="nh-service-band__cta" href="' . esc_url( $url ) . '" aria-label="' . esc_attr( $cta . ': ' . $title ) . '">' . esc_html( $cta ) . '</a>';
	$html .= '</div></article>';

	return $html;
}

/**
 * Compact related-service card.
 *
 * @param array<string,mixed> $service title, url, teaser, image_html.
 * @return string
 */
function nh_service_related_card_markup( array $service ) {
	$title  = isset( $service['title'] ) ? (string) $service['title'] : '';
	$url    = isset( $service['url'] ) ? (string) $service['url'] : '';
	$teaser = isset( $service['teaser'] ) ? (string) $service['teaser'] : '';
	$image  = isset( $service['image_html'] ) ? (string) $service['image_html'] : '';

	$html = '<article class="nh-service-card">';
	if ( $image !== '' ) {
		$html .= '<div class="nh-service-card__media">' . $image . '</div>';
	}
	$html .= '<h3 class="nh-service-card__title"><a href="' . esc_url( $url ) . '">' . esc_html( $title ) . '</a></h3>';
	if ( $teaser !== '' ) {
		$html .= '<p class="nh-service-card__teaser">' . esc_html( $teaser ) . '</p>';
	}
	$html .= '</article>';

	return $html;
}

/**
 * Back link. Blog singles keep their own "Back to Blog" markup.
 *
 * @param string $url   Archive URL.
 * @param string $label Link text.
 * @return string
 */
function nh_service_backlink_markup( $url, $label ) {
	if ( $url === '' ) {
		return '';
	}

	return '<nav class="nh-backbar" aria-label="' . esc_attr( $label ) . '"><a class="nh-backbar__link" href="' . esc_url( $url ) . '"><span aria-hidden="true">←</span> ' . esc_html( $label ) . '</a></nav>';
}

/**
 * Phone, email, and contact-page actions for a long service page.
 *
 * @param array<string,string> $contact phone, phone_href, email, email_href, cta, cta_href.
 * @return string
 */
function nh_service_contact_bar_markup( array $contact ) {
	$phone = isset( $contact['phone'] ) ? (string) $contact['phone'] : '';
	$email = isset( $contact['email'] ) ? (string) $contact['email'] : '';
	$cta   = isset( $contact['cta'] ) ? (string) $contact['cta'] : '';
	$url   = isset( $contact['cta_href'] ) ? (string) $contact['cta_href'] : '';

	if ( $phone === '' && $email === '' && $url === '' ) {
		return '';
	}

	$html = '<div class="nh-service__quick">';
	if ( $phone !== '' ) {
		$html .= '<a class="nh-service__quick-link" href="' . esc_url( $contact['phone_href'] ) . '">' . esc_html( $phone ) . '</a>';
	}
	if ( $email !== '' ) {
		$html .= '<a class="nh-service__quick-link" href="' . esc_url( $contact['email_href'] ) . '">' . esc_html( $email ) . '</a>';
	}
	if ( $cta !== '' && $url !== '' ) {
		$html .= '<a class="nh-service__quick-link nh-service__quick-link--solid" href="' . esc_url( $url ) . '">' . esc_html( $cta ) . '</a>';
	}
	$html .= '</div>';

	return $html;
}

/**
 * Contact section. Heading is an existing translated label, not new sales copy.
 *
 * @param array<string,string> $contact heading, heading_id, phone_label, phone, phone_href, email_label, email, email_href, cta, cta_href.
 * @return string
 */
function nh_service_contact_panel_markup( array $contact ) {
	$heading_id = isset( $contact['heading_id'] ) ? (string) $contact['heading_id'] : 'nh-service-contact-title';
	$heading    = isset( $contact['heading'] ) ? (string) $contact['heading'] : '';
	$phone      = isset( $contact['phone'] ) ? (string) $contact['phone'] : '';
	$email      = isset( $contact['email'] ) ? (string) $contact['email'] : '';

	$html  = '<section class="nh-service-contact" aria-labelledby="' . esc_attr( $heading_id ) . '">';
	$html .= '<h2 id="' . esc_attr( $heading_id ) . '">' . esc_html( $heading ) . '</h2><ul class="nh-service-contact__list">';
	if ( $phone !== '' ) {
		$html .= '<li><span class="nh-service-contact__label">' . esc_html( $contact['phone_label'] ) . '</span> <a href="' . esc_url( $contact['phone_href'] ) . '">' . esc_html( $phone ) . '</a></li>';
	}
	if ( $email !== '' ) {
		$html .= '<li><span class="nh-service-contact__label">' . esc_html( $contact['email_label'] ) . '</span> <a href="' . esc_url( $contact['email_href'] ) . '">' . esc_html( $email ) . '</a></li>';
	}
	$html .= '</ul>';
	if ( ! empty( $contact['cta'] ) && ! empty( $contact['cta_href'] ) ) {
		$html .= '<a class="nh-service__cta" href="' . esc_url( $contact['cta_href'] ) . '">' . esc_html( $contact['cta'] ) . '</a>';
	}
	$html .= '</section>';

	return $html;
}

/**
 * Service JSON-LD. No datePublished and no author — those belong on blog posts.
 *
 * @param array<string,string> $service  title, url, description, image.
 * @param array<string,string> $provider id, name, url, areaServed.
 * @return array<string,mixed>
 */
function nh_service_jsonld_graph( array $service, array $provider = array() ) {
	$graph = array(
		'@context'          => 'https://schema.org',
		'@type'             => 'Service',
		'name'              => isset( $service['title'] ) ? (string) $service['title'] : '',
		'url'               => isset( $service['url'] ) ? (string) $service['url'] : '',
		'mainEntityOfPage'  => isset( $service['url'] ) ? (string) $service['url'] : '',
		'serviceType'       => isset( $service['title'] ) ? (string) $service['title'] : '',
	);

	if ( ! empty( $service['description'] ) ) {
		$graph['description'] = (string) $service['description'];
	}
	if ( ! empty( $service['image'] ) ) {
		$graph['image'] = (string) $service['image'];
	}
	if ( ! empty( $provider['name'] ) ) {
		$org = array(
			'@type' => 'Organization',
			'name'  => (string) $provider['name'],
		);
		if ( ! empty( $provider['id'] ) ) {
			$org['@id'] = (string) $provider['id'];
		}
		if ( ! empty( $provider['url'] ) ) {
			$org['url'] = (string) $provider['url'];
		}
		$graph['provider'] = $org;
	}
	if ( ! empty( $provider['areaServed'] ) ) {
		$graph['areaServed'] = (string) $provider['areaServed'];
	}

	return $graph;
}

/**
 * ItemList of services for the archive.
 *
 * @param string               $name  List name.
 * @param string               $url   Archive URL.
 * @param array<int,array>     $items title, url, description, image.
 * @param array<string,string> $provider Provider shared by each Service.
 * @return array<string,mixed>
 */
function nh_service_itemlist_jsonld( $name, $url, array $items, array $provider = array() ) {
	$elements = array();
	$position = 1;

	foreach ( $items as $item ) {
		if ( empty( $item['url'] ) || empty( $item['title'] ) ) {
			continue;
		}
		$service = nh_service_jsonld_graph( $item, $provider );
		unset( $service['@context'] );

		$elements[] = array(
			'@type'    => 'ListItem',
			'position' => $position,
			'name'     => (string) $item['title'],
			'url'      => (string) $item['url'],
			'item'     => $service,
		);
		$position++;
	}

	return array(
		'@context'        => 'https://schema.org',
		'@type'           => 'ItemList',
		'name'            => (string) $name,
		'url'             => (string) $url,
		'numberOfItems'   => count( $elements ),
		'itemListElement' => $elements,
	);
}

/**
 * Parent whose direct children include the h2 flow.
 *
 * @param DOMElement $root Wrapper.
 * @return DOMElement|null
 */
function nh_service_heading_parent( DOMElement $root ) {
	foreach ( $root->childNodes as $child ) {
		if ( $child->nodeType === XML_ELEMENT_NODE && strtolower( $child->nodeName ) === 'h2' ) {
			return $root;
		}
	}

	$h2s = $root->getElementsByTagName( 'h2' );
	if ( $h2s->length < 1 ) {
		return null;
	}

	$parent = $h2s->item( 0 )->parentNode;
	return $parent instanceof DOMElement ? $parent : null;
}

/**
 * Give h3 elements stable fragment ids without adding them to the contents list.
 *
 * @param DOMElement           $container Sections wrapper.
 * @param array<string,bool>   $used      Ids already taken.
 */
function nh_service_stamp_subheading_ids( DOMElement $container, array &$used ) {
	$nodes = array();
	foreach ( $container->getElementsByTagName( 'h3' ) as $h3 ) {
		$nodes[] = $h3;
	}
	foreach ( $nodes as $h3 ) {
		$text = trim( preg_replace( '/\s+/u', ' ', $h3->textContent ) );
		$id   = nh_service_unique_anchor( $text, $used, $h3->getAttribute( 'id' ) );
		$h3->setAttribute( 'id', $id );
	}
}

/**
 * Unique fragment id from heading text.
 *
 * @param string             $text     Heading text.
 * @param array<string,bool> $used     Ids already used.
 * @param string             $existing Existing id attribute.
 * @return string
 */
function nh_service_unique_anchor( $text, array &$used, $existing = '' ) {
	$existing = trim( (string) $existing );
	if ( $existing !== '' && ! isset( $used[ $existing ] ) ) {
		$used[ $existing ] = true;
		return $existing;
	}

	$base = nh_service_anchor_base( $text );
	$id   = $base;
	$n    = 2;
	while ( isset( $used[ $id ] ) ) {
		$id = $base . '-' . $n;
		$n++;
	}
	$used[ $id ] = true;
	return $id;
}

/**
 * @param string $text Heading text.
 * @return string
 */
function nh_service_anchor_base( $text ) {
	$text = html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	if ( function_exists( 'remove_accents' ) ) {
		$text = remove_accents( $text );
	}
	if ( function_exists( 'sanitize_title' ) ) {
		$base = sanitize_title( $text );
	} else {
		$base = strtolower( (string) $text );
		$base = preg_replace( '/[^a-z0-9]+/', '-', $base );
		$base = trim( (string) $base, '-' );
	}

	return $base !== '' ? $base : 'section';
}

/**
 * @param string $text  Text.
 * @param int    $limit Max characters.
 * @return string
 */
function nh_service_trim_text( $text, $limit ) {
	$text  = trim( (string) $text );
	$limit = (int) $limit;
	if ( $text === '' || $limit < 1 ) {
		return '';
	}
	if ( function_exists( 'wp_html_excerpt' ) ) {
		return wp_html_excerpt( $text, $limit, '…' );
	}
	if ( nh_service_strlen( $text ) <= $limit ) {
		return $text;
	}
	return nh_service_substr( $text, 0, $limit ) . '…';
}

/**
 * @param string $haystack Haystack.
 * @param string $needle   Needle.
 * @return int|false
 */
function nh_service_stripos( $haystack, $needle ) {
	if ( function_exists( 'mb_stripos' ) ) {
		return mb_stripos( $haystack, $needle, 0, 'UTF-8' );
	}
	return stripos( $haystack, $needle );
}

/**
 * @param string $text Text.
 * @return int
 */
function nh_service_strlen( $text ) {
	if ( function_exists( 'mb_strlen' ) ) {
		return (int) mb_strlen( $text, 'UTF-8' );
	}
	return strlen( $text );
}

/**
 * @param string $text  Text.
 * @param int    $start Start.
 * @param int    $len   Length.
 * @return string
 */
function nh_service_substr( $text, $start, $len ) {
	if ( function_exists( 'mb_substr' ) ) {
		return (string) mb_substr( $text, $start, $len, 'UTF-8' );
	}
	return substr( $text, $start, $len );
}

/**
 * Frontend and admin list order for services.
 *
 * menu_order is the editor's drag order. Date only breaks ties until that order is saved.
 *
 * @return array<string,string>
 */
function nh_service_orderby() {
	return array(
		'menu_order' => 'ASC',
		'date'       => 'DESC',
	);
}

/**
 * Apply a dragged page of service IDs to the full list and number menu_order from zero.
 *
 * IDs that are not on the dragged page stay in place. The page is written into those slots
 * in the new order, so page 2 can be sorted without moving page 1.
 *
 * @param int[] $full_ids Services in the current frontend order.
 * @param int[] $page_ids Services on the admin screen, in the dropped order.
 * @return array<int,int> Post ID => menu_order.
 */
function nh_service_menu_order_map( array $full_ids, array $page_ids ) {
	$full = array();
	foreach ( $full_ids as $id ) {
		$id = (int) $id;
		if ( $id > 0 ) {
			$full[] = $id;
		}
	}

	$page = array();
	foreach ( $page_ids as $id ) {
		$id = (int) $id;
		if ( $id > 0 && ! isset( $page[ $id ] ) ) {
			$page[ $id ] = true;
		}
	}

	$in_full = array_fill_keys( $full, true );
	$on_page = array();
	foreach ( array_keys( $page ) as $id ) {
		if ( isset( $in_full[ $id ] ) ) {
			$on_page[ $id ] = true;
		}
	}

	$slots = array();
	foreach ( $full as $index => $id ) {
		if ( isset( $on_page[ $id ] ) ) {
			$slots[] = $index;
		}
	}

	$ordered = $full;
	$slot_i  = 0;
	foreach ( array_keys( $page ) as $id ) {
		if ( ! isset( $on_page[ $id ] ) || ! isset( $slots[ $slot_i ] ) ) {
			continue;
		}
		$ordered[ $slots[ $slot_i ] ] = $id;
		$slot_i++;
	}

	$map  = array();
	$seen = array();
	$n    = 0;
	foreach ( $ordered as $id ) {
		if ( isset( $seen[ $id ] ) ) {
			continue;
		}
		$seen[ $id ] = true;
		$map[ $id ]  = $n++;
	}

	return $map;
}
