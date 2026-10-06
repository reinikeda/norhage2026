<?php
/**
 * CLI tests: WooCommerce FAQ editor.
 *
 * Run: php public/wp-content/themes/astra-custom-for-norhage/tests/test-faq-admin.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/' );
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		unset( $domain );
		return $text;
	}
}

if ( ! function_exists( '_n' ) ) {
	function _n( $single, $plural, $number, $domain = 'default' ) {
		unset( $domain );
		return 1 === (int) $number ? $single : $plural;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		unset( $hook, $callback, $priority, $accepted_args );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $text ) {
		return trim( preg_replace( '/\s+/', ' ', strip_tags( (string) $text ) ) );
	}
}

if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $title ) {
		$title = strtolower( trim( (string) $title ) );
		$title = preg_replace( '/[^a-z0-9_-]+/', '-', $title );
		return trim( $title, '-' );
	}
}

if ( ! function_exists( 'wp_kses' ) ) {
	function wp_kses( $content, $allowed ) {
		unset( $allowed );
		return strip_tags( (string) $content, '<a><strong><em><br>' );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text ) {
		return trim( strip_tags( (string) $text ) );
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}

$GLOBALS['nh_faq_option'] = null;

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $key, $default = false ) {
		if ( 'nh_theme_faq_content' === $key && null !== $GLOBALS['nh_faq_option'] ) {
			return $GLOBALS['nh_faq_option'];
		}

		return $default;
	}
}

require_once dirname( __DIR__ ) . '/inc/faq-data.php';
require_once dirname( __DIR__ ) . '/inc/faq-admin.php';

$failures = 0;

function nh_faq_assert( $label, $ok ) {
	global $failures;

	if ( $ok ) {
		echo "ok  {$label}\n";
		return;
	}

	$failures++;
	echo "FAIL  {$label}\n";
}

$items  = nh_theme_faq_items();
$topics = nh_theme_faq_topics();

nh_faq_assert( 'current faq is prefilled', count( $items ) >= 30 );
nh_faq_assert( 'delivery question is present', isset( $items['delivery-large-items'] ) );
nh_faq_assert(
	'delivery question keeps its text',
	isset( $items['delivery-large-items']['question'] ) && 'How are large items delivered?' === $items['delivery-large-items']['question']
);
nh_faq_assert(
	'link answers stay in the prefilled faq',
	isset( $items['return-period']['answer'] ) && false !== strpos( $items['return-period']['answer'], '<a href=' )
);
nh_faq_assert( 'topics include ordering', isset( $topics['ordering'] ) && 10 === (int) $topics['ordering']['order'] );

$prepared = nh_theme_faq_prepare_content(
	array(
		array(
			'id'    => 'b-topic',
			'label' => 'Second',
			'order' => '20',
		),
		array(
			'id'    => 'a-topic',
			'label' => 'First',
			'order' => '10',
		),
		array(
			'label' => '',
			'order' => '99',
		),
		array(
			'id'    => 'a-topic',
			'label' => 'First again',
			'order' => '15',
		),
		array(
			'id'     => 'gone-topic',
			'label'  => 'Remove me',
			'order'  => '5',
			'delete' => '1',
		),
	),
	array(
		array(
			'id'       => 'keep-me',
			'question' => 'Kept question',
			'answer'   => 'See <a href="https://norhage.eu/refund-and-returns-policy/">Returns</a><script>alert(1)</script>',
			'topics'   => array( 'a-topic', 'missing' ),
		),
		array(
			'question' => 'Brand new question?',
			'answer'   => 'A new answer.',
			'topics'   => array( 'b-topic' ),
		),
		array(
			'question' => 'Missing answer',
			'answer'   => '   ',
		),
		array(
			'id'       => 'remove-me',
			'question' => 'Delete me',
			'answer'   => 'Gone',
			'delete'   => '1',
		),
		array(
			'question' => '',
			'answer'   => '',
		),
	)
);

$topic_ids = array_keys( $prepared['topics'] );

nh_faq_assert( 'topics sort by order', array( 'a-topic', 'a-topic-2', 'b-topic' ) === $topic_ids );
nh_faq_assert( 'removed topic is dropped', ! isset( $prepared['topics']['gone-topic'] ) );
nh_faq_assert( 'existing faq id is kept', isset( $prepared['items']['keep-me'] ) );
nh_faq_assert( 'new question gets an id', isset( $prepared['items']['brand-new-question'] ) );
nh_faq_assert( 'deleted question is dropped', ! isset( $prepared['items']['remove-me'] ) );
nh_faq_assert( 'blank row is ignored', 1 === (int) $prepared['skipped'] );
nh_faq_assert(
	'unknown topic is dropped and known topic stays',
	array( 'a-topic' ) === $prepared['items']['keep-me']['topics']
);
nh_faq_assert(
	'answer keeps the link and drops the script',
	false !== strpos( $prepared['items']['keep-me']['answer'], '<a href="https://norhage.eu/refund-and-returns-policy/">Returns</a>' )
		&& false === strpos( $prepared['items']['keep-me']['answer'], 'script' )
);
nh_faq_assert(
	'question order follows the form',
	array( 'keep-me', 'brand-new-question' ) === array_keys( $prepared['items'] )
);

$GLOBALS['nh_faq_option'] = array(
	'custom' => 1,
	'topics' => array(
		'ordering' => array(
			'label' => 'Custom topic',
			'order' => 1,
		),
	),
	'items'  => array(
		'custom-q' => array(
			'question' => 'Custom question?',
			'answer'   => 'Custom answer.',
			'topics'   => array( 'ordering' ),
		),
	),
);

nh_faq_assert( 'saved faq replaces the built-in list', 'Custom question?' === nh_theme_faq_items()['custom-q']['question'] );
nh_faq_assert( 'built-in id is absent after a save', ! isset( nh_theme_faq_items()['delivery-large-items'] ) );

$GLOBALS['nh_faq_option'] = array(
	'topics' => array(
		'ordering' => array(
			'label' => 'Ignored',
			'order' => 1,
		),
	),
	'items'  => array(
		'custom-q' => array(
			'question' => 'Ignored',
			'answer'   => 'Ignored',
			'topics'   => array( 'ordering' ),
		),
	),
);

nh_faq_assert( 'unsaved option does not replace the built-in faq', isset( nh_theme_faq_items()['delivery-large-items'] ) );

$admin = file_get_contents( dirname( __DIR__ ) . '/inc/faq-admin.php' );
$product = file_get_contents( dirname( __DIR__ ) . '/inc/faq.php' );
$functions = file_get_contents( dirname( __DIR__ ) . '/functions.php' );

nh_faq_assert( 'faq screen is under woocommerce', false !== strpos( $admin, "'woocommerce'" ) && false !== strpos( $admin, "'nh-theme-faqs'" ) );
nh_faq_assert( 'product editor uses question checkboxes', false !== strpos( $product, 'name="nh_faq_ids[]"' ) );
nh_faq_assert( 'product editor no longer asks for raw ids', false === strpos( $product, 'id="nh_faq_ids"' ) );
nh_faq_assert( 'theme loads the faq admin screen', false !== strpos( $functions, '/inc/faq-admin.php' ) );

if ( $failures > 0 ) {
	echo "{$failures} failed\n";
	exit( 1 );
}

echo "all passed\n";
