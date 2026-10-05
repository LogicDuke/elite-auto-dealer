<?php
/**
 * Privacy preferences (EDS Consent contract, after the Elite Nail Studio consent layer).
 *
 * Truthful by construction: the site currently uses only necessary browser storage, so there
 * are no optional categories and no first-visit banner. "Cookie preferences" (any
 * `[data-eds-consent-open]` element or link to `#eds-consent`) opens a necessary-only panel and
 * nothing is stored. A real optional service is added with the `eda_consent_categories` filter;
 * its scripts are printed inert (`type="text/plain" data-eds-consent="<category>" data-src`,
 * see eda_consent_script_tag()) and the banner with Reject / Accept / Save then appears by itself.
 *
 * The decision lives in the visitor's browser (localStorage, eda_consent_config()), the HTML is
 * identical for everyone and nothing needs PHP, a session, a cookie or AJAX at runtime, so it
 * works on a static export. Stands down when the EDS Consent plugin is active.
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether the theme's own consent layer runs (not when the central EDS Consent plugin does).
 *
 * @return bool
 */
function eda_consent_active() {
	return ! ( function_exists( 'eds_consent_enabled' ) && eds_consent_enabled() );
}

/**
 * Optional categories: only services that really run on the site (today: GTranslate, when its
 * plugin is active; see eda_consent_gtranslate()). Without one, the layer is dormant.
 *
 * Shape: slug => array( 'label' => …, 'description' => … (panel), 'notice' => … (banner sentence),
 * 'cleanup' => array( 'localStorage' => [], 'cookies' => [] ), 'placeholder' => array( 'selector',
 * 'label', 'hint', 'focus' ) (optional: a local button in place of the service's control until
 * allowed)). Print the service's scripts with wp_script_add_data( $handle, 'eda_consent', '<slug>' )
 * so they stay inert until allowed.
 *
 * @return array<string, array>
 */
function eda_consent_categories() {
	$categories = (array) apply_filters( 'eda_consent_categories', array() );
	return array_filter( $categories, static fn( $c, $slug ) => is_string( $slug ) && sanitize_key( $slug ) === $slug && 'necessary' !== $slug && ! empty( $c['label'] ), ARRAY_FILTER_USE_BOTH );
}

/**
 * Browser configuration. Bump `version` (filter eda_consent_policy_version) after a material change.
 *
 * @return array
 */
function eda_consent_config() {
	$cleanup = array(
		'localStorage' => array(),
		'cookies'      => array(),
	);
	foreach ( eda_consent_categories() as $slug => $category ) {
		foreach ( array_keys( $cleanup ) as $kind ) {
			$cleanup[ $kind ][ $slug ] = array_values( (array) ( $category['cleanup'][ $kind ] ?? array() ) );
		}
	}
	return array(
		'key'          => 'eds-consent:elite-auto-dealer',
		'version'      => (string) apply_filters( 'eda_consent_policy_version', '1' ),
		'maxAgeDays'   => 180,
		'categories'   => array_keys( eda_consent_categories() ),
		// Removed when a category is refused or withdrawn (scripts that already ran need a reload).
		'cleanup'      => $cleanup,
		'placeholders' => (object) array_filter( wp_list_pluck( eda_consent_categories(), 'placeholder' ) ),
	);
}

/**
 * GTranslate, when its plugin is active: the "Preferences: translation" category. Its widget
 * script (and with it the auto-switch to the browser language, which loads GTranslate's library
 * from cdn.gtranslate.net and sends the page to translate-pa.googleapis.com) stays inert until
 * allowed; until then the footer shows a local "Language" button that opens the preferences.
 * Storage it creates (localStorage): __GT_TRANSLATE_LANGS, gt_autoswitch.
 *
 * @param array $categories Categories.
 * @return array
 */
function eda_consent_gtranslate( $categories ) {
	if ( ! class_exists( 'GTranslate' ) ) {
		return $categories;
	}
	$categories['preferences'] = array(
		'label'       => __( 'Preferences: translation', 'elite-auto-dealer' ),
		'description' => __( 'Lets GTranslate translate pages with Google’s translation service: into the language you choose, or automatically into your browser’s language when it is one of those offered. GTranslate and Google then receive the page text and your IP address and browser details, and may process them outside your country. The language is remembered in your browser.', 'elite-auto-dealer' ),
		'notice'      => __( 'With your permission, pages can be translated by GTranslate using Google’s translation service, which then receives the page text and your IP address and browser details, possibly outside your country. Translation stays off until you allow it.', 'elite-auto-dealer' ),
		'cleanup'     => array( 'localStorage' => array( '__GT_TRANSLATE_LANGS', 'gt_autoswitch' ) ),
		'placeholder' => array(
			'selector' => '.menu-item-gtranslate',
			'label'    => __( 'Language', 'elite-auto-dealer' ),
			'hint'     => __( 'Language: translation needs your permission. Open privacy preferences', 'elite-auto-dealer' ),
			'focus'    => '.menu-item-gtranslate .gt_float_switcher .gt-selected, .menu-item-gtranslate select.gt_selector',
		),
	);
	return $categories;
}
add_filter( 'eda_consent_categories', 'eda_consent_gtranslate' );

/**
 * Mark GTranslate's widget scripts (handles gt_widget_script_<id>, enqueued while the menu
 * renders) for the Preferences category just before they print, so eda_consent_script_tag()
 * prints them inert. Its inline settings script only defines data and stays as it is.
 */
function eda_consent_gate_gtranslate() {
	foreach ( wp_scripts()->queue as $handle ) {
		if ( str_starts_with( $handle, 'gt_widget_script_' ) ) {
			wp_script_add_data( $handle, 'eda_consent', 'preferences' );
		}
	}
}
add_action( 'wp_print_scripts', 'eda_consent_gate_gtranslate', 1 );
add_action( 'wp_print_footer_scripts', 'eda_consent_gate_gtranslate', 1 );

/**
 * Drop GTranslate's data-gt-orig-domain (the install's hostname, e.g. a .local development host):
 * its scripts fall back to location.hostname, and only the sub-domain URL structure reads it.
 * Keeps the development hostname out of the HTML and static exports.
 *
 * @param string $tag    Script tag(s).
 * @param string $handle Handle.
 * @return string
 */
function eda_gtranslate_tag( $tag, $handle ) {
	return str_starts_with( $handle, 'gt_widget_script_' ) ? (string) preg_replace( '/\sdata-gt-orig-domain="[^"]*"/', '', $tag ) : $tag;
}
add_filter( 'script_loader_tag', 'eda_gtranslate_tag', 15, 2 );

/**
 * Print a script registered for an optional category inert, until the visitor allows it.
 *
 * @param string $tag    Script tag.
 * @param string $handle Script handle.
 * @return string
 */
function eda_consent_script_tag( $tag, $handle ) {
	$category = wp_scripts()->get_data( $handle, 'eda_consent' );
	if ( ! $category || ! eda_consent_active() ) {
		return $tag;
	}
	return (string) preg_replace( '/<script(?=[^>]*\ssrc=)([^>]*)\ssrc=/', '<script type="text/plain" data-eds-consent="' . esc_attr( $category ) . '"$1 data-src=', $tag );
}
add_filter( 'script_loader_tag', 'eda_consent_script_tag', 20, 2 );

/**
 * Consent script and its configuration.
 */
function eda_consent_enqueue() {
	if ( ! eda_consent_active() ) {
		return;
	}
	wp_enqueue_script(
		'eda-consent',
		EDA_URI . '/assets/js/consent.js',
		array(),
		eda_asset_version( 'assets/js/consent.js' ),
		array(
			'strategy'  => 'defer',
			'in_footer' => true,
		)
	);
	wp_add_inline_script( 'eda-consent', 'window.edaConsentConfig = ' . wp_json_encode( eda_consent_config() ) . ';', 'before' );
}
add_action( 'wp_enqueue_scripts', 'eda_consent_enqueue', 20 );

/**
 * Published page URL by slug, or ''.
 *
 * @param string $slug Page slug.
 * @return string
 */
function eda_consent_page_url( $slug ) {
	$page = get_page_by_path( $slug );
	return $page && 'publish' === $page->post_status ? (string) get_permalink( $page ) : '';
}

/**
 * The legal pages, the one source of their URLs (generated by WordPress, so root and subdirectory
 * installs and static exports all work): key => array( url, label ). url is '' while unpublished.
 *
 * @return array<string, array{0: string, 1: string}>
 */
function eda_legal_pages() {
	return array(
		'cookies' => array( eda_consent_page_url( 'cookie-policy' ), __( 'Cookie Policy', 'elite-auto-dealer' ) ),
		'privacy' => array( (string) get_privacy_policy_url(), __( 'Privacy Notice', 'elite-auto-dealer' ) ),
		'legal'   => array( eda_consent_page_url( 'legal-notice' ), __( 'Legal Notice', 'elite-auto-dealer' ) ),
	);
}

/**
 * The legal pages that are published: URL => label. Unpublished pages are left out (no broken links).
 *
 * @return array<string, string>
 */
function eda_legal_links() {
	$links = array();
	foreach ( eda_legal_pages() as list( $url, $label ) ) {
		if ( '' !== $url ) {
			$links[ $url ] = $label;
		}
	}
	return $links;
}

/**
 * Links shown in the banner and the panel.
 *
 * @return string
 */
function eda_consent_links() {
	$out = '';
	foreach ( eda_legal_links() as $url => $label ) {
		$out .= '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
	}
	return $out ? '<p class="eda-consent__links">' . $out . '</p>' : '';
}

/**
 * [eda_detail field="name|location|phone|email|privacy|legal|cookies"]: a dealership detail from
 * Site Settings, or a link to a legal page (eda_legal_pages(), never a hard-coded path), for the
 * legal pages, so they never repeat (or contradict) it. An unset detail prints a
 * bracketed placeholder that bin/legal-check.php reports before launch. The enquiry email is used,
 * never the WordPress admin email (often a private address).
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function eda_detail_shortcode( $atts ) {
	$field = sanitize_key( $atts['field'] ?? '' );
	switch ( $field ) {
		case 'name':
			return esc_html( get_bloginfo( 'name' ) );
		case 'location':
			$value = eda_dealer_location();
			return $value ? esc_html( $value ) : '[' . esc_html__( 'Dealership address', 'elite-auto-dealer' ) . ']';
		case 'phone':
			$value = get_theme_mod( 'eda_phone' );
			return $value ? '<a href="' . esc_url( 'tel:' . eda_phone_digits( $value ) ) . '">' . esc_html( $value ) . '</a>' : '[' . esc_html__( 'Telephone', 'elite-auto-dealer' ) . ']';
		case 'email':
			$value = get_theme_mod( 'eda_enquiry_email' );
			return $value ? '<a href="' . esc_url( 'mailto:' . $value ) . '">' . esc_html( $value ) . '</a>' : '[' . esc_html__( 'Email address', 'elite-auto-dealer' ) . ']';
		case 'privacy':
		case 'legal':
		case 'cookies':
			list( $url, $label ) = eda_legal_pages()[ $field ];
			// Unpublished: the name only, never a broken link.
			return $url ? '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>' : esc_html( $label );
	}
	return '';
}
add_shortcode( 'eda_detail', 'eda_detail_shortcode' );

/**
 * Footer legal menu fallback (no "Footer legal" menu assigned): the published legal pages and
 * Cookie preferences.
 */
function eda_legal_menu_fallback() {
	echo '<ul class="site-footer__legal-list">';
	foreach ( eda_legal_links() as $url => $label ) {
		echo '<li><a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a></li>';
	}
	// #eds-consent is the EDS contract: the theme layer or the EDS Consent plugin opens it.
	echo '<li><a href="#eds-consent">' . esc_html__( 'Cookie preferences', 'elite-auto-dealer' ) . '</a></li></ul>';
}

/**
 * Reject / Accept (and Save in the panel): only when a real optional category exists.
 *
 * @param bool $save Include "Save preferences".
 */
function eda_consent_choice_buttons( $save ) {
	?>
	<button type="button" class="button button--quiet" data-eds-consent-action="reject"><?php esc_html_e( 'Reject optional', 'elite-auto-dealer' ); ?></button>
	<button type="button" class="button button--quiet" data-eds-consent-action="accept"><?php esc_html_e( 'Accept optional', 'elite-auto-dealer' ); ?></button>
	<?php if ( $save ) : ?>
		<button type="button" class="button button--primary" data-eds-consent-action="save"><?php esc_html_e( 'Save preferences', 'elite-auto-dealer' ); ?></button>
		<?php
	endif;
}

/**
 * First layer: early in the document so keyboard users meet it first. Printed only when an
 * optional category exists; hidden until the script finds no valid decision.
 */
function eda_consent_banner() {
	if ( ! eda_consent_active() || ! eda_consent_categories() ) {
		return;
	}
	$notices = array();
	foreach ( eda_consent_categories() as $category ) {
		/* translators: %s: optional service, e.g. "Maps". */
		$notices[] = $category['notice'] ?? sprintf( __( 'With your permission the site can also use: %s.', 'elite-auto-dealer' ), $category['label'] );
	}
	?>
	<section class="eda-consent" aria-labelledby="eda-consent-title" data-eda-consent-banner hidden>
		<h2 class="eda-consent__title" id="eda-consent-title"><?php esc_html_e( 'Your privacy', 'elite-auto-dealer' ); ?></h2>
		<p class="eda-consent__text">
			<?php
			/* translators: %s: one sentence per optional service. */
			echo esc_html( sprintf( __( 'Necessary storage is always active. %s Nothing optional starts until you choose, and you can change your mind at any time under “Cookie preferences”.', 'elite-auto-dealer' ), implode( ' ', $notices ) ) );
			?>
		</p>
		<?php echo eda_consent_links(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in eda_consent_links(). ?>
		<div class="eda-consent__actions">
			<?php eda_consent_choice_buttons( false ); ?>
			<button type="button" class="eda-consent__manage" data-eds-consent-action="manage"><?php esc_html_e( 'Manage preferences', 'elite-auto-dealer' ); ?></button>
		</div>
	</section>
	<?php
}
add_action( 'wp_body_open', 'eda_consent_banner', 5 );

/**
 * Preferences panel: a native modal dialog (focus containment, Escape, top layer). Necessary
 * storage is always listed; optional categories only when registered.
 */
function eda_consent_panel() {
	if ( ! eda_consent_active() ) {
		return;
	}
	$categories = eda_consent_categories();
	?>
	<dialog class="eda-consent-panel" aria-labelledby="eda-consent-panel-title" data-eda-consent-panel>
		<button type="button" class="eda-consent-panel__close" data-eds-consent-action="close" aria-label="<?php esc_attr_e( 'Close without changes', 'elite-auto-dealer' ); ?>">&times;</button>
		<h2 class="eda-consent__title" id="eda-consent-panel-title"><?php esc_html_e( 'Privacy preferences', 'elite-auto-dealer' ); ?></h2>
		<p class="eda-consent__text">
			<?php
			echo esc_html(
				$categories
					? __( 'Choose what you allow. Necessary storage is always active; nothing optional is switched on until you choose it, and you can change your mind at any time under “Cookie preferences” in the footer.', 'elite-auto-dealer' )
					: __( 'This website uses no analytics, advertising or other optional services, so there is nothing to accept or reject. It keeps only the small amount of browser storage described below.', 'elite-auto-dealer' )
			);
			?>
		</p>
		<ul class="eda-consent__categories">
			<li class="eda-consent__category">
				<div>
					<h3 class="eda-consent__category-title"><label for="eda-consent-necessary"><?php esc_html_e( 'Necessary', 'elite-auto-dealer' ); ?></label></h3>
					<p class="eda-consent__category-text" id="eda-consent-necessary-desc"><?php esc_html_e( 'Keeps the website working and, once you have chosen, remembers your privacy choice. On demonstration sites it also remembers the colour palette you preview with “Try Colors” (stored only in your browser). Staff who log in receive WordPress login cookies. Always active.', 'elite-auto-dealer' ); ?></p>
				</div>
				<input class="eda-consent__switch" type="checkbox" role="switch" id="eda-consent-necessary" checked disabled aria-describedby="eda-consent-necessary-desc">
			</li>
			<?php foreach ( $categories as $slug => $category ) : ?>
				<li class="eda-consent__category">
					<div>
						<h3 class="eda-consent__category-title"><label for="eda-consent-<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $category['label'] ); ?></label></h3>
						<p class="eda-consent__category-text" id="eda-consent-<?php echo esc_attr( $slug ); ?>-desc"><?php echo esc_html( $category['description'] ?? '' ); ?></p>
					</div>
					<input class="eda-consent__switch" type="checkbox" role="switch" id="eda-consent-<?php echo esc_attr( $slug ); ?>" data-eds-consent-category="<?php echo esc_attr( $slug ); ?>" aria-describedby="eda-consent-<?php echo esc_attr( $slug ); ?>-desc">
				</li>
			<?php endforeach; ?>
		</ul>
		<?php echo eda_consent_links(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in eda_consent_links(). ?>
		<div class="eda-consent__actions">
			<?php if ( $categories ) : ?>
				<?php eda_consent_choice_buttons( true ); ?>
			<?php else : ?>
				<button type="button" class="button button--primary" data-eds-consent-action="close"><?php esc_html_e( 'Close', 'elite-auto-dealer' ); ?></button>
			<?php endif; ?>
		</div>
	</dialog>
	<?php
}
add_action( 'wp_footer', 'eda_consent_panel', 5 );
