<?php
/**
 * Side cart drawer shell.
 *
 * @var int $count Cart contents count.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$count = isset( $count ) ? (int) $count : 0;
?>
<div id="nh-side-cart" class="nh-sc" hidden aria-hidden="true">
	<div class="nh-sc__overlay" data-nh-sc-close tabindex="-1"></div>
	<aside
		class="nh-sc__panel"
		role="dialog"
		aria-modal="true"
		aria-labelledby="nh-sc-title"
		tabindex="-1"
	>
		<header class="nh-sc__header">
			<div class="nh-sc__heading">
				<h2 id="nh-sc-title" class="nh-sc__title"><?php esc_html_e( 'Basket', NH_SC_TD ); ?></h2>
				<?php echo NH_Side_Cart_Render::count_html( $count ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<button type="button" class="nh-sc__close" data-nh-sc-close aria-label="<?php esc_attr_e( 'Close basket', NH_SC_TD ); ?>">
				<span aria-hidden="true">&times;</span>
			</button>
		</header>
		<?php echo NH_Side_Cart_Render::body_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</aside>
</div>
