<?php
/**
 * Template: Dashboard-Widget im WordPress-Dashboard.
 *
 * Erwartete Variablen (durch den Caller bereitgestellt):
 *
 * @var array<string, int> $stats
 *
 * @package ImmoManager
 */

defined( 'ABSPATH' ) || exit;

if ( ! isset( $stats ) || ! is_array( $stats ) ) {
	return;
}
?>
<div class="immo-dashboard-widget">
	<ul class="immo-stats">
		<li>
			<span class="label"><?php esc_html_e( 'Immobilien gesamt', 'immo-manager' ); ?>:</span>
			<strong><?php echo esc_html( number_format_i18n( $stats['total_properties'] ?? 0 ) ); ?></strong>
		</li>
		<li>
			<span class="label"><?php esc_html_e( 'Veröffentlicht', 'immo-manager' ); ?>:</span>
			<strong><?php echo esc_html( number_format_i18n( $stats['published'] ?? 0 ) ); ?></strong>
		</li>
		<li>
			<span class="label"><?php esc_html_e( 'Bauprojekte', 'immo-manager' ); ?>:</span>
			<strong><?php echo esc_html( number_format_i18n( $stats['total_projects'] ?? 0 ) ); ?></strong>
		</li>
		<li>
			<span class="label"><?php esc_html_e( 'Wohneinheiten', 'immo-manager' ); ?>:</span>
			<strong><?php echo esc_html( number_format_i18n( $stats['total_units'] ?? 0 ) ); ?></strong>
		</li>
		<?php if ( ( $stats['new_inquiries'] ?? 0 ) > 0 ) : ?>
		<li class="immo-stat-alert">
			<span class="label"><?php esc_html_e( 'Neue Anfragen', 'immo-manager' ); ?>:</span>
			<strong><?php echo esc_html( number_format_i18n( $stats['new_inquiries'] ) ); ?></strong>
		</li>
		<?php endif; ?>
	</ul>

	<p>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . \ImmoManager\AdminPages::MENU_SLUG ) ); ?>">
			<?php esc_html_e( 'Zum Immo-Manager-Dashboard →', 'immo-manager' ); ?>
		</a>
	</p>
</div>
