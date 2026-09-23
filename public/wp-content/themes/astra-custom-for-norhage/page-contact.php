<?php
/**
 * Contact page.
 *
 * The page hero prints the only H1. Body copy and the form stay as stored
 * on the page; this template only gives them a contact layout.
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
		echo '<div class="nh-contact">';
		echo get_the_password_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';
		continue;
	}

	$html = apply_filters( 'the_content', get_the_content() );
	$html = nh_contact_enhance_html( $html, nh_contact_place_details() );
	?>

	<article class="nh-contact" id="contact-<?php echo esc_attr( get_post_field( 'post_name' ) ); ?>">
		<div class="entry-content nh-contact__content">
			<?php echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
	</article>

<?php endwhile; ?>

<?php
get_footer();
