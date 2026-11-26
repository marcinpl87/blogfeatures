<?php
/**
 * Loaded by the Playlist must-use plugin, ../playlist.php.
 * WordPress only runs PHP files that sit directly in mu-plugins, not files in a subfolder.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'bf_playlist_register' );

function bf_playlist_register() {
	register_block_type(
		__DIR__,
		array(
			'render_callback' => 'bf_playlist_render',
		)
	);
}

/**
 * One block, one list. The items live in this page's content, so another page's block is a different list.
 *
 * @param array $attributes Block attributes.
 * @return string
 */
function bf_playlist_render( $attributes ) {
	$items = array();
	if ( isset( $attributes['items'] ) && is_array( $attributes['items'] ) ) {
		foreach ( $attributes['items'] as $item ) {
			$row = bf_playlist_item( $item );
			if ( $row !== '' ) {
				$items[] = $row;
			}
		}
	}
	if ( ! $items ) {
		return '';
	}

	$heading = '';
	if ( isset( $attributes['heading'] ) && is_scalar( $attributes['heading'] ) ) {
		$heading = trim( (string) $attributes['heading'] );
	}

	$html = '<div ' . get_block_wrapper_attributes( array( 'class' => 'bf-playlist' ) ) . '>';
	if ( $heading !== '' ) {
		$html .= '<h2 class="bf-playlist-heading">' . esc_html( $heading ) . '</h2>';
	}
	$html .= '<ol class="bf-playlist-items">' . implode( '', $items ) . '</ol></div>';
	return $html;
}

/**
 * @param mixed $item
 * @return string Empty when the row has no visible text.
 */
function bf_playlist_item( $item ) {
	if ( ! is_array( $item ) ) {
		return '';
	}

	$title    = bf_playlist_text( $item, 'title' );
	$desc     = bf_playlist_text( $item, 'desc' );
	$url      = bf_playlist_text( $item, 'url' );
	$safe_url = $url !== '' ? esc_url( $url ) : '';

	$title_html = bf_playlist_title_html( $title, $url, $safe_url );
	if ( $title_html === '' && $desc === '' ) {
		return '';
	}

	$html = '<li class="bf-playlist-item">';
	$html .= $title_html;
	if ( $desc !== '' ) {
		$html .= '<p class="bf-playlist-desc">' . nl2br( esc_html( $desc ), false ) . '</p>';
	}
	if ( $safe_url !== '' && bf_playlist_is_audio( $url ) ) {
		$html .= '<audio class="bf-playlist-audio" controls preload="none" src="' . $safe_url . '"></audio>';
	}
	$html .= '</li>';
	return $html;
}

/**
 * @param string $title
 * @param string $url     Original value, used as the link text when the title is empty.
 * @param string $safe_url esc_url() result. Empty when the address is not allowed.
 * @return string
 */
function bf_playlist_title_html( $title, $url, $safe_url ) {
	if ( $safe_url !== '' && $title !== '' ) {
		return '<a class="bf-playlist-title" href="' . $safe_url . '" target="_blank" rel="noopener noreferrer">' . esc_html( $title ) . '</a>';
	}
	if ( $safe_url !== '' ) {
		return '<a class="bf-playlist-title" href="' . $safe_url . '" target="_blank" rel="noopener noreferrer">' . esc_html( $url ) . '</a>';
	}
	if ( $title !== '' ) {
		return '<span class="bf-playlist-title">' . esc_html( $title ) . '</span>';
	}
	return '';
}

/**
 * @param array  $item
 * @param string $key
 * @return string
 */
function bf_playlist_text( $item, $key ) {
	if ( ! isset( $item[ $key ] ) || ! is_scalar( $item[ $key ] ) ) {
		return '';
	}
	return trim( str_replace( array( "\r\n", "\r" ), "\n", (string) $item[ $key ] ) );
}

/**
 * @param string $url
 * @return bool
 */
function bf_playlist_is_audio( $url ) {
	$path = (string) wp_parse_url( $url, PHP_URL_PATH );
	return (bool) preg_match( '/\.(mp3|m4a|ogg|wav|aac)$/i', $path );
}
