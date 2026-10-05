<?php
/**
 * Site footer: brand, navigation, contact (only configured details), legal links and Cookie preferences.
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || exit;

$eda_place    = eda_dealer_location();
$eda_phone    = get_theme_mod( 'eda_phone' );
$eda_whatsapp = ltrim( eda_phone_digits( get_theme_mod( 'eda_whatsapp' ) ), '+' );
$eda_contact  = get_page_by_path( 'contact' );
?>
</main>

<footer class="site-footer">
	<div class="container">
		<div class="site-footer__grid">
			<div class="site-footer__brand">
				<a class="brand brand--light" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php bloginfo( 'name' ); ?></a>
				<?php if ( get_bloginfo( 'description' ) ) : ?>
					<p><?php bloginfo( 'description' ); ?></p>
				<?php endif; ?>
			</div>

			<nav class="site-footer__col" aria-labelledby="footer-nav-title">
				<h2 id="footer-nav-title" class="site-footer__heading"><?php esc_html_e( 'Explore', 'elite-auto-dealer' ); ?></h2>
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'footer',
						'container'      => false,
						'menu_class'     => 'site-footer__list',
						'fallback_cb'    => 'eda_primary_menu_fallback',
						'depth'          => 1,
					)
				);
				?>
			</nav>

			<?php if ( $eda_place || $eda_phone || $eda_whatsapp || $eda_contact ) : ?>
				<div class="site-footer__col">
					<h2 class="site-footer__heading"><?php esc_html_e( 'Contact', 'elite-auto-dealer' ); ?></h2>
					<ul class="site-footer__list">
						<?php if ( $eda_place ) : ?>
							<li><?php echo esc_html( $eda_place ); ?></li>
						<?php endif; ?>
						<?php if ( $eda_phone ) : ?>
							<li><a href="<?php echo esc_url( 'tel:' . eda_phone_digits( $eda_phone ) ); ?>"><?php echo esc_html( $eda_phone ); ?></a></li>
						<?php endif; ?>
						<?php if ( $eda_whatsapp ) : ?>
							<li><a href="<?php echo esc_url( 'https://wa.me/' . $eda_whatsapp ); ?>"><?php esc_html_e( 'WhatsApp', 'elite-auto-dealer' ); ?></a></li>
						<?php endif; ?>
						<?php if ( $eda_contact ) : ?>
							<?php /* translators: %s: site name. */ ?>
							<li><a href="<?php echo esc_url( get_permalink( $eda_contact ) ); ?>"><?php echo esc_html( sprintf( __( 'Contact %s', 'elite-auto-dealer' ), get_bloginfo( 'name' ) ) ); ?></a></li>
						<?php endif; ?>
					</ul>
				</div>
			<?php endif; ?>
		</div>

		<div class="site-footer__legal">
			<p>&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?></p>
			<nav aria-label="<?php esc_attr_e( 'Legal', 'elite-auto-dealer' ); ?>">
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'legal',
						'container'      => false,
						'menu_class'     => 'site-footer__legal-list',
						'fallback_cb'    => 'eda_legal_menu_fallback',
						'depth'          => 1,
					)
				);
				?>
			</nav>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
