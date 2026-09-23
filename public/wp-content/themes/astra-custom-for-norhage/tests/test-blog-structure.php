<?php
/**
 * CLI tests: blog meta keeps the date and drops the author.
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

$live = <<<'HTML'
<div class="entry-meta">By <span class="posted-by vcard author" itemtype="https://schema.org/Person" itemscope="itemscope" itemprop="author">  <span
 class="author-name" itemprop="name"> admin </span>  </span> / <span class="posted-on"><span class="published" itemprop="datePublished"> August 27, 2026 </span></span></div>
HTML;

$clean = nh_blog_clean_meta( $live );
nh_blog_assert( 'live single keeps the date', strpos( $clean, 'August 27, 2026' ) !== false && strpos( $clean, 'posted-on' ) !== false );
nh_blog_assert( 'live single drops the author name', stripos( $clean, 'admin' ) === false && stripos( $clean, 'posted-by' ) === false );
nh_blog_assert( 'live single drops the By prefix', ! preg_match( '/\bBy\b/', $clean ) );
nh_blog_assert( 'live single drops the slash between author and date', ! preg_match( '/>\s*\/\s*</', $clean ) );

$already_empty_author = 'By  / <span class="posted-on"><span class="published"> August 27, 2026 </span></span>';
$from_filter           = nh_blog_clean_meta( $already_empty_author );
nh_blog_assert( 'empty author element still leaves the date', strpos( $from_filter, 'August 27, 2026' ) !== false );
nh_blog_assert( 'empty author element drops By and the slash', stripos( $from_filter, 'By' ) === false && ! preg_match( '/>\s*\/\s*</', $from_filter ) && ! preg_match( '/^\s*\/\s*/', trim( strip_tags( $from_filter ) ) ) );

$german = 'Von <span class="posted-by author"><span class="author-name"> admin </span></span> / <span class="posted-on"><span class="published">27. August 2026</span></span>';
$de     = nh_blog_clean_meta( $german );
nh_blog_assert( 'translated prefix is removed', strpos( $de, 'Von' ) === false && strpos( $de, '27. August 2026' ) !== false );

$date_only = '<div class="entry-meta"><span class="posted-on"><span class="published"> August 24, 2026 </span></span></div>';
$kept      = nh_blog_clean_meta( $date_only );
nh_blog_assert( 'date-only meta stays intact', strpos( $kept, 'August 24, 2026' ) !== false && strpos( $kept, 'posted-on' ) !== false );

$twice = nh_blog_clean_meta( $clean );
nh_blog_assert( 'cleaning is idempotent', $twice === $clean );

nh_blog_assert( 'empty markup stays empty', nh_blog_clean_meta( '   ' ) === '   ' );

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
