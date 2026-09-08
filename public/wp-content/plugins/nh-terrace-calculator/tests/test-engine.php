<?php
/**
 * CLI tests for the terrace BOM engine (no WordPress).
 *
 * Run: php public/wp-content/plugins/nh-terrace-calculator/tests/test-engine.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/' );
}

require_once dirname( __DIR__ ) . '/includes/class-nh-tc-engine.php';

$failures = 0;

function nh_tc_assert( $label, $ok ) {
	global $failures;
	if ( $ok ) {
		echo "ok  {$label}\n";
		return;
	}
	$failures++;
	echo "FAIL  {$label}\n";
}

$settings = array(
	'min_width_mm'            => 600,
	'max_width_mm'            => 12000,
	'min_length_mm'           => 500,
	'max_length_mm'           => 7000,
	'overlap_mm'              => 100,
	'standard_sheet_width_mm' => 2100,
	'wall_profile_mm'         => 2200,
	'screw_spacing_mm'        => 200,
	'screw_pack_size'         => 50,
	'silicon_metres_per_tube' => 10,
	'tape_roll_mm'            => 5000,
	'profile_stock_mm'        => array( 1000, 1500, 2000, 3000, 4000, 5000, 6000 ),
	'recommended_cc'          => array( '10' => 600, '6' => 500 ),
	'screw_size'              => array( '10' => '5-mm-x-60-mm', '6' => '5-mm-x-50-mm' ),
);

$input = array(
	'width_mm'           => 4200,
	'length_mm'          => 4700,
	'cc_mm'              => 600,
	'construction'       => 'single_slope',
	'material'           => 'multiwall',
	'thickness'          => 10,
	'colour'             => 'clear',
	'connecting_profile' => 'clamping',
	'connecting_color'   => 'silver',
	'finish_profile'     => 'f_profile',
	'finish_color'       => 'silver',
	'sheet_layout'       => 'per_cc',
);

$bom = NH_TC_Engine::calculate( $input, $settings );
nh_tc_assert( 'screenshot example calculates', ! empty( $bom['ok'] ) );

$by_role = array();
foreach ( $bom['lines'] as $line ) {
	$by_role[ $line['role'] ] = $line;
}

nh_tc_assert( '7 sheets of 600 x 4700', isset( $by_role['sheet'] ) && 7 === (int) $by_role['sheet']['qty'] && 600 === (int) $by_role['sheet']['cut']['width_mm'] && 4700 === (int) $by_role['sheet']['cut']['length_mm'] );
nh_tc_assert( '6 connecting 5 m profiles', isset( $by_role['connecting'] ) && 6 === (int) $by_role['connecting']['qty'] && 5000 === (int) $by_role['connecting']['stock_mm'] );
nh_tc_assert( 'F-profile 3×2 m + 3×3 m', isset( $by_role['finish']['packed']['2-m'], $by_role['finish']['packed']['3-m'] ) && 3 === (int) $by_role['finish']['packed']['2-m'] && 3 === (int) $by_role['finish']['packed']['3-m'] );
nh_tc_assert( '150 screws → 3 packs of 5x60', isset( $by_role['screw'] ) && 150 === (int) $by_role['screw']['qty_raw'] && 3 === (int) $by_role['screw']['qty'] && '5-mm-x-60-mm' === $by_role['screw']['attrs']['dimensions'] );
nh_tc_assert( '2 wall profiles', isset( $by_role['wall'] ) && 2 === (int) $by_role['wall']['qty'] );
nh_tc_assert( '12 end caps', isset( $by_role['end_cap'] ) && 12 === (int) $by_role['end_cap']['qty'] );
nh_tc_assert( '25 mm ventilation tape, 1 roll', isset( $by_role['vent_tape'] ) && 25 === (int) $by_role['vent_tape']['tape_width_mm'] && 1 === (int) $by_role['vent_tape']['qty'] );
nh_tc_assert( '25 mm isolation tape, 1 roll', isset( $by_role['iso_tape'] ) && 1 === (int) $by_role['iso_tape']['qty'] );
nh_tc_assert( '18 m rubber gasket', isset( $by_role['gasket'] ) && 18 === (int) $by_role['gasket']['qty'] );
nh_tc_assert( '2 silicone tubes', isset( $by_role['silicon'] ) && 2 === (int) $by_role['silicon']['qty'] );
nh_tc_assert( '8 beams single slope', 8 === (int) $bom['meta']['beam_count'] );
nh_tc_assert( 'recommended CC 600', 600 === (int) $bom['meta']['recommended_cc_mm'] );
nh_tc_assert( 'overlap min sheets is 3', 3 === NH_TC_Engine::overlap_sheet_count( 4200, $settings ) );

$uneven = NH_TC_Engine::per_cc_sheets( 4000, 4700, 600 );
nh_tc_assert( 'uneven last sheet 400 mm', 2 === count( $uneven ) && 6 === (int) $uneven[0]['qty'] && 400 === (int) $uneven[1]['width_mm'] );

$gable = $input;
$gable['construction'] = 'gable';
$gbom = NH_TC_Engine::calculate( $gable, $settings );
$groles = array();
foreach ( $gbom['lines'] as $line ) {
	$groles[ $line['role'] ] = $line;
}
nh_tc_assert( 'gable uses ridge not wall', isset( $groles['ridge'] ) && ! isset( $groles['wall'] ) );
nh_tc_assert( 'gable 9 beams', 9 === (int) $gbom['meta']['beam_count'] );

nh_tc_assert( 'parse 1,5 m', 1500 === NH_TC_Engine::parse_size_to_mm( '1,5 m' ) );
nh_tc_assert( 'parse 5 m', 5000 === NH_TC_Engine::parse_size_to_mm( '5 m' ) );
nh_tc_assert( 'parse 25 mm', 25 === NH_TC_Engine::parse_size_to_mm( '25 mm' ) );

$bad = NH_TC_Engine::calculate( array_merge( $input, array( 'width_mm' => 10 ) ), $settings );
nh_tc_assert( 'rejects tiny width', empty( $bad['ok'] ) );

if ( $failures ) {
	echo "\n{$failures} failed\n";
	exit( 1 );
}

echo "\nAll tests passed\n";
exit( 0 );
