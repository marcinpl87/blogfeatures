<?php
/**
 * Loaded by the Timeline must-use plugin, ../timeline.php.
 * WordPress only runs PHP files that sit directly in mu-plugins, not files in a subfolder.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode( 'm_shortcode', 'timeline_render' );
add_action( 'wp_enqueue_scripts', 'timeline_enqueue_styles' );
add_action( 'wp_dashboard_setup', 'timeline_register_widget' );

function timeline_enqueue_styles() {
	wp_enqueue_style(
		'timeline',
		plugins_url( 'timeline.css', __FILE__ ),
		array(),
		'1.2.3'
	);
}
// The dashboard POST is often redirected before the widget is drawn, which drops the body.
// Save on plugins_loaded, before that redirect, then send the browser back yourself.
add_action( 'plugins_loaded', 'timeline_handle_post', 0 );

function timeline_register_widget() {
	if ( ! current_user_can( 'edit_dashboard' ) ) {
		return;
	}
	wp_add_dashboard_widget( 'm_shortcode_widget', 'Kalendarium wydarzeń', 'timeline_widget' );
}

function timeline_get_events() {
	$events = get_option( 'm_shortcode_widget', array() );
	if ( ! is_array( $events ) ) {
		return array();
	}
	return $events;
}

function timeline_render() {
	$html = '';
	foreach ( timeline_get_events() as $event ) {
		if ( ! is_scalar( $event ) ) {
			continue;
		}
		$html .= timeline_render_event( $event );
	}
	return '<div class="sh-timeline-2 sh-timeline-style2 m-custom-timeline">' . $html . '</div>';
}

/**
 * One event is plain text. First line is the title. A leading ! highlights it.
 * Lines between the first and the last are the description. The last line is the date.
 */
function timeline_render_event( $event ) {
	$event = str_replace( array( '\"', "\r\n", "\r" ), array( '"', "\n", "\n" ), (string) $event );
	$event = trim( $event );
	if ( $event === '' ) {
		return '';
	}

	$lines     = explode( "\n", $event );
	$title     = $lines[0];
	$highlight = ( isset( $title[0] ) && $title[0] === '!' ) ? ' highlight' : '';
	$title     = str_replace( '!', '', $title );
	$date      = (string) end( $lines );
	$body      = array_slice( $lines, 1, -1 );

	$content = '<div class="sh-timeline-content-container sh-table-cell sh-timeline-content-full">'
		. '<h3>' . timeline_kses( $title ) . '</h3>'
		. '<div class="sh-timeline-content"><div class="fw-page-builder-content"><p>'
		. implode( '<br />', array_map( 'timeline_kses', $body ) )
		. '</p></div></div></div>';
	$meta = '<div class="sh-timeline-box-circle"></div>'
		. '<div class="sh-timeline-box-tale"></div>'
		. '<div class="sh-timeline-date">' . timeline_kses( $date ) . '</div>';

	$left = '<div class="sh-timeline-box sh-timeline-box-left' . $highlight . '">'
		. '<div class="sh-table">' . $content . '</div>'
		. $meta
		. '</div>';
	$right = '<div class="sh-timeline-box sh-timeline-box-right' . $highlight . '">'
		. '<div class="sh-table">' . $content
		. '<div class="sh-table-cell sh-timeline-image">'
		. '<a href="" rel="lightbox" class="sh-timeline-image-container" style="background-image: url();"></a>'
		. '</div></div>'
		. $meta
		. '</div>';

	return '<div class="sh-timeline-item"><div class="sh-timeline-item-container">' . $left . $right . '</div></div>';
}

function timeline_kses( $text ) {
	return wp_kses(
		(string) $text,
		array(
			'a'      => array(
				'href'   => true,
				'target' => true,
				'rel'    => true,
				'title'  => true,
			),
			'br'     => array(),
			'strong' => array(),
			'em'     => array(),
		)
	);
}

function timeline_widget() {
	if ( ! current_user_can( 'edit_dashboard' ) ) {
		return;
	}
	$events = timeline_get_events();
	$status = isset( $_GET['timeline'] ) ? sanitize_key( wp_unslash( $_GET['timeline'] ) ) : '';
	if ( $status === 'saved' ) {
		echo '<div class="notice notice-success inline"><p>Zapisane.</p></div>';
	} elseif ( $status === 'expired' ) {
		echo '<div class="notice notice-error inline"><p>Formularz wygasł. Odśwież pulpit i zapisz ponownie.</p></div>';
	}
	?>
	<p>Każde pole to jedno wydarzenie. Pierwsza linia to tytuł (zacznij od ! aby wyróżnić), kolejne linie to opis, ostatnia linia to data. Puste pole dodaje wydarzenie w tym miejscu. W tytule można wkleić link <code>&lt;a href="..."&gt;</code>.</p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wp-clearfix">
		<?php
		if ( ! $events ) {
			echo '<textarea style="min-height: 120px; width: 100%;" name="event[]"></textarea>';
		}
		foreach ( $events as $event ) {
			if ( ! is_scalar( $event ) ) {
				continue;
			}
			$event = str_replace( '\"', '"', (string) $event );
			echo '<textarea style="min-height: 120px; width: 100%;" name="event[]">' . esc_textarea( $event ) . '</textarea>';
			echo '<textarea style="min-height: 120px; width: 100%;" name="event[]"></textarea>';
		}
		wp_nonce_field( 'timeline_save', 'timeline_nonce' );
		?>
		<input type="hidden" name="action" value="timeline_save" />
		<input type="hidden" name="timeline_events_save" value="1" />
		<input type="hidden" name="widget_id" value="m_shortcode_widget" />
		<p class="submit">
			<button type="submit" class="button button-primary">Wyślij</button>
		</p>
	</form>
	<?php
}

function timeline_handle_post() {
	if ( ! isset( $_POST['widget_id'] ) || $_POST['widget_id'] !== 'm_shortcode_widget' || ! isset( $_POST['event'] ) ) {
		return;
	}
	$is_new_form = ! empty( $_POST['timeline_events_save'] );
	$is_old_form = isset( $_POST['submit'] );
	if ( ! $is_new_form && ! $is_old_form ) {
		return;
	}
	if ( ! current_user_can( 'edit_dashboard' ) ) {
		return;
	}

	$nonce_ok = false;
	if ( $is_new_form && isset( $_POST['timeline_nonce'] ) ) {
		$nonce_ok = (bool) wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['timeline_nonce'] ) ), 'timeline_save' );
	}
	if ( ! $nonce_ok && $is_old_form && isset( $_POST['dashboard-widget-nonce'] ) ) {
		$nonce_ok = (bool) wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dashboard-widget-nonce'] ) ), 'edit-dashboard-widget_m_shortcode_widget' );
	}
	if ( ! $nonce_ok ) {
		wp_safe_redirect( admin_url( 'index.php?timeline=expired' ) );
		exit;
	}

	$raw    = ( isset( $_POST['event'] ) && is_array( $_POST['event'] ) ) ? wp_unslash( $_POST['event'] ) : array();
	$events = array();
	foreach ( $raw as $event ) {
		if ( ! is_scalar( $event ) ) {
			continue;
		}
		$event = str_replace( array( "\r\n", "\r" ), "\n", (string) $event );
		$event = trim( $event );
		if ( $event !== '' ) {
			$events[] = $event;
		}
	}
	update_option( 'm_shortcode_widget', $events );
	wp_safe_redirect( admin_url( 'index.php?timeline=saved' ) );
	exit;
}
