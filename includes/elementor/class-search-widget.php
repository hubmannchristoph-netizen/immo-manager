<?php
/**
 * Elementor Widget: Suchformular.
 *
 * @package ImmoManager
 */

namespace ImmoManager\Elementor;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use ImmoManager\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class SearchWidget
 */
class SearchWidget extends Widget_Base {

	public function get_name() {
		return 'immo-search';
	}

	public function get_title() {
		return __( 'Immobilien-Suche', 'immo-manager' );
	}

	public function get_icon() {
		return 'eicon-search';
	}

	public function get_categories() {
		return array( 'immo-manager' );
	}

	protected function register_controls() {

		$this->start_controls_section(
			'section_layout',
			array(
				'label' => __( 'Layout', 'immo-manager' ),
			)
		);

		$this->add_control(
			'type',
			array(
				'label'   => __( 'Typ', 'immo-manager' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'inline',
				'options' => array(
					'inline'   => __( 'Inline (Horizontaler Balken)', 'immo-manager' ),
					'lightbox' => __( 'Lightbox-Trigger (Lupen-Icon)', 'immo-manager' ),
				),
			)
		);

		$this->add_control(
			'results_url',
			array(
				'label'       => __( 'Suchergebnis-Seite', 'immo-manager' ),
				'type'        => Controls_Manager::URL,
				'placeholder' => __( 'https://ihre-seite.de/immobilien/', 'immo-manager' ),
				'description' => __( 'URL der Seite, auf der die Ergebnisse angezeigt werden.', 'immo-manager' ),
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$res_url  = $settings['results_url']['url'] ?? '';

		if ( 'lightbox' === $settings['type'] ) {
			$this->render_lightbox_trigger();
		} else {
			$this->render_inline_form( $res_url );
		}
	}

	private function render_lightbox_trigger() {
		?>
		<div class="immo-search-trigger" id="immo-search-trigger-<?php echo esc_attr( $this->get_id() ); ?>">
			<button class="immo-btn-search-icon" aria-label="<?php esc_attr_e( 'Suche öffnen', 'immo-manager' ); ?>">
				<span class="immo-icon-search">🔍</span>
			</button>
		</div>
		<script>
		// Logik zum Öffnen der Such-Lightbox.
		jQuery(document).ready(function($) {
			$('#immo-search-trigger-<?php echo esc_js( $this->get_id() ); ?>').on('click', function() {
				// Hier wird die globale Such-Lightbox getriggert.
				if (window.immoOpenSearch) { window.immoOpenSearch(); }
			});
		});
		</script>
		<?php
	}

	private function render_inline_form( $results_url ) {
		?>
		<form action="<?php echo esc_url( $results_url ); ?>" method="get" class="immo-search-inline-form">
			<div class="immo-search-fields">
				<div class="immo-search-field">
					<input type="text" name="immo_search" class="immo-search-autocomplete" autocomplete="off" placeholder="<?php esc_attr_e( 'Ort, PLZ oder Stichwort…', 'immo-manager' ); ?>">
					<div class="immo-search-autocomplete-results"></div>
				</div>
				<div class="immo-search-field">
					<select name="_immo_mode">
						<option value=""><?php esc_html_e( 'Kauf / Miete', 'immo-manager' ); ?></option>
						<option value="sale"><?php esc_html_e( 'Kauf', 'immo-manager' ); ?></option>
						<option value="rent"><?php esc_html_e( 'Miete', 'immo-manager' ); ?></option>
					</select>
				</div>
				<button type="submit" class="immo-btn immo-btn-primary">
					<?php esc_html_e( 'Suchen', 'immo-manager' ); ?>
				</button>
			</div>
		</form>
		<?php
	}
}
