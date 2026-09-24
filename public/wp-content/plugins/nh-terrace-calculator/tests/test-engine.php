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
	'default_cc_mm'           => 600,
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
	'support_mm'         => 50,
	'overhang_mm'        => 50,
);

$bom = NH_TC_Engine::calculate( $input, $settings );
nh_tc_assert( 'screenshot example calculates', ! empty( $bom['ok'] ) );

$by_role = array();
foreach ( $bom['lines'] as $line ) {
	$by_role[ $line['role'] ] = $line;
}

$sheet_lines = array();
foreach ( $bom['lines'] as $line ) {
	if ( 'sheet' === $line['role'] ) {
		$sheet_lines[] = $line;
	}
}
nh_tc_assert( 'three cut widths', 3 === count( $sheet_lines ) );
nh_tc_assert( 'outer 620 x 4750', 1 === (int) $sheet_lines[0]['qty'] && 620 === (int) $sheet_lines[0]['cut']['width_mm'] && 4750 === (int) $sheet_lines[0]['cut']['length_mm'] );
nh_tc_assert( 'middle 590 x 5', 5 === (int) $sheet_lines[1]['qty'] && 590 === (int) $sheet_lines[1]['cut']['width_mm'] && 4750 === (int) $sheet_lines[1]['cut']['length_mm'] );
nh_tc_assert( 'short outer 570 x 4750', 1 === (int) $sheet_lines[2]['qty'] && 570 === (int) $sheet_lines[2]['cut']['width_mm'] );
$covered = 620 + ( 590 * 5 ) + 570 + ( 6 * 10 );
nh_tc_assert( 'sheet widths plus 10 mm joints equal the frame', 4200 === $covered );
nh_tc_assert( 'outer sheets are 30 mm wider than a full middle sheet', 30 === (int) $bom['meta']['side_extra_mm'] );
nh_tc_assert( '7 sheets still', 7 === (int) $bom['meta']['sheet_count'] );
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

$even = NH_TC_Engine::framed_sheet_plan( 4250, 4000, 700, 50, 10, 0 );
nh_tc_assert(
	'full bays: two outers 720 and four middles 690',
	6 === count( $even )
	&& 720 === (int) $even[0]['width_mm']
	&& 690 === (int) $even[1]['width_mm']
	&& 690 === (int) $even[4]['width_mm']
	&& 720 === (int) $even[5]['width_mm']
	&& 4000 === (int) $even[0]['length_mm']
);
$even_sum = 0;
foreach ( $even as $sheet ) {
	$even_sum += (int) $sheet['width_mm'];
}
nh_tc_assert( '4250 frame closes', 4250 === $even_sum + ( 5 * 10 ) );

$odd = NH_TC_Engine::framed_sheet_plan( 2100, 2000, 1000, 51, 10, 0 );
$odd_sum = 0;
foreach ( $odd as $sheet ) {
	$odd_sum += (int) $sheet['width_mm'];
}
nh_tc_assert( 'odd 51 mm beam still closes the frame', 3 === count( $odd ) && 2100 === $odd_sum + ( 2 * 10 ) );
nh_tc_assert( 'right end keeps the extra millimetre', 70 === (int) $odd[2]['width_mm'] && 1020 === (int) $odd[0]['width_mm'] );

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

$empty_cc = $input;
$empty_cc['cc_mm'] = 0;
$empty_cc['thickness'] = 6;
$empty_bom = NH_TC_Engine::calculate( $empty_cc, $settings );
nh_tc_assert( 'empty CC uses 600 not recommended 500', ! empty( $empty_bom['ok'] ) && 600 === (int) $empty_bom['meta']['cc_mm'] );

$h_in = $input;
$h_in['connecting_profile'] = 'h_plastic';
$h_bom = NH_TC_Engine::calculate( $h_in, $settings );
$h_roles = array();
foreach ( $h_bom['lines'] as $line ) {
	$h_roles[ $line['role'] ] = $line;
}
nh_tc_assert( 'H-profile has no clamping end caps', ! isset( $h_roles['end_cap'] ) );

$bad = NH_TC_Engine::calculate( array_merge( $input, array( 'width_mm' => 10 ) ), $settings );
nh_tc_assert( 'rejects tiny width', empty( $bad['ok'] ) );

$wide = NH_TC_Engine::calculate(
	array_merge( $input, array( 'width_mm' => 2150, 'support_mm' => 150, 'cc_mm' => 2000, 'overhang_mm' => 0 ) ),
	$settings
);
nh_tc_assert( 'rejects a sheet wider than 2100 mm stock', empty( $wide['ok'] ) && in_array( 'sheet_width', $wide['errors'], true ) );

$sliver = NH_TC_Engine::calculate(
	array_merge( $input, array( 'width_mm' => 1251, 'support_mm' => 50, 'cc_mm' => 600, 'overhang_mm' => 0 ) ),
	$settings
);
nh_tc_assert( 'rejects a sliver end sheet', empty( $sliver['ok'] ) && in_array( 'sheet_narrow', $sliver['errors'], true ) );

$flush = $input;
$flush['overhang_mm'] = 0;
$flush_bom = NH_TC_Engine::calculate( $flush, $settings );
nh_tc_assert( 'zero overhang keeps the frame length', ! empty( $flush_bom['ok'] ) && 4700 === (int) $flush_bom['meta']['sheet_length_mm'] );

$implied = $input;
unset( $implied['support_mm'], $implied['overhang_mm'] );
$implied_bom = NH_TC_Engine::calculate( $implied, $settings );
nh_tc_assert( 'missing beam width and overhang use 50 mm', ! empty( $implied_bom['ok'] ) && 50 === (int) $implied_bom['meta']['support_mm'] && 50 === (int) $implied_bom['meta']['overhang_mm'] );

if ( $failures ) {
	echo "\n{$failures} failed\n";
	exit( 1 );
}

echo "\nAll tests passed\n";
exit( 0 );
