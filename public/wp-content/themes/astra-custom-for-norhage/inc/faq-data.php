<?php
/**
 * Theme FAQ data.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FAQ Topics for main FAQ page.
 */
function nh_theme_faq_topics() {
	return array(
		// PROCESS TOPICS
		'ordering' => array(
			'label' => __( 'Ordering & Delivery', 'nh-theme' ),
			'order' => 10,
		),
		'returns' => array(
			'label' => __( 'Returns & Warranty', 'nh-theme' ),
			'order' => 20,
		),

		// PRODUCT TOPICS
		'greenhouses' => array(
			'label' => __( 'Greenhouses', 'nh-theme' ),
			'order' => 30,
		),
		'polycarbonate' => array(
			'label' => __( 'Polycarbonate & Plastics', 'nh-theme' ),
			'order' => 40,
		),
		'installation' => array(
			'label' => __( 'Installation & Assembly', 'nh-theme' ),
			'order' => 50,
		),
		'accessories' => array(
			'label' => __( 'Garden & Accessories', 'nh-theme' ),
			'order' => 60,
		),
	);
}

/**
 * FAQ Registry.
 */
function nh_theme_faq_items() {
	return array(

        // ORDERING
        'delivery-large-items' => array(
            'question' => __( 'How are large items delivered?', 'nh-theme' ),
            'answer'   => __( 'Greenhouse kits and large sheets are delivered via specialized heavy freight transport. Most orders arrive on a single oversized pallet.', 'nh-theme' ),
            'topics'   => array( 'ordering' ),
        ),

        'delivery-forklift' => array(
            'question' => __( 'Do I need a forklift to unload?', 'nh-theme' ),
            'answer'   => __( 'No. Our delivery trucks are equipped with lift-gates to lower the pallet to the ground. However, you should have space available near the curbside or driveway for the truck to operate.', 'nh-theme' ),
            'topics'   => array( 'ordering' ),
        ),

        'delivery-curbside' => array(
            'question' => __( 'What is "Curbside Delivery"?', 'nh-theme' ),
            'answer'   => __( 'This means the driver will unload the pallet at the end of your driveway or the nearest accessible point for the truck. Drivers cannot move the materials into your backyard or garage.', 'nh-theme' ),
            'topics'   => array( 'ordering' ),
        ),

        'delivery-driver-call' => array(
            'question' => __( 'Will the driver call me before arrival?', 'nh-theme' ),
            'answer'   => __( 'Yes. For heavy freight deliveries, the transport company will contact you at the phone number provided in your order to schedule a specific delivery window.', 'nh-theme' ),
            'topics'   => array( 'ordering' ),
        ),

        'order-change' => array(
            'question' => __( 'Can I change my order after it has been placed?', 'nh-theme' ),
            'answer'   => __( 'If the order has not yet been loaded for transport, we can make changes. Contact us as soon as possible if you need to adjust quantities or sizes.', 'nh-theme' ),
            'topics'   => array( 'ordering' ),
        ),

        // RETURN AND WARRANTY
        'return-period' => array(
            'question' => __( 'How long do I have to return an item?', 'nh-theme' ),
            'answer'   => __( 'Our return period follows local consumer protection laws. Please check our <a href="https://norhage.eu/refund-and-returns-policy/">Returns & Refunds</a> page for the specific timeframe applicable in your region.', 'nh-theme' ),
            'topics'   => array( 'returns' ),
        ),

        'return-condition' => array(
            'question' => __( 'What condition must the items be in for a return?', 'nh-theme' ),
            'answer'   => __( 'Items must be unused, in their original packaging, and in a condition suitable for resale. Custom-cut materials or specially ordered items may be subject to different conditions.', 'nh-theme' ),
            'topics'   => array( 'returns' ),
        ),

        'return-shipping-cost' => array(
            'question' => __( 'Who pays for return shipping?', 'nh-theme' ),
            'answer'   => __( 'Unless the item is defective or the wrong product was sent, the customer is generally responsible for return transport costs, especially for large freight items.', 'nh-theme' ),
            'topics'   => array( 'returns' ),
        ),

        'warranty-coverage' => array(
            'question' => __( 'What does the warranty cover?', 'nh-theme' ),
            'answer'   => __( 'Warranty covers manufacturing defects in materials and workmanship. It does not cover damage caused by extreme weather events (storms, heavy snow loads beyond limits), improper assembly, or lack of maintenance.', 'nh-theme' ),
            'topics'   => array( 'returns' ),
        ),

        'damage-on-arrival' => array(
            'question' => __( 'What should I do if my order arrives damaged?', 'nh-theme' ),
            'answer'   => __( 'It is critical to inspect the delivery before signing the courier documents. If damage is visible, note it on the transport document and contact our customer support immediately with photos.', 'nh-theme' ),
            'topics'   => array( 'returns' ),
        ),

        //GREENHOUSES
        'greenhouse-cold-climate' => array(
            'question' => __( 'Which greenhouse material is best for a cold climate?', 'nh-theme' ),
            'answer'   => __( 'For cold climates, polycarbonate glazing is often a practical choice because it provides better thermal insulation and higher impact resistance than single-pane glass. The best option still depends on the greenhouse model, local wind and snow conditions, and whether you plan to heat the greenhouse.', 'nh-theme' ),
            'topics'   => array( 'greenhouses' ),
        ),

        'greenhouse-maintenance' => array(
            'question' => __( 'What maintenance does a greenhouse require?', 'nh-theme' ),
            'answer'   => __( 'Regular maintenance helps keep your greenhouse safe and long-lasting. Clean the glazing, check the frame, fasteners, seals and doors, and make sure vents operate freely. Remove leaves and debris from gutters or roof areas, and follow the manufacturer’s instructions for seasonal checks and snow management.', 'nh-theme' ),
            'topics'   => array( 'greenhouses' ),
        ),

        'greenhouse-ventilation' => array(
            'question' => __( 'What ventilation options are available for greenhouses?', 'nh-theme' ),
            'answer'   => __( 'Greenhouse ventilation may include roof vents, side vents, doors, louvre vents and automatic vent openers. A well-planned combination improves air circulation and helps manage temperature and humidity during warmer periods.', 'nh-theme' ),
            'topics'   => array( 'greenhouses' ),
        ),

        'greenhouse-professional-installation' => array(
            'question' => __( 'Is professional greenhouse installation necessary?', 'nh-theme' ),
            'answer'   => __( 'Professional installation is not always required, but it can be a good option for larger, permanent or more complex greenhouses. Careful assembly, correct anchoring and a level base are essential regardless of who installs the greenhouse. Always follow the supplied installation instructions.', 'nh-theme' ),
            'topics'   => array( 'greenhouses' ),
        ),

        'greenhouse-foundation' => array(
            'question' => __( 'Does a greenhouse need a foundation?', 'nh-theme' ),
            'answer'   => __( 'A stable, level and properly prepared base is important for the safe installation and long-term performance of most greenhouses. Suitable foundation options vary by model and site, and may include a concrete base, timber frame, foundation base or ground anchors. Check the product documentation before choosing a solution.', 'nh-theme' ),
            'topics'   => array( 'greenhouses' ),
        ),

        'greenhouse-expansion' => array(
            'question' => __( 'Can I extend my greenhouse later?', 'nh-theme' ),
            'answer'   => __( 'Some greenhouse models can be extended using compatible extension kits. Availability depends on the specific model, frame system and manufacturer. If future expansion is important, check this before purchasing and allow enough space when planning the installation area.', 'nh-theme' ),
            'topics'   => array( 'greenhouses' ),
        ),
        'greenhouse-wom-exposure' => array(
            'question' => __( 'What does "WOM exposure" mean in the greenhouse plastic specifications?', 'nh-theme' ),
            'answer'   => __( 'WOM exposure refers to standardized testing conducted in a "Weather-Ometer." This laboratory equipment simulates years of outdoor weather conditions in a short period by exposing the material to intense UV radiation, high heat, and moisture. This test ensures the plastic film or sheet meets durability standards and will maintain its structural integrity and light transmission over many years in a real-world environment.', 'nh-theme' ),
            'topics'   => array( 'greenhouses' ),
        ),

        //POLYCARBONATE AND PLASTICS
        'plastic-sheets-pc-vs-pmma' => array(
            'question' => __( 'What is the difference between polycarbonate and acrylic (PMMA) sheets?', 'nh-theme' ),
            'answer'   => __( 'Polycarbonate and acrylic are both strong, lightweight alternatives to glass, but they have different properties. Polycarbonate is known for its very high impact resistance and is often chosen for demanding applications. Acrylic (PMMA) offers excellent clarity and a smooth, glass-like appearance. The best material depends on the application, required strength, appearance and installation conditions.', 'nh-theme' ),
            'topics'   => array( 'polycarbonate' ),
        ),

        'plastic-sheets-solid-vs-multiwall' => array(
            'question' => __( 'What is the difference between solid and multiwall polycarbonate sheets?', 'nh-theme' ),
            'answer'   => __( 'Solid polycarbonate sheets are compact, transparent sheets with a smooth surface and a glass-like appearance. Multiwall polycarbonate sheets have internal channels that make them lighter and improve thermal insulation. Solid sheets are often selected for clear glazing and impact resistance, while multiwall sheets are commonly used for roofs, greenhouses and insulated structures.', 'nh-theme' ),
            'topics'   => array( 'polycarbonate' ),
        ),

        'plastic-sheets-uv-protection' => array(
            'question' => __( 'Do your polycarbonate and acrylic sheets have UV protection?', 'nh-theme' ),
            'answer'   => __( 'Yes. Our polycarbonate and acrylic (PMMA) sheets include UV protection for outdoor use. This helps protect the material from weathering and supports long-term performance when the sheets are installed correctly.', 'nh-theme' ),
            'topics'   => array( 'polycarbonate' ),
        ),

        'plastic-sheets-thickness' => array(
            'question' => __( 'How do I choose the right sheet thickness?', 'nh-theme' ),
            'answer'   => __( 'The right thickness depends on the material, application, supporting frame, distance between supports, local wind and snow conditions, and the insulation or impact resistance required. For roofing and glazing projects, select the sheet thickness together with a suitable support and fixing system.', 'nh-theme' ),
            'topics'   => array( 'polycarbonate' ),
        ),

        'plastic-sheets-cutting' => array(
            'question' => __( 'Can plastic sheets be cut to size?', 'nh-theme' ),
            'answer'   => __( 'Yes. Polycarbonate and acrylic sheets can usually be cut to size using appropriate tools and a blade suitable for plastics. Measure carefully, support the sheet properly while cutting and follow the product-specific cutting guidance. Multiwall polycarbonate channels should be cleaned of cutting dust before the sheet ends are sealed.', 'nh-theme' ),
            'topics'   => array( 'polycarbonate' ),
        ),

        'plastic-sheets-thermal-expansion' => array(
            'question' => __( 'Do plastic sheets expand and contract with temperature changes?', 'nh-theme' ),
            'answer'   => __( 'Yes. Polycarbonate and acrylic sheets expand and contract when temperatures change. Always allow suitable expansion gaps during installation, use compatible profiles and fixings, and avoid overtightening screws. This helps prevent stress, distortion and damage over time.', 'nh-theme' ),
            'topics'   => array( 'polycarbonate' ),
        ),

        'plastic-sheets-multiwall-sealing' => array(
            'question' => __( 'How should multiwall polycarbonate sheet ends be sealed?', 'nh-theme' ),
            'answer'   => __( 'Multiwall polycarbonate sheet channels should be protected from dust, insects and moisture with suitable sealing tape and end profiles. The upper edge is normally sealed with solid tape, while the lower edge commonly uses breathable tape to allow condensation to drain. Always install the channels in the correct direction and follow the instructions for the selected sheet system.', 'nh-theme' ),
            'topics'   => array( 'polycarbonate' ),
        ),

        'plastic-sheets-cleaning' => array(
            'question' => __( 'How do I clean polycarbonate and acrylic sheets?', 'nh-theme' ),
            'answer'   => __( 'Use lukewarm water, mild soap and a soft cloth or sponge. Rinse the sheet thoroughly before wiping to remove dust and loose particles. Avoid abrasive pads, strong solvents and harsh cleaning products, as they can scratch or damage the surface.', 'nh-theme' ),
            'topics'   => array( 'polycarbonate' ),
        ),

        'plastic-sheets-outdoor-use' => array(
            'question' => __( 'Are plastic sheets suitable for outdoor use?', 'nh-theme' ),
            'answer'   => __( 'Yes. Polycarbonate and acrylic sheets are widely used for outdoor glazing, roofing, shelters, greenhouses and similar projects. Choose the material, thickness, support spacing and installation system according to the intended application and local weather conditions.', 'nh-theme' ),
            'topics'   => array( 'polycarbonate' ),
        ),

        'plastic-sheets-greenhouse-use' => array(
            'question' => __( 'Which plastic sheet is best for a greenhouse?', 'nh-theme' ),
            'answer'   => __( 'Multiwall polycarbonate is a popular greenhouse material because its internal structure improves thermal insulation while keeping the sheets lightweight. Solid polycarbonate or acrylic may also be suitable where a clearer appearance is preferred. Consider insulation, light transmission, structural requirements and the greenhouse design when choosing.', 'nh-theme' ),
            'topics'   => array( 'polycarbonate' ),
        ),

        // INSTALLATION AND ASSEMBLY
        'installation-read-instructions' => array(
            'question' => __( 'Should I read the installation instructions before starting?', 'nh-theme' ),
            'answer'   => __( 'Yes. Read the complete product-specific instructions before starting work and check that all components are present. Installation methods, required tools, fixing details and safety requirements may vary between products.', 'nh-theme' ),
            'topics'   => array( 'installation' ),
        ),

        'installation-diy-or-professional' => array(
            'question' => __( 'Can I install the product myself?', 'nh-theme' ),
            'answer'   => __( 'Many products can be installed by an experienced DIY customer when the supplied instructions are followed carefully. Larger, heavier or more complex structures may require more than one person. Professional installation can be a practical option where the project, site conditions or local requirements make it necessary.', 'nh-theme' ),
            'topics'   => array( 'installation' ),
        ),

        'installation-tools' => array(
            'question' => __( 'What tools will I need for installation?', 'nh-theme' ),
            'answer'   => __( 'The required tools depend on the product and installation surface. Common tools may include a tape measure, spirit level, drill, screwdriver, suitable drill bits, saw and personal safety equipment. Check the product instructions before starting and use only tools and fixings suitable for the materials involved.', 'nh-theme' ),
            'topics'   => array( 'installation' ),
        ),

        'installation-level-base' => array(
            'question' => __( 'Why is a level and stable base important?', 'nh-theme' ),
            'answer'   => __( 'A level, stable and properly prepared base is essential for correct alignment, secure fixing and long-term performance. An uneven or unstable surface can cause frames, doors, panels, profiles and seals to fit incorrectly and may place unnecessary stress on the structure.', 'nh-theme' ),
            'topics'   => array( 'installation' ),
        ),

        'installation-weather' => array(
            'question' => __( 'Can I install outdoor products in any weather?', 'nh-theme' ),
            'answer'   => __( 'Avoid installation during strong winds, heavy rain, snow or icy conditions. Large sheets, panels and lightweight components can be difficult to handle safely in poor weather. Choose calm and dry conditions, and do not start work unless the installation area can be made safe.', 'nh-theme' ),
            'topics'   => array( 'installation' ),
        ),

        'installation-fixings' => array(
            'question' => __( 'Can I use my own screws and fixings?', 'nh-theme' ),
            'answer'   => __( 'Use the supplied fixings whenever they are included with the product. If additional or replacement fixings are needed, they must be compatible with the product, installation surface and local weather conditions. Incorrect screws, unsuitable sealants or overtightened fixings can damage the material and affect the installation.', 'nh-theme' ),
            'topics'   => array( 'installation' ),
        ),

        'installation-screw-length' => array(
            'question' => __( 'How do I choose the correct screw length?', 'nh-theme' ),
            'answer'   => __( 'A reliable rule of thumb for roofing and plastic sheets is to double the total thickness of the materials being fastened. Calculation: (Sheet Thickness + Profile Thickness) x 2. For example, a 10mm sheet and a 20mm profile equal 30mm total; multiplying by two results in a recommended 60mm screw length for a secure connection.', 'nh-theme' ),
            'topics'   => array( 'installation' ),
        ),

        'installation-expansion-gaps' => array(
            'question' => __( 'Why do plastic sheets need expansion gaps?', 'nh-theme' ),
            'answer'   => __( 'Polycarbonate, acrylic and other plastic sheets expand and contract as temperatures change. The recommended expansion gaps, profiles and fixing method must be used to help prevent stress, buckling, cracking and leaks. Follow the product-specific installation instructions for the selected sheet system.', 'nh-theme' ),
            'topics'   => array( 'installation' ),
        ),

        'installation-polycarbonate-roof-guide' => array(
            'question' => __( 'Where can I find a step-by-step guide for installing a polycarbonate roof?', 'nh-theme' ),
            'answer'   => __( 'Our <a href="https://norhage.eu/blog/">polycarbonate roof installation guide</a> explains the main stages of the project, including planning the structure, preparing the sheets, fitting profiles, sealing sheet ends and finishing the roof. Use the guide together with the instructions supplied for your specific sheet and installation system.', 'nh-theme' ),
            'topics'   => array( 'installation' ),
        ),

        'installation-greenhouse-guide' => array(
            'question' => __( 'Where can I find greenhouse assembly instructions?', 'nh-theme' ),
            'answer'   => __( 'Our <a href="https://norhage.eu/greenhouse-assembly-instructions/">greenhouse assembly instructions</a> provide guidance for preparing the site, building a suitable base, assembling the frame, fitting glazing and securing the structure. Always follow the instructions supplied with your specific greenhouse model, as components and assembly steps may differ.', 'nh-theme' ),
            'topics'   => array( 'installation' ),
        ),

        'installation-final-check' => array(
            'question' => __( 'What should I check after installation?', 'nh-theme' ),
            'answer'   => __( 'After installation, check that the structure is level, securely fixed and correctly aligned. Confirm that fasteners are secure without being overtightened, seals and end caps are fitted correctly, drainage paths are clear, and moving parts such as doors or vents operate freely.', 'nh-theme' ),
            'topics'   => array( 'installation' ),
        ),

        // GARDEN AND ACCESORIES
        'garden-terrace-roof-kits' => array(
            'question' => __( 'Do you offer complete terrace roof covering kits?', 'nh-theme' ),
            'answer'   => __( 'Yes. We provide complete covering solutions that include the polycarbonate or acrylic sheets along with all necessary aluminum profiles, gaskets, and sealing materials. These kits are designed to ensure a professional and weather-tight finish for your terrace structure.', 'nh-theme' ),
            'topics'   => array( 'accessories' ),
        ),

        'garden-accessories-compatibility' => array(
            'question' => __( 'How do I know if the accessories are compatible with my greenhouse or roof?', 'nh-theme' ),
            'answer'   => __( 'Our accessories and mounting components are designed to work with standard greenhouse and roofing systems. Always check the product specifications for thickness compatibility (e.g., 6mm, 10mm, 16mm) and profile types to ensure a perfect fit with your existing or new structure.', 'nh-theme' ),
            'topics'   => array( 'accessories' ),
        ),

        'garden-sealing-materials' => array(
            'question' => __( 'What sealing materials do I need for a terrace roof?', 'nh-theme' ),
            'answer'   => __( 'A durable terrace roof requires specialized sealing, including EPDM rubber gaskets, anti-dust tapes for multiwall sheet ends, and high-quality neutral silicone sealants. Using the correct professional-grade sealing materials prevents leaks, minimizes vibration, and extends the life of the plastic sheets.', 'nh-theme' ),
            'topics'   => array( 'accessories' ),
        ),

        'garden-hardware-maintenance' => array(
            'question' => __( 'Are your mounting profiles and accessories corrosion-resistant?', 'nh-theme' ),
            'answer'   => __( 'Yes. We primarily supply aluminum profiles and stainless steel or coated fasteners that are designed for permanent outdoor use. These materials offer excellent resistance to rust and weathering, requiring minimal maintenance after correct installation.', 'nh-theme' ),
            'topics'   => array( 'accessories' ),
        ),

        'garden-additional-items' => array(
            'question' => __( 'What other accessories can I find for my garden project?', 'nh-theme' ),
            'answer'   => __( 'In addition to roofing components, we offer a range of products to enhance your outdoor space, including shelving systems for greenhouses, automatic vent openers, and specialized cleaning products for plastic glazing to keep your structures looking new.', 'nh-theme' ),
            'topics'   => array( 'accessories' ),
        ),
	);
}
