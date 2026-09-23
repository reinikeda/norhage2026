<?php
/**
 * Contact page structure.
 *
 * Pure helpers: no WordPress hooks. Page copy stays as the editor wrote it.
 *
 * @package Astra_Custom_For_Norhage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Slugs used for the contact page across the shops.
 *
 * @return string[]
 */
function nh_contact_page_slugs() {
	return array( 'contacts', 'contact', 'kontakt', 'kontakter', 'yhteystiedot', 'kontaktai' );
}

/**
 * Turn the flat contact article into a lead, reach cards, a form, and social links.
 *
 * Phone and email stay the links already in the page, including Cloudflare's
 * email protection markup. Company and address are added only when that text
 * is not already in the article.
 *
 * @param string               $html    Rendered page HTML.
 * @param array<string,string> $details Optional company and address.
 * @return string
 */
function nh_contact_enhance_html( $html, array $details = array() ) {
	$html = (string) $html;
	if ( trim( $html ) === '' || strpos( $html, 'nh-contact__layout' ) !== false ) {
		return $html;
	}

	$dom  = new DOMDocument();
	$prev = libxml_use_internal_errors( true );
	$dom->loadHTML( '<?xml encoding="utf-8"?>' . $html );
	libxml_clear_errors();
	libxml_use_internal_errors( $prev );

	$body = $dom->getElementsByTagName( 'body' )->item( 0 );
	if ( ! $body instanceof DOMElement ) {
		return $html;
	}

	$children = array();
	foreach ( $body->childNodes as $child ) {
		$children[] = $child;
	}

	$intro    = array();
	$sections = array();
	$index    = -1;

	foreach ( $children as $child ) {
		$name = $child->nodeType === XML_ELEMENT_NODE ? strtolower( $child->nodeName ) : '';
		if ( $name === 'h2' || ( $name === 'h3' && $index >= 0 ) ) {
			$sections[] = array(
				'type'  => $name === 'h3' ? 'social' : 'section',
				'nodes' => array( $child ),
			);
			$index = count( $sections ) - 1;
			continue;
		}

		if ( $index >= 0 ) {
			$sections[ $index ]['nodes'][] = $child;
		} else {
			$intro[] = $child;
		}
	}

	if ( ! $sections && ! $intro ) {
		return $html;
	}

	foreach ( $sections as $index => $section ) {
		if ( $section['type'] === 'social' ) {
			continue;
		}
		if ( nh_contact_nodes_have_form( $section['nodes'] ) ) {
			$sections[ $index ]['type'] = 'form';
		} elseif ( $section['type'] === 'section' ) {
			$sections[ $index ]['type'] = 'reach';
		}
	}

	$lead_marked = false;
	foreach ( $intro as $node ) {
		if ( ! $lead_marked && $node instanceof DOMElement && strtolower( $node->nodeName ) === 'p' ) {
			nh_contact_add_class( $node, 'nh-contact__lead' );
			$lead_marked = true;
		}
	}

	$reach = null;
	foreach ( $sections as $index => $section ) {
		if ( $section['type'] !== 'reach' ) {
			continue;
		}
		$reach = $index;
		$sections[ $index ]['nodes'] = nh_contact_channelize_nodes( $dom, $section['nodes'] );
		break;
	}

	$company = isset( $details['company'] ) ? trim( (string) $details['company'] ) : '';
	$address = isset( $details['address'] ) ? trim( (string) $details['address'] ) : '';
	$plain   = trim( preg_replace( '/\s+/u', ' ', $body->textContent ) );
	$extra   = array();
	if ( $company !== '' && nh_contact_missing_text( $plain, $company ) ) {
		$extra[] = array(
			'label' => isset( $details['company_label'] ) ? (string) $details['company_label'] : '',
			'value' => $company,
		);
	}
	if ( $address !== '' && nh_contact_missing_text( $plain, $address ) ) {
		$extra[] = array(
			'label' => isset( $details['address_label'] ) ? (string) $details['address_label'] : '',
			'value' => $address,
		);
	}
	if ( $extra ) {
		$card = nh_contact_static_channels( $dom, $extra );
		if ( $reach !== null ) {
			$sections[ $reach ]['nodes'][] = $card;
		} else {
			array_unshift(
				$sections,
				array(
					'type'  => 'reach',
					'nodes' => array( $card ),
				)
			);
		}
	}

	$layout = $dom->createElement( 'div' );
	$layout->setAttribute( 'class', 'nh-contact__layout' );

	foreach ( $intro as $node ) {
		$layout->appendChild( $node );
	}

	$used = array();
	foreach ( $sections as $section ) {
		$el = $dom->createElement( 'section' );
		$el->setAttribute( 'class', 'nh-contact-section nh-contact-section--' . $section['type'] );
		$heading = null;
		foreach ( $section['nodes'] as $node ) {
			if ( ! $heading && $node instanceof DOMElement && in_array( strtolower( $node->nodeName ), array( 'h2', 'h3' ), true ) ) {
				$heading = $node;
			}
			if ( $node instanceof DOMElement && strtolower( $node->nodeName ) === 'hr' ) {
				continue;
			}
			if ( $node instanceof DOMElement && nh_contact_element_is_empty( $node ) ) {
				continue;
			}
			$el->appendChild( $node );
		}
		if ( $heading instanceof DOMElement ) {
			$text = trim( preg_replace( '/\s+/u', ' ', $heading->textContent ) );
			$id   = $heading->getAttribute( 'id' );
			if ( $id === '' ) {
				$id = nh_contact_anchor( $text, $used );
				$heading->setAttribute( 'id', $id );
			}
			$el->setAttribute( 'aria-labelledby', $id );
		}
		if ( $section['type'] === 'social' ) {
			foreach ( $el->getElementsByTagName( 'ul' ) as $ul ) {
				nh_contact_add_class( $ul, 'nh-contact-social' );
				break;
			}
		}
		$layout->appendChild( $el );
	}

	// Phone and email first, then the form, then social links.
	$ordered = array();
	foreach ( array( 'reach', 'form', 'social' ) as $type ) {
		$matches = array();
		foreach ( $layout->childNodes as $node ) {
			if ( $node instanceof DOMElement && strpos( ' ' . $node->getAttribute( 'class' ) . ' ', ' nh-contact-section--' . $type . ' ' ) !== false ) {
				$matches[] = $node;
			}
		}
		foreach ( $matches as $node ) {
			$ordered[] = $node;
		}
	}
	foreach ( $ordered as $node ) {
		$layout->appendChild( $node );
	}

	while ( $body->firstChild ) {
		$body->removeChild( $body->firstChild );
	}
	$body->appendChild( $layout );

	$out = '';
	foreach ( $body->childNodes as $child ) {
		$out .= $dom->saveHTML( $child );
	}
	return $out;
}

/**
 * @param DOMNode[] $nodes Nodes.
 * @return bool
 */
function nh_contact_nodes_have_form( array $nodes ) {
	foreach ( $nodes as $node ) {
		if ( nh_contact_node_has_class( $node, 'wpforms-container' ) ) {
			return true;
		}
	}
	return false;
}

/**
 * @param DOMNode $node  Node.
 * @param string  $class Class name.
 * @return bool
 */
function nh_contact_node_has_class( $node, $class ) {
	if ( $node instanceof DOMElement ) {
		$classes = preg_split( '/\s+/', $node->getAttribute( 'class' ) );
		if ( in_array( $class, $classes, true ) ) {
			return true;
		}
	}
	if ( ! $node->hasChildNodes() ) {
		return false;
	}
	foreach ( $node->childNodes as $child ) {
		if ( nh_contact_node_has_class( $child, $class ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Replace email and phone paragraphs with tappable channel cards.
 *
 * @param DOMDocument $dom   Document.
 * @param DOMNode[]   $nodes Section nodes.
 * @return DOMNode[]
 */
function nh_contact_channelize_nodes( DOMDocument $dom, array $nodes ) {
	$out = array();
	foreach ( $nodes as $node ) {
		if ( $node instanceof DOMElement && strtolower( $node->nodeName ) === 'p' ) {
			$channels = nh_contact_channels_from_paragraph( $dom, $node );
			if ( $channels instanceof DOMElement ) {
				$out[] = $channels;
				continue;
			}
		}
		$out[] = $node;
	}
	return $out;
}

/**
 * @param DOMDocument $dom Document.
 * @param DOMElement  $p   Paragraph that may contain contact links.
 * @return DOMElement|null
 */
function nh_contact_channels_from_paragraph( DOMDocument $dom, DOMElement $p ) {
	$links = array();
	foreach ( $p->getElementsByTagName( 'a' ) as $anchor ) {
		$links[] = $anchor;
	}
	if ( ! $links ) {
		return null;
	}

	$wrap = $dom->createElement( 'div' );
	$wrap->setAttribute( 'class', 'nh-contact-channels' );

	// Labels belong to the link that follows them, so walk in order.
	$pending = '';
	$kids    = array();
	foreach ( $p->childNodes as $child ) {
		$kids[] = $child;
	}
	foreach ( $kids as $child ) {
		if ( $child instanceof DOMElement && strtolower( $child->nodeName ) === 'strong' ) {
			$pending = trim( preg_replace( '/\s+/u', ' ', $child->textContent ) );
			continue;
		}
		if ( ! $child instanceof DOMElement || strtolower( $child->nodeName ) !== 'a' ) {
			continue;
		}
		$card = $dom->createElement( 'a' );
		$card->setAttribute( 'class', 'nh-contact-channel' );
		$href = $child->getAttribute( 'href' );
		if ( $href !== '' ) {
			$card->setAttribute( 'href', $href );
		}
		if ( $pending !== '' ) {
			$span = $dom->createElement( 'span' );
			$span->setAttribute( 'class', 'nh-contact-channel__label' );
			$span->appendChild( $dom->createTextNode( $pending ) );
			$card->appendChild( $span );
			$pending = '';
		}
		$value = $dom->createElement( 'span' );
		$value->setAttribute( 'class', 'nh-contact-channel__value' );
		while ( $child->firstChild ) {
			$value->appendChild( $child->firstChild );
		}
		$card->appendChild( $value );
		$wrap->appendChild( $card );
	}

	if ( ! $wrap->hasChildNodes() ) {
		return null;
	}
	return $wrap;
}

/**
 * @param DOMDocument              $dom   Document.
 * @param array<int,array{label:string,value:string}> $rows Rows.
 * @return DOMElement
 */
function nh_contact_static_channels( DOMDocument $dom, array $rows ) {
	$wrap = $dom->createElement( 'div' );
	$wrap->setAttribute( 'class', 'nh-contact-channels nh-contact-channels--place' );
	foreach ( $rows as $row ) {
		$card = $dom->createElement( 'div' );
		$card->setAttribute( 'class', 'nh-contact-channel nh-contact-channel--static' );
		if ( $row['label'] !== '' ) {
			$span = $dom->createElement( 'span' );
			$span->setAttribute( 'class', 'nh-contact-channel__label' );
			$span->appendChild( $dom->createTextNode( $row['label'] ) );
			$card->appendChild( $span );
		}
		$value = $dom->createElement( 'span' );
		$value->setAttribute( 'class', 'nh-contact-channel__value' );
		$value->appendChild( $dom->createTextNode( $row['value'] ) );
		$card->appendChild( $value );
		$wrap->appendChild( $card );
	}
	return $wrap;
}

/**
 * @param string $haystack Visible text.
 * @param string $needle   Detail to add.
 * @return bool
 */
function nh_contact_missing_text( $haystack, $needle ) {
	$needle = trim( $needle );
	if ( $needle === '' ) {
		return false;
	}
	if ( function_exists( 'mb_stripos' ) ) {
		return mb_stripos( $haystack, $needle, 0, 'UTF-8' ) === false;
	}
	return stripos( $haystack, $needle ) === false;
}

/**
 * @param DOMElement $el    Element.
 * @param string     $class Class to add.
 */
function nh_contact_add_class( DOMElement $el, $class ) {
	$classes = preg_split( '/\s+/', trim( $el->getAttribute( 'class' ) ) );
	$classes = array_values( array_filter( $classes ) );
	if ( ! in_array( $class, $classes, true ) ) {
		$classes[] = $class;
	}
	$el->setAttribute( 'class', implode( ' ', $classes ) );
}

/**
 * @param DOMElement $el Element.
 * @return bool
 */
function nh_contact_element_is_empty( DOMElement $el ) {
	if ( strtolower( $el->nodeName ) !== 'p' ) {
		return false;
	}
	return trim( $el->textContent ) === '' && $el->getElementsByTagName( '*' )->length === 0;
}

/**
 * @param string   $text Heading text.
 * @param string[] $used Already used ids.
 * @return string
 */
function nh_contact_anchor( $text, array &$used ) {
	$base = strtolower( trim( preg_replace( '/[^a-z0-9]+/i', '-', $text ), '-' ) );
	if ( $base === '' ) {
		$base = 'section';
	}
	$id = $base;
	$n  = 2;
	while ( isset( $used[ $id ] ) ) {
		$id = $base . '-' . $n;
		$n++;
	}
	$used[ $id ] = true;
	return $id;
}
