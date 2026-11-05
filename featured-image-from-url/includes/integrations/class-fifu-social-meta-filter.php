<?php

defined( 'ABSPATH' ) || exit;

/**
 * Filters og/twitter image metadata to protect FIFU tags.
 */
class Fifu_Social_Meta_Filter {

	/**
	 * Removes non-FIFU og:image/twitter:image tags while preserving FIFU blocks.
	 *
	 * @param string $buffer
	 * @return string
	 */
	public static function filter_og_images( string $buffer ): string {
		$tokens = self::tokenize_html( $buffer );
		if ( null === $tokens ) {
			return $buffer;
		}

		$scope_start  = 0;
		$scope_end    = strlen( $buffer );
		$head_open    = null;
		$body_seen    = false;
		$document_tag = false;

		foreach ( $tokens as $index => $token ) {
			if ( 'tag' !== $token['type'] || $token['closing'] ) {
				continue;
			}

			if ( in_array( $token['name'], array( 'html', 'body' ), true ) ) {
				$document_tag = true;
			}

			if ( 'body' === $token['name'] ) {
				$body_seen = true;
			}

			if ( 'head' !== $token['name'] ) {
				continue;
			}

			if ( $body_seen || null !== $head_open ) {
				return $buffer;
			}

			$head_open  = $token;
			$scope_start = $token['end'];

			for ( $close_index = $index + 1, $count = count( $tokens ); $close_index < $count; ++$close_index ) {
				$close_token = $tokens[ $close_index ];
				if ( 'tag' !== $close_token['type'] ) {
					continue;
				}
				if ( ! $close_token['closing'] && 'body' === $close_token['name'] ) {
					return $buffer;
				}
				if ( ! $close_token['closing'] && 'head' === $close_token['name'] ) {
					return $buffer;
				}
				if ( $close_token['closing'] && 'html' === $close_token['name'] ) {
					return $buffer;
				}
				if ( $close_token['closing'] && 'head' === $close_token['name'] ) {
					$scope_end = $close_token['start'];
					break;
				}
			}

			if ( strlen( $buffer ) === $scope_end ) {
				return $buffer;
			}
			break;
		}

		if ( null === $head_open && $document_tag ) {
			return $buffer;
		}

		$blocks = self::find_fifu_blocks( $tokens, $scope_start, $scope_end );
		$has_fifu_ogimage = false;
		foreach ( $tokens as $token ) {
			if ( 'tag' !== $token['type'] || $token['closing'] || $token['start'] < $scope_start || $token['end'] > $scope_end ) {
				continue;
			}
			if ( self::is_image_meta( $token, 'og' ) && self::is_inside_block( $token['start'], $blocks ) ) {
				$has_fifu_ogimage = true;
				break;
			}
		}

		if ( ! $has_fifu_ogimage ) {
			return $buffer;
		}

		$ranges_to_remove = array();
		foreach ( $tokens as $token ) {
			if ( 'tag' !== $token['type'] || $token['closing'] || $token['start'] < $scope_start || $token['end'] > $scope_end ) {
				continue;
			}
			if ( ! self::is_image_meta( $token, 'og' ) && ! self::is_image_meta( $token, 'twitter' ) ) {
				continue;
			}
			if ( self::is_inside_block( $token['start'], $blocks ) ) {
				continue;
			}

			$range_end = $token['end'];
			while ( $range_end < $scope_end && ctype_space( $buffer[ $range_end ] ) ) {
				++$range_end;
			}
			$ranges_to_remove[] = array(
				'start' => $token['start'],
				'end'   => $range_end,
			);
		}

		if ( empty( $ranges_to_remove ) ) {
			return $buffer;
		}

		$filtered_scope = '';
		$cursor         = $scope_start;
		foreach ( $ranges_to_remove as $range ) {
			if ( $range['start'] < $cursor ) {
				continue;
			}
			$filtered_scope .= substr( $buffer, $cursor, $range['start'] - $cursor );
			$cursor = $range['end'];
		}
		$filtered_scope .= substr( $buffer, $cursor, $scope_end - $cursor );

		return substr_replace( $buffer, $filtered_scope, $scope_start, $scope_end - $scope_start );
	}

	/**
	 * Tokenizes tags and comments, skipping content in HTML raw-text elements.
	 * Returns null when the input is malformed enough that offsets are ambiguous.
	 *
	 * @param string $html
	 * @return array<int,array<string,mixed>>|null
	 */
	private static function tokenize_html( string $html ): ?array {
		$tokens       = array();
		$length       = strlen( $html );
		$offset       = 0;
		$raw_text_tags = array( 'script', 'style', 'textarea', 'title', 'xmp', 'iframe', 'noembed', 'noframes' );

		while ( false !== ( $start = strpos( $html, '<', $offset ) ) ) {
			if ( '<!--' === substr( $html, $start, 4 ) ) {
				$comment_end = strpos( $html, '-->', $start + 4 );
				if ( false === $comment_end ) {
					return null;
				}
				$end      = $comment_end + 3;
				$tokens[] = array(
					'type'    => 'comment',
					'start'   => $start,
					'end'     => $end,
					'content' => substr( $html, $start + 4, $comment_end - $start - 4 ),
				);
				$offset = $end;
				continue;
			}

			if ( in_array( substr( $html, $start, 2 ), array( '<!', '<?' ), true ) ) {
				$quote          = '';
				$subset_depth   = 0;
				$declaration_end = $start + 2;
				while ( $declaration_end < $length ) {
					$character = $html[ $declaration_end ];
					if ( '' !== $quote ) {
						if ( $character === $quote ) {
							$quote = '';
						}
					} elseif ( '"' === $character || "'" === $character ) {
						$quote = $character;
					} elseif ( '[' === $character ) {
						++$subset_depth;
					} elseif ( ']' === $character && $subset_depth > 0 ) {
						--$subset_depth;
					} elseif ( '>' === $character && 0 === $subset_depth ) {
						++$declaration_end;
						break;
					}
					++$declaration_end;
				}
				if ( $declaration_end > $length || '>' !== $html[ $declaration_end - 1 ] || '' !== $quote || 0 !== $subset_depth ) {
					return null;
				}
				$offset = $declaration_end;
				continue;
			}

			if ( ! preg_match( '/\G<(\/?)((?:[a-z][a-z0-9:-]*))/i', $html, $match, 0, $start ) ) {
				$offset = $start + 1;
				continue;
			}

			$name_end = $start + strlen( $match[0] );
			if ( $name_end < $length && ! ctype_space( $html[ $name_end ] ) && ! in_array( $html[ $name_end ], array( '>', '/' ), true ) ) {
				$offset = $start + 1;
				continue;
			}

			$quote = '';
			$end   = $name_end;
			while ( $end < $length ) {
				$character = $html[ $end ];
				if ( '' !== $quote ) {
					if ( $character === $quote ) {
						$quote = '';
					}
				} elseif ( '"' === $character || "'" === $character ) {
					$quote = $character;
				} elseif ( '>' === $character ) {
					++$end;
					break;
				}
				++$end;
			}

			if ( $end > $length || $end === $length && '>' !== $html[ $end - 1 ] || '' !== $quote ) {
				return null;
			}

			$closing = '/' === $match[1];
			$name    = strtolower( $match[2] );
			$raw     = substr( $html, $start, $end - $start );
			$tokens[] = array(
				'type'    => 'tag',
				'name'    => $name,
				'closing' => $closing,
				'start'   => $start,
				'end'     => $end,
				'raw'     => $raw,
			);
			$offset = $end;

			if ( ! $closing && in_array( $name, $raw_text_tags, true ) ) {
				$close_start = self::find_raw_text_close( $html, $name, $end );
				if ( null === $close_start ) {
					return null;
				}
				$offset = $close_start;
			}
		}

		return $tokens;
	}

	/**
	 * Finds a raw-text closing tag with a real name boundary.
	 *
	 * @param string $html
	 * @param string $name
	 * @param int    $offset
	 * @return int|null
	 */
	private static function find_raw_text_close( string $html, string $name, int $offset ): ?int {
		$needle = '</' . $name;
		while ( false !== ( $candidate = stripos( $html, $needle, $offset ) ) ) {
			$boundary = $candidate + strlen( $needle );
			if ( $boundary < strlen( $html ) && ( '>' === $html[ $boundary ] || ctype_space( $html[ $boundary ] ) ) ) {
				return $candidate;
			}
			$offset = $candidate + 2;
		}
		return null;
	}

	/**
	 * Finds genuine FIFU marker comment pairs in the selected content range.
	 *
	 * @param array<int,array<string,mixed>> $tokens
	 * @param int                            $start
	 * @param int                            $end
	 * @return array<int,array{start:int,end:int}>
	 */
	private static function find_fifu_blocks( array $tokens, int $start, int $end ): array {
		$open_blocks = array();
		$blocks      = array();
		foreach ( $tokens as $token ) {
			if ( 'comment' !== $token['type'] || $token['start'] < $start || $token['end'] > $end ) {
				continue;
			}
			if ( preg_match( '/^\s*FIFU:meta:begin:([a-z]+)\s*$/i', $token['content'], $marker ) ) {
				$open_blocks[] = array(
					'name'  => strtolower( $marker[1] ),
					'start' => $token['start'],
				);
				continue;
			}
			if ( ! preg_match( '/^\s*FIFU:meta:end:([a-z]+)\s*$/i', $token['content'], $marker ) ) {
				continue;
			}

			$name = strtolower( $marker[1] );
			for ( $index = count( $open_blocks ) - 1; $index >= 0; --$index ) {
				if ( $name !== $open_blocks[ $index ]['name'] ) {
					continue;
				}
				$blocks[] = array(
					'start' => $open_blocks[ $index ]['start'],
					'end'   => $token['end'],
				);
				array_splice( $open_blocks, $index, 1 );
				break;
			}
		}
		return $blocks;
	}

	/**
	 * Checks whether a genuine meta token represents a requested image property.
	 *
	 * @param array<string,mixed> $token
	 * @param string              $kind
	 * @return bool
	 */
	private static function is_image_meta( array $token, string $kind ): bool {
		if ( 'meta' !== $token['name'] ) {
			return false;
		}
		$attributes = self::parse_attributes( $token['raw'] );
		if ( 'og' === $kind ) {
			$value = strtolower( html_entity_decode( $attributes['property'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
			return 'og:image' === $value || 0 === strpos( $value, 'og:image:' );
		}
		return 'twitter:image' === strtolower( html_entity_decode( $attributes['name'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	}

	/**
	 * Parses HTML attributes from a recognized start-tag token.
	 *
	 * @param string $tag
	 * @return array<string,string>
	 */
	private static function parse_attributes( string $tag ): array {
		$attributes = array();
		$length     = strlen( $tag );
		$offset     = 1;
		while ( $offset < $length && ! ctype_space( $tag[ $offset ] ) && ! in_array( $tag[ $offset ], array( '/', '>' ), true ) ) {
			++$offset;
		}

		while ( $offset < $length ) {
			while ( $offset < $length && ( ctype_space( $tag[ $offset ] ) || '/' === $tag[ $offset ] ) ) {
				++$offset;
			}
			if ( $offset >= $length || '>' === $tag[ $offset ] ) {
				break;
			}

			$name_start = $offset;
			while ( $offset < $length && ! ctype_space( $tag[ $offset ] ) && ! in_array( $tag[ $offset ], array( '=', '/', '>' ), true ) ) {
				++$offset;
			}
			if ( $name_start === $offset ) {
				++$offset;
				continue;
			}
			$name = strtolower( substr( $tag, $name_start, $offset - $name_start ) );
			while ( $offset < $length && ctype_space( $tag[ $offset ] ) ) {
				++$offset;
			}

			$value = '';
			if ( $offset < $length && '=' === $tag[ $offset ] ) {
				++$offset;
				while ( $offset < $length && ctype_space( $tag[ $offset ] ) ) {
					++$offset;
				}
				if ( $offset < $length && ( '"' === $tag[ $offset ] || "'" === $tag[ $offset ] ) ) {
					$quote = $tag[ $offset++ ];
					$value_start = $offset;
					while ( $offset < $length && $quote !== $tag[ $offset ] ) {
						++$offset;
					}
					$value = substr( $tag, $value_start, $offset - $value_start );
					if ( $offset < $length ) {
						++$offset;
					}
				} else {
					$value_start = $offset;
					while ( $offset < $length && ! ctype_space( $tag[ $offset ] ) && '>' !== $tag[ $offset ] ) {
						++$offset;
					}
					$value = substr( $tag, $value_start, $offset - $value_start );
				}
			}

			if ( ! array_key_exists( $name, $attributes ) ) {
				$attributes[ $name ] = $value;
			}
		}
		return $attributes;
	}

	/**
	 * Checks whether an offset lies inside one of the marker-delimited blocks.
	 *
	 * @param int                            $offset
	 * @param array<int,array{start:int,end:int}> $blocks
	 * @return bool
	 */
	private static function is_inside_block( int $offset, array $blocks ): bool {
		foreach ( $blocks as $block ) {
			if ( $offset >= $block['start'] && $offset < $block['end'] ) {
				return true;
			}
		}
		return false;
	}
}
