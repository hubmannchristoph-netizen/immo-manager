<?php
/**
 * Template: Plugin-Dashboard-Seite.
 *
 * Erwartete Variablen (durch den Caller bereitgestellt):
 *
 * @var array<string, int> $stats Statistiken vom Dashboard-Handler.
 *
 * @package ImmoManager
 */

defined( 'ABSPATH' ) || exit;

// Fallback, falls $stats nicht gesetzt ist.
if ( ! isset( $stats ) || ! is_array( $stats ) ) {
	$stats = array(
		'total_properties' => 0,
		'published'        => 0,
		'drafts'           => 0,
		'total_projects'   => 0,
		'new_inquiries'    => 0,
	);
}
?>
<div class="wrap immo-manager-admin">
	<h1><?php esc_html_e( 'Immo Manager', 'immo-manager' ); ?></h1>

	<div class="immo-welcome-panel">
		<h2><?php esc_html_e( 'Willkommen beim Immo Manager', 'immo-manager' ); ?></h2>
		<p>
			<?php esc_html_e( 'Verwalte hier Immobilien, Bauprojekte und Anfragen – alles zentral an einem Ort.', 'immo-manager' ); ?>
		</p>
	</div>

	<h2><?php esc_html_e( 'Überblick', 'immo-manager' ); ?></h2>

	<div class="immo-stats-grid">
		<div class="immo-stat-card">
			<div class="number"><?php echo esc_html( number_format_i18n( $stats['total_properties'] ) ); ?></div>
			<div class="label"><?php esc_html_e( 'Immobilien gesamt', 'immo-manager' ); ?></div>
		</div>

		<div class="immo-stat-card">
			<div class="number"><?php echo esc_html( number_format_i18n( $stats['published'] ) ); ?></div>
			<div class="label"><?php esc_html_e( 'Veröffentlicht', 'immo-manager' ); ?></div>
		</div>

		<div class="immo-stat-card">
			<div class="number"><?php echo esc_html( number_format_i18n( $stats['total_projects'] ) ); ?></div>
			<div class="label"><?php esc_html_e( 'Bauprojekte', 'immo-manager' ); ?></div>
		</div>

		<div class="immo-stat-card">
			<div class="number"><?php echo esc_html( number_format_i18n( $stats['total_units'] ) ); ?></div>
			<div class="label"><?php esc_html_e( 'Wohneinheiten', 'immo-manager' ); ?></div>
		</div>

		<div class="immo-stat-card">
			<div class="number"><?php echo esc_html( number_format_i18n( $stats['drafts'] ) ); ?></div>
			<div class="label"><?php esc_html_e( 'Entwürfe', 'immo-manager' ); ?></div>
		</div>

		<div class="immo-stat-card<?php echo $stats['new_inquiries'] > 0 ? ' immo-stat-card--highlight' : ''; ?>">
			<div class="number"><?php echo esc_html( number_format_i18n( $stats['new_inquiries'] ) ); ?></div>
			<div class="label"><?php esc_html_e( 'Neue Anfragen', 'immo-manager' ); ?></div>
		</div>
	</div>

	<div class="immo-quick-links">
		<h3><?php esc_html_e( 'Schnellzugriff', 'immo-manager' ); ?></h3>
		<p>
			<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . \ImmoManager\PostTypes::POST_TYPE_PROPERTY ) ); ?>" class="button button-primary">
				<?php esc_html_e( '+ Neue Immobilie', 'immo-manager' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . \ImmoManager\PostTypes::POST_TYPE_PROPERTY ) ); ?>" class="button">
				<?php esc_html_e( 'Alle Immobilien', 'immo-manager' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . \ImmoManager\PostTypes::POST_TYPE_PROJECT ) ); ?>" class="button">
				<?php esc_html_e( '+ Neues Bauprojekt', 'immo-manager' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . \ImmoManager\Settings::MENU_SLUG ) ); ?>" class="button">
				<?php esc_html_e( 'Einstellungen', 'immo-manager' ); ?>
			</a>
		</p>
	</div>

	<!-- Demo-Daten -->
	<div class="immo-demo-section"
		data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
		data-nonce="<?php echo esc_attr( wp_create_nonce( 'immo_demo_import' ) ); ?>"
		data-has-demo="<?php echo esc_attr( \ImmoManager\DemoData::has_demo_data() ? '1' : '0' ); ?>">
		<h3><?php esc_html_e( 'Demo-Daten', 'immo-manager' ); ?></h3>
		<p><?php esc_html_e( 'Demo-Daten helfen dir, das Plugin schnell kennenzulernen: 4 Muster-Immobilien (Wien, Graz, Salzburg, Innsbruck) und 2 Bauprojekte mit Wohneinheiten.', 'immo-manager' ); ?></p>
		<p>
			<button type="button" class="button button-primary immo-import-demo">
				<?php esc_html_e( 'Demo-Daten importieren', 'immo-manager' ); ?>
			</button>
			<button type="button" class="button immo-remove-demo" <?php echo ! \ImmoManager\DemoData::has_demo_data() ? 'disabled' : ''; ?>>
				<?php esc_html_e( 'Demo-Daten entfernen', 'immo-manager' ); ?>
			</button>
			<span class="immo-demo-status" aria-live="polite"></span>
		</p>
		<div class="immo-demo-spinner" style="display:none;"><?php esc_html_e( 'Bitte warten…', 'immo-manager' ); ?></div>
	</div>

	<div class="immo-info-panel">
		<h3><?php esc_html_e( 'Hinweise', 'immo-manager' ); ?></h3>
		<ul>
			<li><?php esc_html_e( 'Nach der Aktivierung bitte einmal unter "Einstellungen → Permalinks" speichern, damit die URL-Struktur (/immobilien/, /projekte/) korrekt greift.', 'immo-manager' ); ?></li>
			<li><?php esc_html_e( 'Status-basierte Statistiken (Verfügbar/Reserviert/Verkauft) werden ab Phase 3 verfügbar, sobald die Immobilien-Metafelder implementiert sind.', 'immo-manager' ); ?></li>
		</ul>
	</div>
</div>
