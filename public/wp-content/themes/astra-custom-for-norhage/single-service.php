<?php
/**
 * Single service.
 *
 * The page hero prints the H1 and featured image. This template does not
 * repeat them, and it does not print an author or a date (blog posts still do).
 *
 * @package Astra_Custom_For_Norhage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	$post_id = get_the_ID();

	if ( post_password_required() ) {
		echo '<div class="nh-service">';
		echo get_the_password_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';
		continue;
	}

	$prepared = nh_service_prepare_post_content( $post_id );
	$contact  = nh_service_contact_details();
	$contact['heading_id'] = 'nh-service-contact-title';
	$archive  = get_post_type_archive_link( 'service' );
	?>

	<article class="nh-service" id="service-<?php echo esc_attr( get_post_field( 'post_name', $post_id ) ); ?>">
		<?php echo nh_service_backlink_markup( $archive ? $archive : '', __( 'Back to Services', 'nh-theme' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php echo nh_service_contact_bar_markup( $contact ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

		<div class="nh-service__layout">
			<?php echo nh_service_toc_markup( $prepared['toc'], __( 'On this page', 'nh-theme' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

			<div class="nh-service__main">
				<div class="entry-content nh-service__content">
					<?php echo $prepared['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
				<?php echo nh_service_contact_panel_markup( $contact ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</div>
	</article>

	<?php echo nh_service_related_markup( $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

<?php endwhile; ?>

<?php
get_footer();
