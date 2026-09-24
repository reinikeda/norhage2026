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
		<?php if ( ! empty( $view['logged_in'] ) ) : ?>
			<p class="nh-wl__account"><?php esc_html_e( 'Saved on your account.', 'nh-wishlist' ); ?></p>
		<?php else : ?>
			<p class="nh-wl__account">
				<?php esc_html_e( 'The wishlist is stored in a cookie in this browser. Sign in to keep it on your account.', 'nh-wishlist' ); ?>
				<a href="<?php echo esc_url( $view['login_url'] ); ?>"><?php esc_html_e( 'Sign in', 'nh-wishlist' ); ?></a>
			</p>
		<?php endif; ?>
	</header>

	<nav class="nh-wl__lists" aria-label="<?php esc_attr_e( 'Wishlists', 'nh-wishlist' ); ?>">
		<?php foreach ( $view['lists'] as $list ) : ?>
			<a class="nh-wl__list<?php echo ! empty( $list['current'] ) ? ' is-current' : ''; ?>" href="<?php echo esc_url( $list['url'] ); ?>"<?php echo ! empty( $list['current'] ) ? ' aria-current="page"' : ''; ?>>
				<?php echo esc_html( $list['label'] ); ?>
				<span><?php echo esc_html( (string) $list['count'] ); ?></span>
			</a>
		<?php endforeach; ?>
	</nav>

	<div class="nh-wl__manage">
		<form method="post" action="<?php echo esc_url( $view['action'] ); ?>" class="nh-wl__create">
			<?php wp_nonce_field( 'nh_wl' ); ?>
			<input type="hidden" name="action" value="nh_wl">
			<input type="hidden" name="nh_wl_do" value="create">
			<label>
				<span><?php esc_html_e( 'New list', 'nh-wishlist' ); ?></span>
				<input type="text" name="list_name" maxlength="80" required placeholder="<?php esc_attr_e( 'List name', 'nh-wishlist' ); ?>">
			</label>
			<button type="submit"><?php esc_html_e( 'Create', 'nh-wishlist' ); ?></button>
		</form>
		<form method="post" action="<?php echo esc_url( $view['action'] ); ?>" class="nh-wl__rename">
			<?php wp_nonce_field( 'nh_wl' ); ?>
			<input type="hidden" name="action" value="nh_wl">
			<input type="hidden" name="nh_wl_do" value="rename">
			<input type="hidden" name="list_id" value="<?php echo esc_attr( $view['list_id'] ); ?>">
			<label>
				<span><?php esc_html_e( 'Rename', 'nh-wishlist' ); ?></span>
				<input type="text" name="list_name" maxlength="80" required value="<?php echo esc_attr( $view['label'] ); ?>">
			</label>
			<button type="submit"><?php esc_html_e( 'Rename', 'nh-wishlist' ); ?></button>
		</form>
		<?php if ( ! empty( $view['can_delete'] ) ) : ?>
			<form method="post" action="<?php echo esc_url( $view['action'] ); ?>">
				<?php wp_nonce_field( 'nh_wl' ); ?>
				<input type="hidden" name="action" value="nh_wl">
				<input type="hidden" name="nh_wl_do" value="delete">
				<input type="hidden" name="list_id" value="<?php echo esc_attr( $view['list_id'] ); ?>">
				<button type="submit" class="nh-wl__delete"><?php esc_html_e( 'Delete list', 'nh-wishlist' ); ?></button>
			</form>
		<?php endif; ?>
	</div>

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
			<input type="hidden" name="nh_wl_do" value="email">
			<input type="hidden" name="list_id" value="<?php echo esc_attr( $view['list_id'] ); ?>">
			<fieldset>
				<legend><?php esc_html_e( 'Send by email', 'nh-wishlist' ); ?></legend>
				<label class="nh-wl-email__choice">
					<input type="radio" name="target" value="recipient" checked>
					<?php esc_html_e( 'Recipient', 'nh-wishlist' ); ?>
				</label>
				<label>
					<span><?php esc_html_e( 'Recipient email', 'nh-wishlist' ); ?></span>
					<input type="email" name="email" autocomplete="email" placeholder="name@example.com">
				</label>
				<label class="nh-wl-email__choice">
					<input type="radio" name="target" value="service">
					<?php esc_html_e( 'Customer service', 'nh-wishlist' ); ?>
				</label>
				<label>
					<span><?php esc_html_e( 'Comment', 'nh-wishlist' ); ?></span>
					<textarea name="comment" rows="4" maxlength="2000"></textarea>
				</label>
				<button type="submit" class="nh-wl__button"><?php esc_html_e( 'Send', 'nh-wishlist' ); ?></button>
			</fieldset>
		</form>
	<?php endif; ?>
</div>
