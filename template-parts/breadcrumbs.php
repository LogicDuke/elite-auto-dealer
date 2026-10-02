<?php
/**
 * Visible breadcrumb. Same trail as the BreadcrumbList JSON-LD (inc/seo.php).
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || exit;

$eda_trail = eda_breadcrumb_trail();
if ( ! $eda_trail ) {
	return;
}
$eda_last = count( $eda_trail ) - 1;
?>
<nav class="breadcrumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'elite-auto-dealer' ); ?>">
	<ol>
		<?php foreach ( $eda_trail as $eda_i => list( $eda_label, $eda_url ) ) : ?>
			<li>
				<?php if ( $eda_i === $eda_last ) : ?>
					<span aria-current="page"><?php echo esc_html( $eda_label ); ?></span>
				<?php else : ?>
					<a href="<?php echo esc_url( $eda_url ); ?>"><?php echo esc_html( $eda_label ); ?></a>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ol>
</nav>
