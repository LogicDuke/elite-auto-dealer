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
	<div class="container site-footer__inner">
		<div class="site-footer__brand">
			<a class="brand brand--light" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php bloginfo( 'name' ); ?></a>
			<?php if ( get_bloginfo( 'description' ) ) : ?>
				<p><?php bloginfo( 'description' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
		wp_nav_menu(
			array(
				'theme_location'       => 'footer',
				'container'            => 'nav',
				'container_class'      => 'site-footer__nav',
				'container_aria_label' => __( 'Footer', 'elite-auto-dealer' ),
				'fallback_cb'          => false,
				'depth'                => 1,
			)
		);
		?>
		<div class="site-footer__legal">
			<p>&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?></p>
			<?php the_privacy_policy_link(); ?>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
