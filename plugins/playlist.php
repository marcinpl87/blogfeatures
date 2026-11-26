<?php
/**
 * Plugin Name: Playlist
 * Description: Per-page list. In the block editor add the Playlista block and fill in title, desc, and URL. Each page keeps its own list.
 * Version: 1.0.1
 * Author: Marcin
 * Requires at least: 6.1
 * Requires PHP: 7.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require __DIR__ . '/playlist/playlist.php';
