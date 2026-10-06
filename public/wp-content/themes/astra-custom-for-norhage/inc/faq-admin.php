<?php
/**
 * WooCommerce admin screen for FAQ topics and questions.
 *
 * Text domain: nh-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turn the admin form into the stored FAQ list.
 *
 * @param mixed $raw_topics Posted topic rows.
 * @param mixed $raw_items  Posted question rows.
 * @return array
 */
function nh_theme_faq_prepare_content( $raw_topics, $raw_items ) {
	$topics  = array();
	$skipped = 0;

	if ( is_array( $raw_topics ) ) {
		foreach ( $raw_topics as $row ) {
			if ( ! is_array( $row ) || ! empty( $row['delete'] ) ) {
				continue;
			}

			$label = isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '';

			if ( '' === $label ) {
				continue;
			}

			$id = isset( $row['id'] ) ? sanitize_title( $row['id'] ) : '';

			if ( '' === $id ) {
				$id = sanitize_title( $label );
			}

			if ( '' === $id ) {
				$id = 'topic';
			}

			$topics[ nh_theme_faq_unique_id( $id, $topics ) ] = array(
				'label' => $label,
				'order' => isset( $row['order'] ) ? absint( $row['order'] ) : 0,
			);
		}
	}

	$position = 0;

	foreach ( $topics as $topic_id => $topic ) {
		$topics[ $topic_id ]['position'] = $position;
		$position++;
	}

	uasort(
		$topics,
		function( $a, $b ) {
			$by_order = (int) $a['order'] <=> (int) $b['order'];

			if ( 0 !== $by_order ) {
				return $by_order;
			}

			return (int) $a['position'] <=> (int) $b['position'];
		}
	);

	foreach ( $topics as $topic_id => $topic ) {
		unset( $topics[ $topic_id ]['position'] );
	}

	$topic_ids = array_keys( $topics );
	$items     = array();

	if ( is_array( $raw_items ) ) {
		foreach ( $raw_items as $row ) {
			if ( ! is_array( $row ) || ! empty( $row['delete'] ) ) {
				continue;
			}

			$question = isset( $row['question'] ) ? sanitize_text_field( $row['question'] ) : '';
			$answer   = isset( $row['answer'] ) ? nh_theme_faq_sanitize_answer( $row['answer'] ) : '';
			$has_text = '' !== trim( wp_strip_all_tags( $answer ) );

			if ( '' === $question && ! $has_text ) {
				continue;
			}

			if ( '' === $question || ! $has_text ) {
				$skipped++;
				continue;
			}

			$id = isset( $row['id'] ) ? sanitize_title( $row['id'] ) : '';

			if ( '' === $id ) {
				$id = sanitize_title( $question );
			}

			if ( '' === $id ) {
				$id = 'faq';
			}

			$item_topics = array();

			if ( ! empty( $row['topics'] ) && is_array( $row['topics'] ) ) {
				foreach ( $row['topics'] as $topic_id ) {
					$topic_id = sanitize_title( $topic_id );

					if ( in_array( $topic_id, $topic_ids, true ) && ! in_array( $topic_id, $item_topics, true ) ) {
						$item_topics[] = $topic_id;
					}
				}
			}

			$items[ nh_theme_faq_unique_id( $id, $items ) ] = array(
				'question' => $question,
				'answer'   => $answer,
				'topics'   => $item_topics,
			);
		}
	}

	return array(
		'custom'  => 1,
		'topics'  => $topics,
		'items'   => $items,
		'skipped' => $skipped,
	);
}

/**
 * @param string $id   Preferred id.
 * @param array  $used Ids already stored.
 * @return string
 */
function nh_theme_faq_unique_id( $id, $used ) {
	$base = $id;
	$n    = 2;

	while ( isset( $used[ $id ] ) ) {
		$id = $base . '-' . $n;
		$n++;
	}

	return $id;
}

/**
 * Keep the links already used in FAQ answers.
 *
 * @param string $answer Raw answer.
 * @return string
 */
function nh_theme_faq_sanitize_answer( $answer ) {
	$answer = is_string( $answer ) ? $answer : '';

	return trim(
		wp_kses(
			$answer,
			array(
				'a'      => array(
					'href'   => true,
					'title'  => true,
					'target' => true,
					'rel'    => true,
				),
				'strong' => array(),
				'em'     => array(),
				'br'     => array(),
			)
		)
	);
}

/**
 * @return bool
 */
function nh_theme_faq_user_can_edit() {
	return current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' );
}

add_action( 'admin_notices', 'nh_theme_faq_legacy_screen_notice' );
function nh_theme_faq_legacy_screen_notice() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

	if ( ! $screen || 'nh_faq' !== $screen->post_type ) {
		return;
	}

	if ( ! nh_theme_faq_user_can_edit() ) {
		return;
	}

	echo '<div class="notice notice-warning"><p>';
	echo esc_html__( 'The questions on the website are edited under WooCommerce → FAQs.', 'nh-theme' );
	echo ' <a href="' . esc_url( admin_url( 'admin.php?page=nh-theme-faqs' ) ) . '">';
	echo esc_html__( 'Open FAQs', 'nh-theme' );
	echo '</a></p></div>';
}

add_action( 'admin_menu', 'nh_theme_faq_register_admin_menu' );
function nh_theme_faq_register_admin_menu() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	add_submenu_page(
		'woocommerce',
		__( 'FAQs', 'nh-theme' ),
		__( 'FAQs', 'nh-theme' ),
		'manage_woocommerce',
		'nh-theme-faqs',
		'nh_theme_faq_render_admin_page'
	);
}

add_action( 'admin_enqueue_scripts', 'nh_theme_faq_admin_assets' );
function nh_theme_faq_admin_assets( $hook ) {
	if ( 'woocommerce_page_nh-theme-faqs' !== $hook ) {
		return;
	}

	$version = function_exists( 'norhage_asset_version' ) ? norhage_asset_version( '/assets/css/faq-admin.css' ) : '1.0.0';

	wp_enqueue_style(
		'nh-theme-faq-admin',
		get_stylesheet_directory_uri() . '/assets/css/faq-admin.css',
		array(),
		$version
	);

	wp_enqueue_script(
		'nh-theme-faq-admin',
		get_stylesheet_directory_uri() . '/assets/js/faq-admin.js',
		array(),
		$version,
		true
	);
}

add_action( 'admin_post_nh_theme_faq_save', 'nh_theme_faq_handle_save' );
function nh_theme_faq_handle_save() {
	if ( ! nh_theme_faq_user_can_edit() ) {
		wp_die( esc_html__( 'You do not have permission to edit FAQs.', 'nh-theme' ), '', array( 'response' => 403 ) );
	}

	check_admin_referer( 'nh_theme_faq_save' );

	$topics   = isset( $_POST['nh_faq_topics'] ) ? wp_unslash( $_POST['nh_faq_topics'] ) : array();
	$items    = isset( $_POST['nh_faq_items'] ) ? wp_unslash( $_POST['nh_faq_items'] ) : array();
	$prepared = nh_theme_faq_prepare_content( $topics, $items );
	$skipped  = isset( $prepared['skipped'] ) ? (int) $prepared['skipped'] : 0;

	unset( $prepared['skipped'] );
	update_option( 'nh_theme_faq_content', $prepared, true );

	wp_safe_redirect(
		add_query_arg(
			array(
				'page'    => 'nh-theme-faqs',
				'updated' => '1',
				'skipped' => $skipped,
			),
			admin_url( 'admin.php' )
		)
	);
	exit;
}

add_action( 'admin_post_nh_theme_faq_restore', 'nh_theme_faq_handle_restore' );
function nh_theme_faq_handle_restore() {
	if ( ! nh_theme_faq_user_can_edit() ) {
		wp_die( esc_html__( 'You do not have permission to edit FAQs.', 'nh-theme' ), '', array( 'response' => 403 ) );
	}

	check_admin_referer( 'nh_theme_faq_restore' );
	delete_option( 'nh_theme_faq_content' );

	wp_safe_redirect(
		add_query_arg(
			array(
				'page'     => 'nh-theme-faqs',
				'restored' => '1',
			),
			admin_url( 'admin.php' )
		)
	);
	exit;
}

/**
 * WooCommerce → FAQs.
 */
function nh_theme_faq_render_admin_page() {
	if ( ! nh_theme_faq_user_can_edit() ) {
		return;
	}

	$topics    = nh_theme_faq_topics();
	$items     = nh_theme_faq_items();
	$custom    = is_array( nh_theme_faq_saved_content() );
	$skipped   = isset( $_GET['skipped'] ) ? absint( wp_unslash( $_GET['skipped'] ) ) : 0;
	$next_order = 10;

	foreach ( $topics as $topic ) {
		$next_order = max( $next_order, (int) $topic['order'] + 10 );
	}

	?>
	<div class="wrap nh-faq-admin-layout">
		<h1><?php esc_html_e( 'FAQs', 'nh-theme' ); ?></h1>

		<?php if ( isset( $_GET['updated'] ) ) : ?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php esc_html_e( 'FAQs saved.', 'nh-theme' ); ?>
					<?php if ( $skipped > 0 ) : ?>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d: number of incomplete questions */
								_n(
									'%d question was left out because it needs both a question and an answer.',
									'%d questions were left out because they need both a question and an answer.',
									$skipped,
									'nh-theme'
								),
								$skipped
							)
						);
						?>
					<?php endif; ?>
				</p>
			</div>
		<?php endif; ?>

		<?php if ( isset( $_GET['restored'] ) ) : ?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'Original questions restored.', 'nh-theme' ); ?></p>
			</div>
		<?php endif; ?>

		<p>
			<?php esc_html_e( 'These are the questions on the FAQ page. The same questions can be ticked on a product, in the Product FAQs box.', 'nh-theme' ); ?>
		</p>
		<p>
			<?php if ( $custom ) : ?>
				<?php esc_html_e( 'This shop is using the text saved here. Restore original questions brings back the built-in FAQ.', 'nh-theme' ); ?>
			<?php else : ?>
				<?php esc_html_e( 'This list is already filled with the current FAQ. The shop keeps that text until you click Save FAQs.', 'nh-theme' ); ?>
			<?php endif; ?>
		</p>

		<form id="nh-faq-admin-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'nh_theme_faq_save' ); ?>
			<input type="hidden" name="action" value="nh_theme_faq_save">

			<p>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save FAQs', 'nh-theme' ); ?></button>
			</p>

			<h2><?php esc_html_e( 'Topics', 'nh-theme' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Topics are the groups on the FAQ page, such as Ordering & Delivery. Order is the smallest number first.', 'nh-theme' ); ?>
			</p>

			<table class="widefat striped nh-faq-topic-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Topic name', 'nh-theme' ); ?></th>
						<th><?php esc_html_e( 'Order', 'nh-theme' ); ?></th>
						<th><?php esc_html_e( 'Move', 'nh-theme' ); ?></th>
						<th><?php esc_html_e( 'Remove', 'nh-theme' ); ?></th>
					</tr>
				</thead>
				<tbody id="nh-faq-topics">
					<?php
					$topic_index = 0;
					foreach ( $topics as $topic_id => $topic ) {
						nh_theme_faq_admin_topic_row(
							$topic_index,
							$topic_id,
							isset( $topic['label'] ) ? $topic['label'] : '',
							isset( $topic['order'] ) ? (int) $topic['order'] : 0,
							true
						);
						$topic_index++;
					}
					nh_theme_faq_admin_topic_row( $topic_index, '', '', $next_order, false );
					?>
				</tbody>
			</table>

			<p>
				<button type="button" class="button nh-faq-add-topic"><?php esc_html_e( 'Add topic', 'nh-theme' ); ?></button>
			</p>

			<h2>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: number of saved questions */
						__( 'Questions (%d)', 'nh-theme' ),
						count( $items )
					)
				);
				?>
			</h2>
			<p class="description">
				<?php esc_html_e( 'A question needs an answer. Tick every topic where it should appear. Questions with no topic can still be used on products, but they stay off the main FAQ page.', 'nh-theme' ); ?>
			</p>
			<p class="description">
				<?php esc_html_e( 'To keep a link, leave it written as HTML. Example: <a href="https://example.com">link text</a>.', 'nh-theme' ); ?>
			</p>

			<p>
				<label class="screen-reader-text" for="nh-faq-admin-filter"><?php esc_html_e( 'Search questions', 'nh-theme' ); ?></label>
				<input type="search" id="nh-faq-admin-filter" class="nh-faq-admin-filter regular-text" placeholder="<?php esc_attr_e( 'Search questions', 'nh-theme' ); ?>">
			</p>

			<p>
				<button type="button" class="button nh-faq-add-item"><?php esc_html_e( 'Add question', 'nh-theme' ); ?></button>
			</p>

			<div id="nh-faq-items">
				<?php
				$item_index = 0;
				foreach ( $items as $faq_id => $item ) {
					nh_theme_faq_admin_item_card( $item_index, $faq_id, $item, $topics );
					$item_index++;
				}
				nh_theme_faq_admin_item_card(
					$item_index,
					'',
					array(
						'question' => '',
						'answer'   => '',
						'topics'   => array(),
					),
					$topics
				);
				?>
			</div>
		</form>

		<?php if ( $custom ) : ?>
			<form
				id="nh-faq-restore"
				method="post"
				action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
				data-confirm="<?php esc_attr_e( 'Restore the original questions? Your saved edits will be removed.', 'nh-theme' ); ?>"
			>
				<?php wp_nonce_field( 'nh_theme_faq_restore' ); ?>
				<input type="hidden" name="action" value="nh_theme_faq_restore">
			</form>
		<?php endif; ?>

		<div class="nh-faq-admin-submit">
			<button type="submit" class="button button-primary" form="nh-faq-admin-form"><?php esc_html_e( 'Save FAQs', 'nh-theme' ); ?></button>
			<?php if ( $custom ) : ?>
				<button type="submit" class="button" form="nh-faq-restore"><?php esc_html_e( 'Restore original questions', 'nh-theme' ); ?></button>
			<?php endif; ?>
		</div>
	</div>

	<template id="nh-faq-topic-template">
		<?php nh_theme_faq_admin_topic_row( '__i__', '', '', '', false ); ?>
	</template>

	<template id="nh-faq-item-template">
		<?php
		nh_theme_faq_admin_item_card(
			'__i__',
			'',
			array(
				'question' => '',
				'answer'   => '',
				'topics'   => array(),
			),
			$topics
		);
		?>
	</template>
	<?php
}

/**
 * @param int|string $index    Field index.
 * @param string     $topic_id Stable topic id. Empty for a new row.
 * @param string     $label    Topic label.
 * @param int|string $order    Sort order.
 * @param bool       $locked   Keep the id when the label changes.
 */
function nh_theme_faq_admin_topic_row( $index, $topic_id, $label, $order, $locked ) {
	$name = 'nh_faq_topics[' . $index . ']';
	?>
	<tr class="nh-faq-topic-row">
		<td>
			<input
				type="hidden"
				class="nh-faq-topic-id"
				name="<?php echo esc_attr( $name ); ?>[id]"
				value="<?php echo esc_attr( $topic_id ); ?>"
				<?php echo $locked ? 'data-keep="1"' : ''; ?>
			>
			<input
				type="text"
				class="regular-text nh-faq-topic-label"
				name="<?php echo esc_attr( $name ); ?>[label]"
				value="<?php echo esc_attr( $label ); ?>"
				placeholder="<?php esc_attr_e( 'New topic name', 'nh-theme' ); ?>"
			>
		</td>
		<td>
			<input
				type="number"
				class="small-text"
				name="<?php echo esc_attr( $name ); ?>[order]"
				value="<?php echo esc_attr( (string) $order ); ?>"
				min="0"
				step="1"
			>
		</td>
		<td>
			<button type="button" class="button nh-faq-move is-up"><?php esc_html_e( 'Up', 'nh-theme' ); ?></button>
			<button type="button" class="button nh-faq-move is-down"><?php esc_html_e( 'Down', 'nh-theme' ); ?></button>
		</td>
		<td>
			<label>
				<input type="checkbox" class="nh-faq-delete" name="<?php echo esc_attr( $name ); ?>[delete]" value="1">
				<?php esc_html_e( 'Remove', 'nh-theme' ); ?>
			</label>
		</td>
	</tr>
	<?php
}

/**
 * @param int|string $index  Field index.
 * @param string     $faq_id Stable FAQ id. Empty for a new question.
 * @param array      $item   Question, answer, and topic ids.
 * @param array      $topics Topic registry.
 */
function nh_theme_faq_admin_item_card( $index, $faq_id, $item, $topics ) {
	$name      = 'nh_faq_items[' . $index . ']';
	$question  = isset( $item['question'] ) ? $item['question'] : '';
	$answer    = isset( $item['answer'] ) ? $item['answer'] : '';
	$selected  = ! empty( $item['topics'] ) && is_array( $item['topics'] ) ? $item['topics'] : array();
	$is_new    = '' === $faq_id && '' === $question;
	?>
	<div class="nh-faq-admin-item">
		<div class="nh-faq-admin-item__head">
			<strong>
				<?php echo $is_new ? esc_html__( 'New question', 'nh-theme' ) : esc_html__( 'Question', 'nh-theme' ); ?>
			</strong>
			<label>
				<input type="checkbox" class="nh-faq-delete" name="<?php echo esc_attr( $name ); ?>[delete]" value="1">
				<?php esc_html_e( 'Remove', 'nh-theme' ); ?>
			</label>
		</div>

		<input type="hidden" name="<?php echo esc_attr( $name ); ?>[id]" value="<?php echo esc_attr( $faq_id ); ?>">

		<?php if ( '' !== $faq_id ) : ?>
			<p class="description nh-faq-admin-id">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: stable FAQ id used by product imports */
						__( 'ID: %s', 'nh-theme' ),
						$faq_id
					)
				);
				?>
			</p>
		<?php endif; ?>

		<p>
			<label>
				<?php esc_html_e( 'Question', 'nh-theme' ); ?><br>
				<input type="text" class="large-text nh-faq-question" name="<?php echo esc_attr( $name ); ?>[question]" value="<?php echo esc_attr( $question ); ?>">
			</label>
		</p>
		<p>
			<label>
				<?php esc_html_e( 'Answer', 'nh-theme' ); ?><br>
				<textarea class="large-text" rows="4" name="<?php echo esc_attr( $name ); ?>[answer]"><?php echo esc_textarea( $answer ); ?></textarea>
			</label>
		</p>
		<fieldset class="nh-faq-admin-topics">
			<legend><?php esc_html_e( 'Show under', 'nh-theme' ); ?></legend>
			<div class="nh-faq-item-topics" data-name="<?php echo esc_attr( $name ); ?>[topics][]">
				<?php foreach ( $topics as $topic_id => $topic ) : ?>
					<label>
						<input
							type="checkbox"
							name="<?php echo esc_attr( $name ); ?>[topics][]"
							value="<?php echo esc_attr( $topic_id ); ?>"
							<?php checked( in_array( $topic_id, $selected, true ) ); ?>
						>
						<?php echo esc_html( isset( $topic['label'] ) ? $topic['label'] : $topic_id ); ?>
					</label>
				<?php endforeach; ?>
			</div>
		</fieldset>
		<p class="nh-faq-admin-actions">
			<button type="button" class="button nh-faq-move is-up"><?php esc_html_e( 'Up', 'nh-theme' ); ?></button>
			<button type="button" class="button nh-faq-move is-down"><?php esc_html_e( 'Down', 'nh-theme' ); ?></button>
		</p>
	</div>
	<?php
}
