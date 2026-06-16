<?php
/**
 * "Chapter" custom post type. Each chapter belongs to a unit via post_parent.
 *
 * @package MultiDigital\DeSchool
 */

declare( strict_types=1 );

namespace MultiDigital\DeSchool\Post_Types;

use MultiDigital\DeSchool\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the mdds_chapter post type.
 */
final class Chapter_Post_Type {

	/**
	 * Register the post type.
	 */
	public function register(): void {
		$labels = array(
			'name'               => __( 'פרקים', 'md-deschool' ),
			'singular_name'      => __( 'פרק', 'md-deschool' ),
			'menu_name'          => __( 'פרקים', 'md-deschool' ),
			'add_new'            => __( 'הוספת פרק', 'md-deschool' ),
			'add_new_item'       => __( 'הוספת פרק', 'md-deschool' ),
			'edit_item'          => __( 'עריכת פרק', 'md-deschool' ),
			'new_item'           => __( 'פרק חדש', 'md-deschool' ),
			'search_items'       => __( 'חיפוש פרקים', 'md-deschool' ),
			'not_found'          => __( 'לא נמצאו פרקים', 'md-deschool' ),
			'all_items'          => __( 'כל הפרקים', 'md-deschool' ),
		);

		register_post_type(
			Data::POST_TYPE_CHAPTER,
			array(
				'labels'          => $labels,
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'edit.php?post_type=' . Data::POST_TYPE_UNIT,
				'show_in_rest'    => false,
				'hierarchical'    => false,
				'has_archive'     => false,
				'rewrite'         => false,
				'supports'        => array( 'title', 'page-attributes' ),
				'capability_type' => 'post',
				'map_meta_cap'    => true,
			)
		);
	}
}
