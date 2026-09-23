<?php
/**
 * Blog meta cleanup.
 *
 * Pure helper: no WordPress hooks. Dates stay. The author prefix is removed
 * in whatever language Astra printed it.
 *
 * @package Astra_Custom_For_Norhage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Drop the author from an Astra meta line and keep the date.
 *
 * @param string $markup Entry meta HTML.
 * @return string
 */
function nh_blog_clean_meta( $markup ) {
	$markup = (string) $markup;
	if ( trim( $markup ) === '' ) {
		return $markup;
	}

	$doc    = new DOMDocument();
	$prev   = libxml_use_internal_errors( true );
	$loaded = $doc->loadHTML(
		'<?xml encoding="utf-8" ?><div id="nh-blog-meta">' . $markup . '</div>',
		LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
	);
	libxml_clear_errors();
	libxml_use_internal_errors( $prev );

	if ( ! $loaded ) {
		return $markup;
	}

	$root = $doc->getElementById( 'nh-blog-meta' );
	if ( ! $root ) {
		return $markup;
	}

	$xpath   = new DOMXPath( $doc );
	$authors = array();
	foreach ( $xpath->query( './/*[contains(concat(" ", normalize-space(@class), " "), " posted-by ")]', $root ) as $node ) {
		$authors[] = $node;
	}

	foreach ( $authors as $node ) {
		if ( ! $node->parentNode ) {
			continue;
		}
		nh_blog_remove_meta_neighbors( $node );
		$node->parentNode->removeChild( $node );
	}

	$scopes = array();
	foreach ( $xpath->query( './/*[contains(concat(" ", normalize-space(@class), " "), " entry-meta ")]', $root ) as $node ) {
		$scopes[] = $node;
	}
	if ( empty( $scopes ) ) {
		$scopes[] = $root;
	}
	foreach ( $scopes as $scope ) {
		nh_blog_trim_leading_meta_label( $scope );
	}

	$html = '';
	foreach ( $root->childNodes as $child ) {
		$html .= $doc->saveHTML( $child );
	}

	return $html;
}

/**
 * Remove the author prefix and the separator that sits against the author node.
 *
 * @param DOMNode $node Author element.
 */
function nh_blog_remove_meta_neighbors( $node ) {
	$parent = $node->parentNode;
	if ( ! $parent ) {
		return;
	}

	while ( $node->previousSibling ) {
		$prev = $node->previousSibling;
		if ( nh_blog_meta_node_is_author_chrome( $prev ) ) {
			$parent->removeChild( $prev );
			continue;
		}
		break;
	}

	while ( $node->nextSibling ) {
		$next = $node->nextSibling;
		if ( $next->nodeType === XML_ELEMENT_NODE && nh_blog_element_is_separator( $next ) ) {
			$parent->removeChild( $next );
			continue;
		}
		if ( $next->nodeType === XML_TEXT_NODE && nh_blog_text_is_separator( $next->nodeValue ) ) {
			$parent->removeChild( $next );
			continue;
		}
		break;
	}
}

/**
 * A text or element node that belongs to the author chunk, not the date.
 *
 * @param DOMNode $node Sibling of the author element.
 * @return bool
 */
function nh_blog_meta_node_is_author_chrome( $node ) {
	if ( $node->nodeType === XML_TEXT_NODE ) {
		return nh_blog_text_is_separator( $node->nodeValue ) || nh_blog_text_is_author_label( $node->nodeValue );
	}
	if ( $node->nodeType === XML_ELEMENT_NODE ) {
		return nh_blog_element_is_separator( $node ) || nh_blog_element_has_class( $node, 'ast-author-avatar' );
	}
	return false;
}

/**
 * Drop a leftover "By /" when the author element was already empty.
 *
 * @param DOMElement $scope Meta container.
 */
function nh_blog_trim_leading_meta_label( $scope ) {
	while ( $scope->firstChild ) {
		$child = $scope->firstChild;
		if ( $child->nodeType !== XML_TEXT_NODE ) {
			break;
		}
		if ( ! nh_blog_text_is_separator( $child->nodeValue ) && ! nh_blog_text_is_author_label( $child->nodeValue ) ) {
			break;
		}
		$scope->removeChild( $child );
	}
}

/**
 * @param string $value Text node.
 * @return bool
 */
function nh_blog_text_is_separator( $value ) {
	$value = trim( preg_replace( '/\s+/u', ' ', (string) $value ) );
	if ( $value === '' ) {
		return true;
	}
	return (bool) preg_match( '/^[\/|·•\-–—]+$/u', $value );
}

/**
 * Astra's author prefix ("By", "Von", "Av", …) is a short label with no digits.
 *
 * @param string $value Text node.
 * @return bool
 */
function nh_blog_text_is_author_label( $value ) {
	$value = trim( preg_replace( '/\s+/u', ' ', (string) $value ) );
	if ( $value === '' || preg_match( '/\d/u', $value ) ) {
		return false;
	}
	return mb_strlen( $value ) <= 24;
}

/**
 * @param DOMElement $element Element.
 * @return bool
 */
function nh_blog_element_is_separator( $element ) {
	return nh_blog_element_has_class( $element, 'ast-meta-separator' );
}

/**
 * @param DOMElement $element Element.
 * @param string     $class   Class token.
 * @return bool
 */
function nh_blog_element_has_class( $element, $class ) {
	$tokens = preg_split( '/\s+/', (string) $element->getAttribute( 'class' ) );
	return in_array( $class, $tokens, true );
}
