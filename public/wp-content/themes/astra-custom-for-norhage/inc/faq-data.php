<?php
/**
 * Theme FAQ data.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Saved FAQ content, or null while this shop still uses the built-in text.
 *
 * @return array|null
 */
function nh_theme_faq_saved_content() {
	if ( ! function_exists( 'get_option' ) ) {
		return null;
	}

	$saved = get_option( 'nh_theme_faq_content', null );

	if ( ! is_array( $saved ) || empty( $saved['custom'] ) ) {
		return null;
	}

	$topics = array();

	if ( ! empty( $saved['topics'] ) && is_array( $saved['topics'] ) ) {
		foreach ( $saved['topics'] as $topic_id => $topic ) {
			$topic_id = sanitize_title( (string) $topic_id );

			if ( '' === $topic_id || ! is_array( $topic ) ) {
				continue;
			}

			$label = isset( $topic['label'] ) ? (string) $topic['label'] : '';

			if ( '' === $label ) {
				continue;
			}

			$topics[ $topic_id ] = array(
				'label' => $label,
				'order' => isset( $topic['order'] ) ? (int) $topic['order'] : 0,
			);
		}
	}

	$items = array();

	if ( ! empty( $saved['items'] ) && is_array( $saved['items'] ) ) {
		foreach ( $saved['items'] as $faq_id => $item ) {
			$faq_id = sanitize_title( (string) $faq_id );

			if ( '' === $faq_id || ! is_array( $item ) ) {
				continue;
			}

			$question = isset( $item['question'] ) ? (string) $item['question'] : '';
			$answer   = isset( $item['answer'] ) ? (string) $item['answer'] : '';

			if ( '' === $question || '' === $answer ) {
				continue;
			}

			$item_topics = array();

			if ( ! empty( $item['topics'] ) && is_array( $item['topics'] ) ) {
				foreach ( $item['topics'] as $topic_id ) {
					$topic_id = sanitize_title( (string) $topic_id );

					if ( isset( $topics[ $topic_id ] ) && ! in_array( $topic_id, $item_topics, true ) ) {
						$item_topics[] = $topic_id;
					}
				}
			}

			$items[ $faq_id ] = array(
				'question' => $question,
				'answer'   => $answer,
				'topics'   => $item_topics,
			);
		}
	}

	return array(
		'topics' => $topics,
		'items'  => $items,
	);
}

/**
 * FAQ topics for the main FAQ page.
 * Uses text saved in WooCommerce admin when present.
 */
function nh_theme_faq_topics() {
	$saved = nh_theme_faq_saved_content();

	if ( is_array( $saved ) ) {
		return $saved['topics'];
	}

	return nh_theme_faq_default_topics();
}

/**
 * FAQ registry.
 * Uses text saved in WooCommerce admin when present.
 */
function nh_theme_faq_items() {
	$saved = nh_theme_faq_saved_content();

	if ( is_array( $saved ) ) {
		return $saved['items'];
	}

	return nh_theme_faq_default_items();
}

/**
 * Shop language from Settings → General.
 * wp-admin otherwise follows the account language, so the FAQ editor stayed English.
 *
 * @return string
 */
function nh_theme_faq_site_locale() {
	$locale = function_exists( 'get_option' ) ? (string) get_option( 'WPLANG', '' ) : '';
	$locale = str_replace( '-', '_', trim( $locale ) );

	return '' !== $locale ? $locale : 'en_US';
}

/**
 * True when this request is wp-admin in a different language from the shop.
 *
 * @return bool
 */
function nh_theme_faq_use_shop_catalog() {
	if ( ! function_exists( 'is_admin' ) || ! is_admin() ) {
		return false;
	}

	$site   = nh_theme_faq_site_locale();
	$active = $site;

	if ( function_exists( 'determine_locale' ) ) {
		$active = (string) determine_locale();
	} elseif ( function_exists( 'get_locale' ) ) {
		$active = (string) get_locale();
	}

	return $active !== $site;
}

/**
 * Locale codes to try, same order as the theme language loader.
 *
 * @param string $locale Site locale.
 * @return string[]
 */
function nh_theme_faq_locale_codes( $locale ) {
	$codes = array( $locale );

	if ( false !== strpos( $locale, '_' ) ) {
		$codes[] = substr( $locale, 0, strpos( $locale, '_' ) );
	} elseif ( 'fi' === $locale ) {
		$codes[] = 'fi_FI';
	}

	return array_values( array_unique( $codes ) );
}

/**
 * FAQ text for the shop language.
 * In wp-admin this reads the shop language file directly. It does not follow the account language.
 *
 * @param string $text   English source string.
 * @param string $domain Unused. Kept so calls match the old __() shape.
 * @return string
 */
function nh_theme_faq_t( $text, $domain = 'nh-theme' ) {
	unset( $domain );

	if ( nh_theme_faq_use_shop_catalog() ) {
		$map = nh_theme_faq_shop_translation_map();

		if ( isset( $map[ $text ] ) && is_string( $map[ $text ] ) && '' !== $map[ $text ] ) {
			return $map[ $text ];
		}
	}

	return __( $text, 'nh-theme' );
}

/**
 * English msgid => shop translation, from the same language file the storefront uses.
 *
 * @return array<string,string>
 */
function nh_theme_faq_shop_translation_map() {
	static $cache = array();

	$locale = nh_theme_faq_site_locale();

	if ( isset( $cache[ $locale ] ) ) {
		return $cache[ $locale ];
	}

	$cache[ $locale ] = array();
	$dir              = function_exists( 'get_stylesheet_directory' ) ? get_stylesheet_directory() . '/languages' : dirname( __DIR__ ) . '/languages';

	foreach ( nh_theme_faq_locale_codes( $locale ) as $code ) {
		$mo = $dir . '/' . $code . '.mo';
		$po = $dir . '/' . $code . '.po';

		if ( is_readable( $mo ) ) {
			$map = nh_theme_faq_read_mo_map( $mo );

			if ( ! empty( $map ) ) {
				$cache[ $locale ] = $map;
				break;
			}
		}

		if ( is_readable( $po ) ) {
			$cache[ $locale ] = nh_theme_faq_read_po_map( (string) file_get_contents( $po ) );
			break;
		}
	}

	return $cache[ $locale ];
}

/**
 * @param string $file Path to a .mo file.
 * @return array<string,string>
 */
function nh_theme_faq_read_mo_map( $file ) {
	if ( ! class_exists( 'MO' ) && defined( 'ABSPATH' ) && defined( 'WPINC' ) && is_readable( ABSPATH . WPINC . '/pomo/mo.php' ) ) {
		require_once ABSPATH . WPINC . '/pomo/mo.php';
	}

	if ( ! class_exists( 'MO' ) ) {
		return array();
	}

	$mo = new MO();

	if ( ! $mo->import_from_file( $file ) || empty( $mo->entries ) || ! is_array( $mo->entries ) ) {
		return array();
	}

	$map = array();

	foreach ( $mo->entries as $entry ) {
		if ( ! is_object( $entry ) || '' === $entry->singular || empty( $entry->translations[0] ) ) {
			continue;
		}

		$map[ $entry->singular ] = $entry->translations[0];
	}

	return $map;
}

/**
 * @param string $contents PO file contents.
 * @return array<string,string>
 */
function nh_theme_faq_read_po_map( $contents ) {
	$lines  = explode( "\n", str_replace( "\r\n", "\n", $contents ) );
	$map    = array();
	$mode   = '';
	$msgid  = '';
	$msgstr = '';

	$flush = function () use ( &$map, &$msgid, &$msgstr, &$mode ) {
		if ( '' !== $msgid && '' !== $msgstr ) {
			$map[ $msgid ] = $msgstr;
		}

		$msgid  = '';
		$msgstr = '';
		$mode   = '';
	};

	foreach ( $lines as $line ) {
		if ( '' === $line ) {
			if ( '' !== $mode ) {
				$flush();
			}
			continue;
		}

		if ( '#' === $line[0] ) {
			continue;
		}

		if ( 0 === strpos( $line, 'msgid "' ) ) {
			if ( '' !== $mode ) {
				$flush();
			}

			$mode  = 'id';
			$msgid = nh_theme_faq_po_string( $line );
			continue;
		}

		if ( 0 === strpos( $line, 'msgstr "' ) ) {
			$mode   = 'str';
			$msgstr = nh_theme_faq_po_string( $line );
			continue;
		}

		if ( '"' === $line[0] && 'id' === $mode ) {
			$msgid .= nh_theme_faq_po_string( $line );
			continue;
		}

		if ( '"' === $line[0] && 'str' === $mode ) {
			$msgstr .= nh_theme_faq_po_string( $line );
		}
	}

	$flush();

	return $map;
}

/**
 * Unescape one quoted PO string.
 *
 * @param string $line PO line.
 * @return string
 */
function nh_theme_faq_po_string( $line ) {
	if ( ! preg_match( '/"((?:\\\\.|[^"\\\\])*)"/', $line, $matches ) ) {
		return '';
	}

	return stripcslashes( $matches[1] );
}

/**
 * Built-in FAQ topics. These are the starting text, including translations.
 */
function nh_theme_faq_default_topics() {
	return array(
		// PROCESS TOPICS
		'ordering' => array(
			'label' => nh_theme_faq_t( 'Ordering & Delivery', 'nh-theme' ),
			'order' => 10,
		),
		'returns' => array(
			'label' => nh_theme_faq_t( 'Returns & Warranty', 'nh-theme' ),
			'order' => 20,
		),

		// PRODUCT TOPICS
		'greenhouses' => array(
			'label' => nh_theme_faq_t( 'Greenhouses', 'nh-theme' ),
			'order' => 30,
		),
		'polycarbonate' => array(
			'label' => nh_theme_faq_t( 'Polycarbonate & Plastics', 'nh-theme' ),
			'order' => 40,
		),
		'installation' => array(
			'label' => nh_theme_faq_t( 'Installation & Assembly', 'nh-theme' ),
			'order' => 50,
		),
		'accessories' => array(
			'label' => nh_theme_faq_t( 'Garden & Accessories', 'nh-theme' ),
			'order' => 60,
		),
	);
}

/**
 * Built-in FAQ registry. These are the starting questions, including translations.
 */
function nh_theme_faq_default_items() {
	return array(

        // ORDERING
        'delivery-large-items' => array(
            'question' => nh_theme_faq_t( 'How are large items delivered?', 'nh-theme' ),
            'answer'   => nh_theme_faq_t( 'Greenhouse kits and large sheets are delivered via specialized heavy freight transport. Most orders arrive on a single oversized pallet.', 'nh-theme' ),
            'topics'   => array( 'ordering' ),
        ),

        'delivery-forklift' => array(
            'question' => nh_theme_faq_t( 'Do I need a forklift to unload?', 'nh-theme' ),
            'answer'   => nh_theme_faq_t( 'No. Our delivery trucks are equipped with lift-gates to lower the pallet to the ground. However, you should have space available near the curbside or driveway for the truck to operate.', 'nh-theme' ),
            'topics'   => array( 'ordering' ),
        ),

        'delivery-curbside' => array(
            'question' => nh_theme_faq_t( 'What is "Curbside Delivery"?', 'nh-theme' ),
            'answer'   => nh_theme_faq_t( 'This means the driver will unload the pallet at the end of your driveway or the nearest accessible point for the truck. Drivers cannot move the materials into your backyard or garage.', 'nh-theme' ),
            'topics'   => array( 'ordering' ),
        ),

        'delivery-driver-call' => array(
            'question' => nh_theme_faq_t( 'Will the driver call me before arrival?', 'nh-theme' ),
            'answer'   => nh_theme_faq_t( 'Yes. For heavy freight deliveries, the transport company will contact you at the phone number provided in your order to schedule a specific delivery window.', 'nh-theme' ),
            'topics'   => array( 'ordering' ),
        ),

        'order-change' => array(
            'question' => nh_theme_faq_t( 'Can I change my order after it has been placed?', 'nh-theme' ),
            'answer'   => nh_theme_faq_t( 'If the order has not yet been loaded for transport, we can make changes. Contact us as soon as possible if you need to adjust quantities or sizes.', 'nh-theme' ),
            'topics'   => array( 'ordering' ),
        ),

        // RETURN AND WARRANTY
        'return-period' => array(
            'question' => nh_theme_faq_t( 'How long do I have to return an item?', 'nh-theme' ),
            'answer'   => nh_theme_faq_t( 'Our return period follows local consumer protection laws. Please check our <a href="https://norhage.eu/refund-and-returns-policy/">Returns & Refunds</a> page for the specific timeframe applicable in your region.', 'nh-theme' ),
            'topics'   => array( 'returns' ),
        ),

        'return-condition' => array(
            'question' => nh_theme_faq_t( 'What condition must the items be in for a return?', 'nh-theme' ),
            'answer'   => nh_theme_faq_t( 'Items must be unused, in their original packaging, and in a condition suitable for resale. Custom-cut materials or specially ordered items may be subject to different conditions.', 'nh-theme' ),
            'topics'   => array( 'returns' ),
        ),

        'return-shipping-cost' => array(
            'question' => nh_theme_faq_t( 'Who pays for return shipping?', 'nh-theme' ),
            'answer'   => nh_theme_faq_t( 'Unless the item is defective or the wrong product was sent, the customer is generally responsible for return transport costs, especially for large freight items.', 'nh-theme' ),
            'topics'   => array( 'returns' ),
        ),

        'warranty-coverage' => array(
            'question' => nh_theme_faq_t( 'What does the warranty cover?', 'nh-theme' ),
            'answer'   => nh_theme_faq_t( 'Warranty covers manufacturing defects in materials and workmanship. It does not cover damage caused by extreme weather events (storms, heavy snow loads beyond limits), improper assembly, or lack of maintenance.', 'nh-theme' ),
            'topics'   => array( 'returns' ),
        ),

        'damage-on-arrival' => array(
            'question' => nh_theme_faq_t( 'What should I do if my order arrives damaged?', 'nh-theme' ),
            'answer'   => nh_theme_faq_t( 'It is critical to inspect the delivery before signing the courier documents. If damage is visible, note it on the transport document and contact our customer support immediately with photos.', 'nh-theme' ),
            'topics'   => array( 'returns' ),
        ),

        //GREENHOUSES
        'greenhouse-cold-climate' => array(
            'question' => nh_theme_faq_t( 'Which greenhouse material is best for a cold climate?', 'nh-theme' ),
            'answer'   => nh_theme_faq_t( 'For cold climates, polycarbonate glazing is often a practical choice because it provides better thermal insulation and higher impact resistance than single-pane glass. The best option still depends on the greenhouse model, local wind and snow conditions, and whether you plan to heat the greenhouse.', 'nh-theme' ),
            'topics'   => array( 'greenhouses' ),
        ),

        'greenhouse-maintenance' => array(
            'question' => nh_theme_faq_t( 'What maintenance does a greenhouse require?', 'nh-theme' ),
            'answer'   => nh_theme_faq_t( 'Regular maintenance helps keep your greenhouse safe and long-lasting. Clean the glazing, check the frame, fasteners, seals and doors, and make sure vents operate freely. Remove leaves and debris from gutters or roof areas, and follow the manufacturer’s instructions for seasonal checks and snow management.', 'nh-theme' ),
            'topics'   => array( 'greenhouses' ),
        ),

        'greenhouse-ventilation' => array(
            'question' => nh_theme_faq_t( 'What ventilation options are available for greenhouses?', 'nh-theme' ),
            'answer'   => nh_theme_faq_t( 'Greenhouse ventilation may include roof vents, side vents, doors, louvre vents and automatic vent openers. A well-planned combination improves air circulation and helps manage temperature and humidity during warmer periods.', 'nh-theme' ),
            'topics'   => array( 'greenhouses' ),
        ),

        'greenhouse-professional-installation' => array(
            'question' => nh_theme_faq_t( 'Is professional greenhouse installation necessary?', 'nh-theme' ),
            'answer'   => nh_theme_faq_t( 'Professional installation is not always required, but it can be a good option for larger, permanent or more complex greenhouses. Careful assembly, correct anchoring and a level base are essential regardless of who installs the greenhouse. Always follow the supplied installation instructions.', 'nh-theme' ),
            'topics'   => array( 'greenhouses' ),
        ),

        'greenhouse-foundation' => array(
            'question' => nh_theme_faq_t( 'Does a greenhouse need a foundation?', 'nh-theme' ),
            'answer'   => nh_theme_faq_t( 'A stable, level and properly prepared base is important for the safe installation and long-term performance of most greenhouses. Suitable foundation options vary by model and site, and may include a concrete base, timber frame, foundation base or ground anchors. Check the product documentation before choosing a solution.', 'nh-theme' ),
            'topics'   => array( 'greenhouses' ),
        ),

        'greenhouse-expansion' => array(
            'question' => nh_theme_faq_t( 'Can I extend my greenhouse later?', 'nh-theme' ),
            'answer'   => nh_theme_faq_t( 'Some greenhouse models can be extended using compatible extension kits. Availability depends on the specific model, frame system and manufacturer. If future expansion is important, check this before purchasing and allow enough space when planning the installation area.', 'nh-theme' ),
            'topics'   => array( 'greenhouses' ),
        ),
        'greenhouse-wom-exposure' => array(
            'question' => nh_theme_faq_t( 'What does "WOM exposure" mean in the greenhouse plastic specifications?', 'nh-theme' ),
            'answer'   => nh_theme_faq_t( 'WOM exposure refers to standardized testing conducted in a "Weather-Ometer." This laboratory equipment simulates years of outdoor weather conditions in a short period by exposing the material to intense UV radiation, high heat, and moisture. This test ensures the plastic film or sheet meets durability standards and will maintain its structural integrity and light transmission over many years in a real-world environment.', 'nh-theme' ),
            'topics'   => array( 'greenhouses' ),
        ),

        //POLYCARBONATE AND PLASTICS
        'plastic-sheets-pc-vs-pmma' => array(
            'question' => nh_theme_faq_t( 'What is the difference between polycarbonate and acrylic (PMMA) sheets?', 'nh-theme' ),
            'answer'   => nh_theme_faq_t( 'Polycarbonate and acrylic are both strong, lightweight alternatives to glass, but they have different properties. Polycarbonate is known for its very high impact resistance and is often chosen for demanding applications. Acrylic (PMMA) offers excellent clarity and a smooth, glass-like appearance. The best material depends on the application, required strength, appearance and installation conditions.', 'nh-theme' ),
            'topics'   => array( 'polycarbonate' ),
        ),

        'plastic-sheets-solid-vs-multiwall' => array(
            'question' => nh_theme_faq_t( 'What is the difference between solid and multiwall polycarbonate sheets?', 'nh-theme' ),
            'answer'   => nh_theme_faq_t( 'Solid polycarbonate sheets are compact, transparent sheets with a smooth surface and a glass-like appearance. Multiwall polycarbonate sheets have internal channels that make them lighter and improve thermal insulation. Solid sheets are often selected for clear glazing and impact resistance, while multiwall sheets are commonly used for roofs, greenhouses and insulated structures.', 'nh-theme' ),
            'topics'   => array( 'polycarbonate' ),
        ),

        'plastic-sheets-uv-protection' => array(
            'question' => nh_theme_faq_t( 'Do your polycarbonate and acrylic sheets have UV protection?', 'nh-theme' ),
            'answer'   => nh_theme_faq_t( 'Yes. Our polycarbonate and acrylic (PMMA) sheets include UV protection for outdoor use. This helps protect the material from weathering and supports long-term performance when the sheets are installed correctly.', 'nh-theme' ),
            'topics'   => array( 'polycarbonate' ),
        ),

        'plastic-sheets-thickness' => array(
            'question' => nh_theme_faq_t( 'How do I choose the right sheet thickness?', 'nh-theme' ),
            'answer'   => nh_theme_faq_t( 'The right thickness depends on the material, application, supporting frame, distance between supports, local wind and snow conditions, and the insulation or impact resistance required. For roofing and glazing projects, select the sheet thickness together with a suitable support and fixing system.', 'nh-theme' ),
            'topics'   => array( 'polycarbonate' ),
        ),

        'plastic-sheets-cutting' => array(
            'question' => nh_theme_faq_t( 'Can plastic sheets be cut to size?', 'nh-theme' ),
            'answer'   => nh_theme_faq_t( 'Yes. Polycarbonate and acrylic sheets can usually be cut to size using appropriate tools and a blade suitable for plastics. Measure carefully, support the sheet properly while cutting and follow the product-specific cutting guidance. Multiwall polycarbonate channels should be cleaned of cutting dust before the sheet ends are sealed.', 'nh-theme' ),
            'topics'   => array( 'polycarbonate' ),
        ),

        'plastic-sheets-thermal-expansion' => array(
            'question' => nh_theme_faq_t( 'Do plastic sheets expand and contract with temperature changes?', 'nh-theme' ),
            'answer'   => nh_theme_faq_t( 'Yes. Polycarbonate and acrylic sheets expand and contract when temperatures change. Always allow suitable expansion gaps during installation, use compatible profiles and fixings, and avoid overtightening screws. This helps prevent stress, distortion and damage over time.', 'nh-theme' ),
            'topics'   => array( 'polycarbonate' ),
        ),

        'plastic-sheets-multiwall-sealing' => array(
            'question' => nh_theme_faq_t( 'How should multiwall polycarbonate sheet ends be sealed?', 'nh-theme' ),
            'answer'   => nh_theme_faq_t( 'Multiwall polycarbonate sheet channels should be protected from dust, insects and moisture with suitable sealing tape and end profiles. The upper edge is normally sealed with solid tape, while the lower edge commonly uses breathable tape to allow condensation to drain. Always install the channels in the correct direction and follow the instructions for the selected sheet system.', 'nh-theme' ),
            'topics'   => array( 'polycarbonate' ),
        ),

        'plastic-sheets-cleaning' => array(
            'question' => nh_theme_faq_t( 'How do I clean polycarbonate and acrylic sheets?', 'nh-theme' ),
            'answer'   => nh_theme_faq_t( 'Use lukewarm water, mild soap and a soft cloth or sponge. Rinse the sheet thoroughly before wiping to remove dust and loose particles. Avoid abrasive pads, strong solvents and harsh cleaning products, as they can scratch or damage the surface.', 'nh-theme' ),
            'topics'   => array( 'polycarbonate' ),
        ),

        'plastic-sheets-outdoor-use' => array(
            'question' => nh_theme_faq_t( 'Are plastic sheets suitable for outdoor use?', 'nh-theme' ),
            'answer'   => nh_theme_faq_t( 'Yes. Polycarbonate and acrylic sheets are widely used for outdoor glazing, roofing, shelters, greenhouses and similar projects. Choose the material, thickness, support spacing and installation system according to the intended application and local weather conditions.', 'nh-theme' ),
            'topics'   => array( 'polycarbonate' ),
        ),

        'plastic-sheets-greenhouse-use' => array(
            'question' => nh_theme_faq_t( 'Which plastic sheet is best for a greenhouse?', 'nh-theme' ),
            'answer'   => nh_theme_faq_t( 'Multiwall polycarbonate is a popular greenhouse material because its internal structure improves thermal insulation while keeping the sheets lightweight. Solid polycarbonate or acrylic may also be suitable where a clearer appearance is preferred. Consider insulation, light transmission, structural requirements and the greenhouse design when choosing.', 'nh-theme' ),
            'topics'   => array( 'polycarbonate' ),
        ),

        // INSTALLATION AND ASSEMBLY
        'installation-read-instructions' => array(
            'question' => nh_theme_faq_t( 'Should I read the installation instructions before starting?', 'nh-theme' ),
            'answer'   => nh_theme_faq_t( 'Yes. Read the complete product-specific instructions before starting work and check that all components are present. Installation methods, required tools, fixing details and safety requirements may vary between products.', 'nh-theme' ),
            'topics'   => array( 'installation' ),
        ),

        'installation-diy-or-professional' => array(
            'question' => nh_theme_faq_t( 'Can I install the product myself?', 'nh-theme' ),
            'answer'   => nh_theme_faq_t( 'Many products can be installed by an experienced DIY customer when the supplied instructions are followed carefully. Larger, heavier or more complex structures may require more than one person. Professional installation can be a practical option where the project, site conditions or local requirements make it necessary.', 'nh-theme' ),
            'topics'   => array( 'installation' ),
        ),

        'installation-tools' => array(
            'question' => nh_theme_faq_t( 'What tools will I need for installation?', 'nh-theme' ),
            'answer'   => nh_theme_faq_t( 'The required tools depend on the product and installation surface. Common tools may include a tape measure, spirit level, drill, screwdriver, suitable drill bits, saw and personal safety equipment. Check the product instructions before starting and use only tools and fixings suitable for the materials involved.', 'nh-theme' ),
            'topics'   => array( 'installation' ),
        ),

        'installation-level-base' => array(
            'question' => nh_theme_faq_t( 'Why is a level and stable base important?', 'nh-theme' ),
            'answer'   => nh_theme_faq_t( 'A level, stable and properly prepared base is essential for correct alignment, secure fixing and long-term performance. An uneven or unstable surface can cause frames, doors, panels, profiles and seals to fit incorrectly and may place unnecessary stress on the structure.', 'nh-theme' ),
            'topics'   => array( 'installation' ),
        ),

        'installation-weather' => array(
            'question' => nh_theme_faq_t( 'Can I install outdoor products in any weather?', 'nh-theme' ),
            'answer'   => nh_theme_faq_t( 'Avoid installation during strong winds, heavy rain, snow or icy conditions. Large sheets, panels and lightweight components can be difficult to handle safely in poor weather. Choose calm and dry conditions, and do not start work unless the installation area can be made safe.', 'nh-theme' ),
            'topics'   => array( 'installation' ),
        ),

        'installation-fixings' => array(
            'question' => nh_theme_faq_t( 'Can I use my own screws and fixings?', 'nh-theme' ),
            'answer'   => nh_theme_faq_t( 'Use the supplied fixings whenever they are included with the product. If additional or replacement fixings are needed, they must be compatible with the product, installation surface and local weather conditions. Incorrect screws, unsuitable sealants or overtightened fixings can damage the material and affect the installation.', 'nh-theme' ),
            'topics'   => array( 'installation' ),
        ),

        'installation-screw-length' => array(
            'question' => nh_theme_faq_t( 'How do I choose the correct screw length?', 'nh-theme' ),
            'answer'   => nh_theme_faq_t( 'A reliable rule of thumb for roofing and plastic sheets is to double the total thickness of the materials being fastened. Calculation: (Sheet Thickness + Profile Thickness) x 2. For example, a 10mm sheet and a 20mm profile equal 30mm total; multiplying by two results in a recommended 60mm screw length for a secure connection.', 'nh-theme' ),
            'topics'   => array( 'installation' ),
        ),

        'installation-expansion-gaps' => array(
            'question' => nh_theme_faq_t( 'Why do plastic sheets need expansion gaps?', 'nh-theme' ),
            'answer'   => nh_theme_faq_t( 'Polycarbonate, acrylic and other plastic sheets expand and contract as temperatures change. The recommended expansion gaps, profiles and fixing method must be used to help prevent stress, buckling, cracking and leaks. Follow the product-specific installation instructions for the selected sheet system.', 'nh-theme' ),
            'topics'   => array( 'installation' ),
        ),

        'installation-polycarbonate-roof-guide' => array(
            'question' => nh_theme_faq_t( 'Where can I find a step-by-step guide for installing a polycarbonate roof?', 'nh-theme' ),
            'answer'   => nh_theme_faq_t( 'Our <a href="https://norhage.eu/blog/">polycarbonate roof installation guide</a> explains the main stages of the project, including planning the structure, preparing the sheets, fitting profiles, sealing sheet ends and finishing the roof. Use the guide together with the instructions supplied for your specific sheet and installation system.', 'nh-theme' ),
            'topics'   => array( 'installation' ),
        ),

        'installation-greenhouse-guide' => array(
            'question' => nh_theme_faq_t( 'Where can I find greenhouse assembly instructions?', 'nh-theme' ),
            'answer'   => nh_theme_faq_t( 'Our <a href="https://norhage.eu/greenhouse-assembly-instructions/">greenhouse assembly instructions</a> provide guidance for preparing the site, building a suitable base, assembling the frame, fitting glazing and securing the structure. Always follow the instructions supplied with your specific greenhouse model, as components and assembly steps may differ.', 'nh-theme' ),
            'topics'   => array( 'installation' ),
        ),

        'installation-final-check' => array(
            'question' => nh_theme_faq_t( 'What should I check after installation?', 'nh-theme' ),
            'answer'   => nh_theme_faq_t( 'After installation, check that the structure is level, securely fixed and correctly aligned. Confirm that fasteners are secure without being overtightened, seals and end caps are fitted correctly, drainage paths are clear, and moving parts such as doors or vents operate freely.', 'nh-theme' ),
            'topics'   => array( 'installation' ),
        ),

        // GARDEN AND ACCESORIES
        'garden-terrace-roof-kits' => array(
            'question' => nh_theme_faq_t( 'Do you offer complete terrace roof covering kits?', 'nh-theme' ),
            'answer'   => nh_theme_faq_t( 'Yes. We provide complete covering solutions that include the polycarbonate or acrylic sheets along with all necessary aluminum profiles, gaskets, and sealing materials. These kits are designed to ensure a professional and weather-tight finish for your terrace structure.', 'nh-theme' ),
            'topics'   => array( 'accessories' ),
        ),

        'garden-accessories-compatibility' => array(
            'question' => nh_theme_faq_t( 'How do I know if the accessories are compatible with my greenhouse or roof?', 'nh-theme' ),
            'answer'   => nh_theme_faq_t( 'Our accessories and mounting components are designed to work with standard greenhouse and roofing systems. Always check the product specifications for thickness compatibility (e.g., 6mm, 10mm, 16mm) and profile types to ensure a perfect fit with your existing or new structure.', 'nh-theme' ),
            'topics'   => array( 'accessories' ),
        ),

        'garden-sealing-materials' => array(
            'question' => nh_theme_faq_t( 'What sealing materials do I need for a terrace roof?', 'nh-theme' ),
            'answer'   => nh_theme_faq_t( 'A durable terrace roof requires specialized sealing, including EPDM rubber gaskets, anti-dust tapes for multiwall sheet ends, and high-quality neutral silicone sealants. Using the correct professional-grade sealing materials prevents leaks, minimizes vibration, and extends the life of the plastic sheets.', 'nh-theme' ),
            'topics'   => array( 'accessories' ),
        ),

        'garden-hardware-maintenance' => array(
            'question' => nh_theme_faq_t( 'Are your mounting profiles and accessories corrosion-resistant?', 'nh-theme' ),
            'answer'   => nh_theme_faq_t( 'Yes. We primarily supply aluminum profiles and stainless steel or coated fasteners that are designed for permanent outdoor use. These materials offer excellent resistance to rust and weathering, requiring minimal maintenance after correct installation.', 'nh-theme' ),
            'topics'   => array( 'accessories' ),
        ),

        'garden-additional-items' => array(
            'question' => nh_theme_faq_t( 'What other accessories can I find for my garden project?', 'nh-theme' ),
            'answer'   => nh_theme_faq_t( 'In addition to roofing components, we offer a range of products to enhance your outdoor space, including shelving systems for greenhouses, automatic vent openers, and specialized cleaning products for plastic glazing to keep your structures looking new.', 'nh-theme' ),
            'topics'   => array( 'accessories' ),
        ),
	);
}
