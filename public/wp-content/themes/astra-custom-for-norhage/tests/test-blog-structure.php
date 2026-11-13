<?php
/**
 * CLI tests: blog meta leaves the author in place, and Previous / Next translate.
 *
 * Run: php public/wp-content/themes/astra-custom-for-norhage/tests/test-blog-structure.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/' );
}

require_once dirname( __DIR__ ) . '/inc/blog-structure.php';

$failures = 0;

function nh_blog_assert( $label, $ok ) {
	global $failures;
	if ( $ok ) {
		echo "ok  {$label}\n";
		return;
	}
	$failures++;
	echo "FAIL  {$label}\n";
}

$blog_php = file_get_contents( dirname( __DIR__ ) . '/inc/blog.php' );
nh_blog_assert( 'blog.php does not drop the author meta key', strpos( $blog_php, "array( 'author' )" ) === false );
nh_blog_assert( 'blog.php does not blank the author output', strpos( $blog_php, 'astra_post_author' ) === false );
nh_blog_assert( 'blog.php does not rewrite the meta HTML', strpos( $blog_php, 'nh_blog_clean_meta' ) === false );
nh_blog_assert( 'author stripping helper is gone', ! function_exists( 'nh_blog_clean_meta' ) );

$style = file_get_contents( dirname( __DIR__ ) . '/style.css' );
$meta_css = '';
if ( preg_match( '/SINGLE POST:.*?(?=\/\* ---------- Read more)/s', $style, $meta_match ) ) {
	$meta_css = $meta_match[0];
}
nh_blog_assert( 'single post CSS names the author', strpos( $meta_css, '.posted-by' ) !== false );
nh_blog_assert( 'single post CSS does not hide the author', stripos( $meta_css, 'display' ) === false && strpos( $meta_css, 'font-size: 0' ) === false );

$nav = nh_blog_navigation_strings(
	array(
		'string-previous-text'              => 'Previous',
		'string-next-text'                  => 'Next',
		'string-blog-navigation-previous'   => '<span class="ast-left-arrow" aria-hidden="true">&larr;</span> Previous',
		'string-blog-navigation-next'       => 'Next <span class="ast-right-arrow" aria-hidden="true">&rarr;</span>',
		'string-single-navigation-previous' => '<span class="ast-left-arrow" aria-hidden="true">&larr;</span> Previous %s',
		'string-single-navigation-next'     => 'Next %s <span class="ast-right-arrow" aria-hidden="true">&rarr;</span>',
		'string-header-skip-link'           => 'Skip to content',
	),
	'Zurück',
	'Weiter'
);
nh_blog_assert( 'single previous label is translated', $nav['string-previous-text'] === 'Zurück' );
nh_blog_assert( 'single next label is translated', $nav['string-next-text'] === 'Weiter' );
nh_blog_assert( 'archive previous keeps the arrow', strpos( $nav['string-blog-navigation-previous'], 'Zurück' ) !== false && strpos( $nav['string-blog-navigation-previous'], '&larr;' ) !== false && strpos( $nav['string-blog-navigation-previous'], 'Previous' ) === false );
nh_blog_assert( 'other Astra strings stay untouched', $nav['string-header-skip-link'] === 'Skip to content' );
nh_blog_assert( 'non-array navigation input is unchanged', nh_blog_navigation_strings( 'Previous', 'Zurück', 'Weiter' ) === 'Previous' );

echo $failures === 0 ? "\nAll blog structure checks passed.\n" : "\n{$failures} failed.\n";
exit( $failures === 0 ? 0 : 1 );
