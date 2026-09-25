<?php
/**
 * Standard stock-sheet catalog. No WordPress.
 *
 * Run: php public/wp-content/plugins/nh-terrace-calculator/tests/test-standard-sheets.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/' );
}

require_once dirname( __DIR__ ) . '/includes/class-nh-tc-defaults.php';

$failures = 0;

function nh_tc_sheet_assert( $label, $ok ) {
	global $failures;
	if ( $ok ) {
		echo "ok  {$label}\n";
		return;
	}
	$failures++;
	echo "FAIL  {$label}\n";
}

$to_6 = array( 1000, 2000, 3000, 4000, 6000 );
$to_7 = array( 1000, 2000, 3000, 4000, 5000, 7000 );

nh_tc_sheet_assert(
	'4 mm clear is 1050 and 2100 up to 6 m',
	$to_6 === NH_TC_Defaults::standard_sheet_lengths( 'multiwall', 4, 'clear', 2100 )
	&& $to_6 === NH_TC_Defaults::standard_sheet_lengths( 'multiwall', 4, 'clear', 1050 )
	&& array() === NH_TC_Defaults::standard_sheet_lengths( 'multiwall', 4, 'clear', 900 )
	&& '40101210060401P' === NH_TC_Defaults::standard_sheet_groups( 'multiwall', 4, 'clear' )['stock']['sku']
);

nh_tc_sheet_assert(
	'8 mm clear uses 5 m and 7 m stock',
	$to_7 === NH_TC_Defaults::standard_sheet_lengths( 'multiwall', 8, 'clear', 2100 )
	&& ! in_array( 6000, NH_TC_Defaults::standard_sheet_lengths( 'multiwall', 8, 'clear', 2100 ), true )
);

nh_tc_sheet_assert(
	'10 mm clear includes 1250 mm',
	$to_6 === NH_TC_Defaults::standard_sheet_lengths( 'multiwall', 10, 'clear', 1250 )
	&& '40101210061001P' === NH_TC_Defaults::standard_sheet_groups( 'multiwall', 10, 'clear' )['stock']['sku']
);

nh_tc_sheet_assert(
	'10 mm opal 1250 mm is only the 2 m length',
	array( 2000 ) === NH_TC_Defaults::standard_sheet_lengths( 'multiwall', 10, 'opal', 1250 )
	&& $to_6 === NH_TC_Defaults::standard_sheet_lengths( 'multiwall', 10, 'opal', 2100 )
);

$clear16 = NH_TC_Defaults::standard_sheet_groups( 'multiwall', 16, 'clear' );
nh_tc_sheet_assert(
	'16 mm clear keeps 6-wall, 5-wall and 3-wall apart',
	array( '6w', '5x', '3w' ) === array_keys( $clear16 )
	&& '40106210071601P' === $clear16['6w']['sku']
	&& $to_6 === NH_TC_Defaults::standard_sheet_lengths( 'multiwall', 16, 'clear', 2100, '6w' )
	&& '' === $clear16['6w']['widths']['2100']['1000']
	&& ! isset( $clear16['6w']['widths']['900'] )
);

nh_tc_sheet_assert(
	'16 mm 5-wall lists 900, 980 and 1200 with their own lengths',
	$to_7 === NH_TC_Defaults::standard_sheet_lengths( 'multiwall', 16, 'clear', 900, '5x' )
	&& $to_7 === NH_TC_Defaults::standard_sheet_lengths( 'multiwall', 16, 'clear', 980, '5x' )
	&& array( 1000, 2000, 4000, 5000, 7500 ) === NH_TC_Defaults::standard_sheet_lengths( 'multiwall', 16, 'clear', 1200, '5x' )
	&& array() === NH_TC_Defaults::standard_sheet_lengths( 'multiwall', 16, 'clear', 900, '6w' )
);

nh_tc_sheet_assert(
	'16 mm 3-wall is only 980 x 5000 mm',
	array( 5000 ) === NH_TC_Defaults::standard_sheet_lengths( 'multiwall', 16, 'clear', 980, '3w' )
);

nh_tc_sheet_assert(
	'16 mm opal 1200 mm stops at 3 m',
	array( 1000, 2000, 3000 ) === NH_TC_Defaults::standard_sheet_lengths( 'multiwall', 16, 'opal', 1200 )
	&& $to_7 === NH_TC_Defaults::standard_sheet_lengths( 'multiwall', 16, 'opal', 2100 )
);

nh_tc_sheet_assert(
	'40 mm clear is the 1230 mm sheet',
	$to_7 === NH_TC_Defaults::standard_sheet_lengths( 'multiwall', 40, 'clear', 1230 )
	&& '40274123074001P' === NH_TC_Defaults::standard_sheet_groups( 'multiwall', 40, 'clear' )['stock']['sku']
	&& array() === NH_TC_Defaults::standard_sheet_lengths( 'multiwall', 40, 'clear', 2100 )
);

nh_tc_sheet_assert(
	'solid 10 mm clear is 2050 x 1520 and 3050',
	array( 1520, 3050 ) === NH_TC_Defaults::standard_sheet_lengths( 'solid', 10, 'clear', 2050 )
	&& '3011001' === NH_TC_Defaults::standard_sheet_groups( 'solid', 10, 'clear' )['stock']['sku']
	&& array() === NH_TC_Defaults::standard_sheet_lengths( 'solid', 10, 'clear', 1050 )
);

nh_tc_sheet_assert(
	'6 mm bronze uses the Arla parent',
	'40101210060602P' === NH_TC_Defaults::standard_sheet_groups( 'multiwall', 6, 'bronze' )['stock']['sku']
);

nh_tc_sheet_assert(
	'a thickness without standard plates is empty',
	array() === NH_TC_Defaults::standard_sheet_groups( 'multiwall', 12, 'clear' )
	&& array() === NH_TC_Defaults::standard_sheet_groups( 'solid', 10, 'bronze' )
);

$catalog = NH_TC_Defaults::standard_sheet_catalog();
$skus = array();
foreach ( $catalog as $materials ) {
	foreach ( $materials as $colours ) {
		foreach ( $colours as $groups ) {
			foreach ( $groups as $group ) {
				$skus[] = $group['sku'];
			}
		}
	}
}
nh_tc_sheet_assert(
	'a 4750 mm run takes the shortest covering stock length',
	6000 === NH_TC_Defaults::covering_stock_length( $to_6, 4750 )
	&& 5000 === NH_TC_Defaults::covering_stock_length( array( 1000, 2000, 4000, 5000, 7500 ), 4750 )
	&& 3050 === NH_TC_Defaults::covering_stock_length( array( 1520, 3050 ), 4750 )
	&& 5000 === NH_TC_Defaults::covering_stock_length( array( 5000 ), 980 )
	&& 1000 === NH_TC_Defaults::covering_stock_length( $to_6, 500 )
	&& 0 === NH_TC_Defaults::covering_stock_length( array(), 4750 )
);

nh_tc_sheet_assert(
	'variation slots start without an invented SKU',
	'' === NH_TC_Defaults::standard_sheet_variation_sku( 'multiwall', 10, 'clear', 1050, 1000 )
	&& array( '1000' => '', '2000' => 'SKU' ) === NH_TC_Defaults::length_sku_map( array( '1000' => '', '2000' => 'SKU' ) )
	&& array( '1000' => '', '2000' => '' ) === NH_TC_Defaults::length_sku_map( array( 1000, 2000 ) )
);

$merged_base = array(
	'widths' => array(
		'2100' => array(
			'1000' => '',
			'6000' => '',
		),
	),
);
$merged_list = NH_TC_Defaults::merge_deep(
	$merged_base,
	array(
		'widths' => array(
			'2100' => array( 1000, 2000, 3000, 4000, 6000 ),
		),
	)
);
$merged_skus = NH_TC_Defaults::merge_deep(
	array(
		'widths' => array(
			'2100' => array(
				'1000' => '',
				'6000' => '',
			),
		),
	),
	array(
		'widths' => array(
			'2100' => array(
				'1000' => 'SKU-1000',
				'6000' => 'SKU-6000',
			),
		),
	)
);
nh_tc_sheet_assert(
	'saved length lists replace maps and saved SKUs overlay them',
	array( 1000, 2000, 3000, 4000, 6000 ) === $merged_list['widths']['2100']
	&& 'SKU-1000' === $merged_skus['widths']['2100']['1000']
	&& 'SKU-6000' === $merged_skus['widths']['2100']['6000']
);

$picked = NH_TC_Defaults::pick_standard_sheet( NH_TC_Defaults::standard_sheet_catalog(), 'multiwall', 10, 'clear', 'stock', 2100, 4750 );
nh_tc_sheet_assert(
	'the quote picks 2100 x 6000 for a 4750 mm run',
	is_array( $picked )
	&& 2100 === $picked['width_mm']
	&& 6000 === $picked['length_mm']
	&& '40101210061001P' === $picked['parent_sku']
	&& '' === $picked['sku']
);
nh_tc_sheet_assert(
	'an unknown thickness has no standard sheet',
	null === NH_TC_Defaults::pick_standard_sheet( NH_TC_Defaults::standard_sheet_catalog(), 'multiwall', 12, 'clear', 'stock', 2100, 4750 )
);

nh_tc_sheet_assert(
	'Polygal duplicates of an Arla sheet are left out',
	! in_array( '40201210060602P', $skus, true )
	&& ! in_array( '40201210071002P', $skus, true )
	&& ! in_array( '40206210071602P', $skus, true )
);

if ( $failures ) {
	echo "\n{$failures} failed\n";
	exit( 1 );
}

echo "\nAll tests passed\n";
exit( 0 );
