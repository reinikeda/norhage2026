<?php
/**
 * Wishlist PDF. Embeds Liberation Sans so Nordic, German, and Lithuanian names stay intact.
 *
 * @package nh-wishlist
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @param string $bytes Bytes.
 * @param int    $offset Offset.
 * @return int
 */
function nh_wl_pdf_u16( $bytes, $offset ) {
	$values = unpack( 'n', substr( $bytes, $offset, 2 ) );
	return (int) $values[1];
}

/**
 * @param string $bytes Bytes.
 * @param int    $offset Offset.
 * @return int
 */
function nh_wl_pdf_u32( $bytes, $offset ) {
	$values = unpack( 'N', substr( $bytes, $offset, 4 ) );
	return (int) $values[1];
}

/**
 * @param string $bytes Bytes.
 * @param int    $offset Offset.
 * @return int
 */
function nh_wl_pdf_s16( $bytes, $offset ) {
	$value = nh_wl_pdf_u16( $bytes, $offset );
	return $value >= 0x8000 ? $value - 0x10000 : $value;
}

/**
 * Read the tables a PDF font needs.
 *
 * @param string $path Font file.
 * @return array<string,mixed>|null
 */
function nh_wl_pdf_font_info( $path ) {
	$bytes = file_get_contents( $path );
	if ( ! is_string( $bytes ) || strlen( $bytes ) < 12 ) {
		return null;
	}
	$num    = nh_wl_pdf_u16( $bytes, 4 );
	$tables = array();
	for ( $i = 0; $i < $num; $i++ ) {
		$record = 12 + ( $i * 16 );
		$tag    = substr( $bytes, $record, 4 );
		$tables[ $tag ] = array(
			'offset' => nh_wl_pdf_u32( $bytes, $record + 8 ),
			'length' => nh_wl_pdf_u32( $bytes, $record + 12 ),
		);
	}
	foreach ( array( 'cmap', 'head', 'hhea', 'hmtx', 'maxp' ) as $required ) {
		if ( ! isset( $tables[ $required ] ) ) {
			return null;
		}
	}
	$head   = $tables['head']['offset'];
	$hhea   = $tables['hhea']['offset'];
	$maxp   = $tables['maxp']['offset'];
	$units  = nh_wl_pdf_u16( $bytes, $head + 18 );
	$glyphs = nh_wl_pdf_u16( $bytes, $maxp + 4 );
	$hmetrics = nh_wl_pdf_u16( $bytes, $hhea + 34 );
	$widths = array();
	$hmtx   = $tables['hmtx']['offset'];
	for ( $i = 0; $i < $hmetrics; $i++ ) {
		$widths[ $i ] = nh_wl_pdf_u16( $bytes, $hmtx + ( $i * 4 ) );
	}
	$last = $hmetrics > 0 ? $widths[ $hmetrics - 1 ] : 500;
	for ( $i = $hmetrics; $i < $glyphs; $i++ ) {
		$widths[ $i ] = $last;
	}
	$cmap = nh_wl_pdf_parse_cmap( $bytes, $tables['cmap']['offset'] );
	if ( ! $cmap ) {
		return null;
	}
	return array(
		'bytes'  => $bytes,
		'units'  => $units > 0 ? $units : 1000,
		'ascent' => nh_wl_pdf_s16( $bytes, $hhea + 4 ),
		'descent'=> nh_wl_pdf_s16( $bytes, $hhea + 6 ),
		'bbox'   => array(
			nh_wl_pdf_s16( $bytes, $head + 36 ),
			nh_wl_pdf_s16( $bytes, $head + 38 ),
			nh_wl_pdf_s16( $bytes, $head + 40 ),
			nh_wl_pdf_s16( $bytes, $head + 42 ),
		),
		'widths' => $widths,
		'cmap'   => $cmap,
	);
}

/**
 * @param string $bytes Font bytes.
 * @param int    $offset cmap offset.
 * @return array<int,int>
 */
function nh_wl_pdf_parse_cmap( $bytes, $offset ) {
	$num = nh_wl_pdf_u16( $bytes, $offset + 2 );
	$chosen = null;
	$score  = -1;
	for ( $i = 0; $i < $num; $i++ ) {
		$record = $offset + 4 + ( $i * 8 );
		$platform = nh_wl_pdf_u16( $bytes, $record );
		$encoding = nh_wl_pdf_u16( $bytes, $record + 2 );
		$sub      = nh_wl_pdf_u32( $bytes, $record + 4 );
		$rank     = -1;
		if ( 3 === $platform && 1 === $encoding ) {
			$rank = 3;
		} elseif ( 0 === $platform ) {
			$rank = 2;
		} elseif ( 3 === $platform && 10 === $encoding ) {
			$rank = 1;
		}
		if ( $rank > $score ) {
			$score  = $rank;
			$chosen = $offset + $sub;
		}
	}
	if ( null === $chosen ) {
		return array();
	}
	$format = nh_wl_pdf_u16( $bytes, $chosen );
	if ( 4 === $format ) {
		return nh_wl_pdf_cmap_format4( $bytes, $chosen );
	}
	if ( 12 === $format ) {
		return nh_wl_pdf_cmap_format12( $bytes, $chosen );
	}
	return array();
}

/**
 * @param string $bytes Font bytes.
 * @param int    $offset Subtable offset.
 * @return array<int,int>
 */
function nh_wl_pdf_cmap_format4( $bytes, $offset ) {
	$seg_count = (int) ( nh_wl_pdf_u16( $bytes, $offset + 6 ) / 2 );
	$end_at    = $offset + 14;
	$start_at  = $end_at + ( $seg_count * 2 ) + 2;
	$delta_at  = $start_at + ( $seg_count * 2 );
	$offset_at = $delta_at + ( $seg_count * 2 );
	$end       = array();
	$start     = array();
	$delta     = array();
	$range     = array();
	for ( $i = 0; $i < $seg_count; $i++ ) {
		$end[]   = nh_wl_pdf_u16( $bytes, $end_at + ( $i * 2 ) );
		$start[] = nh_wl_pdf_u16( $bytes, $start_at + ( $i * 2 ) );
		$delta[] = nh_wl_pdf_s16( $bytes, $delta_at + ( $i * 2 ) );
		$range[] = nh_wl_pdf_u16( $bytes, $offset_at + ( $i * 2 ) );
	}
	$map = array();
	for ( $i = 0; $i < $seg_count; $i++ ) {
		if ( $start[ $i ] === 0xFFFF ) {
			continue;
		}
		for ( $code = $start[ $i ]; $code <= $end[ $i ]; $code++ ) {
			if ( 0 === $range[ $i ] ) {
				$glyph = ( $delta[ $i ] + $code ) & 0xFFFF;
			} else {
				$index = (int) ( $range[ $i ] / 2 ) + ( $code - $start[ $i ] ) - ( $seg_count - $i );
				$glyph_at = $offset_at + ( $seg_count * 2 ) + ( $index * 2 );
				if ( $glyph_at < 0 || $glyph_at + 2 > strlen( $bytes ) ) {
					continue;
				}
				$glyph = nh_wl_pdf_u16( $bytes, $glyph_at );
				if ( 0 !== $glyph ) {
					$glyph = ( $glyph + $delta[ $i ] ) & 0xFFFF;
				}
			}
			if ( $glyph > 0 ) {
				$map[ $code ] = $glyph;
			}
		}
	}
	return $map;
}

/**
 * @param string $bytes Font bytes.
 * @param int    $offset Subtable offset.
 * @return array<int,int>
 */
function nh_wl_pdf_cmap_format12( $bytes, $offset ) {
	$groups = nh_wl_pdf_u32( $bytes, $offset + 12 );
	$map    = array();
	$cursor = $offset + 16;
	for ( $i = 0; $i < $groups; $i++ ) {
		$start = nh_wl_pdf_u32( $bytes, $cursor );
		$end   = nh_wl_pdf_u32( $bytes, $cursor + 4 );
		$glyph = nh_wl_pdf_u32( $bytes, $cursor + 8 );
		$cursor += 12;
		if ( $end - $start > 20000 ) {
			continue;
		}
		for ( $code = $start; $code <= $end; $code++ ) {
			$map[ $code ] = $glyph + ( $code - $start );
		}
	}
	return $map;
}

/**
 * @param array<string,mixed> $font Font info.
 * @param int                 $codepoint Codepoint.
 * @return int
 */
function nh_wl_pdf_glyph( $font, $codepoint ) {
	if ( isset( $font['cmap'][ $codepoint ] ) ) {
		return (int) $font['cmap'][ $codepoint ];
	}
	return 0;
}

/**
 * @param string $hex RRGGBB.
 * @return array{0:float,1:float,2:float}
 */
function nh_wl_pdf_color( $hex ) {
	$hex = ltrim( (string) $hex, '#' );
	if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
		return array( 0.0, 0.0, 0.0 );
	}
	return array(
		hexdec( substr( $hex, 0, 2 ) ) / 255,
		hexdec( substr( $hex, 2, 2 ) ) / 255,
		hexdec( substr( $hex, 4, 2 ) ) / 255,
	);
}

/**
 * @param float  $x X.
 * @param float  $y Y.
 * @param float  $w Width.
 * @param float  $h Height.
 * @param string $hex Fill.
 * @return string
 */
function nh_wl_pdf_fill_rect( $x, $y, $w, $h, $hex ) {
	$color = nh_wl_pdf_color( $hex );
	return sprintf( "%.3F %.3F %.3F rg %.2F %.2F %.2F %.2F re f\n", $color[0], $color[1], $color[2], $x, $y, $w, $h );
}

/**
 * @param string              $text Text.
 * @param array<string,mixed> $font Font.
 * @param float               $size Size.
 * @param float               $x X.
 * @param float               $y Y.
 * @param string              $hex Color.
 * @param array<int,int>      $used Glyph map.
 * @return string
 */
function nh_wl_pdf_draw_text( $text, $font, $size, $x, $y, $hex, &$used ) {
	$color = nh_wl_pdf_color( $hex );
	return sprintf( "%.3F %.3F %.3F rg\n", $color[0], $color[1], $color[2] ) . nh_wl_pdf_text_operator( $text, $font, $size, $x, $y, $used );
}

/**
 * @param string              $text Text.
 * @param array<string,mixed> $font Font.
 * @param float               $size Size.
 * @param float               $right Right edge.
 * @param float               $y Y.
 * @param string              $hex Color.
 * @param array<int,int>      $used Glyph map.
 * @return string
 */
function nh_wl_pdf_draw_right( $text, $font, $size, $right, $y, $hex, &$used ) {
	$x = $right - nh_wl_pdf_width( $text, $font, $size );
	return nh_wl_pdf_draw_text( $text, $font, $size, $x, $y, $hex, $used );
}

/**
 * @param array<string,mixed> $row Row.
 * @param array<string,mixed> $font Font.
 * @return array<string,mixed>
 */
function nh_wl_pdf_sheet_row( $row, $font ) {
	$name = nh_wl_pdf_wrap( isset( $row['name'] ) ? (string) $row['name'] : '', $font, 10, 200 );
	if ( ! $name ) {
		$name = array( '' );
	}
	$details = array();
	$source  = isset( $row['details'] ) && is_array( $row['details'] ) ? $row['details'] : array();
	foreach ( $source as $line ) {
		foreach ( nh_wl_pdf_wrap( (string) $line, $font, 8, 168 ) as $wrapped ) {
			if ( '' !== $wrapped ) {
				$details[] = $wrapped;
			}
		}
	}
	$note_text = isset( $row['note'] ) ? (string) $row['note'] : '';
	$note      = '' !== $note_text ? nh_wl_pdf_wrap( $note_text, $font, 8, 500 ) : array();
	$body      = max( count( $name ), count( $details ), 1 );
	$height    = 12 + ( $body * 12 ) + ( $note ? 4 + ( count( $note ) * 11 ) : 0 ) + 6;
	return array(
		'type'    => 'row',
		'name'    => $name,
		'details' => $details,
		'note'    => $note,
		'qty'     => isset( $row['qty'] ) ? (string) $row['qty'] : '',
		'price'   => isset( $row['price'] ) ? (string) $row['price'] : '',
		'height'  => $height,
	);
}

/**
 * Quote-style sheet: branded header, columns, and a total for ready lines.
 *
 * @param array<string,mixed> $document Document.
 * @param array<string,mixed> $font Font.
 * @return string
 */
function nh_wl_pdf_render_sheet( $document, $font ) {
	$columns = isset( $document['columns'] ) && is_array( $document['columns'] ) ? $document['columns'] : array();
	$columns = array_merge(
		array(
			'product' => 'Product',
			'details' => 'Details',
			'qty'     => 'Quantity',
			'price'   => 'Price',
		),
		$columns
	);
	$blocks = array();
	$rows   = isset( $document['rows'] ) && is_array( $document['rows'] ) ? $document['rows'] : array();
	foreach ( $rows as $row ) {
		if ( is_array( $row ) ) {
			$blocks[] = nh_wl_pdf_sheet_row( $row, $font );
		}
	}
	$total = isset( $document['total'] ) ? (string) $document['total'] : '';
	if ( '' !== $total ) {
		$blocks[] = array(
			'type'   => 'total',
			'label'  => isset( $document['total_label'] ) ? (string) $document['total_label'] : '',
			'amount' => $total,
			'height' => 28,
		);
	}
	$note = isset( $document['note'] ) ? (string) $document['note'] : '';
	if ( '' !== $note ) {
		$lines = nh_wl_pdf_wrap( $note, $font, 8, 523 );
		$blocks[] = array(
			'type'   => 'note',
			'lines'  => $lines,
			'height' => 10 + ( count( $lines ) * 11 ),
		);
	}
	$chunks  = array();
	$current = array();
	$first   = true;
	$y       = 734.0;
	foreach ( $blocks as $block ) {
		if ( $current && ( $y - $block['height'] ) < 52 ) {
			$chunks[] = array(
				'first'  => $first,
				'blocks' => $current,
			);
			$current = array();
			$first   = false;
			$y       = 770.0;
		}
		$current[] = $block;
		$y        -= $block['height'];
	}
	$chunks[] = array(
		'first'  => $first,
		'blocks' => $current,
	);

	$used    = array();
	$streams = array();
	$count   = count( $chunks );
	foreach ( $chunks as $index => $chunk ) {
		$streams[] = nh_wl_pdf_sheet_page( $document, $columns, $chunk, $index + 1, $count, $font, $used );
	}
	return nh_wl_pdf_assemble( $font, $streams, $used );
}

/**
 * @param array<string,mixed>      $document Document.
 * @param array<string,string>     $columns Columns.
 * @param array<string,mixed>      $chunk Page chunk.
 * @param int                      $number Page number.
 * @param int                      $count Page count.
 * @param array<string,mixed>      $font Font.
 * @param array<int,int>           $used Glyph map.
 * @return string
 */
function nh_wl_pdf_sheet_page( $document, $columns, $chunk, $number, $count, $font, &$used ) {
	$forest = '1E3932';
	$cream  = 'F1E6D6';
	$off    = 'FAF7F2';
	$char   = '2C2A29';
	$muted  = '5F5C59';
	$white  = 'FFFFFF';
	$stream = nh_wl_pdf_fill_rect( 0, 0, 595, 842, $white );
	$title  = isset( $document['title'] ) ? (string) $document['title'] : '';
	$list   = isset( $document['subtitle'] ) ? (string) $document['subtitle'] : '';
	$meta   = isset( $document['meta'] ) ? (string) $document['meta'] : '';
	$brand  = isset( $document['brand'] ) ? (string) $document['brand'] : '';
	if ( ! empty( $chunk['first'] ) ) {
		$stream .= nh_wl_pdf_fill_rect( 0, 770, 595, 72, $forest );
		$stream .= nh_wl_pdf_draw_text( $title, $font, 18, 36, 812, $white, $used );
		if ( '' !== $list ) {
			$stream .= nh_wl_pdf_draw_text( $list, $font, 11, 36, 792, $cream, $used );
		}
		if ( '' !== $meta ) {
			$stream .= nh_wl_pdf_draw_text( $meta, $font, 9, 36, 778, $cream, $used );
		}
		if ( '' !== $brand ) {
			$stream .= nh_wl_pdf_draw_right( $brand, $font, 9, 559, 812, $cream, $used );
		}
		$head = 742.0;
		$top  = 734.0;
	} else {
		$stream .= nh_wl_pdf_fill_rect( 0, 806, 595, 36, $forest );
		$stream .= nh_wl_pdf_draw_text( trim( $title . ( '' !== $list ? ' - ' . $list : '' ) ), $font, 11, 36, 820, $white, $used );
		$head = 778.0;
		$top  = 770.0;
	}
	$stream .= nh_wl_pdf_fill_rect( 36, $head, 523, 20, $cream );
	$stream .= nh_wl_pdf_draw_text( (string) $columns['product'], $font, 8, 44, $head + 6, $forest, $used );
	$stream .= nh_wl_pdf_draw_text( (string) $columns['details'], $font, 8, 262, $head + 6, $forest, $used );
	$stream .= nh_wl_pdf_draw_right( (string) $columns['qty'], $font, 8, 470, $head + 6, $forest, $used );
	$stream .= nh_wl_pdf_draw_right( (string) $columns['price'], $font, 8, 551, $head + 6, $forest, $used );

	$stripe = 0;
	foreach ( $chunk['blocks'] as $block ) {
		$bottom = $top - $block['height'];
		if ( 'row' === $block['type'] ) {
			if ( 1 === $stripe % 2 ) {
				$stream .= nh_wl_pdf_fill_rect( 36, $bottom, 523, $block['height'], $off );
			}
			$stream .= nh_wl_pdf_fill_rect( 36, $bottom, 523, 0.6, 'E4DDD2' );
			$cursor = $top - 16;
			foreach ( $block['name'] as $line ) {
				$stream .= nh_wl_pdf_draw_text( $line, $font, 10, 44, $cursor, $char, $used );
				$cursor -= 12;
			}
			$cursor = $top - 16;
			foreach ( $block['details'] as $line ) {
				$stream .= nh_wl_pdf_draw_text( $line, $font, 8, 262, $cursor, $muted, $used );
				$cursor -= 12;
			}
			if ( '' !== $block['qty'] ) {
				$stream .= nh_wl_pdf_draw_right( $block['qty'], $font, 10, 470, $top - 16, $char, $used );
			}
			if ( '' !== $block['price'] ) {
				$stream .= nh_wl_pdf_draw_right( $block['price'], $font, 10, 551, $top - 16, $char, $used );
			}
			if ( $block['note'] ) {
				$note_y = $top - 16 - ( max( count( $block['name'] ), count( $block['details'] ), 1 ) * 12 ) - 2;
				foreach ( $block['note'] as $line ) {
					$stream .= nh_wl_pdf_draw_text( $line, $font, 8, 44, $note_y, '4B2C20', $used );
					$note_y -= 11;
				}
			}
			++$stripe;
		} elseif ( 'total' === $block['type'] ) {
			$stream .= nh_wl_pdf_fill_rect( 360, $bottom + 8, 199, 0.8, $forest );
			$stream .= nh_wl_pdf_draw_text( $block['label'], $font, 10, 360, $bottom + 14, $forest, $used );
			$stream .= nh_wl_pdf_draw_right( $block['amount'], $font, 10, 551, $bottom + 14, $forest, $used );
		} else {
			$cursor = $top - 12;
			foreach ( $block['lines'] as $line ) {
				$stream .= nh_wl_pdf_draw_text( $line, $font, 8, 36, $cursor, $muted, $used );
				$cursor -= 11;
			}
		}
		$top = $bottom;
	}

	$stream .= nh_wl_pdf_fill_rect( 0, 0, 595, 36, $cream );
	$footer  = isset( $document['footer'] ) ? (string) $document['footer'] : '';
	$contact = isset( $document['contact'] ) ? (string) $document['contact'] : '';
	if ( '' !== $footer ) {
		$stream .= nh_wl_pdf_draw_text( $footer, $font, 8, 36, 16, $forest, $used );
	}
	if ( '' !== $contact ) {
		$stream .= nh_wl_pdf_draw_text( $contact, $font, 8, 220, 16, $muted, $used );
	}
	$stream .= nh_wl_pdf_draw_right( $number . '/' . $count, $font, 8, 559, 16, $muted, $used );
	return $stream;
}

/**
 * @param array<string,mixed> $document Document.
 * @param string              $font_path Font path.
 * @return string
 */
function nh_wl_pdf_render( $document, $font_path ) {
	$font = nh_wl_pdf_font_info( $font_path );
	if ( ! $font ) {
		return nh_wl_pdf_render_plain( $document );
	}
	$document = is_array( $document ) ? $document : array();
	if ( isset( $document['rows'] ) && is_array( $document['rows'] ) ) {
		return nh_wl_pdf_render_sheet( $document, $font );
	}
	$title    = isset( $document['title'] ) ? (string) $document['title'] : '';
	$subtitle = isset( $document['subtitle'] ) ? (string) $document['subtitle'] : '';
	$meta     = isset( $document['meta'] ) ? (string) $document['meta'] : '';
	$footer   = isset( $document['footer'] ) ? (string) $document['footer'] : '';
	$blocks   = isset( $document['blocks'] ) && is_array( $document['blocks'] ) ? $document['blocks'] : array();

	$pages   = array( '' );
	$page    = 0;
	$y       = 790;
	$leading = 15;
	$used    = array();

	$write = function ( $text, $size, $gap ) use ( &$pages, &$page, &$y, $font, &$used, $leading ) {
		$lines = nh_wl_pdf_wrap( (string) $text, $font, $size, 499 );
		foreach ( $lines as $line ) {
			if ( $y < 64 ) {
				++$page;
				$pages[ $page ] = '';
				$y = 790;
			}
			$pages[ $page ] .= nh_wl_pdf_text_operator( $line, $font, $size, 48, $y, $used );
			$y -= max( $gap, (int) round( $size * 1.25 ) );
		}
		$y -= 2;
	};

	foreach ( array( array( $title, 18, 8 ), array( $subtitle, 13, 4 ), array( $meta, 10, 10 ) ) as $row ) {
		if ( '' !== $row[0] ) {
			$write( $row[0], $row[1], $row[2] );
		}
	}
	if ( ! $blocks ) {
		$blocks[] = array(
			'heading' => '',
			'lines'   => array( '' ),
		);
	}
	foreach ( $blocks as $block ) {
		if ( ! is_array( $block ) ) {
			continue;
		}
		$heading = isset( $block['heading'] ) ? (string) $block['heading'] : '';
		if ( '' !== $heading ) {
			if ( $y < 90 ) {
				++$page;
				$pages[ $page ] = '';
				$y = 790;
			}
			$y -= 6;
			$write( $heading, 12, 4 );
		}
		$lines = isset( $block['lines'] ) && is_array( $block['lines'] ) ? $block['lines'] : array();
		foreach ( $lines as $line ) {
			$write( (string) $line, 10, 2 );
		}
	}

	$page_count = count( $pages );
	$contents   = array();
	foreach ( $pages as $index => $stream ) {
		$number = $index + 1;
		$label  = trim( $footer . ( $footer ? '  ' : '' ) . $number . '/' . $page_count );
		$stream .= nh_wl_pdf_text_operator( $label, $font, 9, 48, 36, $used );
		$contents[] = $stream;
	}

	return nh_wl_pdf_assemble( $font, $contents, $used );
}

/**
 * @param string $stream Stream bytes.
 * @return string
 */
function nh_wl_pdf_stream_object( $stream ) {
	return '<< /Length ' . strlen( $stream ) . " >>\nstream\n" . $stream . "\nendstream";
}

/**
 * @param string              $text Text.
 * @param array<string,mixed> $font Font.
 * @param float               $size Size.
 * @param float               $max_width Width.
 * @return array<int,string>
 */
function nh_wl_pdf_wrap( $text, $font, $size, $max_width ) {
	$text = str_replace( array( "\r\n", "\r" ), "\n", $text );
	$rows = explode( "\n", $text );
	$out  = array();
	foreach ( $rows as $row ) {
		$words   = preg_split( '/\s+/', $row ) ?: array();
		$current = '';
		foreach ( $words as $word ) {
			if ( '' === $word ) {
				continue;
			}
			$candidate = '' === $current ? $word : $current . ' ' . $word;
			if ( nh_wl_pdf_width( $candidate, $font, $size ) <= $max_width ) {
				$current = $candidate;
				continue;
			}
			if ( '' !== $current ) {
				$out[]   = $current;
				$current = '';
			}
			if ( nh_wl_pdf_width( $word, $font, $size ) <= $max_width ) {
				$current = $word;
				continue;
			}
			$chunk = '';
			$codes = nh_wl_utf8_codepoints( $word );
			foreach ( $codes as $code ) {
				$next = $chunk . nh_wl_codepoint_utf8( $code );
				if ( '' !== $chunk && nh_wl_pdf_width( $next, $font, $size ) > $max_width ) {
					$out[] = $chunk;
					$chunk = nh_wl_codepoint_utf8( $code );
				} else {
					$chunk = $next;
				}
			}
			$current = $chunk;
		}
		$out[] = $current;
	}
	return $out ? $out : array( '' );
}

/**
 * @param string              $text Text.
 * @param array<string,mixed> $font Font.
 * @param float               $size Size.
 * @return float
 */
function nh_wl_pdf_width( $text, $font, $size ) {
	$width = 0;
	foreach ( nh_wl_utf8_codepoints( $text ) as $code ) {
		$glyph  = nh_wl_pdf_glyph( $font, $code );
		$advance = isset( $font['widths'][ $glyph ] ) ? (int) $font['widths'][ $glyph ] : 500;
		$width  += $advance;
	}
	return $width * $size / (int) $font['units'];
}

/**
 * @param string                    $text Text.
 * @param array<string,mixed>       $font Font.
 * @param float                     $size Size.
 * @param float                     $x X.
 * @param float                     $y Y.
 * @param array<int,int>            $used Glyph to codepoint.
 * @return string
 */
function nh_wl_pdf_text_operator( $text, $font, $size, $x, $y, &$used ) {
	$hex = '';
	foreach ( nh_wl_utf8_codepoints( $text ) as $code ) {
		if ( $code > 0xFFFF ) {
			$code = 0x3F;
		}
		$glyph = nh_wl_pdf_glyph( $font, $code );
		if ( $glyph <= 0 ) {
			$glyph = nh_wl_pdf_glyph( $font, 0x3F );
			$code  = 0x3F;
		}
		if ( $glyph <= 0 ) {
			continue;
		}
		$used[ $glyph ] = $code;
		$hex           .= sprintf( '%04X', $glyph );
	}
	if ( '' === $hex ) {
		return '';
	}
	return sprintf( "BT /F1 %.2F Tf %.2F %.2F Td <%s> Tj ET\n", $size, $x, $y, $hex );
}

/**
 * @param array<string,mixed> $font Font.
 * @param array<int,string>   $contents Page streams.
 * @param array<int,int>      $used Glyph map.
 * @return string
 */
function nh_wl_pdf_assemble( $font, $contents, $used ) {
	$units = (int) $font['units'];
	$scale = 1000 / $units;
	$bbox  = $font['bbox'];
	$box   = sprintf(
		'[ %d %d %d %d ]',
		(int) round( $bbox[0] * $scale ),
		(int) round( $bbox[1] * $scale ),
		(int) round( $bbox[2] * $scale ),
		(int) round( $bbox[3] * $scale )
	);
	$ascent  = (int) round( $font['ascent'] * $scale );
	$descent = (int) round( $font['descent'] * $scale );
	$widths  = array();
	ksort( $used );
	foreach ( $used as $glyph => $code ) {
		$advance  = isset( $font['widths'][ $glyph ] ) ? (int) $font['widths'][ $glyph ] : 500;
		$widths[] = $glyph . ' [ ' . (int) round( $advance * $scale ) . ' ]';
	}
	$tounicode = nh_wl_pdf_tounicode( $used );
	$compressed = gzcompress( $font['bytes'] );
	$font_stream = is_string( $compressed ) ? $compressed : $font['bytes'];
	$filter      = is_string( $compressed ) ? ' /Filter /FlateDecode' : '';

	$page_count   = count( $contents );
	$font_dict_id = 3 + ( $page_count * 2 );
	$cid_id       = $font_dict_id + 1;
	$desc_id      = $font_dict_id + 2;
	$file_id      = $font_dict_id + 3;
	$unicode_id   = $font_dict_id + 4;
	$kids         = array();
	$objects      = array();
	$objects[0]   = '<< /Type /Catalog /Pages 2 0 R >>';

	for ( $i = 0; $i < $page_count; $i++ ) {
		$page_id    = 3 + ( $i * 2 );
		$content_id = $page_id + 1;
		$kids[]     = $page_id . ' 0 R';
		$objects[ $page_id - 1 ] = '<< /Type /Page /Parent 2 0 R /MediaBox [ 0 0 595 842 ] /Contents ' . $content_id . ' 0 R /Resources << /Font << /F1 ' . $font_dict_id . ' 0 R >> >> >>';
		$objects[ $content_id - 1 ] = nh_wl_pdf_stream_object( $contents[ $i ] );
	}
	$objects[1] = '<< /Type /Pages /Count ' . $page_count . ' /Kids [ ' . implode( ' ', $kids ) . ' ] >>';

	$objects[ $font_dict_id - 1 ] = '<< /Type /Font /Subtype /Type0 /BaseFont /LiberationSans /Encoding /Identity-H /DescendantFonts [ ' . $cid_id . ' 0 R ] /ToUnicode ' . $unicode_id . ' 0 R >>';
	$objects[ $cid_id - 1 ]       = '<< /Type /Font /Subtype /CIDFontType2 /BaseFont /LiberationSans /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /FontDescriptor ' . $desc_id . ' 0 R /CIDToGIDMap /Identity /DW 500 /W [ ' . implode( ' ', $widths ) . ' ] >>';
	$objects[ $desc_id - 1 ]      = '<< /Type /FontDescriptor /FontName /LiberationSans /Flags 32 /FontBBox ' . $box . ' /ItalicAngle 0 /Ascent ' . $ascent . ' /Descent ' . $descent . ' /CapHeight ' . $ascent . ' /StemV 80 /FontFile2 ' . $file_id . ' 0 R >>';
	$objects[ $file_id - 1 ]      = '<< /Length ' . strlen( $font_stream ) . ' /Length1 ' . strlen( $font['bytes'] ) . $filter . " >>\nstream\n" . $font_stream . "\nendstream";
	$objects[ $unicode_id - 1 ]   = nh_wl_pdf_stream_object( $tounicode );

	ksort( $objects );
	return nh_wl_pdf_output( array_values( $objects ) );
}

/**
 * @param array<int,int> $used Glyph to codepoint.
 * @return string
 */
function nh_wl_pdf_tounicode( $used ) {
	$chunks = array_chunk( $used, 100, true );
	$body   = "/CIDInit /ProcSet findresource begin\n12 dict begin\nbegincmap\n/CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> def\n/CMapName /Adobe-Identity-UCS def\n/CMapType 2 def\n1 begincodespacerange\n<0000> <FFFF>\nendcodespacerange\n";
	foreach ( $chunks as $chunk ) {
		$body .= count( $chunk ) . " beginbfchar\n";
		foreach ( $chunk as $glyph => $code ) {
			$body .= sprintf( "<%04X> <%04X>\n", $glyph, $code );
		}
		$body .= "endbfchar\n";
	}
	$body .= "endcmap\nCMapName currentdict /CMap defineresource pop\nend\nend\n";
	return $body;
}

/**
 * @param array<int,string> $objects Object bodies, object 1 first. Empty strings are skipped only if truly unused.
 * @return string
 */
function nh_wl_pdf_output( $objects ) {
	$pdf     = "%PDF-1.4\n";
	$offsets = array( 0 );
	foreach ( $objects as $index => $body ) {
		$offsets[ $index + 1 ] = strlen( $pdf );
		$pdf                  .= ( $index + 1 ) . " 0 obj\n" . $body . "\nendobj\n";
	}
	$xref  = strlen( $pdf );
	$count = count( $objects ) + 1;
	$pdf  .= "xref\n0 $count\n";
	$pdf  .= "0000000000 65535 f \n";
	for ( $i = 1; $i < $count; $i++ ) {
		$pdf .= sprintf( "%010d 00000 n \n", $offsets[ $i ] );
	}
	$pdf .= "trailer\n<< /Size $count /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
	return $pdf;
}

/**
 * Helvetica fallback when the font file cannot be read. ASCII only.
 *
 * @param array<string,mixed> $document Document.
 * @return string
 */
function nh_wl_pdf_render_plain( $document ) {
	$title = isset( $document['title'] ) ? preg_replace( '/[^\x20-\x7E]/', '?', (string) $document['title'] ) : 'Wishlist';
	$title = str_replace( array( '\\', '(', ')' ), array( '\\\\', '\\(', '\\)' ), (string) $title );
	$stream = "BT /F1 18 Tf 48 790 Td ($title) Tj ET\n";
	$objects = array(
		'<< /Type /Catalog /Pages 2 0 R >>',
		'<< /Type /Pages /Count 1 /Kids [ 3 0 R ] >>',
		'<< /Type /Page /Parent 2 0 R /MediaBox [ 0 0 595 842 ] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
		nh_wl_pdf_stream_object( $stream ),
		'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
	);
	return nh_wl_pdf_output( $objects );
}
