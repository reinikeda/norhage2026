<?php
/**
 * Services archive — landing page, not a blog index.
 *
 * The page hero (astra_header_after) already prints the H1.
 *
 * @package Astra_Custom_For_Norhage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$services = array();
if ( have_posts() ) {
	$index = 0;
	while ( have_posts() ) {
		the_post();
		$services[] = nh_service_card_from_post( get_post(), $index );
		$index++;
	}
}

$lead = get_the_archive_description();
?>

<div class="nh-services">
	<?php if ( trim( wp_strip_all_tags( (string) $lead ) ) !== '' ) : ?>
		<div class="nh-services__lead"><?php echo wp_kses_post( $lead ); ?></div>
	<?php endif; ?>

	<?php if ( $services ) : ?>
		<?php echo nh_service_archive_nav_markup( $services, nh_service_archive_label() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

		<div class="nh-services__bands">
			<?php foreach ( $services as $index => $service ) : ?>
				<?php echo nh_service_band_markup( $service, (int) $index ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endforeach; ?>
		</div>

		<?php
		the_posts_pagination( array(
			'mid_size'           => 1,
			'prev_text'          => '←',
			'next_text'          => '→',
			'screen_reader_text' => nh_service_archive_label(),
		) );
		?>
	<?php else : ?>
		<p class="nh-services__empty"><?php esc_html_e( 'No services are published yet.', 'nh-theme' ); ?></p>
	<?php endif; ?>

	<?php
	$contact = nh_service_contact_details();
	$contact['heading_id'] = 'nh-services-contact-title';
	echo nh_service_contact_panel_markup( $contact ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	?>
</div>

<?php
get_footer();
