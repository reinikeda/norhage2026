<?php
/**
 * Wishlist page.
 *
 * @var array<string,mixed> $view
 * @package nh-wishlist
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="nh-wl">
	<header class="nh-wl__header">
		<h1><?php esc_html_e( 'Wishlist', 'nh-wishlist' ); ?></h1>
		<?php if ( empty( $view['logged_in'] ) ) : ?>
			<p class="nh-wl__account">
				<?php esc_html_e( 'The wishlist is stored in a cookie in this browser. Sign in to keep it on your account.', 'nh-wishlist' ); ?>
				<a href="<?php echo esc_url( $view['login_url'] ); ?>"><?php esc_html_e( 'Sign in', 'nh-wishlist' ); ?></a>
			</p>
		<?php endif; ?>
		<p class="nh-wl__lists-label" id="nh-wl-lists-label"><?php esc_html_e( 'Your saved wishlists:', 'nh-wishlist' ); ?></p>
	</header>

	<nav class="nh-wl__lists" aria-labelledby="nh-wl-lists-label">
		<?php foreach ( $view['lists'] as $list ) : ?>
			<div class="nh-wl__list-item<?php echo ! empty( $list['current'] ) ? ' is-current' : ''; ?>">
				<a class="nh-wl__list" href="<?php echo esc_url( $list['url'] ); ?>"<?php echo ! empty( $list['current'] ) ? ' aria-current="page"' : ''; ?>>
					<?php echo esc_html( $list['label'] ); ?>
					<span><?php echo esc_html( (string) $list['count'] ); ?></span>
				</a>
				<?php if ( ! empty( $list['current'] ) ) : ?>
					<details class="nh-wl__edit">
						<summary class="nh-wl__icon" aria-label="<?php esc_attr_e( 'Rename', 'nh-wishlist' ); ?>">
							<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 16.5V20h3.5L18.8 8.7l-3.5-3.5L4 16.5z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M13.8 6.7l3.5 3.5" fill="none" stroke="currentColor" stroke-width="1.7"/></svg>
						</summary>
						<form method="post" action="<?php echo esc_url( $view['action'] ); ?>" class="nh-wl__rename">
							<?php wp_nonce_field( 'nh_wl' ); ?>
							<input type="hidden" name="action" value="nh_wl">
							<input type="hidden" name="nh_wl_do" value="rename">
							<input type="hidden" name="list_id" value="<?php echo esc_attr( $view['list_id'] ); ?>">
							<label>
								<span class="screen-reader-text"><?php esc_html_e( 'List name', 'nh-wishlist' ); ?></span>
								<input type="text" name="list_name" maxlength="80" required value="<?php echo esc_attr( $view['label'] ); ?>" aria-label="<?php esc_attr_e( 'List name', 'nh-wishlist' ); ?>">
							</label>
							<button type="submit" class="nh-wl__button nh-wl__button--ghost"><?php esc_html_e( 'Rename', 'nh-wishlist' ); ?></button>
						</form>
					</details>
					<?php if ( ! empty( $view['can_delete'] ) ) : ?>
						<form method="post" action="<?php echo esc_url( $view['action'] ); ?>" class="nh-wl__delete-form" onsubmit="return confirm(this.getAttribute('data-confirm'));" data-confirm="<?php echo esc_attr( __( 'Delete this list?', 'nh-wishlist' ) ); ?>">
							<?php wp_nonce_field( 'nh_wl' ); ?>
							<input type="hidden" name="action" value="nh_wl">
							<input type="hidden" name="nh_wl_do" value="delete">
							<input type="hidden" name="list_id" value="<?php echo esc_attr( $view['list_id'] ); ?>">
							<button type="submit" class="nh-wl__icon" aria-label="<?php esc_attr_e( 'Delete list', 'nh-wishlist' ); ?>">
								<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5 7h14" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><path d="M9 7V5h6v2" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M8 7l.8 12h6.4L16 7" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>
							</button>
						</form>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</nav>

	<?php if ( empty( $view['items'] ) ) : ?>
		<p class="nh-wl__empty"><?php esc_html_e( 'Your wishlist is empty.', 'nh-wishlist' ); ?></p>
		<p><a class="nh-wl__button" href="<?php echo esc_url( $view['shop_url'] ); ?>"><?php esc_html_e( 'Continue shopping', 'nh-wishlist' ); ?></a></p>
	<?php else : ?>
		<div class="nh-wl__toolbar">
			<?php if ( ! empty( $view['ready'] ) ) : ?>
				<form method="post" action="<?php echo esc_url( $view['action'] ); ?>">
					<?php wp_nonce_field( 'nh_wl' ); ?>
					<input type="hidden" name="action" value="nh_wl">
					<input type="hidden" name="nh_wl_do" value="cart_all">
					<input type="hidden" name="list_id" value="<?php echo esc_attr( $view['list_id'] ); ?>">
					<button type="submit" class="nh-wl__button"><?php esc_html_e( 'Add all to basket', 'nh-wishlist' ); ?></button>
				</form>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( $view['action'] ); ?>" target="_blank">
				<?php wp_nonce_field( 'nh_wl' ); ?>
				<input type="hidden" name="action" value="nh_wl">
				<input type="hidden" name="nh_wl_do" value="pdf">
				<input type="hidden" name="list_id" value="<?php echo esc_attr( $view['list_id'] ); ?>">
				<button type="submit" class="nh-wl__button nh-wl__button--ghost"><?php esc_html_e( 'Save as PDF', 'nh-wishlist' ); ?></button>
			</form>
		</div>

		<ul class="nh-wl__items">
			<?php foreach ( $view['items'] as $item ) : ?>
				<li class="nh-wl-card">
					<?php if ( ! empty( $item['url'] ) ) : ?>
						<a class="nh-wl-card__media" href="<?php echo esc_url( $item['url'] ); ?>">
							<?php echo $item['image']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</a>
					<?php else : ?>
						<div class="nh-wl-card__media"><?php echo $item['image']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
					<?php endif; ?>
					<div class="nh-wl-card__body">
						<h2>
							<?php if ( ! empty( $item['url'] ) ) : ?>
								<a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['name'] ); ?></a>
							<?php else : ?>
								<?php echo esc_html( $item['name'] ); ?>
							<?php endif; ?>
						</h2>
						<?php if ( ! empty( $item['price_html'] ) ) : ?>
							<p class="nh-wl-card__price"><?php echo wp_kses_post( $item['price_html'] ); ?></p>
						<?php endif; ?>
						<ul class="nh-wl-card__meta">
							<?php foreach ( $item['lines'] as $line ) : ?>
								<li><span><?php echo esc_html( $line['label'] ); ?></span> <?php echo esc_html( $line['value'] ); ?></li>
							<?php endforeach; ?>
						</ul>
						<?php if ( ! empty( $item['notice'] ) ) : ?>
							<p class="nh-wl-card__notice"><?php echo esc_html( $item['notice'] ); ?></p>
						<?php endif; ?>
						<form method="post" action="<?php echo esc_url( $view['action'] ); ?>" class="nh-wl-card__form">
							<?php wp_nonce_field( 'nh_wl' ); ?>
							<input type="hidden" name="action" value="nh_wl">
							<input type="hidden" name="list_id" value="<?php echo esc_attr( $view['list_id'] ); ?>">
							<input type="hidden" name="item_key" value="<?php echo esc_attr( $item['key'] ); ?>">
							<label>
								<span><?php esc_html_e( 'Quantity', 'nh-wishlist' ); ?></span>
								<input type="number" name="quantity" min="1" max="999" value="<?php echo esc_attr( (string) $item['quantity'] ); ?>">
							</label>
							<button type="submit" name="nh_wl_do" value="qty"><?php esc_html_e( 'Update', 'nh-wishlist' ); ?></button>
							<?php if ( ! empty( $item['available'] ) ) : ?>
								<button type="submit" name="nh_wl_do" value="cart" class="nh-wl__button"><?php esc_html_e( 'Add to basket', 'nh-wishlist' ); ?></button>
							<?php elseif ( ! empty( $item['needs_customize'] ) && ! empty( $item['url'] ) ) : ?>
								<a class="nh-wl__button" href="<?php echo esc_url( $item['url'] ); ?>"><?php esc_html_e( 'Customize', 'nh-wishlist' ); ?></a>
							<?php endif; ?>
							<button type="submit" name="nh_wl_do" value="remove" class="nh-wl__text"><?php esc_html_e( 'Remove', 'nh-wishlist' ); ?></button>
						</form>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>

		<form method="post" action="<?php echo esc_url( $view['action'] ); ?>" class="nh-wl-email">
			<?php wp_nonce_field( 'nh_wl' ); ?>
			<input type="hidden" name="action" value="nh_wl">
			<input type="hidden" name="nh_wl_do" value="quote">
			<input type="hidden" name="list_id" value="<?php echo esc_attr( $view['list_id'] ); ?>">
			<fieldset>
				<legend><?php esc_html_e( 'Send a quote to customer service', 'nh-wishlist' ); ?></legend>
				<label>
					<span><?php esc_html_e( 'Comment', 'nh-wishlist' ); ?></span>
					<textarea name="comment" rows="4" maxlength="2000" required></textarea>
				</label>
				<button type="submit" class="nh-wl__button"><?php esc_html_e( 'Send a quote', 'nh-wishlist' ); ?></button>
			</fieldset>
		</form>
	<?php endif; ?>
</div>
