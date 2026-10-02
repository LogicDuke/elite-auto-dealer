<?php
/**
 * Front page placeholder. Homepage design is a later phase.
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<h1><?php bloginfo( 'name' ); ?></h1>
<p>
	<a href="<?php echo esc_url( get_post_type_archive_link( 'vehicle' ) ); ?>"><?php esc_html_e( 'View all vehicles', 'elite-auto-dealer' ); ?></a>
</p>
<?php
get_footer();
