<?php
/**
 * Site footer.
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || exit;
?>
</main>

<footer class="site-footer">
	<?php
	wp_nav_menu(
		array(
			'theme_location'       => 'footer',
			'container'            => 'nav',
			'container_aria_label' => __( 'Footer', 'elite-auto-dealer' ),
			'fallback_cb'          => false,
			'depth'                => 1,
		)
	);
	?>
	<p>&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?></p>
</footer>

<?php wp_footer(); ?>
</body>
</html>
