<?php
/**
 * Unit header part.
 *
 * @package MultiDigital\DeSchool
 *
 * @var array $args Passed via Template_Loader::get_part().
 */

declare( strict_types=1 );

use MultiDigital\DeSchool\Data;

defined( 'ABSPATH' ) || exit;

$unit_id    = (int) ( $args['unit_id'] ?? 0 );
$progress   = (array) ( $args['progress'] ?? array() );
$can_access = (bool) ( $args['can_access'] ?? false );
$compact    = (bool) ( $args['compact'] ?? false );

$short    = (string) get_post_meta( $unit_id, Data::META_SHORT_DESC, true );
$includes = (string) get_post_meta( $unit_id, Data::META_INCLUDES, true );
$total    = (int) ( $progress['total'] ?? 0 );
$done     = (int) ( $progress['completed'] ?? 0 );
$percent  = $total > 0 ? (int) round( ( $done / $total ) * 100 ) : 0;
?>
<header class="mdds-unit-header<?php echo $compact ? ' is-compact' : ''; ?>">
	<?php if ( ! $compact && has_post_thumbnail( $unit_id ) ) : ?>
		<div class="mdds-unit-thumb">
			<?php echo get_the_post_thumbnail( $unit_id, 'large', array( 'loading' => 'lazy' ) ); ?>
		</div>
	<?php endif; ?>

	<h1 class="mdds-unit-title"><?php echo esc_html( get_the_title( $unit_id ) ); ?></h1>

	<?php if ( ! $compact && '' !== $short ) : ?>
		<p class="mdds-unit-subtitle"><?php echo esc_html( $short ); ?></p>
	<?php endif; ?>

	<?php if ( $can_access && $total > 0 ) : ?>
		<div class="mdds-progress" data-mdds-progress>
			<div class="mdds-progress-bar" role="progressbar"
				aria-valuemin="0" aria-valuemax="100"
				aria-valuenow="<?php echo esc_attr( (string) $percent ); ?>"
				aria-label="<?php esc_attr_e( 'התקדמות ביחידה', 'md-deschool' ); ?>">
				<span class="mdds-progress-fill" style="width:<?php echo esc_attr( (string) $percent ); ?>%"></span>
			</div>
			<p class="mdds-progress-text" data-mdds-progress-text>
				<?php
				printf(
					/* translators: 1: completed chapters, 2: total chapters */
					esc_html__( '%1$d מתוך %2$d פרקים הושלמו', 'md-deschool' ),
					(int) $done,
					(int) $total
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<?php
	// In compact (learning) mode, skip the marketing description and "includes"
	// so the course content is the focus.
	if ( $compact ) {
		echo '</header>';
		return;
	}

	$content = apply_filters( 'the_content', get_post_field( 'post_content', $unit_id ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- applying WordPress core filter.
	if ( '' !== trim( wp_strip_all_tags( $content ) ) ) :
		?>
		<div class="mdds-unit-description"><?php echo wp_kses_post( $content ); ?></div>
	<?php endif; ?>

	<?php if ( '' !== trim( $includes ) ) : ?>
		<section class="mdds-unit-includes" aria-labelledby="mdds-includes-title">
			<h2 id="mdds-includes-title"><?php esc_html_e( 'מה כוללת היחידה', 'md-deschool' ); ?></h2>
			<ul>
				<?php
				$lines = preg_split( '/\r\n|\r|\n/', $includes );
				foreach ( (array) $lines as $line ) :
					$line = trim( $line );
					if ( '' === $line ) {
						continue;
					}
					?>
					<li><?php echo esc_html( $line ); ?></li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>
</header>
