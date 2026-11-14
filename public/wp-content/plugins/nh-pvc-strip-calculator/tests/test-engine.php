<?php
/**
 * PVC strip curtain engine tests (no WordPress).
 *
 * Run: php public/wp-content/plugins/nh-pvc-strip-calculator/tests/test-engine.php
 */

define( 'ABSPATH', '/tmp/' );

require_once dirname( __DIR__ ) . '/includes/class-nh-psc-defaults.php';
require_once dirname( __DIR__ ) . '/includes/class-nh-psc-engine.php';

$failed = 0;

function nh_psc_assert( $label, $ok ) {
	global $failed;
	if ( $ok ) {
		echo "ok  $label\n";
		return;
	}
	$failed++;
	echo "FAIL  $label\n";
}

$settings = NH_PSC_Defaults::all();

// Face mount 2000×2200, 200 mm, medium overlap (77%).
$face = NH_PSC_Engine::calculate(
	array(
		'mounting'       => 'face',
		'width_mm'       => 2000,
		'height_mm'      => 2200,
		'strip_width_mm' => 200,
		'overlap'        => 'med',
		'strip_type'     => 'smooth',
		'grade'          => 'standard',
	),
	$settings
);
nh_psc_assert( 'face calc width 2080', 2080 === (int) $face['meta']['calc_width_mm'] );
nh_psc_assert( 'face calc height 2230', 2230 === (int) $face['meta']['calc_height_mm'] );
// ceil(2080 * 1.77 / 200) = ceil(18.408) = 19
nh_psc_assert( 'face 200 med strips 19', 19 === (int) $face['meta']['strip_count'] );
nh_psc_assert( 'face total metres 42.37', abs( 42.37 - (float) $face['meta']['total_metres'] ) < 0.01 );
nh_psc_assert( 'face rail qty 2 of 1500', 2 === (int) $face['meta']['rail_qty'] );
nh_psc_assert( 'face hangers = strips', 19 === (int) $face['meta']['hanger_qty'] );

$soffit = NH_PSC_Engine::calculate(
	array(
		'mounting'       => 'soffit',
		'width_mm'       => 2000,
		'height_mm'      => 2200,
		'strip_width_mm' => 200,
		'overlap'        => 'med',
	),
	$settings
);
nh_psc_assert( 'soffit width unchanged', 2000 === (int) $soffit['meta']['calc_width_mm'] );
nh_psc_assert( 'soffit height −45', 2155 === (int) $soffit['meta']['calc_height_mm'] );
// ceil(2000 * 1.77 / 200) = ceil(17.7) = 18
nh_psc_assert( 'soffit 200 med strips 18', 18 === (int) $soffit['meta']['strip_count'] );

$face300 = NH_PSC_Engine::calculate(
	array(
		'mounting'       => 'face',
		'width_mm'       => 3000,
		'height_mm'      => 3000,
		'strip_width_mm' => 300,
		'overlap'        => 'max',
	),
	$settings
);
nh_psc_assert( 'face 300 width +90', 3090 === (int) $face300['meta']['calc_width_mm'] );
nh_psc_assert( 'face 300 height +30', 3030 === (int) $face300['meta']['calc_height_mm'] );
// ceil(3090 * 1.91 / 300) = ceil(19.673) = 20
nh_psc_assert( 'face 300 max strips 20', 20 === (int) $face300['meta']['strip_count'] );

$face400 = NH_PSC_Engine::calculate(
	array(
		'mounting'       => 'face',
		'width_mm'       => 4000,
		'height_mm'      => 4500,
		'strip_width_mm' => 400,
		'overlap'        => 'max',
		'red_edges'      => true,
	),
	$settings
);
nh_psc_assert( 'face 400 width +100', 4100 === (int) $face400['meta']['calc_width_mm'] );
nh_psc_assert( 'face 400 height +30', 4530 === (int) $face400['meta']['calc_height_mm'] );
// ceil(4100 * 1.98 / 400) = ceil(20.295) = 21
nh_psc_assert( 'face 400 max strips 21', 21 === (int) $face400['meta']['strip_count'] );
nh_psc_assert( 'red edges take 2 strips', 2 === (int) $face400['meta']['red_count'] && 19 === (int) $face400['meta']['clear_count'] );

nh_psc_assert( 'recommend under 2.5 m → 200', 200 === NH_PSC_Engine::recommend_strip_width( 2400 ) );
nh_psc_assert( 'recommend 2.5–4 m → 300', 300 === NH_PSC_Engine::recommend_strip_width( 3000 ) );
nh_psc_assert( 'recommend 4 m+ → 400', 400 === NH_PSC_Engine::recommend_strip_width( 4000 ) );

$bad = NH_PSC_Engine::calculate( array( 'width_mm' => 10, 'height_mm' => 10 ), $settings );
nh_psc_assert( 'rejects tiny opening', empty( $bad['ok'] ) );

$by = array();
foreach ( $face['lines'] as $line ) {
	$by[ $line['role'] ] = $line;
}
nh_psc_assert( 'BOM has strip + rail + hanger', isset( $by['strip'], $by['rail'], $by['hanger'] ) );

if ( $failed ) {
	echo "\n$failed failed\n";
	exit( 1 );
}
echo "\nAll tests passed\n";
