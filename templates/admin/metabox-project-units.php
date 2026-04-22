<?php
/**
 * Template: Wohneinheiten-Verwaltung innerhalb des Project-Editors.
 *
 * Erwartete Variablen (durch den Caller):
 *
 * @var \WP_Post                   $post  Aktuelles Projekt.
 * @var array<int, array<string,mixed>> $units Units dieses Projekts.
 *
 * @package ImmoManager
 */

defined( 'ABSPATH' ) || exit;

if ( ! isset( $post ) || ! isset( $units ) || ! is_array( $units ) ) {
	return;
}

$status_labels = array(
	'available' => __( 'Verfügbar', 'immo-manager' ),
	'reserved'  => __( 'Reserviert', 'immo-manager' ),
	'sold'      => __( 'Verkauft', 'immo-manager' ),
	'rented'    => __( 'Vermietet', 'immo-manager' ),
);

$currency_symbol = \ImmoManager\Settings::get( 'currency_symbol', '€' );
$ajax_nonce      = wp_create_nonce( 'immo_units_ajax' );
?>
<div class="immo-units-manager"
	data-project-id="<?php echo esc_attr( (string) $post->ID ); ?>"
	data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
	data-nonce="<?php echo esc_attr( $ajax_nonce ); ?>"
	data-currency="<?php echo esc_attr( $currency_symbol ); ?>">

	<p class="description">
		<?php esc_html_e( 'Verwalte hier alle Wohneinheiten dieses Bauprojekts. Jede Einheit hat ihren eigenen Preis und Status.', 'immo-manager' ); ?>
	</p>

	<?php if ( $post->ID <= 0 ) : ?>
		<div class="notice notice-info inline">
			<p><?php esc_html_e( 'Bitte speichere das Projekt zuerst, dann kannst du Wohneinheiten anlegen.', 'immo-manager' ); ?></p>
		</div>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped immo-units-table">
			<thead>
				<tr>
					<th class="col-nr"><?php esc_html_e( 'Nr.', 'immo-manager' ); ?></th>
					<th class="col-floor"><?php esc_html_e( 'Etage', 'immo-manager' ); ?></th>
					<th class="col-area"><?php esc_html_e( 'Fläche', 'immo-manager' ); ?></th>
					<th class="col-rooms"><?php esc_html_e( 'Zimmer', 'immo-manager' ); ?></th>
					<th class="col-price"><?php esc_html_e( 'Preis', 'immo-manager' ); ?></th>
					<th class="col-rent"><?php esc_html_e( 'Miete', 'immo-manager' ); ?></th>
					<th class="col-status"><?php esc_html_e( 'Status', 'immo-manager' ); ?></th>
					<th class="col-actions"><?php esc_html_e( 'Aktionen', 'immo-manager' ); ?></th>
				</tr>
			</thead>
			<tbody class="immo-units-list">
				<?php if ( empty( $units ) ) : ?>
					<tr class="immo-no-units"><td colspan="8"><?php esc_html_e( 'Noch keine Wohneinheiten angelegt.', 'immo-manager' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $units as $unit ) : ?>
						<tr data-unit-id="<?php echo esc_attr( (string) $unit['id'] ); ?>">
							<td><?php echo esc_html( (string) $unit['unit_number'] ); ?></td>
							<td><?php echo esc_html( (string) $unit['floor'] ); ?></td>
							<td><?php echo esc_html( number_format_i18n( (float) $unit['area'], 2 ) . ' m²' ); ?></td>
							<td><?php echo esc_html( (string) $unit['rooms'] ); ?></td>
							<td><?php echo esc_html( number_format_i18n( (float) $unit['price'] ) . ' ' . $currency_symbol ); ?></td>
							<td><?php echo $unit['rent'] > 0 ? esc_html( number_format_i18n( (float) $unit['rent'] ) . ' ' . $currency_symbol ) : '—'; ?></td>
							<td>
								<span class="immo-status-badge status-<?php echo esc_attr( (string) $unit['status'] ); ?>">
									<?php echo esc_html( $status_labels[ $unit['status'] ] ?? $unit['status'] ); ?>
								</span>
							</td>
							<td>
								<button type="button" class="button button-small immo-edit-unit"><?php esc_html_e( 'Bearbeiten', 'immo-manager' ); ?></button>
								<button type="button" class="button button-small button-link-delete immo-delete-unit"><?php esc_html_e( 'Löschen', 'immo-manager' ); ?></button>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<p><button type="button" class="button button-primary immo-add-unit">+ <?php esc_html_e( 'Wohneinheit hinzufügen', 'immo-manager' ); ?></button></p>

		<!-- Edit/Add Modal (initially hidden, controlled by JS) -->
		<div class="immo-unit-editor" hidden>
			<h3 class="immo-unit-editor-title"><?php esc_html_e( 'Wohneinheit', 'immo-manager' ); ?></h3>

			<input type="hidden" name="unit_id" value="0" />

			<div class="immo-unit-grid">
				<label>
					<span><?php esc_html_e( 'Nummer/Bezeichnung', 'immo-manager' ); ?></span>
					<input type="text" name="unit_number" class="regular-text" placeholder="<?php esc_attr_e( 'z. B. 1.01 oder Top 5', 'immo-manager' ); ?>" />
				</label>
				<label>
					<span><?php esc_html_e( 'Etage', 'immo-manager' ); ?></span>
					<input type="number" name="floor" value="0" />
				</label>
				<label>
					<span><?php esc_html_e( 'Fläche (m²)', 'immo-manager' ); ?></span>
					<input type="number" name="area" step="0.01" min="0" />
				</label>
				<label>
					<span><?php esc_html_e( 'Wohnfläche (m²)', 'immo-manager' ); ?></span>
					<input type="number" name="usable_area" step="0.01" min="0" />
				</label>
				<label>
					<span><?php esc_html_e( 'Zimmer', 'immo-manager' ); ?></span>
					<input type="number" name="rooms" min="0" />
				</label>
				<label>
					<span><?php esc_html_e( 'Schlafzimmer', 'immo-manager' ); ?></span>
					<input type="number" name="bedrooms" min="0" />
				</label>
				<label>
					<span><?php esc_html_e( 'Badezimmer', 'immo-manager' ); ?></span>
					<input type="number" name="bathrooms" min="0" />
				</label>
				<label>
					<span><?php esc_html_e( 'Status', 'immo-manager' ); ?></span>
					<select name="status">
						<?php foreach ( $status_labels as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>
					<span><?php esc_html_e( 'Kaufpreis', 'immo-manager' ); ?> (<?php echo esc_html( $currency_symbol ); ?>)</span>
					<input type="number" name="price" step="1" min="0" />
				</label>
				<label>
					<span><?php esc_html_e( 'Miete/Monat', 'immo-manager' ); ?> (<?php echo esc_html( $currency_symbol ); ?>)</span>
					<input type="number" name="rent" step="1" min="0" />
				</label>
				<label>
					<span><?php esc_html_e( 'Kontakt-E-Mail', 'immo-manager' ); ?></span>
					<input type="email" name="contact_email" />
				</label>
				<label>
					<span><?php esc_html_e( 'Kontakt-Telefon', 'immo-manager' ); ?></span>
					<input type="text" name="contact_phone" />
				</label>
				<label class="immo-unit-full">
					<span><?php esc_html_e( 'Grundriss (Bild-ID aus Mediathek)', 'immo-manager' ); ?></span>
					<div class="immo-unit-floor-plan">
						<input type="number" name="floor_plan_image_id" min="0" class="small-text" placeholder="Bild-ID">
						<button type="button" class="button immo-select-floor-plan">
							<?php esc_html_e( 'Aus Mediathek wählen', 'immo-manager' ); ?>
						</button>
						<div class="immo-floor-plan-preview"></div>
					</div>
				</label>
				<label class="immo-unit-full">
					<span><?php esc_html_e( 'Beschreibung', 'immo-manager' ); ?></span>
					<textarea name="description" rows="3"></textarea>
				</label>
			</div>

			<p class="immo-unit-actions">
				<button type="button" class="button button-primary immo-save-unit"><?php esc_html_e( 'Speichern', 'immo-manager' ); ?></button>
				<button type="button" class="button immo-cancel-unit"><?php esc_html_e( 'Abbrechen', 'immo-manager' ); ?></button>
				<span class="spinner"></span>
				<span class="immo-unit-message" role="status" aria-live="polite"></span>
			</p>
		</div>
	<?php endif; ?>
</div>
