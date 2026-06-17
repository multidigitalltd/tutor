<?php
/**
 * "Content Unit" custom post type.
 *
 * @package MultiDigital\DeSchool
 */

declare( strict_types=1 );

namespace MultiDigital\DeSchool\Post_Types;

use MultiDigital\DeSchool\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the mdds_unit post type.
 */
final class Unit_Post_Type {

	/**
	 * Register the post type.
	 */
	public function register(): void {
		$labels = array(
			'name'               => __( 'יחידות תוכן', 'md-deschool' ),
			'singular_name'      => __( 'יחידת תוכן', 'md-deschool' ),
			'menu_name'          => __( 'DeSchool', 'md-deschool' ),
			'add_new'            => __( 'הוספת יחידה', 'md-deschool' ),
			'add_new_item'       => __( 'הוספת יחידת תוכן', 'md-deschool' ),
			'edit_item'          => __( 'עריכת יחידת תוכן', 'md-deschool' ),
			'new_item'           => __( 'יחידת תוכן חדשה', 'md-deschool' ),
			'view_item'          => __( 'צפייה ביחידה', 'md-deschool' ),
			'search_items'       => __( 'חיפוש יחידות', 'md-deschool' ),
			'not_found'          => __( 'לא נמצאו יחידות', 'md-deschool' ),
			'not_found_in_trash' => __( 'לא נמצאו יחידות בפח', 'md-deschool' ),
			'all_items'          => __( 'כל היחידות', 'md-deschool' ),
		);

		register_post_type(
			Data::POST_TYPE_UNIT,
			array(
				'labels'          => $labels,
				'public'          => true,
				'has_archive'     => true,
				'show_in_rest'    => true,
				'menu_icon'       => 'dashicons-welcome-learn-more',
				'menu_position'   => 26,
				'rewrite'         => array(
					'slug'       => 'unit',
					'with_front' => false,
				),
				'supports'        => array( 'title', 'editor', 'thumbnail', 'excerpt', 'author' ),
				'capability_type' => 'post',
				'map_meta_cap'    => true,
			)
		);
	}
}
