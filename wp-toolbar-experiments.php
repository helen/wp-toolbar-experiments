<?php
/**
 * Plugin Name: WP Toolbar Experiments
 * Plugin URI: https://github.com/helenhousandi/wp-toolbar-experiments
 * Description: Re-imagining the toolbar.
 * Version: 0.1
 * Author: The WordPress Team
 * Author URI: https://wordpress.org/
 * Tags: toolbar, admin bar, experiments
 * License: GPL

=====================================================================================
Copyright (C) 2015 WordPress

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with WordPress; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
=====================================================================================
*/

add_action( 'wp_enqueue_scripts', 'wp_toolbar_experiments_enqueue' );
add_action( 'admin_enqueue_scripts', 'wp_toolbar_experiments_enqueue' );
function wp_toolbar_experiments_enqueue() {
	wp_enqueue_style( 'wp-toolbar-experiments', plugin_dir_url( __FILE__ ) . '/wp-toolbar-experiments.css' );
	wp_enqueue_script( 'wp-toolbar-experiments', plugin_dir_url( __FILE__ ) . '/wp-toolbar-experiments.js', array( 'jquery' ) );
}

add_action( 'add_admin_bar_menus', 'wp_toolbar_experiments_add_menus' );
function wp_toolbar_experiments_add_menus() {
	remove_action( 'admin_bar_menu', 'wp_admin_bar_wp_menu', 10 );
	remove_action( 'admin_bar_menu', 'wp_admin_bar_my_sites_menu', 20 );
	remove_action( 'admin_bar_menu', 'wp_admin_bar_site_menu', 30 );
	remove_action( 'admin_bar_menu', 'wp_admin_bar_comments_menu', 60 );
	// Core makes the critical mistake of returning if is_admin() in the function instead of in WP_Admin_Bar::add_menus(), so we have to overwrite the entire function.
	remove_action( 'admin_bar_menu', 'wp_admin_bar_customize_menu', 40 );

	add_action( 'admin_bar_menu', 'wp_toolbar_experiments_site_menu', 20 );
	add_action( 'admin_bar_menu', 'wp_admin_bar_my_sites_menu', 30 );
	add_action( 'admin_bar_menu', 'wp_toolbar_experiments_customize_menu', 40 );
}

// @todo add About to admin menu under dashboard
// @todo remove Customizer Header & Background from admin menu.

/**
* Adds the "Customize" link to the Toolbar.
*
* Core makes the critical mistake of returning if is_admin() in the function instead of in WP_Admin_Bar::add_menus(), so we have to overwrite the entire function.
*
* @since 4.4.0
*
* @param WP_Admin_Bar $wp_admin_bar WP_Admin_Bar instance.
*/
function wp_toolbar_experiments_customize_menu( $wp_admin_bar ) {
	// Don't show for users who can't access the customizer.
	if ( ! current_user_can( 'customize' ) ) {
		return;
	}

	$current_url = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
	$customize_url = add_query_arg( 'url', urlencode( $current_url ), wp_customize_url() );

	$wp_admin_bar->add_menu( array(
		'id'     => 'customize',
		'title'  => __( 'Customize' ),
		'href'   => $customize_url,
		'meta'   => array(
			'class' => 'hide-if-no-customize',
		),
	) );
	add_action( 'wp_before_admin_bar_render', 'wp_customize_support_script' );
}

/**
 * Add the "Site Name" menu.
 *
 * @since 4.4.0
 *
 * @param WP_Admin_Bar $wp_admin_bar
 */
function wp_toolbar_experiments_site_menu( $wp_admin_bar ) {
	// Don't show for logged out users.
	if ( ! is_user_logged_in() ) {
		return;
	}

	// Show only when the user is a member of this site, or they're a super admin.
	if ( ! is_user_member_of_blog() && ! is_super_admin() ) {
		return;
	}

	$blogname = get_bloginfo( 'name' );

	if ( ! $blogname ) {
		$blogname = preg_replace( '#^(https?://)?(www.)?#', '', get_home_url() );
	}

	if ( is_network_admin() ) {
		$blogname = sprintf( __( 'Network Admin: %s' ), esc_html( get_current_site()->site_name ) );
	} elseif ( is_user_admin() ) {
		$blogname = sprintf( __( 'User Dashboard: %s' ), esc_html( get_current_site()->site_name ) );
	}

	$title = wp_html_excerpt( $blogname, 40, '&hellip;' );

	$notification = false;
	$awaiting_mod = wp_count_comments();
	if ( current_user_can( 'edit_posts' ) && $awaiting_mod->moderated > 0 ) {
		$notification = true;
	} elseif ( ! is_multisite() && current_user_can( 'update_plugins' ) ) {
		$update_data = wp_get_update_data();
		if ( $update_data['counts']['plugins'] > 0 ) {
			$notification = true;
		}
	}

	// Add front/admin cross-links.

	if ( is_admin() ) {
		// Add an option to visit the site.
		$wp_admin_bar->add_menu( array(
			'id'     => 'visit-site',
			'title'  => $title,
			'href'   => home_url( '/' ),
		) );
	} else {
		// We're on the front end, link to the Dashboard.
		$wp_admin_bar->add_menu( array(
			'id'    => 'site-name',
			'title' => $title,
			'href'  => admin_url(),
			'meta'   => array(
				'class' => ( ! is_admin() && $notification ) ? 'notification-pending' : '',
			),
		) );

		// Add a Dashboard link to the site name submenu.
		$icon = '<span class="ab-icon dashicons-dashboard"></span>';
		$wp_admin_bar->add_menu( array(
			'parent' => 'site-name',
			'id'     => 'dashboard',
			'title'  => $icon . '<span class="ab-label">' . __( 'Dashboard' ) . '</span>',
			'href'   => admin_url(),
		) );

		// Add the admin submenu items.
		wp_toolbar_experiments_ab_admin_menu( $wp_admin_bar );

		// Pair the dashboard icon on the toolbar with the visit site icon (for mobile).
		$wp_admin_bar->add_menu( array(
			'id'     => 'dashboard-toggle',
			'title'  => $icon . '<span class="ab-label">' . __( 'Dashboard' ) . '</span>',
			'href'   => admin_url(),
		) );
	}
}

/**
 * Add admin submenu items to the "Site Name" menu.
 *
 * @since 4.4.0
 *
 * @param WP_Admin_Bar $wp_admin_bar
 */
function wp_toolbar_experiments_ab_admin_menu( $wp_admin_bar ) {
	$wp_admin_bar->add_group( array( 'parent' => 'site-name', 'id' => 'admin' ) );

	// Post types.
	$cpts = (array) get_post_types( array( 'show_in_admin_bar' => true ), 'objects' );
	if ( isset( $cpts['post'] ) && current_user_can( $cpts['post']->cap->edit_posts ) ) {
		$menu_icon = '<span class="ab-icon dashicons-admin-post"></span>';
		$actions[ 'edit.php' ] = array( $cpts['post']->labels->name, 'edit-posts', $menu_icon );
	}
	if ( isset( $cpts['attachment'] ) && current_user_can( 'edit_posts' ) ) {
		$menu_icon = '<span class="ab-icon dashicons-admin-media"></span>';
		$actions[ 'upload.php' ] = array( $cpts['attachment']->labels->name, 'edit-media', $menu_icon );
	}
	if ( isset( $cpts['page'] ) && current_user_can( $cpts['page']->cap->edit_posts ) ) {
		$menu_icon = '<span class="ab-icon dashicons-admin-page"></span>';
		$actions[ 'edit.php?post_type=page' ] = array( $cpts['page']->labels->name, 'edit-pages', $menu_icon );
 	}
	unset( $cpts['post'], $cpts['page'], $cpts['attachment'] );

	// Add any additional custom post types.
	foreach ( $cpts as $cpt ) {
		if ( ! current_user_can( $cpt->cap->edit_posts ) ) {
			continue;
		}
		if ( is_string( $cpt->menu_icon ) ) {
			// Special handling for data:image/svg+xml and Dashicons.
			if ( 0 === strpos( $cpt->menu_icon, 'dashicons-' ) ) {
				$menu_icon = '<span class="ab-icon ' . $cpt->menu_icon . '"></span>';
			} elseif ( 0 === strpos( $cpt->menu_icon, 'data:image/svg+xml;base64,' ) ) {
				$menu_icon = '<span class="ab-icon"><img src="' . $cpt->menu_icon . '"></span>';
			} else {
				$menu_icon = '<span class="ab-icon"><img src="' . esc_url( $cpt->menu_icon ) . '"></span>';
			}
		} else {
			$menu_icon   = '<span class="ab-icon dashicons-admin-post"></span>';
		}
		$key = 'edit.php?post_type=' . $cpt->name;
		$actions[ $key ] = array( $cpt->labels->menu_name, 'edit-' . $cpt->name, $menu_icon );
 	}

	if ( $actions ) {
		foreach ( $actions as $link => $action ) {
			list( $title, $id, $menu_icon ) = $action;
			$wp_admin_bar->add_menu( array(
				'parent'    => 'admin',
				'id'        => $id,
				'title'     => $menu_icon . '<span class="ab-label">' . $title . '</span>',
				'href'      => admin_url( $link )
 			) );
 		}
 	}

	// Comments
	if ( current_user_can( 'edit_posts' ) ) {
		$awaiting_mod = wp_count_comments();
		$awaiting_mod = $awaiting_mod->moderated;
		$icon = '<span class="ab-icon dashicons-admin-comments"></span>';
		$wp_admin_bar->add_menu( array(
			'parent' => 'admin',
			'id'     => 'comments',
			'title'  => $icon . '<span class="ab-label">' . sprintf( __( 'Comments %s' ), "<span class='awaiting-mod count-$awaiting_mod'><span class='pending-count'>" . number_format_i18n( $awaiting_mod ) . "</span></span>" ) . '</span>',
			'href'   => admin_url( 'edit-comments.php' ),
		) );
	}

	// Appearance.
	if ( current_user_can( 'switch_themes' ) || current_user_can( 'edit_theme_options' ) ) {
		$icon = '<span class="ab-icon dashicons-admin-appearance"></span>';
		$wp_admin_bar->add_menu( array(
			'parent' => 'admin',
			'id'     => 'themes',
			'title'  => $icon . '<span class="ab-label">'  . __( 'Appearance' ) . '</span>', // @todo should we just say themes here since there isn't a submenu?
			'href'   => admin_url( 'themes.php' )
		) );
	}

	// Plugins.
	if ( current_user_can( 'activate_plugins' ) ) {
		if ( ! is_multisite() && current_user_can( 'update_plugins' ) ) {
			$update_data = wp_get_update_data();
			$count = "<span class='update-plugins count-{$update_data['counts']['plugins']}'><span class='plugin-count'>" . number_format_i18n($update_data['counts']['plugins']) . "</span></span>";
		} else {
			$count = '';
		}
		$icon = '<span class="ab-icon dashicons-admin-plugins"></span>';
		$wp_admin_bar->add_menu( array(
			'parent' => 'admin',
			'id'     => 'plugins',
			'title'  => $icon . '<span class="ab-label">' . sprintf( __( 'Plugins %s' ), $count ) . '</span>',
			'href'   => admin_url( 'plugins.php' ),
		) );
	}

	// Users.
	if ( current_user_can( 'list_users' ) ) {
		$icon = '<span class="ab-icon dashicons-admin-users"></span>';
		$wp_admin_bar->add_menu( array(
			'parent' => 'admin',
			'id'     => 'edit-users',
			'title'  => $icon . '<span class="ab-label">' . __( 'Users' ) . '</span>',
			'href'   => admin_url( 'users.php' ),
		) );
	}

	// Tools.
	if ( current_user_can( 'edit_posts' ) ) {
		$icon = '<span class="ab-icon dashicons-admin-tools"></span>';
		$wp_admin_bar->add_menu( array(
			'parent' => 'admin',
			'id'     => 'tools',
			'title'  => $icon . '<span class="ab-label">' . __( 'Tools' ) . '</span>',
			'href'   => admin_url( 'tools.php' )
		) );
	}
 
	// Settings.
	if ( current_user_can( 'manage_options' ) ) {
		$icon = '<span class="ab-icon dashicons-admin-settings"></span>';
		$wp_admin_bar->add_menu( array(
			'parent' => 'admin',
			'id'     => 'settings',
			'title'  => $icon . '<span class="ab-label">' . __( 'Settings' ) . '</span>',
			'href'   => admin_url( 'options.php' )
		) );
	}
}


