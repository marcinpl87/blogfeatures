<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'dependencies' => array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components' ),
	'version'      => (string) filemtime( __DIR__ . '/editor.js' ),
);
