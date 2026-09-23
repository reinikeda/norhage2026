<?php
/**
 * About page structure.
 *
 * Pure helpers: no WordPress hooks. Page copy stays as the editor wrote it.
 *
 * @package Astra_Custom_For_Norhage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Slugs used for the about page across the shops.
 *
 * @return string[]
 */
function nh_about_page_slugs() {
	return array( 'about-us', 'about', 'ueber-uns', 'om-os', 'om-oss', 'tietoa-meista', 'apie-mus' );
}

/**
 * Turn a flat about article into a lead, a section list, and a Trustpilot card.
 *
 * Shops that use h2 keep h3 inside those sections. Shops that only use h3,
 * such as Lithuania and Finland, get a section per h3.
 *
 * @param string $html      Rendered page HTML.
 * @param string $nav_label Visible label for the section list. Empty skips the list.
 * @return string
 */
function nh_about_enhance_html( $html, $nav_label = '' ) {
	$html = (string) $html;
	if ( trim( $html ) === '' || strpos( $html, 'nh-about__wrap' ) !== false ) {
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

	$level = nh_about_outline_level( $children );
	if ( $level === '' ) {
		return $html;
	}

	$intro    = array();
	$sections = array();
	$index    = -1;

	foreach ( $children as $child ) {
		if ( $child instanceof DOMElement && strtolower( $child->nodeName ) === 'hr' ) {
			continue;
		}
		if ( nh_about_is_trust_node( $child ) ) {
			$sections[] = array(
				'type'  => 'trust',
				'nodes' => array( $child ),
			);
			$index = count( $sections ) - 1;
			continue;
		}
		if ( $index >= 0 && $sections[ $index ]['type'] === 'trust' ) {
			if ( $child instanceof DOMElement && trim( $child->textContent ) === '' && $child->getElementsByTagName( '*' )->length === 0 ) {
				continue;
			}
			$sections[ $index ]['nodes'][] = $child;
			continue;
		}

		$name = $child->nodeType === XML_ELEMENT_NODE ? strtolower( $child->nodeName ) : '';
		if ( $name === $level ) {
			$sections[] = array(
				'type'  => 'story',
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

	if ( ! $sections ) {
		return $html;
	}

	$wrap = $dom->createElement( 'div' );
	$wrap->setAttribute( 'class', 'nh-about__wrap' );

	$lead_marked = false;
	foreach ( $intro as $node ) {
		if ( ! $lead_marked && $node instanceof DOMElement && strtolower( $node->nodeName ) === 'p' ) {
			nh_about_add_class( $node, 'nh-about__lead' );
			$lead_marked = true;
		}
		if ( $node instanceof DOMElement && strtolower( $node->nodeName ) === 'p' && trim( $node->textContent ) === '' ) {
			continue;
		}
		$wrap->appendChild( $node );
	}

	$layout = $dom->createElement( 'div' );
	$layout->setAttribute( 'class', 'nh-about__layout' );
	$main = $dom->createElement( 'div' );
	$main->setAttribute( 'class', 'nh-about__main' );
	$wrap->appendChild( $layout );
	$layout->appendChild( $main );

	$toc  = array();
	$used = array();
	foreach ( $sections as $section ) {
		$class = $section['type'] === 'trust' ? 'nh-about-trust' : 'nh-about-section';
		$el    = $dom->createElement( 'section' );
		$el->setAttribute( 'class', $class );
		$heading = null;
		foreach ( $section['nodes'] as $node ) {
			if ( $node instanceof DOMElement && strtolower( $node->nodeName ) === 'p' && trim( $node->textContent ) === '' && $node->getElementsByTagName( '*' )->length === 0 ) {
				continue;
			}
			if ( ! $heading && $node instanceof DOMElement && strtolower( $node->nodeName ) === $level ) {
				$heading = $node;
			}
			$el->appendChild( $node );
		}
		if ( $heading instanceof DOMElement ) {
			$text = trim( preg_replace( '/\s+/u', ' ', $heading->textContent ) );
			$id   = $heading->getAttribute( 'id' );
			if ( $id === '' ) {
				$id = nh_about_anchor( $text, $used );
				$heading->setAttribute( 'id', $id );
			}
			$el->setAttribute( 'aria-labelledby', $id );
			$toc[] = array(
				'id'   => $id,
				'text' => $text,
			);
		}
		$main->appendChild( $el );
	}

	$nav = nh_about_nav_element( $dom, $toc, (string) $nav_label );
	if ( $nav instanceof DOMElement ) {
		$layout->insertBefore( $nav, $main );
	}

	while ( $body->firstChild ) {
		$body->removeChild( $body->firstChild );
	}
	$body->appendChild( $wrap );

	$out = '';
	foreach ( $body->childNodes as $child ) {
		$out .= $dom->saveHTML( $child );
	}
	return $out;
}

/**
 * @param DOMNode[] $children Body children.
 * @return string h2, h3, or empty.
 */
function nh_about_outline_level( array $children ) {
	$has_h2 = false;
	$has_h3 = false;
	foreach ( $children as $child ) {
		if ( ! $child instanceof DOMElement ) {
			continue;
		}
		$name = strtolower( $child->nodeName );
		if ( $name === 'h2' ) {
			$has_h2 = true;
		}
		if ( $name === 'h3' ) {
			$has_h3 = true;
		}
	}
	if ( $has_h2 ) {
		return 'h2';
	}
	if ( $has_h3 ) {
		return 'h3';
	}
	return '';
}

/**
 * @param DOMNode $node Node.
 * @return bool
 */
function nh_about_is_trust_node( $node ) {
	if ( ! $node instanceof DOMElement ) {
		return false;
	}
	$class = $node->getAttribute( 'class' );
	if ( strpos( $class, 'wp-block-media-text' ) !== false ) {
		return true;
	}
	$blob = strtolower( $node->getAttribute( 'src' ) . ' ' . $node->getAttribute( 'alt' ) . ' ' . $class );
	if ( strpos( $blob, 'trustpilot' ) !== false ) {
		return true;
	}
	foreach ( $node->getElementsByTagName( 'img' ) as $img ) {
		$img_blob = strtolower( $img->getAttribute( 'src' ) . ' ' . $img->getAttribute( 'alt' ) );
		if ( strpos( $img_blob, 'trustpilot' ) !== false ) {
			return true;
		}
	}
	return false;
}

/**
 * @param DOMDocument            $dom   Document.
 * @param array<int,array{id:string,text:string}> $toc   Sections.
 * @param string                 $label Nav label.
 * @return DOMElement|null
 */
function nh_about_nav_element( DOMDocument $dom, array $toc, $label ) {
	if ( count( $toc ) < 2 || $label === '' ) {
		return null;
	}
	$nav = $dom->createElement( 'nav' );
	$nav->setAttribute( 'class', 'nh-about__nav' );
	$nav->setAttribute( 'aria-label', $label );
	$p = $dom->createElement( 'p' );
	$p->setAttribute( 'class', 'nh-about__nav-label' );
	$p->appendChild( $dom->createTextNode( $label ) );
	$nav->appendChild( $p );
	$ol = $dom->createElement( 'ol' );
	foreach ( $toc as $item ) {
		$li = $dom->createElement( 'li' );
		$a  = $dom->createElement( 'a' );
		$a->setAttribute( 'href', '#' . $item['id'] );
		$a->appendChild( $dom->createTextNode( $item['text'] ) );
		$li->appendChild( $a );
		$ol->appendChild( $li );
	}
	$nav->appendChild( $ol );
	return $nav;
}

/**
 * @param DOMElement $el    Element.
 * @param string     $class Class to add.
 */
function nh_about_add_class( DOMElement $el, $class ) {
	$classes = preg_split( '/\s+/', trim( $el->getAttribute( 'class' ) ) );
	$classes = array_values( array_filter( $classes ) );
	if ( ! in_array( $class, $classes, true ) ) {
		$classes[] = $class;
	}
	$el->setAttribute( 'class', implode( ' ', $classes ) );
}

/**
 * @param string   $text Heading text.
 * @param string[] $used Already used ids.
 * @return string
 */
function nh_about_anchor( $text, array &$used ) {
	$base = trim( preg_replace( '/[^\p{L}\p{N}]+/u', '-', $text ), '-' );
	if ( function_exists( 'mb_strtolower' ) ) {
		$base = mb_strtolower( $base, 'UTF-8' );
	} else {
		$base = strtolower( $base );
	}
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
