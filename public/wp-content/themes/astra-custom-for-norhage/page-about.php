<?php
/**
 * About page.
 *
 * The page hero prints the only H1. Body copy stays as stored on the page.
 *
 * @package Astra_Custom_For_Norhage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();

	if ( post_password_required() ) {
		echo '<div class="nh-about">';
		echo get_the_password_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';
		continue;
	}

	$html = apply_filters( 'the_content', get_the_content() );
	$html = nh_about_enhance_html( $html, __( 'On this page', 'nh-theme' ) );
	?>

	<article class="nh-about" id="about-<?php echo esc_attr( get_post_field( 'post_name' ) ); ?>">
		<div class="entry-content nh-about__content">
			<?php echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
	</article>

<?php endwhile; ?>

<?php
get_footer();
