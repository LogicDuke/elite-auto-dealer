<?php
/**
 * Shared inner-page header (Vehicles, About, Finance, Contact): breadcrumb, eyebrow, H1, lead.
 *
 * With a header image it is a full-width hero, shallower than the homepage's: the image fills the
 * header under a subtle overlay and the text sits on it in light type. Without one it is the plain
 * text header on the cream background. The header image is separate from any body image (a page's
 * featured image stays in its content). It is the page's main visual, so it loads eagerly with high
 * priority. See docs/WEBSITE-IMAGE-ROADMAP.md.
 *
 * Args: title (string, required), eyebrow (string), lead (string), image (attachment ID),
 * motion (key for the per-page hero motion variant in main.css, e.g. the page slug).
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || exit;

$eda_args = wp_parse_args(
	$args ?? array(),
	array(
		'title'   => '',
		'eyebrow' => '',
		'lead'    => '',
		'image'   => 0,
		'motion'  => '',
	)
);
$eda_hero = wp_attachment_is_image( (int) $eda_args['image'] );
?>
<div class="page-intro<?php echo $eda_hero ? ' page-intro--hero' . ( $eda_args['motion'] ? ' page-intro--motion-' . esc_attr( sanitize_html_class( $eda_args['motion'] ) ) : '' ) : ''; ?>">
	<?php if ( $eda_hero ) : ?>
		<div class="page-intro__backdrop">
			<?php
			echo wp_get_attachment_image(
				(int) $eda_args['image'],
				'full',
				false,
				array(
					'class'         => 'page-intro__backdrop-image',
					'sizes'         => '100vw',
					'loading'       => 'eager',
					'fetchpriority' => 'high',
				)
			);
			?>
		</div>
	<?php endif; ?>
	<div class="container page-intro__inner">
		<div class="page-intro__text">
			<?php get_template_part( 'template-parts/breadcrumbs' ); ?>
			<div class="page-intro__heading">
				<?php if ( '' !== $eda_args['eyebrow'] ) : ?>
					<p class="eyebrow<?php echo $eda_hero ? ' eyebrow--light' : ''; ?>"><?php echo esc_html( $eda_args['eyebrow'] ); ?></p>
				<?php endif; ?>
				<h1 class="page-title"><?php echo esc_html( $eda_args['title'] ); ?></h1>
				<?php if ( '' !== $eda_args['lead'] ) : ?>
					<p class="page-lead"><?php echo esc_html( $eda_args['lead'] ); ?></p>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>
