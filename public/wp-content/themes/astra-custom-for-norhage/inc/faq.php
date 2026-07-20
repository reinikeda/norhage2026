<?php
/**
 * Theme FAQ functionality:
 * - [nh_faq] shortcode for the main FAQ page
 * - Product meta field: nh_faq_ids
 * - WooCommerce FAQ tab
 * - FAQPage JSON-LD schema
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NH_Theme_FAQ {

	const PRODUCT_META_KEY = 'nh_faq_ids';

	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );

		add_shortcode( 'nh_faq', array( $this, 'shortcode' ) );

		add_action( 'add_meta_boxes_product', array( $this, 'add_product_metabox' ) );
		add_action( 'save_post_product', array( $this, 'save_product_metabox' ), 10, 2 );

		if ( class_exists( 'WooCommerce' ) ) {
			add_filter( 'woocommerce_product_tabs', array( $this, 'add_product_tab' ), 25 );
		}
	}

	/**
	 * Register front-end assets.
	 */
	public function register_assets() {
		$theme_version = wp_get_theme()->get( 'Version' );

		wp_register_style(
			'nh-theme-faq',
			get_stylesheet_directory_uri() . '/assets/css/faq.css',
			array(),
			$theme_version
		);

		wp_register_script(
			'nh-theme-faq',
			get_stylesheet_directory_uri() . '/assets/js/faq.js',
			array(),
			$theme_version,
			true
		);
	}

	/**
	 * Load assets only where an FAQ is rendered.
	 */
	private function enqueue_assets() {
		wp_enqueue_style( 'nh-theme-faq' );
		wp_enqueue_script( 'nh-theme-faq' );
	}

	/**
	 * Main FAQ page shortcode.
	 *
	 * Examples:
	 * [nh_faq]
	 * [nh_faq topics="delivery,returns"]
	 * [nh_faq schema="0"]
	 */
	public function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'topics' => '',
				'schema' => '1',
			),
			$atts,
			'nh_faq'
		);

		$requested_topics = array();

		if ( ! empty( $atts['topics'] ) ) {
			$requested_topics = array_filter(
				array_map(
					'sanitize_key',
					explode( ',', $atts['topics'] )
				)
			);
		}

		$html = $this->render_global_faqs( $requested_topics );

		if ( '' === $html ) {
			return '';
		}

		$this->enqueue_assets();

		if ( '1' === $atts['schema'] ) {
			$this->print_schema( $this->get_global_faq_items( $requested_topics ) );
		}

		return $html;
	}

	/**
	 * Reads a comma-separated, semicolon-separated, pipe-separated,
	 * whitespace-separated, or array-based value into stable FAQ IDs.
	 *
	 * Example stored product meta:
	 * delivery-times,return-policy,warranty
	 */
	public function get_product_faq_ids( $product_id ) {
		$raw = get_post_meta( $product_id, self::PRODUCT_META_KEY, true );

		if ( is_array( $raw ) ) {
			$raw = implode( ',', $raw );
		}

		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			return array();
		}

		$ids = preg_split( '/[\s,;|]+/', $raw );

		$ids = array_map( 'sanitize_key', $ids );
		$ids = array_filter( $ids );

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Returns only valid registered FAQs attached to a product.
	 * Product CSV order is respected.
	 */
	public function get_product_faq_items( $product_id ) {
		$all_items = nh_theme_faq_items();
		$product_ids = $this->get_product_faq_ids( $product_id );
		$items = array();

		foreach ( $product_ids as $faq_id ) {
			if ( isset( $all_items[ $faq_id ] ) ) {
				$items[ $faq_id ] = $all_items[ $faq_id ];
			}
		}

		return $items;
	}

	/**
	 * Gets FAQ items which are assigned to at least one displayed topic.
	 */
	private function get_global_faq_items( $requested_topics = array() ) {
		$items = nh_theme_faq_items();
		$topics = nh_theme_faq_topics();

		if ( empty( $requested_topics ) ) {
			$requested_topics = array_keys( $topics );
		}

		$result = array();

		foreach ( $items as $faq_id => $item ) {
			$item_topics = ! empty( $item['topics'] ) && is_array( $item['topics'] )
				? $item['topics']
				: array();

			if ( array_intersect( $requested_topics, $item_topics ) ) {
				$result[ $faq_id ] = $item;
			}
		}

		return $result;
	}

	/**
	 * Renders the main FAQ page, grouped by topic.
	 */
	private function render_global_faqs( $requested_topics = array() ) {
		$topics = nh_theme_faq_topics();
		$items = $this->get_global_faq_items( $requested_topics );

		if ( empty( $items ) ) {
			return '';
		}

		if ( ! empty( $requested_topics ) ) {
			$topics = array_intersect_key(
				$topics,
				array_flip( $requested_topics )
			);
		}

		uasort(
			$topics,
			function( $a, $b ) {
				return (int) $a['order'] <=> (int) $b['order'];
			}
		);

		$groups = array();

		foreach ( $topics as $topic_id => $topic ) {
			$groups[ $topic_id ] = array(
				'label' => $topic['label'],
				'faqs'  => array(),
			);
		}

		foreach ( $items as $faq_id => $item ) {
			$item_topics = ! empty( $item['topics'] ) ? $item['topics'] : array();

			foreach ( $item_topics as $topic_id ) {
				if ( isset( $groups[ $topic_id ] ) ) {
					$groups[ $topic_id ]['faqs'][ $faq_id ] = $item;
				}
			}
		}

		$groups = array_filter(
			$groups,
			function( $group ) {
				return ! empty( $group['faqs'] );
			}
		);

		if ( empty( $groups ) ) {
			return '';
		}

		$instance_id = 'nh-faq-' . wp_generate_uuid4();

		ob_start();
		?>

		<nav class="nh-faq-index" aria-label="<?php echo esc_attr__( 'FAQ topics', 'nh-theme' ); ?>">
			<?php foreach ( $groups as $topic_id => $group ) : ?>
				<a href="#faq-topic-<?php echo esc_attr( $topic_id ); ?>">
					<?php echo esc_html( $group['label'] ); ?>
				</a>
			<?php endforeach; ?>
		</nav>

		<div class="nh-faq nh-faq--global nh-faq-grid" data-accordion="multi">
			<?php foreach ( $groups as $topic_id => $group ) : ?>
				<section
					class="nh-faq-topic-card"
					id="faq-topic-<?php echo esc_attr( $topic_id ); ?>"
					aria-labelledby="faq-topic-<?php echo esc_attr( $topic_id ); ?>-label"
				>
					<div class="nh-faq-card-head">
						<h2
							class="nh-faq-topic-heading"
							id="faq-topic-<?php echo esc_attr( $topic_id ); ?>-label"
						>
							<?php echo esc_html( $group['label'] ); ?>
						</h2>

						<div class="nh-faq-toolbar">
							<button type="button" class="nh-faq-tool" data-nh="expand-all">
								<?php esc_html_e( 'Expand all', 'nh-theme' ); ?>
							</button>

							<button type="button" class="nh-faq-tool" data-nh="collapse-all">
								<?php esc_html_e( 'Collapse all', 'nh-theme' ); ?>
							</button>
						</div>
					</div>

					<div class="nh-faq-group" role="list">
						<?php
						$this->render_faq_items(
							$group['faqs'],
							$instance_id . '-' . $topic_id
						);
						?>
					</div>
				</section>
			<?php endforeach; ?>
		</div>

		<?php
		return ob_get_clean();
	}

	/**
	 * Renders FAQs in the WooCommerce product tab.
	 */
	private function render_product_faqs( $items ) {
		if ( empty( $items ) ) {
			return '';
		}

		$instance_id = 'nh-product-faq-' . wp_generate_uuid4();

		ob_start();
		?>

		<div class="nh-faq" role="list" data-accordion="multi">
			<?php $this->render_faq_items( $items, $instance_id ); ?>
		</div>

		<?php
		return ob_get_clean();
	}

	/**
	 * Shared accordion markup.
	 */
	private function render_faq_items( $items, $instance_id ) {
		foreach ( $items as $faq_id => $item ) {
			$question = isset( $item['question'] ) ? $item['question'] : '';
			$answer   = isset( $item['answer'] ) ? $item['answer'] : '';

			if ( '' === $question || '' === $answer ) {
				continue;
			}

			$element_id = $instance_id . '-' . sanitize_html_class( $faq_id );
			?>

			<div class="nh-faq-item" id="<?php echo esc_attr( $element_id ); ?>" role="listitem">
				<h3 class="nh-faq-q-h3">
					<button
						class="nh-faq-h3btn"
						id="<?php echo esc_attr( $element_id ); ?>-button"
						type="button"
						aria-expanded="false"
						aria-controls="<?php echo esc_attr( $element_id ); ?>-answer"
					>
						<?php echo esc_html( $question ); ?>
					</button>
				</h3>

				<div
					class="nh-faq-a"
					id="<?php echo esc_attr( $element_id ); ?>-answer"
					role="region"
					aria-labelledby="<?php echo esc_attr( $element_id ); ?>-button"
					hidden
				>
					<?php echo wp_kses_post( wpautop( $answer ) ); ?>
				</div>
			</div>

			<?php
		}
	}

	/**
	 * Add the product FAQ tab only if at least one valid FAQ ID is attached.
	 */
	public function add_product_tab( $tabs ) {
		global $product;

		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return $tabs;
		}

		$items = $this->get_product_faq_items( $product->get_id() );

		if ( empty( $items ) ) {
			return $tabs;
		}

		$tabs['nh_faq'] = array(
			'title'    => __( 'Questions & Answers', 'nh-theme' ),
			'priority' => 25,
			'callback' => array( $this, 'render_product_tab' ),
		);

		return $tabs;
	}

	/**
	 * Product FAQ tab callback.
	 */
	public function render_product_tab() {
		global $product;

		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return;
		}

		$items = $this->get_product_faq_items( $product->get_id() );

		if ( empty( $items ) ) {
			return;
		}

		$this->enqueue_assets();

		echo $this->render_product_faqs( $items ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		$this->print_schema( $items );
	}

	/**
	 * Product editor field.
	 *
	 * This is optional for manual edits. Your CSV import should write
	 * directly to the same nh_faq_ids product meta field.
	 */
	public function add_product_metabox() {
		add_meta_box(
			'nh-theme-faq-ids',
			__( 'Product FAQ IDs', 'nh-theme' ),
			array( $this, 'render_product_metabox' ),
			'product',
			'side',
			'default'
		);
	}

	public function render_product_metabox( $post ) {
		$value = get_post_meta( $post->ID, self::PRODUCT_META_KEY, true );
		$items = nh_theme_faq_items();

		wp_nonce_field( 'nh_theme_faq_save_product', 'nh_theme_faq_nonce' );
		?>

		<p>
			<label for="nh_faq_ids">
				<?php esc_html_e( 'Attached FAQ IDs', 'nh-theme' ); ?>
			</label>
		</p>

		<textarea
			id="nh_faq_ids"
			name="nh_faq_ids"
			rows="4"
			style="width:100%;"
			placeholder="delivery-times,return-policy"
		><?php echo esc_textarea( $value ); ?></textarea>

		<p class="description">
			<?php esc_html_e( 'Use comma-separated FAQ IDs. This is the same product meta field used by CSV imports.', 'nh-theme' ); ?>
		</p>

		<?php if ( ! empty( $items ) ) : ?>
			<p><strong><?php esc_html_e( 'Available IDs:', 'nh-theme' ); ?></strong></p>

			<ul style="margin-left: 1em; list-style: disc;">
				<?php foreach ( $items as $faq_id => $item ) : ?>
					<li>
						<code><?php echo esc_html( $faq_id ); ?></code><br>
						<?php echo esc_html( $item['question'] ); ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php
	}

	public function save_product_metabox( $post_id, $post ) {
		if ( ! isset( $_POST['nh_theme_faq_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_POST['nh_theme_faq_nonce'], 'nh_theme_faq_save_product' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$value = isset( $_POST['nh_faq_ids'] )
			? sanitize_text_field( wp_unslash( $_POST['nh_faq_ids'] ) )
			: '';

		if ( '' === $value ) {
			delete_post_meta( $post_id, self::PRODUCT_META_KEY );
			return;
		}

		update_post_meta( $post_id, self::PRODUCT_META_KEY, $value );
	}

	/**
	 * Print FAQPage schema when there are at least two FAQs.
	 */
	private function print_schema( $items ) {
		if ( count( $items ) < 2 ) {
			return;
		}

		$entities = array();

		foreach ( $items as $item ) {
			if ( empty( $item['question'] ) || empty( $item['answer'] ) ) {
				continue;
			}

			$entities[] = array(
				'@type' => 'Question',
				'name'  => wp_strip_all_tags( $item['question'] ),
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => wp_strip_all_tags( $item['answer'] ),
				),
			);
		}

		if ( count( $entities ) < 2 ) {
			return;
		}

		$schema = array(
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'inLanguage' => get_bloginfo( 'language' ),
			'mainEntity' => $entities,
		);

		printf(
			'<script type="application/ld+json">%s</script>',
			wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
		);
	}
}

new NH_Theme_FAQ();
