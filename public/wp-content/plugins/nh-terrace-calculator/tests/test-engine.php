<?php
/**
 * CLI tests for the terrace BOM engine (no WordPress).
 *
 * Run: php public/wp-content/plugins/nh-terrace-calculator/tests/test-engine.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/' );
}

require_once dirname( __DIR__ ) . '/includes/class-nh-tc-defaults.php';
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
nh_tc_assert( 'equal outers 613 x 4750', 2 === (int) $sheet_lines[0]['qty'] && 613 === (int) $sheet_lines[0]['cut']['width_mm'] && 4750 === (int) $sheet_lines[0]['cut']['length_mm'] );
nh_tc_assert( 'middle 583 x 4', 4 === (int) $sheet_lines[1]['qty'] && 583 === (int) $sheet_lines[1]['cut']['width_mm'] && 4750 === (int) $sheet_lines[1]['cut']['length_mm'] );
nh_tc_assert( 'one middle 582 x 4750', 1 === (int) $sheet_lines[2]['qty'] && 582 === (int) $sheet_lines[2]['cut']['width_mm'] );
$covered = ( 613 * 2 ) + ( 583 * 4 ) + 582 + ( 6 * 10 );
nh_tc_assert( 'sheet widths plus 10 mm joints equal the frame', 4200 === $covered );
$plan    = $bom['meta']['sheet_plan'];
$rafters = $bom['meta']['rafters_mm'];
$joints_on_centres = 8 === count( $rafters ) && 7 === count( $plan ) && 0 === (int) $plan[0]['x_mm'] && 613 === (int) $plan[0]['width_mm'] && 613 === (int) $plan[6]['width_mm'];
$expected_bays     = array( 593, 593, 593, 593, 593, 592, 593 );
for ( $i = 0; $i < 7; $i++ ) {
	$bay = (int) $rafters[ $i + 1 ] - (int) $rafters[ $i ];
	if ( $bay !== $expected_bays[ $i ] ) {
		$joints_on_centres = false;
	}
	if ( $i < 6 ) {
		$joint = (int) $plan[ $i ]['x_mm'] + (int) $plan[ $i ]['width_mm'] + 5;
		if ( $joint !== (int) $rafters[ $i + 1 ] ) {
			$joints_on_centres = false;
		}
	}
}
$last_edge = (int) $plan[6]['x_mm'] + (int) $plan[6]['width_mm'];
nh_tc_assert( 'equal bays keep every joint on a rafter centre', $joints_on_centres && 25 === (int) $rafters[0] && 4175 === (int) $rafters[7] && 4200 === $last_edge );
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
nh_tc_assert(
	'recommended spacing for 10 mm multiwall is a 600 mm prefill inside 500–600',
	600 === (int) $bom['meta']['recommended_cc_mm']
	&& 500 === (int) $bom['meta']['recommended_min_mm']
	&& 600 === (int) $bom['meta']['recommended_max_mm']
	&& 8 === (int) $bom['meta']['rafter_count']
);
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
nh_tc_assert( 'odd beam keeps equal bays and the extra millimetre on the right sheet', 703 === (int) $odd[0]['width_mm'] && 673 === (int) $odd[1]['width_mm'] && 704 === (int) $odd[2]['width_mm'] );

$two = NH_TC_Engine::framed_sheet_plan( 1301, 2000, 700, 50, 10, 0 );
nh_tc_assert( 'two bays put the spare millimetre on the right sheet', 2 === count( $two ) && 645 === (int) $two[0]['width_mm'] && 646 === (int) $two[1]['width_mm'] );

$gable = $input;
$gable['construction'] = 'gable';
$gbom = NH_TC_Engine::calculate( $gable, $settings );
$groles = array();
foreach ( $gbom['lines'] as $line ) {
	$groles[ $line['role'] ] = $line;
}
nh_tc_assert( 'gable uses ridge not wall', isset( $groles['ridge'] ) && ! isset( $groles['wall'] ) );
nh_tc_assert( 'gable 9 beams', 9 === (int) $gbom['meta']['beam_count'] );
nh_tc_assert(
	'gable counts the entered side twice',
	2 === (int) $gbom['meta']['slopes']
	&& 4700 === (int) $gbom['meta']['projection_mm']
	&& 9400 === (int) $gbom['meta']['length_mm']
	&& 9450 === (int) $gbom['meta']['sheet_length_mm']
);
nh_tc_assert( 'lean-to keeps the entered length', 1 === (int) $bom['meta']['slopes'] && 4700 === (int) $bom['meta']['projection_mm'] && 4700 === (int) $bom['meta']['length_mm'] );

nh_tc_assert( 'parse 1,5 m', 1500 === NH_TC_Engine::parse_size_to_mm( '1,5 m' ) );
nh_tc_assert( 'parse 5 m', 5000 === NH_TC_Engine::parse_size_to_mm( '5 m' ) );
nh_tc_assert( 'parse 25 mm', 25 === NH_TC_Engine::parse_size_to_mm( '25 mm' ) );

$empty_cc = $input;
$empty_cc['cc_mm'] = 0;
$empty_cc['thickness'] = 6;
$empty_bom = NH_TC_Engine::calculate( $empty_cc, $settings );
nh_tc_assert(
	'empty CC uses the 6 mm prefill of 500 mm',
	! empty( $empty_bom['ok'] )
	&& 500 === (int) $empty_bom['meta']['cc_mm']
	&& 400 === (int) $empty_bom['meta']['recommended_min_mm']
	&& 500 === (int) $empty_bom['meta']['recommended_max_mm']
	&& 10 === (int) $empty_bom['meta']['rafter_count']
);

$mw8 = NH_TC_Engine::support_range( 'multiwall', 8 );
$mw12 = NH_TC_Engine::support_range( 'multiwall', 12 );
nh_tc_assert( '8 mm multiwall uses the 6 mm range', 400 === (int) $mw8['min_mm'] && 500 === (int) $mw8['max_mm'] );
nh_tc_assert( '12 mm multiwall uses the 10 mm range', 500 === (int) $mw12['min_mm'] && 600 === (int) $mw12['max_mm'] );

$solid_advice = NH_TC_Engine::recommended_support( 'solid', 10, 4200, 50 );
nh_tc_assert(
	'solid 10 mm prefills 1000 mm and 6 rafters',
	1000 === (int) $solid_advice['cc_mm']
	&& 6 === (int) $solid_advice['rafters']
	&& 900 === (int) $solid_advice['min_mm']
	&& 1100 === (int) $solid_advice['max_mm']
);

$mw4 = NH_TC_Engine::recommended_support( 'multiwall', 4, 4200, 50 );
nh_tc_assert( '4 mm multiwall prefills 400 mm and 12 rafters', 400 === (int) $mw4['cc_mm'] && 12 === (int) $mw4['rafters'] && 350 === (int) $mw4['min_mm'] && 400 === (int) $mw4['max_mm'] );

$prefills = array(
	array( 'multiwall', 4, 400 ),
	array( 'multiwall', 6, 500 ),
	array( 'multiwall', 8, 500 ),
	array( 'multiwall', 12, 600 ),
	array( 'multiwall', 10, 600 ),
	array( 'multiwall', 16, 700 ),
	array( 'multiwall', 25, 900 ),
	array( 'multiwall', 40, 1000 ),
	array( 'solid', 2, 300 ),
	array( 'solid', 3, 400 ),
	array( 'solid', 4, 500 ),
	array( 'solid', 5, 600 ),
	array( 'solid', 6, 700 ),
	array( 'solid', 8, 800 ),
	array( 'solid', 10, 1000 ),
);
foreach ( $prefills as $row ) {
	$got = NH_TC_Engine::support_range( $row[0], $row[1] );
	nh_tc_assert( $row[0] . ' ' . $row[1] . ' mm prefills ' . $row[2] . ' mm', (int) $got['prefill_mm'] === (int) $row[2] );
}

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
nh_tc_assert(
	'a 1251 mm frame keeps both side sheets and drops the narrow end piece',
	! empty( $sliver['ok'] )
	&& 3 === count( $sliver['meta']['sheet_plan'] )
	&& 420 === (int) $sliver['meta']['sheet_plan'][0]['width_mm']
	&& 391 === (int) $sliver['meta']['sheet_plan'][1]['width_mm']
	&& 420 === (int) $sliver['meta']['sheet_plan'][2]['width_mm']
);

$flush = $input;
$flush['overhang_mm'] = 0;
$flush_bom = NH_TC_Engine::calculate( $flush, $settings );
nh_tc_assert( 'zero overhang keeps the frame length', ! empty( $flush_bom['ok'] ) && 4700 === (int) $flush_bom['meta']['sheet_length_mm'] );

$implied = $input;
unset( $implied['support_mm'], $implied['overhang_mm'] );
$implied_bom = NH_TC_Engine::calculate( $implied, $settings );
nh_tc_assert( 'missing beam width and overhang use 50 mm', ! empty( $implied_bom['ok'] ) && 50 === (int) $implied_bom['meta']['support_mm'] && 50 === (int) $implied_bom['meta']['overhang_mm'] );

$solid = array_merge( $input, array( 'material' => 'solid', 'thickness' => 10 ) );
$solid_bom = NH_TC_Engine::calculate( $solid, $settings );
$solid_roles = array();
$solid_sheets = array();
foreach ( $solid_bom['lines'] as $line ) {
	$solid_roles[ $line['role'] ] = $line;
	if ( 'sheet' === $line['role'] ) {
		$solid_sheets[] = $line;
	}
}
nh_tc_assert( 'solid sheet quote calculates', ! empty( $solid_bom['ok'] ) );
nh_tc_assert( 'solid sheets omit sealing tapes', ! isset( $solid_roles['vent_tape'] ) && ! isset( $solid_roles['iso_tape'] ) && isset( $solid_roles['gasket'] ) && isset( $solid_roles['silicon'] ) );
nh_tc_assert( 'solid length split keeps six connecting profiles', 6 === (int) $solid_bom['meta']['connecting_count'] && isset( $solid_roles['connecting'] ) && 6 === (int) $solid_roles['connecting']['qty'] );
nh_tc_assert(
	'solid 4750 mm run is two 2375 mm pieces on the same widths',
	2 === (int) $solid_bom['meta']['length_pieces']
	&& 14 === (int) $solid_bom['meta']['sheet_count']
	&& 3 === count( $solid_sheets )
	&& 613 === (int) $solid_sheets[0]['cut']['width_mm'] && 2375 === (int) $solid_sheets[0]['cut']['length_mm'] && 4 === (int) $solid_sheets[0]['qty']
	&& 583 === (int) $solid_sheets[1]['cut']['width_mm'] && 2375 === (int) $solid_sheets[1]['cut']['length_mm'] && 8 === (int) $solid_sheets[1]['qty']
	&& 582 === (int) $solid_sheets[2]['cut']['width_mm'] && 2375 === (int) $solid_sheets[2]['cut']['length_mm'] && 2 === (int) $solid_sheets[2]['qty']
);
nh_tc_assert( 'multiwall quote still includes both tapes', isset( $by_role['vent_tape'] ) && isset( $by_role['iso_tape'] ) );

$wide_solid_pieces = NH_TC_Engine::solid_length_pieces( 2200, 4750, $settings );
nh_tc_assert(
	'a 2200 mm solid cut uses the 2050 mm side as the length',
	is_array( $wide_solid_pieces )
	&& 3 === count( $wide_solid_pieces )
	&& 1584 === (int) $wide_solid_pieces[0]
	&& 1583 === (int) $wide_solid_pieces[1]
	&& 1583 === (int) $wide_solid_pieces[2]
);
nh_tc_assert( 'a solid cut wider than 3050 mm does not fit', null === NH_TC_Engine::solid_length_pieces( 3100, 4750, $settings ) );

$stock_even = NH_TC_Engine::stock_sheet_plan( 4250, 4000, 700, 50, 10, 0, 2100, 100 );
$even_cover = 0;
foreach ( $stock_even as $sheet ) {
	$even_cover += (int) $sheet['width_mm'];
}
nh_tc_assert(
	'4250 stock layout is 1420, 2090 and 720',
	3 === count( $stock_even )
	&& 1420 === (int) $stock_even[0]['width_mm']
	&& 2090 === (int) $stock_even[1]['width_mm']
	&& 720 === (int) $stock_even[2]['width_mm']
	&& 4250 === $even_cover + ( 2 * 10 )
);

$one = NH_TC_Engine::stock_sheet_plan( 2000, 4700, 600, 50, 10, 0, 2100, 100 );
nh_tc_assert( 'a frame inside 2100 mm is one stock sheet', 1 === count( $one ) && 2000 === (int) $one[0]['width_mm'] );

$sliver_fix = NH_TC_Engine::stock_sheet_plan( 2120, 2000, 1000, 50, 10, 0, 2100, 100 );
$sliver_cover = 0;
foreach ( $sliver_fix as $sheet ) {
	$sliver_cover += (int) $sheet['width_mm'];
}
nh_tc_assert(
	'a narrow end piece moves the joint back onto an earlier beam',
	2 === count( $sliver_fix )
	&& 1020 === (int) $sliver_fix[0]['width_mm']
	&& 1090 === (int) $sliver_fix[1]['width_mm']
	&& 2120 === $sliver_cover + 10
);

$cover = NH_TC_Engine::stock_sheet_qty( 4200, 4750, 2100, 6000 );
nh_tc_assert( 'two 2100 mm sheets cover a 4200 mm frame', 2 === $cover['across'] && 1 === $cover['along'] && 2 === $cover['qty'] );
$cover_short = NH_TC_Engine::stock_sheet_qty( 4200, 4750, 2050, 3050 );
nh_tc_assert( 'a short solid blank is bought across and along the run', 3 === $cover_short['across'] && 2 === $cover_short['along'] && 6 === $cover_short['qty'] );

$std_settings                     = $settings;
$std_settings['standard_sheets']  = NH_TC_Defaults::standard_sheet_catalog();
$std_settings['standard_sheets']['multiwall']['10']['clear']['stock']['widths']['2100']['6000'] = 'SKU-2100-6000';
$std_input                        = $input;
$std_input['sheet_supply']        = 'standard';
$std_input['stock_channel']       = 'stock';
$std_input['stock_width_mm']      = 2100;
$std_input['stock_length_mm']     = 1000;
$std_bom                          = NH_TC_Engine::calculate( $std_input, $std_settings );
$std_sheets                       = array();
foreach ( $std_bom['lines'] as $line ) {
	if ( 'sheet' === $line['role'] ) {
		$std_sheets[] = $line;
	}
}
nh_tc_assert(
	'standard supply lists the covering stock sheet, not the bay cuts',
	! empty( $std_bom['ok'] )
	&& 1 === count( $std_sheets )
	&& 2 === (int) $std_sheets[0]['qty']
	&& 2100 === (int) $std_sheets[0]['cut']['width_mm']
	&& 6000 === (int) $std_sheets[0]['cut']['length_mm']
	&& 'SKU-2100-6000' === $std_sheets[0]['sku']
	&& 1 === (int) $std_sheets[0]['stock']
	&& 2 === (int) $std_bom['meta']['sheet_count']
	&& 'standard' === $std_bom['meta']['sheet_supply']
	&& 6 === (int) $std_bom['meta']['connecting_count']
);

$narrow                    = $std_input;
$narrow['stock_width_mm']  = 1050;
$narrow_bom                = NH_TC_Engine::calculate( $narrow, $std_settings );
$narrow_sheet              = null;
foreach ( $narrow_bom['lines'] as $line ) {
	if ( 'sheet' === $line['role'] ) {
		$narrow_sheet = $line;
	}
}
nh_tc_assert(
	'a 1050 mm stock width needs four sheets',
	$narrow_sheet && 4 === (int) $narrow_sheet['qty'] && 1050 === (int) $narrow_sheet['cut']['width_mm'] && 6000 === (int) $narrow_sheet['cut']['length_mm']
);

$opal                   = $std_input;
$opal['colour']         = 'opal';
$opal['stock_width_mm'] = 1250;
$opal_bom               = NH_TC_Engine::calculate( $opal, $std_settings );
$opal_sheet             = null;
foreach ( $opal_bom['lines'] as $line ) {
	if ( 'sheet' === $line['role'] ) {
		$opal_sheet = $line;
	}
}
nh_tc_assert(
	'10 mm opal 1250 mm repeats the 2 m sheet along the run',
	$opal_sheet && 12 === (int) $opal_sheet['qty'] && 1250 === (int) $opal_sheet['cut']['width_mm'] && 2000 === (int) $opal_sheet['cut']['length_mm'] && 4 === (int) $opal_bom['meta']['stock_across'] && 3 === (int) $opal_bom['meta']['stock_along']
);

$five                      = $std_input;
$five['thickness']         = 16;
$five['stock_channel']     = '5x';
$five['stock_width_mm']    = 1200;
$five_bom                  = NH_TC_Engine::calculate( $five, $std_settings );
$five_sheet                = null;
foreach ( $five_bom['lines'] as $line ) {
	if ( 'sheet' === $line['role'] ) {
		$five_sheet = $line;
	}
}
nh_tc_assert(
	'16 mm 5-wall 1200 mm uses the 5000 mm stock length',
	$five_sheet && 4 === (int) $five_sheet['qty'] && 1200 === (int) $five_sheet['cut']['width_mm'] && 5000 === (int) $five_sheet['cut']['length_mm']
);

$solid_in                  = $std_input;
$solid_in['material']      = 'solid';
$solid_in['stock_width_mm'] = 2050;
$solid_bom                 = NH_TC_Engine::calculate( $solid_in, $std_settings );
$solid_sheet               = null;
$solid_tapes               = false;
foreach ( $solid_bom['lines'] as $line ) {
	if ( 'sheet' === $line['role'] ) {
		$solid_sheet = $line;
	}
	if ( 'vent_tape' === $line['role'] || 'iso_tape' === $line['role'] ) {
		$solid_tapes = true;
	}
}
nh_tc_assert(
	'solid standard blanks cover the width and the run',
	$solid_sheet && 6 === (int) $solid_sheet['qty'] && 2050 === (int) $solid_sheet['cut']['width_mm'] && 3050 === (int) $solid_sheet['cut']['length_mm'] && ! $solid_tapes
);

$none               = $std_input;
$none['thickness']  = 12;
$none_bom           = NH_TC_Engine::calculate( $none, $std_settings );
nh_tc_assert( 'a thickness without standard plates is refused', empty( $none_bom['ok'] ) && in_array( 'standard_sheet', $none_bom['errors'], true ) );

if ( $failures ) {
	echo "\n{$failures} failed\n";
	exit( 1 );
}

echo "\nAll tests passed\n";
exit( 0 );
