<?php
/**
 * Template: Wizard-Rahmen.
 * @package ImmoManager
 */
defined( 'ABSPATH' ) || exit;
$steps = array(
	1 => __( 'Typ & Modus', 'immo-manager' ),
	2 => __( 'Standort', 'immo-manager' ),
	3 => __( 'Details', 'immo-manager' ),
	4 => __( 'Preis', 'immo-manager' ),
	5 => __( 'Ausstattung', 'immo-manager' ),
	6 => __( 'Medien', 'immo-manager' ),
	7 => __( 'Abschluss', 'immo-manager' ),
);
$step_slugs = array( 1 => 'type', 2 => 'location', 3 => 'details', 4 => 'price', 5 => 'features', 6 => 'media', 7 => 'contact' );
?>
<style>
/* Fix für die Fortschrittsanzeige im Backend/Frontend */
.immo-wizard-progress {
	display: flex;
	justify-content: space-between;
	position: relative;
	margin-bottom: 2.5rem;
	padding: 0;
}
.immo-wizard-step-indicator {
	display: flex;
	flex-direction: column;
	align-items: center;
	z-index: 2;
	flex: 1;
	text-align: center;
	position: relative;
}
.immo-wizard-step-dot {
	width: 32px;
	height: 32px;
	border-radius: 50%;
	background: #fff;
	border: 2px solid #ccc;
	display: flex;
	align-items: center;
	justify-content: center;
	font-weight: bold;
	margin-bottom: 8px;
	color: #666;
	transition: all 0.3s ease;
}
.immo-wizard-step-indicator.active .immo-wizard-step-dot { border-color: #0073aa; color: #0073aa; background: #e5f5fa; }
.immo-wizard-step-indicator.completed .immo-wizard-step-dot { background: #0073aa; border-color: #0073aa; color: #fff; }
.immo-wizard-step-indicator.completed .step-num { display: none; }
.immo-wizard-step-indicator:not(.completed) .step-check { display: none; }
.immo-wizard-step-line {
	position: absolute;
	top: 16px;
	left: 50%;
	width: 100%;
	height: 2px;
	background: #ccc;
	z-index: -1;
}
.immo-wizard-step-line.completed { background: #0073aa; }
.immo-wizard-step-label { font-size: 13px; color: #666; }
.immo-wizard-step-indicator.active .immo-wizard-step-label { color: #0073aa; font-weight: 600; }
.immo-entity-option.selected { border-color: #0073aa !important; background: rgba(0,115,170,0.05) !important; }
.immo-wizard-step-indicator { cursor: pointer; }
.immo-wizard-step-indicator:hover .immo-wizard-step-dot { border-color: #0073aa; }
.immo-wizard-step { display: none; }
.immo-wizard-step.active { display: block; }

/* Nebeneinander-Darstellung für Kacheln (Immobilie, Projekt, Immobilienart etc.) */
.immo-tile-grid { display: flex; flex-wrap: wrap; gap: 1rem; }
.immo-tile-grid > label {
	flex: 1 1 calc(33.333% - 1rem);
	min-width: 150px;
	border: 2px solid var(--immo-border, #ccc); padding: 1rem; border-radius: 8px; cursor: pointer; text-align: center; transition: all 0.2s;
}
.immo-tile-grid > label.selected, .immo-type-tile.selected, .immo-mode-option.selected, .immo-status-option.selected { border-color: #0073aa !important; background: rgba(0,115,170,0.05) !important; }

/* Ausstattungsmerkmale (Step 5) */
.immo-wizard-feature-group { border: none; padding: 0; margin: 0 0 2rem 0; }
.immo-wizard-feature-group legend { font-size: 1.1em; font-weight: bold; margin-bottom: 1rem; width: 100%; border-bottom: 1px solid var(--immo-border, #eee); padding-bottom: 0.5rem; }
.immo-wizard-feature-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 0.75rem; }
.immo-wizard-feature-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; border: 1px solid var(--immo-border, #ccc); border-radius: 6px; cursor: pointer; transition: all 0.2s ease; background: #fff; margin: 0; }
.immo-wizard-feature-item input[type="checkbox"] { display: none; }
.immo-wizard-feature-item.checked { border-color: #0073aa; background: rgba(0,115,170,0.05); }
.immo-feature-icon { font-size: 1.25em; line-height: 1; }
.immo-feature-label { font-size: 0.95em; line-height: 1.2; margin: 0; }

/* Bildergalerie Upload (Step 5) */
.immo-upload-drop-zone { border: 2px dashed var(--immo-border, #ccc); border-radius: 8px; padding: 3rem 1rem; text-align: center; cursor: pointer; transition: all 0.2s ease; background: #fafafa; margin-bottom: 1rem; }
.immo-upload-drop-zone:hover, .immo-upload-drop-zone.drag-over { border-color: #0073aa; background: rgba(0,115,170,0.05); }
.immo-upload-icon { font-size: 2.5em; display: block; margin-bottom: 0.5rem; }
.immo-upload-preview { display: flex; flex-wrap: wrap; gap: 1rem; margin-top: 1rem; }
.immo-preview-item { position: relative; width: 120px; height: 120px; border-radius: 6px; overflow: hidden; border: 1px solid #ddd; background: #fff; display: flex; align-items: center; justify-content: center; }
.immo-preview-item img { width: 100%; height: 100%; object-fit: cover; }
.immo-preview-remove { position: absolute; top: 4px; right: 4px; background: rgba(220, 53, 69, 0.9); color: #fff; border: none; border-radius: 50%; width: 24px; height: 24px; cursor: pointer; font-size: 12px; display: flex; align-items: center; justify-content: center; transition: background 0.2s; padding: 0; line-height: 1; z-index: 10;}
.immo-preview-remove:hover { background: #c82333; }
.immo-preview-uploading .immo-spinner { width: 24px; height: 24px; border: 3px solid #ccc; border-top-color: #0073aa; border-radius: 50%; animation: immo-spin 1s linear infinite; }
@keyframes immo-spin { to { transform: rotate(360deg); } }

/* Allgemeine Options-Kacheln (Step 1 & Step 4) */
.immo-type-grid, .immo-mode-options, .immo-status-options { display: flex; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem; }
.immo-type-tile, .immo-mode-option, .immo-status-option { flex: 1 1 calc(33.333% - 1rem); min-width: 140px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 0.5rem; padding: 1rem; border: 2px solid var(--immo-border, #ccc); border-radius: 8px; cursor: pointer; text-align: center; transition: all 0.2s; background: #fff; }
.immo-type-tile input, .immo-mode-option input, .immo-status-option input { display: none; }
.immo-type-icon, .immo-mode-icon { font-size: 1.5rem; margin-bottom: 0.25rem; }

/* Form Sections & Fields Modernization */
.immo-wizard-section {
	background: #fff;
	padding: 2rem;
	border-radius: 12px;
	border: 1px solid var(--immo-border, #e5e7eb);
	box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
	margin-bottom: 2rem;
}
.immo-wizard-section h3 {
	margin-top: 0;
	border-bottom: 1px solid var(--immo-border, #eee);
	padding-bottom: 0.75rem;
	margin-bottom: 1.5rem;
	color: #1f2937;
	font-size: 1.25rem;
}
.immo-wizard-fields { display: flex; flex-wrap: wrap; gap: 1.5rem; margin-bottom: 1.5rem; }
.immo-field { display: flex; flex-direction: column; gap: 0.5rem; }
.immo-field--full { flex: 1 1 100%; }
.immo-field--half { flex: 1 1 calc(50% - 0.75rem); }
.immo-field--third { flex: 1 1 calc(33.333% - 1rem); }
.immo-field--quarter { flex: 1 1 calc(25% - 1.125rem); }
.immo-field--fifth { flex: 1 1 calc(20% - 1.2rem); }
.immo-field label { font-weight: 600; color: #374151; font-size: 0.95rem; margin-bottom: 0.25rem;}
.immo-input, .immo-select-full { 
	width: 100%; 
	padding: 0.75rem 1rem !important; 
	border: 1px solid #d1d5db !important; 
	border-radius: 8px !important; 
	font-size: 1rem !important; 
	background: #f9fafb !important; 
	transition: all 0.2s ease !important;
	box-sizing: border-box !important;
	color: #111827 !important;
	line-height: 1.5 !important;
	height: auto !important;
}
.immo-input:focus, .immo-select-full:focus { 
	border-color: #0073aa !important; 
	background: #fff !important;
	box-shadow: 0 0 0 3px rgba(0, 115, 170, 0.15) !important; 
	outline: none !important; 
}
.immo-wp-editor-wrap { border-radius: 8px; overflow: hidden; border: 1px solid #d1d5db; }
.immo-wp-editor-wrap iframe { min-height: 250px !important; }

.immo-field-error {
	color: #d63638;
	font-size: 0.85rem;
	margin-top: 0.5rem;
	display: none;
	font-weight: 600;
}
.immo-wizard-error-global {
	background: #fcf0f1;
	border-left: 4px solid #d63638;
	padding: 1rem;
	margin-bottom: 2rem;
	color: #d63638;
	font-weight: bold;
}

/* Status Badges in der Tabelle */
.immo-unit-status-badge {
	display: inline-block; padding: 4px 8px; border-radius: 4px;
	font-size: 0.85em; font-weight: 600; line-height: 1; text-align: center;
	position: static; margin: 0;
}
.status-available { background: #e5f5fa; color: #0073aa; border: 1px solid #c7e6f1; }
.status-reserved { background: #fff8e5; color: #d68b00; border: 1px solid #ffebba; }
.status-sold { background: #fcf0f1; color: #d63638; border: 1px solid #fadcdc; }
.status-rented { background: #f0f0f1; color: #555d66; border: 1px solid #ccd0d4; }

/* Summary Step */
.immo-summary-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
	gap: 2rem;
}
.immo-summary-group h4 {
	font-size: 1.1em;
	margin-top: 0;
	margin-bottom: 1rem;
	border-bottom: 2px solid var(--immo-primary, #0073aa);
	padding-bottom: 0.5rem;
	color: #1f2937;
}
.immo-summary-row { display: flex; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid #f0f0f1; }
.immo-summary-row:last-child { border-bottom: none; }
.immo-summary-label { font-weight: 600; color: #555; }
.immo-summary-value { text-align: right; color: #111; }
.immo-summary-group--full { grid-column: 1 / -1; }

/* Unit Modal (Lightbox) */
.immo-unit-modal-overlay {
	position: fixed; top: 0; left: 0; right: 0; bottom: 0;
	background: rgba(0,0,0,0.6); z-index: 999999;
	display: flex; align-items: center; justify-content: center;
	backdrop-filter: blur(4px);
}
.immo-unit-modal-content {
	background: #fff; border-radius: 12px; padding: 2.5rem;
	width: 100%; max-width: 700px; max-height: 90vh; overflow-y: auto;
	box-shadow: 0 20px 40px rgba(0,0,0,0.2); position: relative;
}
.immo-unit-modal-close {
	position: absolute; top: 1.25rem; right: 1.25rem;
	background: #f3f4f6; border: none; font-size: 1.2rem; cursor: pointer; color: #666;
	width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
	transition: background 0.2s;
}
.immo-unit-modal-close:hover { background: #e5e7eb; color: #000; }

/* Wizard Navigations-Leiste */
.immo-wizard-nav {
	display: flex; justify-content: space-between; align-items: center;
	margin-top: 3rem; padding-top: 1.5rem;
	border-top: 1px solid var(--immo-border, #e5e7eb);
}
.immo-wizard-nav-right { display: flex; gap: 1rem; align-items: center; }
.immo-wizard-autosave-status { flex: 1; text-align: center; color: #6b7280; font-size: 0.9em; padding: 0 1rem; }

.immo-btn {
	padding: 0.75rem 1.5rem; font-size: 1rem; border-radius: 8px; cursor: pointer;
	transition: all 0.2s ease; display: inline-flex; align-items: center; justify-content: center; font-weight: 600;
}
.immo-btn-primary { background: #0073aa; color: #fff; border: 1px solid #0073aa; }
.immo-btn-primary:hover { background: #005177; border-color: #005177; }
.immo-btn-secondary { background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; }
.immo-btn-secondary:hover { background: #e5e7eb; }
</style>

<div class="immo-wizard"
	data-ajax-url="<?php echo esc_url( $ajax_url ); ?>"
	data-api-base="<?php echo esc_url( $api_base ); ?>"
	data-nonce="<?php echo esc_attr( $nonce ); ?>"
	data-rest-nonce="<?php echo esc_attr( $rest_nonce ); ?>"
	data-media-nonce="<?php echo esc_attr( $media_nonce ); ?>"
	data-redirect="<?php echo esc_attr( $redirect ); ?>"
	data-post-id="<?php echo esc_attr( (string) ( $post_id ?? 0 ) ); ?>"
	data-total-steps="<?php echo esc_attr( (string) count( $steps ) ); ?>">

	<div class="immo-wizard-progress" role="progressbar" aria-valuenow="1" aria-valuemin="1" aria-valuemax="<?php echo esc_attr( (string) count( $steps ) ); ?>">
		<?php foreach ( $steps as $n => $label ) : ?>
			<div class="immo-wizard-step-indicator <?php echo 1 === $n ? 'active' : ''; ?>" data-step="<?php echo esc_attr( (string) $n ); ?>">
				<div class="immo-wizard-step-dot">
					<span class="step-num"><?php echo esc_html( (string) $n ); ?></span>
					<span class="step-check">✓</span>
				</div>
				<span class="immo-wizard-step-label"><?php echo esc_html( $label ); ?></span>
				<?php if ( $n < count( $steps ) ) : ?><div class="immo-wizard-step-line"></div><?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>

	<div class="immo-wizard-error-global" hidden role="alert"></div>

	<div class="immo-wizard-body">
		<?php foreach ( $step_slugs as $s => $slug ) :
			$tpl = IMMO_MANAGER_PLUGIN_DIR . "templates/wizard/step-{$s}-{$slug}.php"; ?>
			<div class="immo-wizard-step <?php echo 1 === $s ? 'active' : ''; ?>" data-step="<?php echo esc_attr( (string) $s ); ?>" hidden>
				<?php if ( 1 === $s ) : ?>
					<div class="immo-form-field" style="margin-bottom: 2rem;">
						<label style="display:block; font-size: 1.1em; font-weight:bold; margin-bottom: 1rem;"><?php esc_html_e( 'Was möchtest du anlegen?', 'immo-manager' ); ?></label>
						<div class="immo-tile-grid">
							<label class="immo-entity-option <?php echo 'property' === $entity_type ? 'selected' : ''; ?>">
								<input type="radio" name="entity_type" value="property" <?php checked( 'property', $entity_type ); ?> style="display:none;">
								<div class="immo-tile-content"><strong><?php esc_html_e( 'Eigene Immobilie', 'immo-manager' ); ?></strong><br><small style="color:#666;"><?php esc_html_e( 'Haus, Wohnung, etc.', 'immo-manager' ); ?></small></div>
							</label>
							<label class="immo-entity-option <?php echo 'project' === $entity_type ? 'selected' : ''; ?>">
								<input type="radio" name="entity_type" value="project" <?php checked( 'project', $entity_type ); ?> style="display:none;">
								<div class="immo-tile-content"><strong><?php esc_html_e( 'Bauprojekt', 'immo-manager' ); ?></strong><br><small style="color:#666;"><?php esc_html_e( 'Container für Einheiten', 'immo-manager' ); ?></small></div>
							</label>
						</div>
					</div>
				<?php endif; ?>
				<?php if ( is_readable( $tpl ) ) { include $tpl; } ?>
			</div>
		<?php endforeach; ?>

		<?php if ( 'project' === $entity_type && $post_id > 0 ) : 
			$all_properties = get_posts( array(
				'post_type'      => \ImmoManager\PostTypes::POST_TYPE_PROPERTY,
				'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			) );
		?>
			<div class="immo-wizard-section immo-units-integration" style="margin-top: 3rem; border-top: 2px solid #0073aa; padding-top: 2rem;">
				<h3><?php esc_html_e( 'Wohneinheiten in diesem Projekt', 'immo-manager' ); ?></h3>
				<p><?php esc_html_e( 'Füge Wohneinheiten hinzu und bearbeite deren Preise und Verfügbarkeiten.', 'immo-manager' ); ?></p>
				<div class="immo-units-manager"
					data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
					data-project-id="<?php echo esc_attr( $post_id ); ?>"
					data-nonce="<?php echo esc_attr( wp_create_nonce( \ImmoManager\AdminAjax::UNITS_NONCE ) ); ?>"
					data-currency="<?php echo esc_attr( $currency ); ?>">
					
					<button type="button" class="button button-primary immo-add-unit" style="margin-bottom: 1.5rem;"><?php esc_html_e( '+ Wohneinheit hinzufügen', 'immo-manager' ); ?></button>
					
					<div class="immo-unit-editor immo-unit-modal-overlay" style="display: none;">
						<div class="immo-unit-modal-content">
							<button type="button" class="immo-unit-modal-close immo-cancel-unit" title="<?php esc_attr_e( 'Schließen', 'immo-manager' ); ?>">✕</button>
							<h4 class="immo-unit-editor-title" style="margin-top: 0; font-size: 1.4em; margin-bottom: 1.5rem;"></h4>
							<div class="immo-unit-message" style="margin-bottom: 1rem; color: #d63638;"></div>
							<div class="immo-unit-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
								<input type="hidden" name="unit_id" value="0">
								<div><label style="display:block; font-weight:bold; margin-bottom:6px; font-size:0.95em;">Top/Tür</label><input type="text" name="unit_number" class="immo-input"></div>
								<div><label style="display:block; font-weight:bold; margin-bottom:6px; font-size:0.95em;">Etage (0=EG)</label><input type="number" name="floor" class="immo-input"></div>
								<div><label style="display:block; font-weight:bold; margin-bottom:6px; font-size:0.95em;">Fläche (m²)</label><input type="number" step="0.01" name="area" class="immo-input"></div>
								<div><label style="display:block; font-weight:bold; margin-bottom:6px; font-size:0.95em;">Zimmer</label><input type="number" name="rooms" class="immo-input"></div>
								<div><label style="display:block; font-weight:bold; margin-bottom:6px; font-size:0.95em;">Kaufpreis</label><input type="number" step="0.01" name="price" class="immo-input"></div>
								<div><label style="display:block; font-weight:bold; margin-bottom:6px; font-size:0.95em;">Mietpreis</label><input type="number" step="0.01" name="rent" class="immo-input"></div>
								<div><label style="display:block; font-weight:bold; margin-bottom:6px; font-size:0.95em;">Status</label>
									<select name="status" class="immo-input" style="height: auto;">
										<option value="available">Verfügbar</option>
										<option value="reserved">Reserviert</option>
										<option value="sold">Verkauft</option>
										<option value="rented">Vermietet</option>
									</select>
								</div>
								<div>
									<label style="display:block; font-weight:bold; margin-bottom:6px; font-size:0.95em;"><?php esc_html_e( 'Verknüpfte Immobilie', 'immo-manager' ); ?></label>
									<input type="text" class="immo-input immo-property-search" placeholder="<?php esc_attr_e( 'Suchen...', 'immo-manager' ); ?>" style="margin-bottom: 5px; padding: 6px 10px !important; font-size: 0.9em !important;">
									<select name="property_id" class="immo-input" style="height: auto;">
										<option value="0"><?php esc_html_e( '— Keine —', 'immo-manager' ); ?></option>
										<?php foreach ( $all_properties as $prop ) : ?>
											<option value="<?php echo esc_attr( $prop->ID ); ?>"><?php echo esc_html( $prop->post_title ); ?></option>
										<?php endforeach; ?>
									</select>
								</div>
								<div>
									<label style="display:block; font-weight:bold; margin-bottom:6px; font-size:0.95em;">Grundriss</label>
									<div class="immo-floorplan-preview" style="margin-bottom:8px; max-height:80px; overflow:hidden;"></div>
									<input type="hidden" name="floor_plan_image_id" class="immo-input">
									<button type="button" class="button immo-floorplan-btn">Auswählen</button>
									<button type="button" class="button immo-floorplan-remove" style="display:none; color:#d63638;">✕</button>
								</div>
							</div>
							<div style="display:flex; gap: 1rem; justify-content: flex-end; border-top: 1px solid var(--immo-border, #e5e7eb); padding-top: 1.5rem;">
								<button type="button" class="immo-btn immo-btn-secondary immo-cancel-unit"><?php esc_html_e( 'Abbrechen', 'immo-manager' ); ?></button>
								<button type="button" class="immo-btn immo-btn-primary immo-save-unit"><?php esc_html_e( 'Speichern', 'immo-manager' ); ?></button>
								<span class="spinner" style="float:none; margin: 4px 0 0 0;"></span>
							</div>
						</div>
					</div>

					<table class="wp-list-table widefat fixed striped immo-units-list">
						<thead>
							<tr>
                                <th><?php esc_html_e( 'Einheit', 'immo-manager' ); ?></th>
                                <th><?php esc_html_e( 'Etage', 'immo-manager' ); ?></th>
                                <th><?php esc_html_e( 'Fläche', 'immo-manager' ); ?></th>
                                <th><?php esc_html_e( 'Zimmer', 'immo-manager' ); ?></th>
                                <th><?php esc_html_e( 'Preis', 'immo-manager' ); ?></th>
                                <th><?php esc_html_e( 'Miete', 'immo-manager' ); ?></th>
                                <th><?php esc_html_e( 'Status', 'immo-manager' ); ?></th>
                                <th><?php esc_html_e( 'Aktionen', 'immo-manager' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							global $wpdb;
							$table = \ImmoManager\Database::units_table();
							$units = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE project_id = %d ORDER BY floor ASC, unit_number ASC", $post_id ), ARRAY_A );
							if ( $units ) {
								foreach ( $units as $u ) {
									$area_txt = number_format_i18n( (float) $u['area'], 2 ) . ' m²';
									$price_txt = number_format_i18n( (float) $u['price'] ) . ' ' . $currency;
									$rent_txt = (float) $u['rent'] > 0 ? number_format_i18n( (float) $u['rent'] ) . ' ' . $currency : '—';
									$status_labels = array( 'available' => 'Verfügbar', 'reserved' => 'Reserviert', 'sold' => 'Verkauft', 'rented' => 'Vermietet' );
									$unit_status = in_array( $u['status'] ?? '', array_keys( $status_labels ), true ) ? $u['status'] : 'available';
									echo '<tr data-unit-id="' . esc_attr( $u['id'] ) . '">';
									echo '<td><strong>' . esc_html( $u['unit_number'] ) . '</strong></td>';
									echo '<td>' . esc_html( $u['floor'] ) . '</td>';
									echo '<td>' . esc_html( $area_txt ) . '</td>';
									echo '<td>' . esc_html( $u['rooms'] ) . '</td>';
									echo '<td>' . esc_html( $price_txt ) . '</td>';
									echo '<td>' . esc_html( $rent_txt ) . '</td>';
									echo '<td><span class="immo-unit-status-badge status-' . esc_attr( $unit_status ) . '">' . esc_html( $status_labels[ $unit_status ] ) . '</span></td>';
									echo '<td><button type="button" class="button button-small immo-edit-unit">Bearbeiten</button> <button type="button" class="button button-small button-link-delete immo-delete-unit" style="color: #d63638;">Löschen</button></td>';
									echo '</tr>';
								}
							} else {
								echo '<tr class="immo-no-units"><td colspan="8">' . esc_html__( 'Bisher keine Wohneinheiten angelegt.', 'immo-manager' ) . '</td></tr>';
							}
							?>
						</tbody>
					</table>
				</div>
			</div>
		<?php elseif ( 'project' === $entity_type && 0 === $post_id ) : ?>
			<div class="immo-wizard-section immo-units-integration-placeholder" style="display: none;">
				<h3><?php esc_html_e( 'Wohneinheiten', 'immo-manager' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Speichere das Bauprojekt zuerst, um Wohneinheiten hinzufügen zu können. Nach dem Speichern wird dieser Bereich automatisch freigeschaltet.', 'immo-manager' ); ?></p>
			</div>
		<?php endif; ?>
	</div>

	<script>
	jQuery(document).ready(function($) {
		// Visuelle Aktualisierung für Kachel-Radio-Buttons (Modus, Status, Typ)
		$(document).on('change', '.immo-type-tile input, .immo-mode-option input, .immo-status-option input, .immo-entity-option input', function() {
			var name = $(this).attr('name');
			$('input[name="' + name + '"]').closest('label').removeClass('selected');
			$(this).closest('label').addClass('selected');
		});

		// Visuelle Aktualisierung für Checkboxen (Ausstattung)
		$(document).on('change', '.immo-wizard-feature-item input[type="checkbox"]', function() {
			$(this).closest('label').toggleClass('checked', $(this).is(':checked'));
		});

		// Suchfunktion für Immobilien-Dropdown
		$(document).on('input', '.immo-property-search', function() {
			var term = $(this).val().toLowerCase();
			var $select = $(this).siblings('select');
			if (!$select.data('options')) {
				$select.data('options', $select.find('option').clone());
			}
			var currentVal = $select.val();
			var $options = $select.data('options').filter(function() {
				return $(this).val() === "0" || $(this).text().toLowerCase().indexOf(term) > -1;
			});
			$select.empty().append($options);
			if ($select.find('option[value="' + currentVal + '"]').length) {
				$select.val(currentVal);
			}
		});
		// Suchfeld beim Öffnen des Modals leeren und Dropdown zurücksetzen
		$(document).on('click', '.immo-add-unit, .immo-edit-unit', function() {
			$('.immo-property-search').val('').trigger('input');
		});
	});
	</script>

	<div class="immo-wizard-nav">
		<button type="button" class="immo-btn immo-btn-secondary immo-wizard-prev" hidden>← <?php esc_html_e( 'Zurück', 'immo-manager' ); ?></button>
		<span class="immo-wizard-autosave-status" aria-live="polite"></span>
		<div class="immo-wizard-nav-right">
			<?php if ( 'publish' !== ( $post_id ? get_post_status( $post_id ) : '' ) ) : ?>
				<button type="button" class="immo-btn immo-btn-secondary immo-wizard-draft"><?php esc_html_e( 'Als Entwurf speichern', 'immo-manager' ); ?></button>
			<?php endif; ?>
			<button type="button" class="immo-btn immo-btn-primary immo-wizard-next"><?php esc_html_e( 'Weiter', 'immo-manager' ); ?> →</button>
			<button type="button" class="immo-btn immo-btn-primary immo-wizard-submit" hidden>
				<span class="immo-wizard-submit-text"><?php echo $is_edit ? esc_html__( 'Änderungen speichern', 'immo-manager' ) : esc_html__( 'Immobilie veröffentlichen', 'immo-manager' ); ?></span>
				<span class="immo-wizard-spinner" hidden>⟳</span>
			</button>
		</div>
	</div>

	<div class="immo-wizard-success" hidden>
		<div class="immo-wizard-success-inner">
			<div class="immo-wizard-success-icon">🎉</div>
			<h3><?php esc_html_e( 'Erfolgreich gespeichert!', 'immo-manager' ); ?></h3>
			<p class="immo-wizard-success-message"></p>
			<a href="#" class="immo-btn immo-btn-primary immo-wizard-success-link" target="_blank" rel="noopener"><?php esc_html_e( 'Immobilie ansehen', 'immo-manager' ); ?></a>
		</div>
	</div>
</div>
