<?php
/**
 * Plugin Name: Sitemap
 * Description: Graph of pages, menus, and internal links. Tools → Sitemap, administrators only. Loads nothing from the internet.
 * Version: 1.0.0
 * Author: Marcin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'sitemap_register_page' );

function sitemap_register_page() {
	add_management_page(
		'Sitemap',
		'Sitemap',
		'manage_options',
		'sitemap',
		'sitemap_render_page'
	);
}

/* ============================================================================
 * PHP — collect pages, menus, the footer, and internal links from the WordPress database
 * ============================================================================ */

function sitemap_norm( $path ) {
	$path = rawurldecode( (string) $path );
	$path = strtolower( trim( $path, '/' ) );
	return $path;
}

function sitemap_is_file_path( $path ) {
	return (bool) preg_match(
		'~^(wp-content|wp-includes|wp-admin|mp3|files|feed|wp-json)(/|$)|\\.(pdf|mp3|mp4|jpe?g|png|gif|webp|zip|docx?|xlsx?|pptx?|php)$~i',
		$path
	);
}

function sitemap_hosts() {
	$hosts = array();
	foreach ( array( home_url(), site_url() ) as $url ) {
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		if ( $host === '' ) {
			continue;
		}
		$bare            = preg_replace( '/^www\./', '', $host );
		$hosts[ $host ]  = true;
		$hosts[ $bare ]  = true;
		$hosts[ 'www.' . $bare ] = true;
	}
	return $hosts;
}

function sitemap_resolve( $href, $hosts, $front, $pub, $by_path, $by_slug ) {
	$href = trim( html_entity_decode( (string) $href, ENT_QUOTES, 'UTF-8' ) );
	if ( $href === '' || preg_match( '~^(mailto:|tel:|#|javascript:)~i', $href ) ) {
		return null;
	}
	if ( strpos( $href, '//' ) === 0 ) {
		$href = 'https:' . $href;
	}
	$parts = wp_parse_url( $href );
	$host  = isset( $parts['host'] ) ? strtolower( $parts['host'] ) : '';
	if ( $host !== '' && empty( $hosts[ $host ] ) ) {
		return null;
	}
	if ( $host === '' && isset( $href[0] ) && $href[0] !== '/' && $href[0] !== '?' ) {
		return null;
	}
	$query = array();
	if ( ! empty( $parts['query'] ) ) {
		parse_str( $parts['query'], $query );
	}
	foreach ( array( 'page_id', 'p' ) as $key ) {
		if ( empty( $query[ $key ] ) ) {
			continue;
		}
		$id = (string) absint( $query[ $key ] );
		return isset( $pub[ $id ] ) ? array( 'page', $id ) : array( 'broken', '?' . $key . '=' . $id );
	}
	$path = sitemap_norm( isset( $parts['path'] ) ? $parts['path'] : '' );
	if ( $path === '' ) {
		return $front !== '' ? array( 'page', $front ) : null;
	}
	if ( sitemap_is_file_path( $path ) || preg_match( '~^(category|tag|author)/~', $path ) ) {
		return array( 'file', $path );
	}
	if ( isset( $by_path[ $path ] ) ) {
		return array( 'page', $by_path[ $path ] );
	}
	$bits = explode( '/', $path );
	$last = (string) end( $bits );
	if ( isset( $by_slug[ $last ] ) && count( $by_slug[ $last ] ) === 1 ) {
		return array( 'page', $by_slug[ $last ][0] );
	}
	return array( 'broken', $path );
}

function sitemap_menu_role( $name, $locations, $term_id ) {
	// Menu names and theme locations may be Polish on this site (stopka, boczne, glowne).
	$where = array();
	foreach ( $locations as $location => $id ) {
		if ( (int) $id === (int) $term_id ) {
			$where[] = $location;
		}
	}
	$blob = remove_accents( strtolower( $name . ' ' . implode( ' ', $where ) ) );
	if ( preg_match( '/footer|stopka|boczn|side|spolecz|social/', $blob ) ) {
		return 'side';
	}
	if ( preg_match( '/primary|header|main|glow|menu\s*1|menu-1/', $blob ) ) {
		return 'main';
	}
	return '';
}

function sitemap_tree( $items ) {
	$ids = array();
	foreach ( $items as $item ) {
		$ids[ $item['id'] ] = true;
	}
	$by_parent = array();
	foreach ( $items as $item ) {
		$parent = isset( $ids[ $item['parent'] ] ) ? $item['parent'] : '0';
		$by_parent[ $parent ][] = $item;
	}
	$walk = function ( $parent ) use ( &$walk, $by_parent ) {
		$out = array();
		foreach ( $by_parent[ $parent ] ?? array() as $item ) {
			$out[] = array(
				'label'    => $item['label'],
				'target'   => $item['target'],
				'url'      => $item['url'],
				'children' => $walk( $item['id'] ),
			);
		}
		return $out;
	};
	return $walk( '0' );
}

function sitemap_items_from_menu( $menu, $pub, $hosts, $front, $by_path, $by_slug ) {
	$raw = wp_get_nav_menu_items( $menu->term_id );
	if ( ! is_array( $raw ) ) {
		return array();
	}
	usort(
		$raw,
		function ( $a, $b ) {
			return (int) $a->menu_order <=> (int) $b->menu_order;
		}
	);
	$items = array();
	foreach ( $raw as $item ) {
		$target = null;
		$url    = (string) $item->url;
		$label  = wp_strip_all_tags( (string) $item->title );
		if ( $item->type === 'post_type' && isset( $pub[ (string) $item->object_id ] ) ) {
			$target = (string) $item->object_id;
			if ( $label === '' ) {
				$label = $pub[ $target ]['title'];
			}
		} else {
			$resolved = sitemap_resolve( $url, $hosts, $front, $pub, $by_path, $by_slug );
			if ( $resolved && $resolved[0] === 'page' ) {
				$target = $resolved[1];
			}
		}
		if ( $label === '' ) {
			$label = $url !== '' ? $url : '(untitled)';
		}
		$items[] = array(
			'id'     => 'm' . (int) $item->ID,
			'parent' => (int) $item->menu_item_parent > 0 ? 'm' . (int) $item->menu_item_parent : '0',
			'label'  => $label,
			'target' => $target,
			'url'    => $url,
		);
	}
	return $items;
}

function sitemap_menu_item_key( $item ) {
	if ( ! empty( $item['target'] ) ) {
		return 'p:' . $item['target'];
	}
	$url = strtolower( trim( (string) $item['url'] ) );
	if ( $url !== '' ) {
		return 'u:' . $url;
	}
	return 'id:' . $item['id'];
}

function sitemap_merge_menu_items( $base, $extra ) {
	$kept  = array();
	$remap = array();
	foreach ( $base as $item ) {
		$kept[ sitemap_menu_item_key( $item ) ] = $item['id'];
	}
	foreach ( $extra as $item ) {
		$key = sitemap_menu_item_key( $item );
		if ( isset( $kept[ $key ] ) ) {
			$remap[ $item['id'] ] = $kept[ $key ];
			continue;
		}
		$kept[ $key ] = $item['id'];
		$base[]       = $item;
	}
	if ( $remap ) {
		foreach ( $base as &$item ) {
			if ( isset( $remap[ $item['parent'] ] ) ) {
				$item['parent'] = $remap[ $item['parent'] ];
			}
		}
		unset( $item );
	}
	return $base;
}

function sitemap_items_from_navigation_post( $post, $pub, $hosts, $front, $by_path, $by_slug ) {
	$items  = array();
	$seq    = 0;
	$prefix = 'b' . (int) $post->ID . '-';
	$walk   = function ( $blocks, $parent ) use ( &$walk, &$items, &$seq, $prefix, $pub, $hosts, $front, $by_path, $by_slug ) {
		foreach ( $blocks as $block ) {
			$name = isset( $block['blockName'] ) ? $block['blockName'] : '';
			if ( $name === 'core/navigation-link' || $name === 'core/navigation-submenu' ) {
				$seq++;
				$id     = $prefix . $seq;
				$attrs  = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
				$label  = isset( $attrs['label'] ) ? wp_strip_all_tags( (string) $attrs['label'] ) : '';
				$url    = isset( $attrs['url'] ) ? (string) $attrs['url'] : '';
				$kind   = isset( $attrs['kind'] ) ? (string) $attrs['kind'] : '';
				$type   = isset( $attrs['type'] ) ? (string) $attrs['type'] : '';
				$target = null;
				if ( isset( $attrs['id'] ) && ( $kind === 'post-type' || $type === 'page' || $type === 'post' ) && isset( $pub[ (string) $attrs['id'] ] ) ) {
					$target = (string) $attrs['id'];
				} else {
					$resolved = sitemap_resolve( $url, $hosts, $front, $pub, $by_path, $by_slug );
					if ( $resolved && $resolved[0] === 'page' ) {
						$target = $resolved[1];
					}
				}
				$items[] = array(
					'id'     => $id,
					'parent' => $parent,
					'label'  => $label !== '' ? $label : ( $url !== '' ? $url : '(untitled)' ),
					'target' => $target,
					'url'    => $url,
				);
				if ( ! empty( $block['innerBlocks'] ) ) {
					$walk( $block['innerBlocks'], $id );
				}
			} elseif ( ! empty( $block['innerBlocks'] ) ) {
				$walk( $block['innerBlocks'], $parent );
			}
		}
	};
	$walk( parse_blocks( (string) $post->post_content ), '0' );
	return $items;
}

function sitemap_footer_ids( $pub ) {
	$ids      = array();
	$sidebars = wp_get_sidebars_widgets();
	if ( ! is_array( $sidebars ) ) {
		return array();
	}
	foreach ( $sidebars as $name => $widgets ) {
		if ( $name === 'wp_inactive_widgets' || ! preg_match( '/footer|stopka/', (string) $name ) || ! is_array( $widgets ) ) {
			continue;
		}
		foreach ( $widgets as $widget_id ) {
			if ( ! preg_match( '/^(.+)-(\d+)$/', (string) $widget_id, $match ) ) {
				continue;
			}
			$option = get_option( 'widget_' . $match[1] );
			if ( ! is_array( $option ) || empty( $option[ $match[2] ] ) || ! is_array( $option[ $match[2] ] ) ) {
				continue;
			}
			$instance = $option[ $match[2] ];
			if ( ! empty( $instance['exinclude_values'] ) ) {
				foreach ( preg_split( '/\s*,\s*/', (string) $instance['exinclude_values'] ) as $id ) {
					$id = (string) absint( $id );
					if ( isset( $pub[ $id ] ) ) {
						$ids[ $id ] = true;
					}
				}
			}
		}
	}
	$with_children = $ids;
	foreach ( array_keys( $ids ) as $id ) {
		foreach ( $pub as $child_id => $page ) {
			if ( $page['parent'] === $id ) {
				$with_children[ $child_id ] = true;
			}
		}
	}
	return array_keys( $with_children );
}

function sitemap_orphan_group( $node ) {
	// Group labels are English. The matches below look at this site's Polish titles and slugs.
	$title = remove_accents( strtolower( $node['title'] ) );
	$path  = $node['path'];
	if ( $title === 'test' || preg_match( '/^(test|strona-glowna)(-|$)/', $path ) || strpos( $path, '-old' ) !== false ) {
		return 'Unlinked: old versions and tests';
	}
	if ( strpos( $title, 'studium krystalizujace' ) === 0 || strpos( $path, 'szkolenie' ) !== false || strpos( $title, 'przeglad centralnego' ) === 0 || strpos( $title, 'trudzic sie' ) === 0 ) {
		return 'Unlinked: trainings (crystallization studies)';
	}
	if ( preg_match( '/konferencj|zaproszenie|spotkania|uroczysto|dzieci i ml|materialy na/', $title ) ) {
		return 'Unlinked: conferences and invitations';
	}
	return 'Unlinked: publication excerpts and articles';
}

function sitemap_collect() {
	$posts = get_posts(
		array(
			'post_type'   => array( 'page', 'post' ),
			'post_status' => 'publish',
			'numberposts' => -1,
			'orderby'     => 'ID',
			'order'       => 'ASC',
		)
	);
	$pub = array();
	foreach ( $posts as $post ) {
		$id         = (string) $post->ID;
		$pub[ $id ] = array(
			'id'       => $id,
			'title'    => wp_strip_all_tags( $post->post_title ) !== '' ? wp_strip_all_tags( $post->post_title ) : '(no title)',
			'type'     => $post->post_type,
			'slug'     => strtolower( $post->post_name ),
			'parent'   => (string) (int) $post->post_parent,
			'content'  => (string) $post->post_content,
			'modified' => substr( (string) $post->post_modified, 0, 10 ),
			'url'      => get_permalink( $post ),
		);
	}
	$path_of = function ( $id, $seen = array() ) use ( &$path_of, $pub ) {
		$page = $pub[ $id ];
		if ( $page['type'] === 'post' ) {
			return $page['slug'];
		}
		if ( $page['parent'] !== '0' && isset( $pub[ $page['parent'] ] ) && empty( $seen[ $page['parent'] ] ) ) {
			$seen[ $id ] = true;
			return $path_of( $page['parent'], $seen ) . '/' . $page['slug'];
		}
		return $page['slug'];
	};
	$by_path = array();
	$by_slug = array();
	foreach ( $pub as $id => $page ) {
		$pub[ $id ]['path']         = $path_of( $id );
		$by_path[ $pub[ $id ]['path'] ] = $id;
		$by_slug[ $page['slug'] ][] = $id;
	}
	$old_rows = $GLOBALS['wpdb']->get_results(
		"SELECT post_id, meta_value FROM {$GLOBALS['wpdb']->postmeta} WHERE meta_key = '_wp_old_slug'",
		ARRAY_A
	);
	if ( is_array( $old_rows ) ) {
		foreach ( $old_rows as $row ) {
			$id   = (string) $row['post_id'];
			$slug = sitemap_norm( $row['meta_value'] );
			if ( isset( $pub[ $id ] ) && $slug !== '' && empty( $by_slug[ $slug ] ) ) {
				$by_slug[ $slug ] = array( $id );
			} elseif ( isset( $pub[ $id ] ) && $slug !== '' && ! in_array( $id, $by_slug[ $slug ], true ) ) {
				$by_slug[ $slug ][] = $id;
			}
		}
	}

	$front = (string) absint( get_option( 'page_on_front' ) );
	$blog  = (string) absint( get_option( 'page_for_posts' ) );
	if ( ! isset( $pub[ $front ] ) ) {
		$front = '';
	}
	if ( ! isset( $pub[ $blog ] ) ) {
		$blog = '';
	}
	$hosts  = sitemap_hosts();
	$out    = array();
	$broken = array();
	foreach ( $pub as $id => $page ) {
		$out[ $id ] = array();
		if ( ! preg_match_all( '/href\s*=\s*(["\'])([^"\']+)\1/i', $page['content'], $matches ) ) {
			continue;
		}
		foreach ( array_unique( $matches[2] ) as $href ) {
			$resolved = sitemap_resolve( $href, $hosts, $front, $pub, $by_path, $by_slug );
			if ( ! $resolved ) {
				continue;
			}
			if ( $resolved[0] === 'page' && $resolved[1] !== $id && isset( $pub[ $resolved[1] ] ) ) {
				$out[ $id ][ $resolved[1] ] = true;
			} elseif ( $resolved[0] === 'broken' ) {
				$broken[ $id ][ $resolved[1] ] = true;
			}
		}
	}
	if ( $blog !== '' ) {
		foreach ( $pub as $id => $page ) {
			if ( $page['type'] === 'post' && $id !== $blog ) {
				$out[ $blog ][ $id ] = true;
			}
		}
	}

	$locations = get_nav_menu_locations();
	if ( ! is_array( $locations ) ) {
		$locations = array();
	}
	$main_items = array();
	$side_items = array();
	$classified = array();
	foreach ( wp_get_nav_menus() as $menu ) {
		$role = sitemap_menu_role( $menu->name, $locations, $menu->term_id );
		$classified[] = array( $role, $menu );
	}
	$has_main = false;
	foreach ( $classified as $row ) {
		if ( $row[0] === 'main' ) {
			$has_main = true;
		}
	}
	if ( ! $has_main && $classified ) {
		usort(
			$classified,
			function ( $a, $b ) {
				return count( wp_get_nav_menu_items( $b[1]->term_id ) ?: array() ) <=> count( wp_get_nav_menu_items( $a[1]->term_id ) ?: array() );
			}
		);
		if ( $classified[0][0] === '' ) {
			$classified[0][0] = 'main';
		}
	}
	foreach ( $classified as $row ) {
		$items = sitemap_items_from_menu( $row[1], $pub, $hosts, $front, $by_path, $by_slug );
		if ( $row[0] === 'side' ) {
			$side_items = array_merge( $side_items, $items );
		} else {
			$main_items = array_merge( $main_items, $items );
		}
	}
	// Site Editor navigations (Appearance → Editor → Navigation), e.g. "TOP MAIN MENU".
	// A classic menu of any size used to hide these, so the graph kept only that menu.
	$nav_posts = get_posts(
		array(
			'post_type'   => 'wp_navigation',
			'post_status' => array( 'publish', 'draft' ),
			'numberposts' => -1,
			'orderby'     => 'ID',
			'order'       => 'ASC',
		)
	);
	if ( is_array( $nav_posts ) ) {
		usort(
			$nav_posts,
			function ( $a, $b ) {
				$rank = array( 'publish' => 0, 'draft' => 1 );
				return ( $rank[ $a->post_status ] ?? 2 ) <=> ( $rank[ $b->post_status ] ?? 2 );
			}
		);
		$nav_main  = array();
		$nav_other = array();
		foreach ( $nav_posts as $nav_post ) {
			$items = sitemap_items_from_navigation_post( $nav_post, $pub, $hosts, $front, $by_path, $by_slug );
			if ( ! $items ) {
				continue;
			}
			$role = sitemap_menu_role( $nav_post->post_title, array(), 0 );
			if ( $role === 'side' ) {
				$side_items = array_merge( $side_items, $items );
			} elseif ( $role === 'main' ) {
				$nav_main = array_merge( $nav_main, $items );
			} else {
				$nav_other[] = $items;
			}
		}
		$main_items = sitemap_merge_menu_items( $main_items, $nav_main );
		if ( ! $main_items && $nav_other ) {
			usort(
				$nav_other,
				function ( $a, $b ) {
					return count( $b ) <=> count( $a );
				}
			);
			$main_items = $nav_other[0];
		}
	}
	$main_tree = sitemap_tree( $main_items );
	$side_tree = sitemap_tree( $side_items );

	$main_targets = array();
	foreach ( $main_items as $item ) {
		if ( $item['target'] ) {
			$main_targets[ $item['target'] ] = true;
		}
	}
	$footer_ids = array();
	foreach ( sitemap_footer_ids( $pub ) as $id ) {
		$footer_ids[ $id ] = true;
	}
	foreach ( $side_items as $item ) {
		if ( $item['target'] ) {
			$footer_ids[ $item['target'] ] = true;
		}
	}

	$section    = array();
	$top_label  = array();
	$externals  = array();
	$edges_menu = array();
	$menu_seen  = array();
	// The header menu is rendered on every page. Drawing each of its links from
	// every page would cover the graph with arrows, so the whole menu is shown
	// once, as links from the front page. Nested items still keep a parent edge.
	$add_menu_edge = function ( $from, $to ) use ( &$edges_menu, &$menu_seen ) {
		if ( $from === '' || $to === '' || $from === $to ) {
			return;
		}
		$key = $from . "\0" . $to;
		if ( isset( $menu_seen[ $key ] ) ) {
			return;
		}
		$menu_seen[ $key ] = true;
		$edges_menu[]      = array( $from, $to );
	};
	if ( $front !== '' ) {
		$section[ $front ] = 'Home';
	}
	$walk = function ( $nodes, $sec, $parent_target ) use ( &$walk, &$section, $add_menu_edge, $front ) {
		foreach ( $nodes as $item ) {
			$target = $item['target'];
			if ( $target ) {
				if ( ! isset( $section[ $target ] ) ) {
					$section[ $target ] = $sec;
				}
				if ( $front !== '' ) {
					$add_menu_edge( $front, $target );
				}
				if ( $parent_target && $parent_target !== $target && $parent_target !== $front ) {
					$add_menu_edge( $parent_target, $target );
				}
			}
			$walk( $item['children'], $sec, $target ? $target : $parent_target );
		}
	};
	foreach ( $main_tree as $top ) {
		if ( $top['target'] ) {
			if ( $front !== '' ) {
				$add_menu_edge( $front, $top['target'] );
			}
			$section[ $top['target'] ]   = 'Main menu';
			$top_label[ $top['target'] ] = $top['label'];
		} elseif ( $top['url'] ) {
			$eid = 'ext-' . count( $externals );
			if ( $front !== '' ) {
				$add_menu_edge( $front, $eid );
			}
			$externals[] = array(
				'id'       => $eid,
				'title'    => $top['label'],
				'type'     => 'external',
				'path'     => '',
				'url'      => $top['url'],
				'status'   => 'menu',
				'section'  => 'Main menu',
				'in'       => $front !== '' ? array( $front ) : array(),
				'out'      => array(),
				'broken'   => array(),
				'noSearch' => false,
				'modified' => '',
			);
		}
		$walk( $top['children'], $top['label'], $top['target'] );
	}
	foreach ( array_keys( $footer_ids ) as $id ) {
		if ( ! isset( $section[ $id ] ) ) {
			$section[ $id ] = 'Footer';
		}
	}

	$queue = array_keys( $section );
	while ( $queue ) {
		$from = array_shift( $queue );
		foreach ( array_keys( $out[ $from ] ?? array() ) as $to ) {
			if ( isset( $section[ $to ] ) ) {
				continue;
			}
			$src = ( isset( $section[ $from ] ) && $section[ $from ] === 'Main menu' && isset( $top_label[ $from ] ) ) ? $top_label[ $from ] : $section[ $from ];
			$section[ $to ] = ( $src === 'Home' ) ? 'Home' : $src;
			$queue[]        = $to;
		}
	}

	$inbound = array();
	foreach ( $out as $from => $tos ) {
		foreach ( array_keys( $tos ) as $to ) {
			$inbound[ $to ][ $from ] = true;
		}
	}
	$entry = $section;
	$reach = $entry;
	$queue = array_keys( $entry );
	while ( $queue ) {
		$from = array_shift( $queue );
		foreach ( array_keys( $out[ $from ] ?? array() ) as $to ) {
			if ( empty( $reach[ $to ] ) ) {
				$reach[ $to ] = true;
				$queue[]      = $to;
			}
		}
	}

	$nodes = array();
	foreach ( $pub as $id => $page ) {
		if ( $id === $front ) {
			$status = 'front';
		} elseif ( ! empty( $main_targets[ $id ] ) ) {
			$status = 'menu';
		} elseif ( ! empty( $footer_ids[ $id ] ) ) {
			$status = 'footer';
		} elseif ( ! empty( $reach[ $id ] ) ) {
			$status = 'linked';
		} elseif ( ! empty( $inbound[ $id ] ) ) {
			$status = 'unreachable';
		} else {
			$status = 'orphan';
		}
		$node = array(
			'id'       => $id,
			'title'    => $page['title'],
			'type'     => $page['type'],
			'path'     => $page['path'],
			'url'      => $page['url'] ? $page['url'] : home_url( '/' . $page['path'] . '/' ),
			'status'   => $status,
			'in'       => array_keys( $inbound[ $id ] ?? array() ),
			'out'      => array_keys( $out[ $id ] ?? array() ),
			'broken'   => array_keys( $broken[ $id ] ?? array() ),
			'noSearch' => false,
			'modified' => $page['modified'],
		);
		if ( ! isset( $section[ $id ] ) ) {
			$section[ $id ] = sitemap_orphan_group( $node );
		}
		$node['section'] = $section[ $id ];
		$nodes[]         = $node;
	}
	$excluded = get_option( 'sep_exclude' );
	if ( is_array( $excluded ) ) {
		$excluded = array_fill_keys( array_map( 'strval', $excluded ), true );
		foreach ( $nodes as &$node ) {
			if ( ! empty( $excluded[ $node['id'] ] ) ) {
				$node['noSearch'] = true;
			}
		}
		unset( $node );
	}
	$nodes = array_merge( $nodes, $externals );

	$top_labels = array();
	foreach ( $main_tree as $top ) {
		$top_labels[] = $top['label'];
	}
	$preferred = array_merge(
		array( 'Home', 'Main menu', 'Footer' ),
		$top_labels,
		array(
			'Unlinked: trainings (crystallization studies)',
			'Unlinked: conferences and invitations',
			'Unlinked: old versions and tests',
			'Unlinked: publication excerpts and articles',
		)
	);
	$present  = array();
	foreach ( $nodes as $node ) {
		$present[ $node['section'] ] = true;
	}
	$sections = array();
	foreach ( $preferred as $name ) {
		if ( ! empty( $present[ $name ] ) && ! in_array( $name, $sections, true ) ) {
			$sections[] = $name;
		}
	}
	$rest = array_keys( $present );
	sort( $rest, SORT_NATURAL | SORT_FLAG_CASE );
	foreach ( $rest as $name ) {
		if ( ! in_array( $name, $sections, true ) ) {
			$sections[] = $name;
		}
	}

	$edges_foot = array();
	$edges_hier = array();
	$edges_link = array();
	foreach ( $nodes as $node ) {
		if ( $node['type'] === 'external' ) {
			continue;
		}
		if ( $front !== '' && $node['section'] === 'Footer' && $node['id'] !== $front ) {
			$edges_foot[] = array( $front, $node['id'] );
		}
		if ( $node['path'] !== '' && strpos( $node['path'], '/' ) !== false ) {
			$parent_path = substr( $node['path'], 0, (int) strrpos( $node['path'], '/' ) );
			if ( isset( $by_path[ $parent_path ] ) ) {
				$edges_hier[] = array( $by_path[ $parent_path ], $node['id'] );
			}
		}
		foreach ( $node['out'] as $to ) {
			$edges_link[] = array( $node['id'], $to );
		}
	}

	return array(
		'front'     => $front,
		'sections'  => $sections,
		'nodes'     => $nodes,
		'menuTree'  => $main_tree,
		'sideTree'  => $side_tree,
		'edges'     => array(
			'menu' => $edges_menu,
			'foot' => $edges_foot,
			'hier' => $edges_hier,
			'link' => $edges_link,
		),
	);
}

function sitemap_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to do that.' ) );
	}
	$data = sitemap_collect();
	$json = wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE );
	?>
	<div class="wrap sitemap-app">
		<h1>Sitemap</h1>
		<p class="sitemap-lead">Published pages and posts, menus, and internal links. Nothing is loaded from the internet. Click a dot to see where the links go.</p>

		<!-- ====================================================================
		     HTML — admin page markup
		     ==================================================================== -->
		<div class="sitemap-stats" id="sitemap-stats"></div>
		<div class="sitemap-tools">
			<input type="search" id="sitemap-q" placeholder="Search by title or path…">
			<label><input type="checkbox" id="sitemap-t-link" checked> links in content</label>
			<label><input type="checkbox" id="sitemap-t-menu" checked> menu</label>
			<label><input type="checkbox" id="sitemap-t-hier"> parent page</label>
			<label><input type="checkbox" id="sitemap-t-lbl"> all labels</label>
			<button type="button" class="button" id="sitemap-fit">Center</button>
		</div>
		<div class="sitemap-graph" id="sitemap-graph">
			<svg id="sitemap-svg"><g id="sitemap-vp"><g id="sitemap-g-sec"></g><g id="sitemap-g-e"></g><g id="sitemap-g-n"></g><g id="sitemap-g-l"></g></g></svg>
			<button type="button" class="sitemap-fs" id="sitemap-fs" title="Full screen" aria-label="Full screen">
				<svg viewBox="0 0 20 20" width="12" height="12" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="1.6" d="M3 7V3h4M17 7V3h-4M3 13v4h4M17 13v4h-4"/></svg>
			</button>
			<div class="sitemap-tip" id="sitemap-tip"></div>
			<div class="sitemap-panel" id="sitemap-panel"></div>
		</div>
		<div class="sitemap-legend" id="sitemap-legend"></div>
		<details class="sitemap-box"><summary>Main menu, side menu, and footer</summary><div class="sitemap-box-in"><ul class="sitemap-tree" id="sitemap-menu"></ul><ul class="sitemap-tree" id="sitemap-side"></ul></div></details>
		<details class="sitemap-box"><summary id="sitemap-orph-title">Pages nothing links to</summary><div class="sitemap-box-in" id="sitemap-orphans"></div></details>
		<details class="sitemap-box"><summary id="sitemap-broken-title">Broken internal links</summary><div class="sitemap-box-in" id="sitemap-broken"></div></details>
	</div>

	<!-- ======================================================================
	     CSS — light styling to match wp-admin
	     ====================================================================== -->
	<style>
		.sitemap-app { --c-front:#1d2327; --c-menu:#2271b1; --c-footer:#007017; --c-linked:#b26200; --c-unreach:#7a3e9d; --c-orphan:#d63638; --fg:#1d2327; --mut:#646970; --bd:#c3c4c7; --card:#fff; color-scheme:light; color:var(--fg); }
		.sitemap-app h1 { margin-bottom:8px; }
		.sitemap-lead { color:var(--mut); max-width:70rem; margin-top:0; }
		.sitemap-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:8px; margin:12px 0; }
		.sitemap-stat { background:var(--card); border:1px solid var(--bd); border-radius:4px; padding:10px 12px; cursor:pointer; }
		.sitemap-stat.off { opacity:.35; }
		.sitemap-stat b { display:block; font-size:22px; line-height:1.1; color:var(--fg); }
		.sitemap-stat span { display:flex; gap:6px; align-items:center; color:var(--mut); font-size:12px; }
		.sitemap-dot { width:10px; height:10px; border-radius:50%; display:inline-block; flex:none; }
		.sitemap-tools { display:flex; flex-wrap:wrap; gap:8px 14px; align-items:center; margin-bottom:10px; }
		.sitemap-tools input[type=search] { flex:1 1 220px; min-width:180px; background:#fff; color:#1d2327; border:1px solid #8c8f94; border-radius:4px; padding:6px 8px; }
		.sitemap-tools label { display:flex; gap:5px; align-items:center; color:var(--mut); }
		.sitemap-graph { position:relative; height:640px; background:#fff; border:1px solid var(--bd); border-radius:4px; overflow:hidden; }
		.sitemap-graph:fullscreen { width:100%; height:100%; border:0; border-radius:0; background:#fff; }
		.sitemap-graph > svg { width:100%; height:100%; display:block; cursor:grab; touch-action:none; background:#fff; }
		.sitemap-graph > svg.drag { cursor:grabbing; }
		.sitemap-app button.sitemap-fs { position:absolute; z-index:4; top:6px; left:6px; display:flex; align-items:center; justify-content:center; width:auto; height:auto; min-width:0; min-height:0; margin:0; padding:6px; border:0; border-radius:4px; background:transparent; color:#646970; cursor:pointer; box-shadow:none; line-height:0; }
		.sitemap-app button.sitemap-fs:hover { background:rgba(0,0,0,.06); color:#1d2327; }
		.sitemap-app button.sitemap-fs svg, .sitemap-panel .sitemap-fold svg { width:12px; height:12px; display:block; pointer-events:none; }
		.sitemap-sec { font-size:14px; font-weight:600; fill:#50575e; pointer-events:none; }
		.sitemap-e-link { stroke:#8c8f94; stroke-opacity:.45; stroke-width:.8; }
		.sitemap-e-menu { stroke:var(--c-menu); stroke-opacity:.75; stroke-width:2; }
		.sitemap-e-foot { stroke:var(--c-footer); stroke-opacity:.55; stroke-width:1.3; }
		.sitemap-e-hier { stroke:#8c8f94; stroke-opacity:.55; stroke-width:1; stroke-dasharray:3 3; }
		.sitemap-n { stroke:#fff; stroke-width:1.5; cursor:pointer; }
		.sitemap-lbl { font-size:11px; fill:#1d2327; pointer-events:none; paint-order:stroke; stroke:#fff; stroke-width:3px; }
		.sitemap-dim { opacity:.1; }
		.sitemap-hl { stroke-opacity:1 !important; stroke-width:2.4 !important; }
		.sitemap-hl-in { stroke:var(--c-footer) !important; }
		.sitemap-hl-out { stroke:var(--c-menu) !important; }
		.sitemap-sel { stroke:#1d2327 !important; stroke-width:3 !important; }
		.sitemap-match { stroke:#1d2327 !important; stroke-width:2.5 !important; }
		.sitemap-tip, .sitemap-panel { position:absolute; background:#fff; color:#1d2327; border:1px solid var(--bd); border-radius:4px; box-shadow:0 4px 16px rgba(0,0,0,.12); }
		.sitemap-tip { display:none; z-index:3; padding:6px 8px; font-size:12px; max-width:280px; pointer-events:none; }
		.sitemap-panel { display:none; z-index:2; top:10px; right:10px; width:min(320px,calc(100% - 20px)); max-height:calc(100% - 20px); overflow:auto; padding:8px 10px 12px; font-size:13px; }
		.sitemap-panel-bar { display:flex; align-items:flex-start; gap:2px; }
		.sitemap-panel-bar h3 { flex:1; min-width:0; margin:3px 0 4px; font-size:14px; }
		.sitemap-panel .sitemap-x, .sitemap-panel .sitemap-fold { flex:none; width:18px; height:18px; border:0; background:none; padding:0; cursor:pointer; color:#646970; display:inline-flex; align-items:center; justify-content:center; }
		.sitemap-panel .sitemap-fold { transform:translateY(2px); }
		.sitemap-panel.is-collapsed .sitemap-fold { transform:translate(3px, 0); }
		.sitemap-panel .sitemap-x { font-size:16px; line-height:1; }
		.sitemap-panel .sitemap-x:hover, .sitemap-panel .sitemap-fold:hover { color:#1d2327; }
		.sitemap-panel.is-collapsed { width:auto; max-width:min(360px,calc(100% - 20px)); padding:6px 8px; overflow:hidden; }
		.sitemap-panel.is-collapsed .sitemap-panel-body { display:none; }
		.sitemap-panel.is-collapsed .sitemap-panel-bar h3 { margin:3px 0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:280px; }
		.sitemap-panel a { color:#2271b1; word-break:break-all; }
		.sitemap-panel ul { margin:4px 0 8px; padding-left:16px; }
		.sitemap-panel li { margin:2px 0; cursor:pointer; }
		.sitemap-badge { display:inline-flex; gap:5px; align-items:center; border:1px solid var(--bd); border-radius:99px; padding:2px 7px; font-size:11px; margin:4px 4px 0 0; }
		.sitemap-legend { display:flex; flex-wrap:wrap; gap:6px 16px; color:var(--mut); font-size:12px; margin:8px 0 12px; }
		.sitemap-legend i { display:inline-block; width:22px; height:0; border-top:2px solid; vertical-align:middle; margin-right:5px; }
		.sitemap-box { background:#fff; border:1px solid var(--bd); border-radius:4px; padding:10px 14px; margin:0 0 8px; }
		.sitemap-box summary { cursor:pointer; font-weight:600; }
		.sitemap-box-in { margin-top:8px; }
		.sitemap-tree, .sitemap-tree ul { list-style:none; margin:0; padding-left:16px; }
		.sitemap-tree { padding-left:0; }
		.sitemap-tree li { margin:3px 0; }
		.sitemap-item { cursor:pointer; display:inline-flex; gap:6px; align-items:center; }
		.sitemap-item:hover { text-decoration:underline; }
		.sitemap-ext { color:var(--mut); font-size:12px; }
		.sitemap-cols { columns:2 260px; column-gap:24px; }
		.sitemap-cols div { break-inside:avoid; margin:2px 0; }
		.sitemap-muted { color:var(--mut); }
	</style>

	<script>const SITEMAP_DATA = <?php echo $json; ?>;</script>
	<script>
	/* ==========================================================================
	 * JavaScript — lay out and draw the graph, no libraries from the internet
	 * ========================================================================== */
	(function () {
		const D = SITEMAP_DATA;
		const ST = {
			front: ['Home', '#1d2327'],
			menu: ['In the main menu', '#2271b1'],
			footer: ['Side menu / footer', '#007017'],
			linked: ['Linked from other pages', '#b26200'],
			unreachable: ['Linked only from unlinked pages', '#7a3e9d'],
			orphan: ['No incoming links', '#d63638']
		};
		const byId = Object.fromEntries(D.nodes.map(n => [String(n.id), n]));
		D.nodes.forEach(n => { n.id = String(n.id); n.in = n.in.map(String); n.out = n.out.map(String); });
		const NS = 'http://www.w3.org/2000/svg';
		const qs = id => document.getElementById(id);
		const el = (tag, attrs, parent) => { const node = document.createElementNS(NS, tag); for (const key in attrs) node.setAttribute(key, attrs[key]); if (parent) parent.appendChild(node); return node; };
		const esc = value => String(value == null ? '' : value).replace(/[&<>"]/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[ch]));
		if (!D.nodes.length) { qs('sitemap-graph').textContent = 'No published pages.'; return; }

		const graph = qs('sitemap-graph');
		function fsElement() { return document.fullscreenElement || document.webkitFullscreenElement || null; }
		function sizeGraph() {
			if (fsElement() === graph) { graph.style.height = '100%'; return; }
			graph.style.height = Math.round(Math.max(520, Math.min(860, graph.clientWidth * 0.62))) + 'px';
		}
		sizeGraph();

		const W = 1700, H = 1100, CX = 560, CY = H / 2;
		const mainSecs = D.sections.filter(s => s.indexOf('Unlinked') !== 0);
		const orphSecs = D.sections.filter(s => s.indexOf('Unlinked') === 0);
		const center = { 'Home': [CX, CY], 'Main menu': [CX, CY - 145], 'Footer': [CX - 260, CY + 20] };
		const ring = mainSecs.filter(s => !center[s]);
		ring.forEach((name, i) => { const angle = -Math.PI / 2 + i * 2 * Math.PI / ring.length; center[name] = [CX + Math.cos(angle) * 500, CY + Math.sin(angle) * 440]; });
		orphSecs.forEach((name, i) => { center[name] = [1330 + (i % 2) * 230, 220 + Math.floor(i / 2) * 520 + (i % 2) * 120]; });
		let seed = 7; const rnd = () => (seed = (seed * 16807) % 2147483647) / 2147483647;
		D.nodes.forEach(n => {
			const c = center[n.section] || [W / 2, H / 2];
			const spread = (n.section === 'Main menu' || n.section === 'Footer') ? 36 : 120;
			n.x = c[0] + (rnd() - .5) * spread; n.y = c[1] + (rnd() - .5) * spread; n.vx = 0; n.vy = 0;
			n.r = n.id === String(D.front) ? 13 : 4 + Math.min(9, Math.sqrt(n.in.length) * 2.2);
		});
		const front = D.front ? byId[String(D.front)] : null;
		if (front) { front.x = CX; front.y = CY; }
		const springs = [];
		Object.keys(D.edges).forEach(kind => (D.edges[kind] || []).forEach(pair => {
			const a = byId[String(pair[0])], b = byId[String(pair[1])];
			if (a && b) springs.push([a, b, kind, !(kind === 'menu' && a.section !== b.section)]);
		}));
		for (let step = 0, temperature = 1; step < 420; step++, temperature *= 0.992) {
			for (let i = 0; i < D.nodes.length; i++) {
				const a = D.nodes[i];
				for (let j = i + 1; j < D.nodes.length; j++) {
					const b = D.nodes[j]; let dx = a.x - b.x, dy = a.y - b.y; let dist2 = dx * dx + dy * dy || .01;
					if (dist2 > 90000) continue;
					const force = (a.section === b.section ? 700 : 1400) / dist2; const dist = Math.sqrt(dist2);
					dx /= dist; dy /= dist; a.vx += dx * force; a.vy += dy * force; b.vx -= dx * force; b.vy -= dy * force;
				}
			}
			springs.forEach(row => {
				if (!row[3]) return;
				const a = row[0], b = row[1], kind = row[2];
				let dx = b.x - a.x, dy = b.y - a.y; const dist = Math.sqrt(dx * dx + dy * dy) || .01;
				const rest = (kind === 'menu' || kind === 'foot') ? 52 : 80;
				const pull = ((kind === 'menu' || kind === 'foot') ? .02 : .006) * (dist - rest);
				dx /= dist; dy /= dist; a.vx += dx * pull; a.vy += dy * pull; b.vx -= dx * pull; b.vy -= dy * pull;
			});
			D.nodes.forEach(n => {
				const c = center[n.section] || [W / 2, H / 2];
				const gravity = (n.section === 'Main menu' || n.section === 'Footer') ? .09 : (n.section.indexOf('Unlinked') === 0 ? .03 : .016);
				n.vx += (c[0] - n.x) * gravity; n.vy += (c[1] - n.y) * gravity; n.vx *= .82; n.vy *= .82;
				n.x += Math.max(-12, Math.min(12, n.vx * temperature)); n.y += Math.max(-12, Math.min(12, n.vy * temperature));
			});
			if (front) { front.x = CX; front.y = CY; }
		}

		const svg = qs('sitemap-svg');
		const defs = el('defs', {}, svg);
		const marker = el('marker', { id: 'sitemap-arr', viewBox: '0 0 10 10', refX: 10, refY: 5, markerWidth: 5, markerHeight: 5, orient: 'auto-start-reverse' }, defs);
		el('path', { d: 'M0,0L10,5L0,10z', fill: '#8c8f94' }, marker);
		D.sections.forEach(name => {
			const group = D.nodes.filter(n => n.section === name); if (!group.length) return;
			const label = el('text', { x: group.reduce((sum, n) => sum + n.x, 0) / group.length, y: Math.min.apply(null, group.map(n => n.y)) - 16, 'text-anchor': 'middle', class: 'sitemap-sec' }, qs('sitemap-g-sec'));
			label.textContent = name + ' (' + group.length + ')';
		});
		const edgeEls = [];
		springs.forEach(row => {
			const a = row[0], b = row[1], kind = row[2];
			const dx = b.x - a.x, dy = b.y - a.y, dist = Math.sqrt(dx * dx + dy * dy) || 1;
			const line = el('line', { x1: a.x, y1: a.y, x2: b.x - dx / dist * (b.r + 2), y2: b.y - dy / dist * (b.r + 2), class: 'sitemap-e-' + kind }, qs('sitemap-g-e'));
			if (kind === 'link') line.setAttribute('marker-end', 'url(#sitemap-arr)');
			line._a = a; line._b = b; line._k = kind; edgeEls.push(line);
		});
		D.nodes.forEach(n => {
			n.el = el('circle', { cx: n.x, cy: n.y, r: n.r, class: 'sitemap-n', fill: (ST[n.status] || ST.orphan)[1] }, qs('sitemap-g-n'));
			n.el.addEventListener('mouseenter', ev => showTip(n, ev));
			n.el.addEventListener('mousemove', moveTip);
			n.el.addEventListener('mouseleave', () => { qs('sitemap-tip').style.display = 'none'; });
			n.el.addEventListener('click', ev => { ev.stopPropagation(); select(n.id); });
			n.important = n.id === String(D.front) || n.status === 'menu' || n.status === 'footer' || n.in.length >= 3;
			n.lbl = el('text', { x: n.x + n.r + 3, y: n.y + 3.5, class: 'sitemap-lbl' }, qs('sitemap-g-l'));
			n.lbl.textContent = n.title.length > 38 ? n.title.slice(0, 36) + '…' : n.title;
		});
		function applyLabels() { const all = qs('sitemap-t-lbl').checked; D.nodes.forEach(n => { n.lbl.style.display = (all || n.important || n.hl) ? '' : 'none'; }); }
		function applyEdges() {
			const on = { link: qs('sitemap-t-link').checked, menu: qs('sitemap-t-menu').checked, foot: qs('sitemap-t-menu').checked, hier: qs('sitemap-t-hier').checked };
			edgeEls.forEach(edge => { edge.style.display = on[edge._k] ? '' : 'none'; });
		}
		['sitemap-t-link', 'sitemap-t-menu', 'sitemap-t-hier'].forEach(id => qs(id).addEventListener('change', applyEdges));
		qs('sitemap-t-lbl').addEventListener('change', applyLabels);

		let view = { x: 0, y: 0, k: 1 }, drag = null;
		const vp = qs('sitemap-vp');
		function setView() { vp.setAttribute('transform', 'translate(' + view.x + ',' + view.y + ') scale(' + view.k + ')'); }
		function fit() {
			const rect = svg.getBoundingClientRect();
			const xs = D.nodes.map(n => n.x), ys = D.nodes.map(n => n.y);
			const x0 = Math.min.apply(null, xs) - 60, x1 = Math.max.apply(null, xs) + 180, y0 = Math.min.apply(null, ys) - 50, y1 = Math.max.apply(null, ys) + 40;
			view.k = Math.min(rect.width / (x1 - x0), rect.height / (y1 - y0));
			view.x = (rect.width - (x1 - x0) * view.k) / 2 - x0 * view.k;
			view.y = (rect.height - (y1 - y0) * view.k) / 2 - y0 * view.k;
			setView();
		}
		svg.addEventListener('wheel', ev => { ev.preventDefault(); const rect = svg.getBoundingClientRect(); const mx = ev.clientX - rect.left, my = ev.clientY - rect.top; const next = Math.max(.2, Math.min(6, view.k * Math.exp(-ev.deltaY * .0015))); view.x = mx - (mx - view.x) * next / view.k; view.y = my - (my - view.y) * next / view.k; view.k = next; setView(); }, { passive: false });
		svg.addEventListener('pointerdown', ev => { drag = { x: ev.clientX, y: ev.clientY, vx: view.x, vy: view.y, moved: false }; svg.classList.add('drag'); });
		window.addEventListener('pointermove', ev => { if (!drag) return; const dx = ev.clientX - drag.x, dy = ev.clientY - drag.y; if (Math.abs(dx) + Math.abs(dy) > 3) drag.moved = true; view.x = drag.vx + dx; view.y = drag.vy + dy; setView(); });
		window.addEventListener('pointerup', () => { svg.classList.remove('drag'); setTimeout(() => { drag = null; }, 0); });
		svg.addEventListener('click', () => { if (!drag || !drag.moved) select(null); });
		qs('sitemap-fit').addEventListener('click', fit);

		const fsEnter = '<svg viewBox="0 0 20 20" width="12" height="12" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="1.6" d="M3 7V3h4M17 7V3h-4M3 13v4h4M17 13v4h-4"/></svg>';
		const fsExit = '<svg viewBox="0 0 20 20" width="12" height="12" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="1.6" d="M7 3v4H3M13 3v4h4M7 17v-4H3M13 17v-4h4"/></svg>';
		const fsBtn = qs('sitemap-fs');
		function syncFullscreen() {
			const on = fsElement() === graph;
			fsBtn.title = on ? 'Exit full screen' : 'Full screen';
			fsBtn.setAttribute('aria-label', fsBtn.title);
			fsBtn.innerHTML = on ? fsExit : fsEnter;
			sizeGraph();
			requestAnimationFrame(fit);
		}
		fsBtn.addEventListener('click', ev => {
			ev.stopPropagation();
			if (fsElement() === graph) {
				const exit = document.exitFullscreen || document.webkitExitFullscreen;
				if (exit) exit.call(document);
			} else {
				const req = graph.requestFullscreen || graph.webkitRequestFullscreen;
				if (req) req.call(graph);
			}
		});
		document.addEventListener('fullscreenchange', syncFullscreen);
		document.addEventListener('webkitfullscreenchange', syncFullscreen);

		function showTip(n, ev) { const tip = qs('sitemap-tip'); tip.innerHTML = '<b>' + esc(n.title) + '</b><br><span class="sitemap-muted">/' + esc(n.path) + '/</span><br>' + esc((ST[n.status] || ST.orphan)[0]) + ' · incoming: ' + n.in.length + ' · outgoing: ' + n.out.length; tip.style.display = 'block'; moveTip(ev); }
		function moveTip(ev) { const rect = qs('sitemap-graph').getBoundingClientRect(); const tip = qs('sitemap-tip'); tip.style.left = Math.min(ev.clientX - rect.left + 12, rect.width - 290) + 'px'; tip.style.top = (ev.clientY - rect.top + 12) + 'px'; }
		function itemHtml(id) { const n = byId[id]; return n ? '<li data-id="' + esc(id) + '"><span class="sitemap-dot" style="background:' + (ST[n.status] || ST.orphan)[1] + '"></span> ' + esc(n.title) + '</li>' : ''; }
		let panelCollapsed = false;
		const chevronUp = '<svg viewBox="0 0 20 20" width="12" height="12" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="1.6" d="M4 12l6-6 6 6"/></svg>';
		const chevronDown = '<svg viewBox="0 0 20 20" width="12" height="12" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="1.6" d="M4 8l6 6 6-6"/></svg>';
		function applyPanelFold(panel) {
			panel.classList.toggle('is-collapsed', panelCollapsed);
			const fold = panel.querySelector('.sitemap-fold');
			if (!fold) return;
			fold.title = panelCollapsed ? 'Expand' : 'Collapse';
			fold.setAttribute('aria-label', fold.title);
			fold.innerHTML = panelCollapsed ? chevronDown : chevronUp;
		}
		function select(id) {
			const panel = qs('sitemap-panel');
			D.nodes.forEach(n => { n.el.classList.remove('sitemap-dim', 'sitemap-sel'); n.lbl.classList.remove('sitemap-dim'); n.hl = false; });
			edgeEls.forEach(edge => edge.classList.remove('sitemap-dim', 'sitemap-hl', 'sitemap-hl-in', 'sitemap-hl-out'));
			if (!id || !byId[id]) { panelCollapsed = false; panel.style.display = 'none'; panel.classList.remove('is-collapsed'); applyLabels(); applyStatus(); return; }
			const n = byId[id]; const near = new Set([id].concat(n.in, n.out));
			edgeEls.forEach(edge => { if (edge._a.id === id || edge._b.id === id) { near.add(edge._a.id); near.add(edge._b.id); } });
			D.nodes.forEach(other => { if (!near.has(other.id)) { other.el.classList.add('sitemap-dim'); other.lbl.classList.add('sitemap-dim'); } else other.hl = true; });
			n.el.classList.add('sitemap-sel');
			edgeEls.forEach(edge => { if (edge._a.id === id) edge.classList.add('sitemap-hl', 'sitemap-hl-out'); else if (edge._b.id === id) edge.classList.add('sitemap-hl', 'sitemap-hl-in'); else edge.classList.add('sitemap-dim'); });
			applyLabels();
			const parent = n.path.indexOf('/') > 0 ? D.nodes.find(other => n.path.indexOf(other.path + '/') === 0 && other.path.split('/').length === n.path.split('/').length - 1) : null;
			panel.innerHTML = '<div class="sitemap-panel-bar"><h3>' + esc(n.title) + '</h3><button type="button" class="sitemap-fold" title="Collapse" aria-label="Collapse">' + chevronUp + '</button><button type="button" class="sitemap-x" title="Close" aria-label="Close">×</button></div><div class="sitemap-panel-body">' +
				(n.url ? '<a href="' + esc(n.url) + '" target="_blank" rel="noopener">' + esc(n.url) + '</a><br>' : '') +
				'<span class="sitemap-badge"><span class="sitemap-dot" style="background:' + (ST[n.status] || ST.orphan)[1] + '"></span>' + esc((ST[n.status] || ST.orphan)[0]) + '</span><span class="sitemap-badge">section: ' + esc(n.section) + '</span>' +
				(n.type === 'post' ? '<span class="sitemap-badge">blog post</span>' : '') + (n.noSearch ? '<span class="sitemap-badge">hidden from site search</span>' : '') + (n.modified ? '<span class="sitemap-badge">modified ' + esc(n.modified) + '</span>' : '') +
				(parent ? '<div class="sitemap-muted" style="margin-top:4px">Parent page:</div><ul>' + itemHtml(parent.id) + '</ul>' : '') +
				'<div class="sitemap-muted" style="margin-top:6px">Linked from (' + n.in.length + '):</div><ul>' + (n.in.map(itemHtml).join('') || '<span class="sitemap-muted">none</span>') + '</ul>' +
				'<div class="sitemap-muted">Links to (' + n.out.length + '):</div><ul>' + (n.out.map(itemHtml).join('') || '<span class="sitemap-muted">none</span>') + '</ul>' +
				(n.broken.length ? '<div class="sitemap-muted">Broken links:</div><ul>' + n.broken.map(path => '<li>/' + esc(path) + '/</li>').join('') + '</ul>' : '') +
				'</div>';
			panel.style.display = 'block';
			applyPanelFold(panel);
			panel.querySelector('.sitemap-fold').onclick = ev => { ev.stopPropagation(); panelCollapsed = !panelCollapsed; applyPanelFold(panel); };
			panel.querySelector('.sitemap-x').onclick = ev => { ev.stopPropagation(); select(null); };
			panel.querySelectorAll('li[data-id]').forEach(row => { row.onclick = () => focusNode(row.getAttribute('data-id')); });
		}
		function focusNode(id) {
			const n = byId[id]; if (!n) return;
			const rect = svg.getBoundingClientRect();
			view.k = Math.max(view.k, 1.4); view.x = rect.width * .4 - n.x * view.k; view.y = rect.height / 2 - n.y * view.k; setView(); select(id);
		}
		const off = new Set();
		function applyStatus() {
			if (qs('sitemap-panel').style.display === 'block') return;
			D.nodes.forEach(n => { const dim = off.has(n.status); n.el.classList.toggle('sitemap-dim', dim); n.lbl.classList.toggle('sitemap-dim', dim); });
			edgeEls.forEach(edge => edge.classList.toggle('sitemap-dim', off.has(edge._a.status) && off.has(edge._b.status)));
		}
		const pages = D.nodes.filter(n => n.type !== 'external');
		const counts = {}; pages.forEach(n => { counts[n.status] = (counts[n.status] || 0) + 1; });
		qs('sitemap-stats').innerHTML = '<div class="sitemap-stat" style="cursor:default"><b>' + pages.length + '</b><span>published pages and posts</span></div>' +
			Object.keys(ST).filter(key => counts[key]).map(key => '<div class="sitemap-stat" data-s="' + key + '"><b>' + counts[key] + '</b><span><span class="sitemap-dot" style="background:' + ST[key][1] + '"></span>' + ST[key][0] + '</span></div>').join('');
		qs('sitemap-stats').querySelectorAll('.sitemap-stat[data-s]').forEach(card => { card.onclick = () => { const key = card.getAttribute('data-s'); off.has(key) ? off.delete(key) : off.add(key); card.classList.toggle('off'); select(null); }; });
		qs('sitemap-legend').innerHTML = Object.keys(ST).filter(key => counts[key]).map(key => '<span><span class="sitemap-dot" style="background:' + ST[key][1] + ';vertical-align:middle;margin-right:5px"></span>' + ST[key][0] + '</span>').join('') +
			'<span><i style="border-color:#2271b1"></i>menu item</span><span><i style="border-color:#007017"></i>footer / side menu</span><span><i style="border-color:#8c8f94"></i>link in content</span><span><i style="border-color:#8c8f94;border-top-style:dashed"></i>parent page</span>';

		qs('sitemap-q').addEventListener('input', () => { const query = qs('sitemap-q').value.trim().toLowerCase(); D.nodes.forEach(n => n.el.classList.toggle('sitemap-match', !!(query && (n.title.toLowerCase().indexOf(query) >= 0 || n.path.indexOf(query) >= 0)))); });
		qs('sitemap-q').addEventListener('keydown', ev => { if (ev.key !== 'Enter') return; const query = qs('sitemap-q').value.trim().toLowerCase(); const found = D.nodes.find(n => query && (n.title.toLowerCase().indexOf(query) >= 0 || n.path.indexOf(query) >= 0)); if (found) focusNode(found.id); });

		function treeHtml(items) {
			return (items || []).map(item => {
				const n = item.target && byId[String(item.target)];
				const head = n ? '<span class="sitemap-item" data-id="' + esc(n.id) + '"><span class="sitemap-dot" style="background:' + (ST[n.status] || ST.orphan)[1] + '"></span>' + esc(item.label) + '</span> <span class="sitemap-ext">· incoming: ' + n.in.length + '</span>' : '<span>' + esc(item.label) + '</span> <span class="sitemap-ext">→ ' + esc(item.url) + '</span>';
				return '<li>' + head + (item.children && item.children.length ? '<ul>' + treeHtml(item.children) + '</ul>' : '') + '</li>';
			}).join('');
		}
		qs('sitemap-menu').innerHTML = treeHtml(D.menuTree);
		qs('sitemap-side').innerHTML = treeHtml(D.sideTree);
		const orphans = D.nodes.filter(n => n.status === 'orphan' || n.status === 'unreachable');
		qs('sitemap-orph-title').textContent = 'Pages nothing links to (' + orphans.length + ')';
		qs('sitemap-orphans').innerHTML = orphSecs.map(name => {
			const list = orphans.filter(n => n.section === name).sort((a, b) => a.title.localeCompare(b.title, 'pl'));
			return '<details><summary>' + esc(name.replace('Unlinked: ', '')) + ' (' + list.length + ')</summary><div class="sitemap-cols">' + list.map(n => '<div><span class="sitemap-item" data-id="' + esc(n.id) + '"><span class="sitemap-dot" style="background:' + (ST[n.status] || ST.orphan)[1] + '"></span>' + esc(n.title) + '</span> <span class="sitemap-ext">' + esc((n.modified || '').slice(0, 4)) + (n.noSearch ? ' · hidden' : '') + '</span></div>').join('') + '</div></details>';
		}).join('');
		const broken = D.nodes.filter(n => n.broken.length);
		qs('sitemap-broken-title').textContent = 'Broken internal links (' + broken.reduce((sum, n) => sum + n.broken.length, 0) + ')';
		qs('sitemap-broken').innerHTML = broken.length ? broken.map(n => '<div><span class="sitemap-item" data-id="' + esc(n.id) + '">' + esc(n.title) + '</span> → ' + n.broken.map(path => '<code>/' + esc(path) + '/</code>').join(', ') + '</div>').join('') : '<p>None.</p>';
		document.querySelectorAll('.sitemap-app .sitemap-item[data-id]').forEach(row => { row.onclick = () => focusNode(row.getAttribute('data-id')); });
		applyEdges(); applyLabels(); fit();
		window.addEventListener('resize', () => { sizeGraph(); fit(); });
	})();
	</script>
	<?php
}
