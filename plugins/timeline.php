<?php
/**
 * Plugin Name: Timeline
 * Description: Event timeline. Shortcode [m_shortcode]. Edit events on the dashboard under Kalendarium wydarzeń. Events stay in the m_shortcode_widget option.
 * Version: 1.2.3
 * Author: Marcin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require __DIR__ . '/timeline/timeline.php';
