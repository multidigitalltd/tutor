<?php
/**
 * Uninstall routine.
 *
 * Removes plugin options only. Content (units/chapters) and learner progress
 * are intentionally preserved to avoid accidental data loss; remove the posts
 * manually if a full wipe is required.
 *
 * @package MultiDigital\DeSchool
 */

declare( strict_types=1 );

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'mdds_demo_unit_id' );
