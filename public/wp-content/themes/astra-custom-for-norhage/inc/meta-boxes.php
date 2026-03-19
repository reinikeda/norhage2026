<?php
// 1) ADMIN ONLY: metabox registration & save
if ( is_admin() ) {

	// Register the Downloads, Video, Custom Cutting, and Homepage Hero Slider metaboxes
	add_action( 'add_meta_boxes', function () {
		add_meta_box(
			'nrh_downloads_box',
			__( 'Downloads', 'nh-theme' ),
			'nrh_downloads_box_html',
			'product',
			'normal',
			'high'
		);

		add_meta_box(
			'nrh_video_box',
			__( 'Video', 'nh-theme' ),
			'nrh_video_box_html',
			'product',
			'normal',
			'high'
		);

		add_meta_box(
			'nh_custom_cutting_box',
			__( 'Custom Cutting', 'nh-theme' ),
			'nh_custom_cutting_box_html',
			'product',
			'normal',
			'default'
		);

		add_meta_box(
			'nh_home_hero_slider_box',
			__( 'Homepage Hero Slider', 'nh-theme' ),
			'nh_home_hero_slider_box_html',
			'page',
			'normal',
			'high'
		);
	} );

	// Render the Downloads metabox
	function nrh_downloads_box_html( $post ) {
		$downloads = get_post_meta( $post->ID, '_nrh_downloads', true );
		if ( ! is_array( $downloads ) ) {
			$downloads = [];
		}

		wp_nonce_field( 'nrh_save_downloads', 'nrh_downloads_nonce' );

		echo '<table class="form-table widefat fixed" id="nrh-downloads-table"><thead><tr>
				<th style="width:40%;">' . esc_html__( 'Label', 'nh-theme' ) . '</th>
				<th style="width:55%;">' . esc_html__( 'PDF URL', 'nh-theme' ) . '</th>
				<th style="width:5%;"></th>
			  </tr></thead><tbody>';

		foreach ( $downloads as $i => $row ) {
			printf(
				'<tr>
					<td><input type="text" name="nrh_downloads[%1$d][label]" value="%2$s" style="width:100%%;"></td>
					<td><input type="url"  name="nrh_downloads[%1$d][url]"   value="%3$s" style="width:100%%;"></td>
					<td><button class="button remove-download" type="button" aria-label="%4$s">–</button></td>
				</tr>',
				$i,
				esc_attr( $row['label'] ?? '' ),
				esc_url( $row['url'] ?? '' ),
				esc_attr__( 'Remove download row', 'nh-theme' )
			);
		}

		echo '</tbody></table>';
		echo '<p><button id="add-download" class="button" type="button">+ '
			 . esc_html__( 'Add Download', 'nh-theme' )
			 . '</button></p>';
		?>
		<script>
		jQuery(function($){
			var $tbody   = $('#nrh-downloads-table tbody'),
				template = '<tr><td><input type="text" name="" style="width:100%;"></td>'
						 + '<td><input type="url"  name="" style="width:100%;"></td>'
						 + '<td><button class="button remove-download" type="button" aria-label="<​?php echo esc_js( esc_html__( 'Remove download row', 'nh-theme' ) ); ?>">–</button></td></tr>';

			$('#add-download').on('click', function(){
				var idx  = $tbody.children('tr').length,
					$row = $(template);

				$row.find('input[type=text]')
					.attr('name','nrh_downloads['+idx+'][label]');

				$row.find('input[type=url]')
					.attr('name','nrh_downloads['+idx+'][url]');

				$tbody.append($row);
			});

			$tbody.on('click', '.remove-download', function(){
				$(this).closest('tr').remove();

				$tbody.children('tr').each(function(i){
					$(this).find('input[type=text]')
						.attr('name','nrh_downloads['+i+'][label]');
					$(this).find('input[type=url]')
						.attr('name','nrh_downloads['+i+'][url]');
				});
			});
		});
		</script>
		<?php
	}

	// Render the Video metabox
	function nrh_video_box_html( $post ) {
		$video_url = get_post_meta( $post->ID, '_nrh_video_url', true );
		wp_nonce_field( 'nrh_save_video', 'nrh_video_nonce' );

		echo '<table class="form-table"><tr>
				<th><label for="nrh_video_url">'
				  . esc_html__( 'YouTube / Vimeo URL', 'nh-theme' )
				. '</label></th>
				<td><input type="url" id="nrh_video_url" name="nrh_video_url" '
				  . 'value="' . esc_url( $video_url ) . '" style="width:100%;" />'
				. '</td>
			  </tr></table>';
	}

	// Render the Custom Cutting metabox
	// Weight per m² is now taken from WooCommerce "Weight (kg)" on product/variation
	function nh_custom_cutting_box_html( $post ) {
		$pfx  = '_nh_cc_';
		$vals = [
			'enabled' => (bool) get_post_meta( $post->ID, $pfx . 'enabled', true ),
			'cut_fee' => get_post_meta( $post->ID, $pfx . 'cut_fee', true ),
			'min_w'   => get_post_meta( $post->ID, $pfx . 'min_w', true ),
			'max_w'   => get_post_meta( $post->ID, $pfx . 'max_w', true ),
			'min_l'   => get_post_meta( $post->ID, $pfx . 'min_l', true ),
			'max_l'   => get_post_meta( $post->ID, $pfx . 'max_l', true ),
			'step_mm' => get_post_meta( $post->ID, $pfx . 'step_mm', true ),
		];

		wp_nonce_field( 'nh_cc_save', 'nh_cc_nonce' );
		?>
		<style>
			.nh-cc-grid{display:grid;grid-template-columns:220px 1fr;gap:8px 16px;align-items:center}
			.nh-cc-grid label{font-weight:600}
			.nh-cc-row{display:contents}
			.nh-cc-desc{grid-column:1 / -1;color:#666}
		</style>

		<div class="nh-cc-grid">
			<div class="nh-cc-row">
				<label for="nh_cc_enabled"><?php esc_html_e( 'Enable custom cutting', 'nh-theme' ); ?></label>
				<input type="checkbox" id="nh_cc_enabled" name="nh_cc_enabled" value="1" <?php checked( $vals['enabled'] ); ?> />
			</div>

			<div class="nh-cc-row">
				<label for="nh_cc_cut_fee"><?php esc_html_e( 'Cutting fee per sheet', 'nh-theme' ); ?></label>
				<input type="number" step="0.01" min="0" name="nh_cc_cut_fee" id="nh_cc_cut_fee" value="<​?php echo esc_attr( $vals['cut_fee'] ); ?>" />
			</div>

			<div class="nh-cc-row">
				<label for="nh_cc_min_w"><?php esc_html_e( 'Min width (mm)', 'nh-theme' ); ?></label>
				<input type="number" step="1" min="0" name="nh_cc_min_w" id="nh_cc_min_w" value="<​?php echo esc_attr( $vals['min_w'] ); ?>" />
			</div>

			<div class="nh-cc-row">
				<label for="nh_cc_max_w"><?php esc_html_e( 'Max width (mm)', 'nh-theme' ); ?></label>
				<input type="number" step="1" min="0" name="nh_cc_max_w" id="nh_cc_max_w" value="<​?php echo esc_attr( $vals['max_w'] ); ?>" />
			</div>

			<div class="nh-cc-row">
				<label for="nh_cc_min_l"><?php esc_html_e( 'Min length (mm)', 'nh-theme' ); ?></label>
				<input type="number" step="1" min="0" name="nh_cc_min_l" id="nh_cc_min_l" value="<​?php echo esc_attr( $vals['min_l'] ); ?>" />
			</div>

			<div class="nh-cc-row">
				<label for="nh_cc_max_l"><?php esc_html_e( 'Max length (mm)', 'nh-theme' ); ?></label>
				<input type="number" step="1" min="0" name="nh_cc_max_l" id="nh_cc_max_l" value="<​?php echo esc_attr( $vals['max_l'] ); ?>" />
			</div>

			<div class="nh-cc-row">
				<label for="nh_cc_step_mm"><?php esc_html_e( 'Cutting step (mm)', 'nh-theme' ); ?></label>
				<input type="number" step="1" min="1" name="nh_cc_step_mm" id="nh_cc_step_mm" value="<​?php echo esc_attr( $vals['step_mm'] ); ?>" />
			</div>

			<div class="nh-cc-desc">
				<?php
				echo esc_html__(
					'Weight is taken from the WooCommerce "Weight (kg)" field. For custom cutting, that weight is interpreted as kg per m² (set per product or per variation).',
					'nh-theme'
				);
				?>
			</div>
		</div>
		<?php
	}

	// Render the Homepage Hero Slider metabox
	function nh_home_hero_slider_box_html( $post ) {
		$front_page_id = (int) get_option( 'page_on_front' );

		if ( ! $front_page_id ) {
			echo '<p>' . esc_html__( 'Set a static homepage first in Settings → Reading.', 'nh-theme' ) . '</p>';
			return;
		}

		if ( (int) $post->ID !== $front_page_id ) {
			echo '<p>' . esc_html__( 'This slider is editable only on the page currently assigned as the homepage.', 'nh-theme' ) . '</p>';
			return;
		}

		wp_enqueue_media();

		$slides = get_post_meta( $post->ID, '_nh_home_hero_slides', true );
		$slides = is_array( $slides ) ? array_values( $slides ) : [];

		for ( $i = 0; $i < 5; $i++ ) {
			$slides[ $i ] = wp_parse_args(
				$slides[ $i ] ?? [],
				[
					'image_id' => 0,
					'title'    => '',
					'text'     => '',
				]
			);
		}

		wp_nonce_field( 'nh_save_home_hero_slider', 'nh_home_hero_slider_nonce' );
		?>
		<style>
			.nh-hero-admin-wrap{display:grid;gap:18px}
			.nh-hero-admin-card{border:1px solid #dcdcde;background:#fff;padding:16px}
			.nh-hero-admin-grid{display:grid;grid-template-columns:220px 1fr;gap:16px}
			.nh-hero-admin-preview{
				width:100%;
				height:140px;
				border:1px dashed #c3c4c7;
				display:flex;
				align-items:center;
				justify-content:center;
				background:#f6f7f7;
				overflow:hidden;
			}
			.nh-hero-admin-preview img{
				width:100%;
				height:100%;
				object-fit:cover;
				display:block;
			}
			.nh-hero-admin-fields label{
				display:block;
				font-weight:600;
				margin:0 0 6px;
			}
			.nh-hero-admin-fields p{margin:0 0 12px}
			@media (max-width: 782px){
				.nh-hero-admin-grid{grid-template-columns:1fr}
			}
		</style>

		<div class="nh-hero-admin-wrap">
			<p>
				<?php esc_html_e( 'Add up to 5 homepage slides. Each slide has an image, H2 heading, and paragraph text.', 'nh-theme' ); ?>
			</p>

			<?php foreach ( $slides as $i => $slide ) : ?>
				<?php
				$image_id  = absint( $slide['image_id'] ?? 0 );
				$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'medium_large' ) : '';
				$title     = (string) ( $slide['title'] ?? '' );
				$text      = (string) ( $slide['text'] ?? '' );
				?>
				<div class="nh-hero-admin-card">
					<h3 style="margin-top:0;">
						<?php echo esc_html( sprintf( __( 'Slide %d', 'nh-theme' ), $i + 1 ) ); ?>
					</h3>

					<div class="nh-hero-admin-grid">
						<div>
							<div class="nh-hero-admin-preview" data-preview>
								<?php if ( $image_url ) : ?>
									<img src="<​?php echo esc_url( $image_url ); ?>" alt="">
								<?php else : ?>
									<span><?php esc_html_e( 'No image selected', 'nh-theme' ); ?></span>
								<?php endif; ?>
							</div>

							<input
								type="hidden"
								class="nh-hero-image-id"
								name="nh_home_hero_slides[<?php echo esc_attr( $i ); ?>][image_id]"
								value="<​?php echo esc_attr( $image_id ); ?>"
							>

							<p style="margin-top:10px;">
								<button type="button" class="button nh-hero-upload">
									<?php esc_html_e( 'Choose image', 'nh-theme' ); ?>
								</button>

								<button type="button" class="button-link-delete nh-hero-remove" <?php disabled( ! $image_id ); ?>>
									<?php esc_html_e( 'Remove image', 'nh-theme' ); ?>
								</button>
							</p>
						</div>

						<div class="nh-hero-admin-fields">
							<p>
								<label for="nh_home_hero_title_<?php echo esc_attr( $i ); ?>">
									<?php esc_html_e( 'Heading (H2)', 'nh-theme' ); ?>
								</label>
								<input
									type="text"
									class="widefat"
									id="nh_home_hero_title_<?php echo esc_attr( $i ); ?>"
									name="nh_home_hero_slides[<?php echo esc_attr( $i ); ?>][title]"
									value="<​?php echo esc_attr( $title ); ?>"
								>
							</p>

							<p>
								<label for="nh_home_hero_text_<?php echo esc_attr( $i ); ?>">
									<?php esc_html_e( 'Paragraph', 'nh-theme' ); ?>
								</label>
								<textarea
									class="widefat"
									rows="4"
									id="nh_home_hero_text_<?php echo esc_attr( $i ); ?>"
									name="nh_home_hero_slides[<?php echo esc_attr( $i ); ?>][text]"
								><?php echo esc_textarea( $text ); ?></textarea>
							</p>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<script>
		jQuery(function($){
			$(document).on('click', '.nh-hero-upload', function(e){
				e.preventDefault();

				const $card    = $(this).closest('.nh-hero-admin-card');
				const $preview = $card.find('[data-preview]');
				const $input   = $card.find('.nh-hero-image-id');
				const $remove  = $card.find('.nh-hero-remove');

				const frame = wp.media({
					title: '<?php echo esc_js( __( 'Select slide image', 'nh-theme' ) ); ?>',
					button: {
						text: '<?php echo esc_js( __( 'Use image', 'nh-theme' ) ); ?>'
					},
					multiple: false
				});

				frame.on('select', function(){
					const attachment = frame.state().get('selection').first().toJSON();
					const imageUrl = attachment.sizes && attachment.sizes.medium_large
						? attachment.sizes.medium_large.url
						: attachment.url;

					$input.val(attachment.id);
					$preview.html('<img src="' + imageUrl + '" alt="">');
					$remove.prop('disabled', false);
				});

				frame.open();
			});

			$(document).on('click', '.nh-hero-remove', function(e){
				e.preventDefault();

				const $card    = $(this).closest('.nh-hero-admin-card');
				const $preview = $card.find('[data-preview]');
				const $input   = $card.find('.nh-hero-image-id');

				$input.val('');
				$preview.html('<span><?php echo esc_js( __( 'No image selected', 'nh-theme' ) ); ?></span>');
				$(this).prop('disabled', true);
			});
		});
		</script>
		<?php
	}

	// Save Downloads, Video, and Custom Cutting meta
	add_action( 'save_post_product', function ( $post_id ) {
		// Downloads
		if (
			isset( $_POST['nrh_downloads_nonce'] ) &&
			wp_verify_nonce( $_POST['nrh_downloads_nonce'], 'nrh_save_downloads' ) &&
			current_user_can( 'edit_post', $post_id )
		) {
			$clean = [];

			if ( ! empty( $_POST['nrh_downloads'] ) && is_array( $_POST['nrh_downloads'] ) ) {
				$rows = wp_unslash( $_POST['nrh_downloads'] );

				foreach ( $rows as $row ) {
					$label = sanitize_text_field( $row['label'] ?? '' );
					$url   = esc_url_raw( $row['url'] ?? '' );

					if ( $label && $url ) {
						$clean[] = [
							'label' => $label,
							'url'   => $url,
						];
					}
				}
			}

			update_post_meta( $post_id, '_nrh_downloads', $clean );
		}

		// Video
		if (
			isset( $_POST['nrh_video_nonce'] ) &&
			wp_verify_nonce( $_POST['nrh_video_nonce'], 'nrh_save_video' ) &&
			current_user_can( 'edit_post', $post_id )
		) {
			$video = ! empty( $_POST['nrh_video_url'] )
				? esc_url_raw( wp_unslash( $_POST['nrh_video_url'] ) )
				: '';

			update_post_meta( $post_id, '_nrh_video_url', $video );
		}

		// Custom Cutting
		if (
			isset( $_POST['nh_cc_nonce'] ) &&
			wp_verify_nonce( $_POST['nh_cc_nonce'], 'nh_cc_save' ) &&
			current_user_can( 'edit_post', $post_id )
		) {
			$pfx    = '_nh_cc_';
			$fields = [
				'enabled' => isset( $_POST['nh_cc_enabled'] ) ? '1' : '',
				'cut_fee' => wc_format_decimal( wp_unslash( $_POST['nh_cc_cut_fee'] ?? '' ) ),
				'min_w'   => ( $v = wp_unslash( $_POST['nh_cc_min_w'] ?? '' ) ) === '' ? '' : absint( $v ),
				'max_w'   => ( $v = wp_unslash( $_POST['nh_cc_max_w'] ?? '' ) ) === '' ? '' : absint( $v ),
				'min_l'   => ( $v = wp_unslash( $_POST['nh_cc_min_l'] ?? '' ) ) === '' ? '' : absint( $v ),
				'max_l'   => ( $v = wp_unslash( $_POST['nh_cc_max_l'] ?? '' ) ) === '' ? '' : absint( $v ),
				'step_mm' => ( $v = wp_unslash( $_POST['nh_cc_step_mm'] ?? '' ) ) === '' ? '' : max( 1, absint( $v ) ),
			];

			foreach ( $fields as $k => $v ) {
				$meta_key = $pfx . $k;

				if ( $v === '' || $v === null ) {
					delete_post_meta( $post_id, $meta_key );
				} else {
					update_post_meta( $post_id, $meta_key, $v );
				}
			}

			delete_post_meta( $post_id, $pfx . 'weight_per_m2' );
		}
	} );

	// Save Homepage Hero Slider meta
	add_action( 'save_post_page', function ( $post_id ) {
		if ( ! isset( $_POST['nh_home_hero_slider_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_POST['nh_home_hero_slider_nonce'], 'nh_save_home_hero_slider' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$front_page_id = (int) get_option( 'page_on_front' );
		if ( (int) $post_id !== $front_page_id ) {
			return;
		}

		$raw = isset( $_POST['nh_home_hero_slides'] ) ? (array) wp_unslash( $_POST['nh_home_hero_slides'] ) : [];
		$out = [];
		$has_content = false;

		for ( $i = 0; $i < 5; $i++ ) {
			$row = isset( $raw[ $i ] ) && is_array( $raw[ $i ] ) ? $raw[ $i ] : [];

			$image_id = isset( $row['image_id'] ) ? absint( $row['image_id'] ) : 0;
			$title    = isset( $row['title'] ) ? sanitize_text_field( $row['title'] ) : '';
			$text     = isset( $row['text'] ) ? sanitize_textarea_field( $row['text'] ) : '';

			if ( $image_id || $title !== '' || $text !== '' ) {
				$has_content = true;
			}

			$out[] = [
				'image_id' => $image_id,
				'title'    => $title,
				'text'     => $text,
			];
		}

		if ( $has_content ) {
			update_post_meta( $post_id, '_nh_home_hero_slides', $out );
		} else {
			delete_post_meta( $post_id, '_nh_home_hero_slides' );
		}
	} );
}

// 2) ALWAYS (admin & front): register product tabs only when there is content
add_filter( 'woocommerce_product_tabs', function ( $tabs ) {

	$product_id = 0;

	if ( function_exists( 'is_product' ) && is_product() ) {
		$product_id = get_the_ID();
	}

	if ( ! $product_id ) {
		global $product;
		if ( $product instanceof WC_Product ) {
			$product_id = $product->get_id();
		}
	}

	if ( ! $product_id ) {
		return $tabs;
	}

	$downloads     = get_post_meta( $product_id, '_nrh_downloads', true );
	$has_downloads = false;

	if ( is_array( $downloads ) ) {
		foreach ( $downloads as $row ) {
			$label = isset( $row['label'] ) ? trim( (string) $row['label'] ) : '';
			$url   = isset( $row['url'] ) ? trim( (string) $row['url'] ) : '';

			if ( $label !== '' && $url !== '' ) {
				$has_downloads = true;
				break;
			}
		}
	}

	if ( $has_downloads ) {
		$tabs['nrh_downloads'] = [
			'title'    => __( 'Downloads', 'nh-theme' ),
			'priority' => 25,
			'callback' => 'nrh_downloads_tab_content',
		];
	}

	$video_url = trim( (string) get_post_meta( $product_id, '_nrh_video_url', true ) );
	$has_video = false;

	if ( $video_url !== '' ) {
		$embed = wp_oembed_get( esc_url( $video_url ) );
		if ( $embed ) {
			$has_video = true;
		} else {
			$has_video = true;
		}
	}

	if ( $has_video ) {
		$tabs['nrh_video'] = [
			'title'    => __( 'Video', 'nh-theme' ),
			'priority' => 30,
			'callback' => 'nrh_video_tab_content',
		];
	}

	if ( isset( $tabs['reviews'] ) ) {
		$tabs['reviews']['priority'] = 35;
	}

	return $tabs;
}, 98 );

/**
 * Render Downloads tab
 */
function nrh_downloads_tab_content() {
	global $product;

	if ( ! $product instanceof WC_Product ) {
		return;
	}

	$downloads = get_post_meta( $product->get_id(), '_nrh_downloads', true );
	if ( ! is_array( $downloads ) ) {
		return;
	}

	echo '<ul class="nrh-download-list">';
	foreach ( $downloads as $row ) {
		$label = isset( $row['label'] ) ? trim( (string) $row['label'] ) : '';
		$url   = isset( $row['url'] ) ? trim( (string) $row['url'] ) : '';

		if ( $label !== '' && $url !== '' ) {
			printf(
				'<li><a href="%1$s" target="_blank" rel="noopener">%2$s</a></li>',
				esc_url( $url ),
				esc_html( $label )
			);
		}
	}
	echo '</ul>';
}

/**
 * Render Video tab
 */
function nrh_video_tab_content() {
	global $product;

	if ( ! $product instanceof WC_Product ) {
		return;
	}

	$url = trim( (string) get_post_meta( $product->get_id(), '_nrh_video_url', true ) );
	if ( $url === '' ) {
		return;
	}

	$embed = wp_oembed_get( esc_url( $url ) );

	echo '<div class="product-video-wrap">';
	if ( $embed ) {
		echo $embed; // safe from wp_oembed_get
	} else {
		printf(
			'<p><a href="%s" target="_blank" rel="noopener">%s</a></p>',
			esc_url( $url ),
			esc_html__( 'Watch video', 'nh-theme' )
		);
	}
	echo '</div>';
}


// === Product extras metabox (uses Woo wc-product-search) =====================
if ( ! defined( 'NC_BUNDLE_META_KEY' ) ) {
	define( 'NC_BUNDLE_META_KEY', '_nc_bundle_items_v2' );
}

add_action( 'add_meta_boxes', function () {
	add_meta_box(
		'nc_bundle_items_box',
		__( 'Product extras', 'nh-theme' ),
		'nc_bundle_items_box_html',
		'product',
		'normal',
		'default'
	);
} );

function nc_bundle_items_box_html( $post ) {
	$rows = get_post_meta( $post->ID, NC_BUNDLE_META_KEY, true );
	if ( ! is_array( $rows ) ) {
		$rows = [];
	}

	wp_nonce_field( 'nc_bundle_items_save', 'nc_bundle_items_nonce' );

	echo '<p><strong>' . esc_html__( "Product extra's", 'nh-theme' ) . '</strong></p>';
	echo '<p>' . esc_html__( 'Select one or more products that can be added to this product as add-ons. Only simple products or product-variants are allowed.', 'nh-theme' ) . '</p>';

	echo '<table class="widefat striped" id="nc-bundle-rows" style="margin-top:10px">';
	echo '<thead><tr>';
	echo '<th style="width:65%;">' . esc_html__( 'Product ', 'nh-theme' ) . '</th>';
	echo '<th style="width:20%;">' . esc_html__( 'Maximum quantity', 'nh-theme' ) . '</th>';
	echo '<th style="width:15%;"></th>';
	echo '</tr></thead><tbody class="nc-sortable">';

	if ( empty( $rows ) ) {
		echo nc_bundle_row_template_wc( null, '' );
	} else {
		foreach ( $rows as $r ) {
			$id  = isset( $r['id'] ) ? (int) $r['id'] : 0;
			$max = ( isset( $r['max'] ) && $r['max'] !== '' ) ? (int) $r['max'] : '';
			echo nc_bundle_row_template_wc( $id, $max );
		}
	}

	echo '</tbody></table>';
	echo '<p><button type="button" class="button button-primary" id="nc-add-bundle-row">' . esc_html__( 'Add product as add-on', 'nh-theme' ) . '</button></p>';
	?>
	<style>
		#nc-bundle-rows td { vertical-align: middle; }
		.nc-handle { cursor: move; opacity:.7; margin-right:6px; }
		.nc-remove { color:#a00; }
	</style>
	<script>
	jQuery(function($){
		$('#nc-add-bundle-row').on('click', function(){
			var html = <?php echo json_encode( preg_replace( '/\s+/', ' ', nc_bundle_row_template_wc( null, '' ) ) ); ?>;
			$('#nc-bundle-rows tbody').append(html);
			$(document.body).trigger('wc-enhanced-select-init');
		});

		$(document).on('click', '.nc-remove', function(){
			$(this).closest('tr').remove();
		});

		if ($.fn.sortable) {
			$('#nc-bundle-rows tbody.nc-sortable').sortable({
				handle: '.nc-handle',
				items: '> tr'
			});
		}

		$(document.body).trigger('wc-enhanced-select-init');
	});
	</script>
	<?php
}

function nc_bundle_row_template_wc( $prod_id = null, $max = '' ) {
	$prod_id = $prod_id ? (int) $prod_id : 0;

	$option_html = '';
	if ( $prod_id ) {
		$p = wc_get_product( $prod_id );
		if ( $p ) {
			$option_html = '<option value="' . esc_attr( $prod_id ) . '" selected="selected">' . esc_html( $p->get_formatted_name() ) . '</option>';
		}
	}

	ob_start();
	?>
	<tr>
		<td>
			<span class="dashicons dashicons-menu nc-handle" title="<​?php echo esc_attr__( 'Drag to reorder', 'nh-theme' ); ?>"></span>
			<select
				class="wc-product-search"
				name="nc_bundle[id][]"
				data-placeholder="<​?php echo esc_attr__( 'Search products & variations…', 'nh-theme' ); ?>"
				data-action="woocommerce_json_search_products_and_variations"
				style="width:92%">
				<?php echo $option_html; ?>
			</select>
		</td>
		<td>
			<input
				type="number"
				min="1"
				step="1"
				name="nc_bundle[max][]"
				value="<​?php echo esc_attr( $max ); ?>"
				placeholder="—" />
		</td>
		<td>
			<button type="button" class="button-link nc-remove" aria-label="<​?php echo esc_attr__( 'Remove row', 'nh-theme' ); ?>">&times;</button>
		</td>
	</tr>
	<?php
	return ob_get_clean();
}

add_action( 'save_post_product', function ( $post_id ) {
	if ( ! isset( $_POST['nc_bundle_items_nonce'] ) || ! wp_verify_nonce( $_POST['nc_bundle_items_nonce'], 'nc_bundle_items_save' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$ids = isset( $_POST['nc_bundle']['id'] ) ? (array) $_POST['nc_bundle']['id'] : [];
	$mxs = isset( $_POST['nc_bundle']['max'] ) ? (array) $_POST['nc_bundle']['max'] : [];

	$out = [];

	foreach ( $ids as $i => $id ) {
		$id = (int) $id;
		if ( $id <= 0 ) {
			continue;
		}

		$row = [ 'id' => $id ];

		$raw_max = isset( $mxs[ $i ] ) ? trim( (string) $mxs[ $i ] ) : '';
		if ( $raw_max !== '' ) {
			$row['max'] = max( 1, (int) $raw_max );
		}

		$out[] = $row;
	}

	if ( $out ) {
		update_post_meta( $post_id, NC_BUNDLE_META_KEY, $out );
	} else {
		delete_post_meta( $post_id, NC_BUNDLE_META_KEY );
	}
}, 10 );
