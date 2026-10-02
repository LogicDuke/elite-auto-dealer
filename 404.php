<?php
/**
 * 404 template.
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="container empty-state section">
	<h1 class="page-title"><?php esc_html_e( 'Page not found', 'elite-auto-dealer' ); ?></h1>
	<a class="button button--primary" href="<?php echo esc_url( get_post_type_archive_link( 'vehicle' ) ); ?>"><?php esc_html_e( 'Browse our vehicles', 'elite-auto-dealer' ); ?></a>
</div>
<?php
get_footer();
