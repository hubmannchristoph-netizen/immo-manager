<?php
/**
 * Template: Plugin-Settings-Seite mit JS-basierten Tabs.
 *
 * @package ImmoManager
 */

defined( 'ABSPATH' ) || exit;

$tabs = array(
	'general'     => array( 'label' => __( 'Allgemein', 'immo-manager' ), 'icon' => '⚙️', 'section' => 'immo_general' ),
	'design'      => array( 'label' => __( 'Design & Layout', 'immo-manager' ), 'icon' => '🎨', 'section' => 'immo_design' ),
	'features'    => array( 'label' => __( 'Module', 'immo-manager' ), 'icon' => '🧩', 'section' => 'immo_features' ),
	'contact'     => array( 'label' => __( 'Kontakt', 'immo-manager' ), 'icon' => '📧', 'section' => 'immo_contact' ),
	'maps'        => array( 'label' => __( 'Karten', 'immo-manager' ), 'icon' => '🗺️', 'section' => 'immo_maps' ),
	'api'         => array( 'label' => __( 'API & Integration', 'immo-manager' ), 'icon' => '🔌', 'section' => 'immo_api' ),
);
?>
<div class="wrap immo-manager-admin immo-settings-page">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Immo Manager – Einstellungen', 'immo-manager' ); ?></h1>
	<hr class="wp-header-end">

	<?php settings_errors(); ?>

	<h2 class="nav-tab-wrapper">
		<?php $i = 0; foreach ( $tabs as $id => $tab ) : ?>
			<a href="#tab-<?php echo esc_attr( $id ); ?>" 
			   class="nav-tab <?php echo 0 === $i ? 'nav-tab-active' : ''; ?>" 
			   data-tab="<?php echo esc_attr( $id ); ?>">
				<?php echo esc_html( $tab['icon'] . ' ' . $tab['label'] ); ?>
			</a>
		<?php $i++; endforeach; ?>
	</h2>

	<form method="post" action="options.php" class="immo-settings-form">
		<?php
		settings_fields( \ImmoManager\Settings::SETTINGS_GROUP );
		
		global $wp_settings_sections, $wp_settings_fields;
		$sections = $wp_settings_sections[ \ImmoManager\Settings::MENU_SLUG ] ?? array();
		
		foreach ( $tabs as $id => $tab ) :
			$section_id = $tab['section'];
			if ( ! isset( $sections[ $section_id ] ) ) continue;
			
			$section = $sections[ $section_id ];
			?>
			<div id="immo-tab-content-<?php echo esc_attr( $id ); ?>" class="immo-tab-content" <?php echo 'general' !== $id ? 'style="display:none;"' : ''; ?>>
				<?php
				if ( $section['title'] ) {
					echo "<h2>" . esc_html( $section['title'] ) . "</h2>\n";
				}
				if ( $section['callback'] ) {
					call_user_func( $section['callback'], $section );
				}
				
				if ( isset( $wp_settings_fields[ \ImmoManager\Settings::MENU_SLUG ][ $section_id ] ) ) {
					echo '<table class="form-table" role="presentation">';
					do_settings_fields( \ImmoManager\Settings::MENU_SLUG, $section_id );
					echo '</table>';
				}
				?>
			</div>
		<?php endforeach; ?>

		<?php submit_button(); ?>
	</form>
</div>

<script>
jQuery(document).ready(function($) {
	var $tabs = $('.immo-settings-page .nav-tab');
	var $contents = $('.immo-tab-content');

	$tabs.on('click', function(e) {
		e.preventDefault();
		var target = $(this).data('tab');

		$tabs.removeClass('nav-tab-active');
		$(this).addClass('nav-tab-active');

		$contents.hide();
		$('#immo-tab-content-' + target).show();
		
		// URL-Hash aktualisieren ohne Scroll
		if (history.pushState) {
			history.pushState(null, null, '#tab-' + target);
		} else {
			location.hash = 'tab-' + target;
		}
	});

	// Hash beim Laden prüfen
	var hash = window.location.hash.replace('#tab-', '');
	if (hash && $('.nav-tab[data-tab="' + hash + '"]').length) {
		$('.nav-tab[data-tab="' + hash + '"]').trigger('click');
	}
});
</script>

<style>
.immo-settings-page .nav-tab-wrapper {
	margin-bottom: 0;
}
.immo-settings-page .nav-tab {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	cursor: pointer;
}
.immo-settings-form {
	background: #fff;
	border: 1px solid #ccd0d4;
	border-top: none;
	padding: 20px 30px;
	max-width: 1200px;
}
.immo-settings-form h2 {
	margin-top: 0;
	padding-bottom: 10px;
	border-bottom: 1px solid #eee;
}
.immo-tab-content {
	animation: immoFadeIn 0.2s ease-out;
}
@keyframes immoFadeIn {
	from { opacity: 0; transform: translateY(5px); }
	to { opacity: 1; transform: translateY(0); }
}
</style>
