<?php
/**
 * 404 template.
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<h1><?php esc_html_e( 'Page not found', 'elite-auto-dealer' ); ?></h1>
<p>
	<a href="<?php echo esc_url( get_post_type_archive_link( 'vehicle' ) ); ?>"><?php esc_html_e( 'Browse our vehicles', 'elite-auto-dealer' ); ?></a>
</p>
<?php
get_footer();
