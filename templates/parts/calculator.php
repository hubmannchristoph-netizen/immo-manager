<?php
/**
 * Akkordeon-Templates: Nebenkosten- und Finanzierungsrechner.
 *
 * Rendert zwei getrennte Akkordeons (Nebenkosten / Finanzierung),
 * jeweils self-contained mit eigenem Kaufpreis-Input und ggf. Unit-Dropdown.
 *
 * Variablen-Erwartung (vom inkludierenden Template gesetzt):
 *   $calc_context = array(
 *       'base_price'        => float,        // Default-Kaufpreis
 *       'commission_free'   => bool,         // Per-Property-Override
 *       'units'             => array,        // [ ['id'=>..,'label'=>..,'price'=>..,'commission_free'=>..], … ]
 *   );
 *
 * @package ImmoManager
 */
defined( 'ABSPATH' ) || exit;

$ctx             = $calc_context ?? array();
$base_price      = (float) ( $ctx['base_price'] ?? 0 );
$commission_free = (bool) ( $ctx['commission_free'] ?? false );
$units           = (array) ( $ctx['units'] ?? array() );

$enable_costs     = (bool) \ImmoManager\Settings::get( 'calc_enable_costs', 1 );
$enable_financing = (bool) \ImmoManager\Settings::get( 'calc_enable_financing', 1 );

if ( ! $enable_costs && ! $enable_financing ) {
	return;
}

$show_table    = (bool)  \ImmoManager\Settings::get( 'calc_show_amortization_table', 1 );
$default_eq    = (int)   \ImmoManager\Settings::get( 'calc_default_equity_pct', 20 );
$default_int   = (float) \ImmoManager\Settings::get( 'calc_default_interest_rate', 3.5 );
$default_term  = (int)   \ImmoManager\Settings::get( 'calc_default_term_years', 25 );
$default_extra = (int)   \ImmoManager\Settings::get( 'calc_default_extra_payment', 0 );

$units_json = ! empty( $units ) ? wp_json_encode( $units ) : '';

/**
 * Render-Helper: Unit-Dropdown.
 */
$render_unit_select = function () use ( $units ) {
	if ( empty( $units ) ) {
		return;
	}
	?>
	<div class="immo-calc-unit-wrap">
		<label class="immo-calc-section-title" style="display:block; margin-bottom:0.4rem;">
			<?php esc_html_e( 'Wohneinheit', 'immo-manager' ); ?>
		</label>
		<select class="immo-calc-unit-select">
			<?php foreach ( $units as $u ) : ?>
				<option value="<?php echo esc_attr( (string) $u['id'] ); ?>"
					data-price="<?php echo esc_attr( (string) $u['price'] ); ?>"
					data-commission-free="<?php echo esc_attr( ! empty( $u['commission_free'] ) ? '1' : '0' ); ?>">
					<?php echo esc_html( $u['label'] ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</div>
	<?php
};
?>

<?php if ( $enable_costs ) : ?>
	<div class="immo-accordion immo-accordion--calculator">
		<button class="immo-accordion-header" aria-expanded="false">
			<?php esc_html_e( '💰 Nebenkostenrechner', 'immo-manager' ); ?>
			<span class="immo-accordion-icon" aria-hidden="true"></span>
		</button>
		<div class="immo-accordion-body" hidden>
			<div class="immo-calculator"
				data-mode="costs"
				data-base-price="<?php echo esc_attr( (string) $base_price ); ?>"
				data-commission-free="<?php echo esc_attr( $commission_free ? '1' : '0' ); ?>"
				<?php if ( $units_json ) : ?> data-units="<?php echo esc_attr( $units_json ); ?>"<?php endif; ?>>

				<?php $render_unit_select(); ?>

				<div class="immo-calculator-panel" data-panel="costs">
					<div class="immo-calc-section immo-calc-section--inputs">
						<h4 class="immo-calc-section-title"><?php esc_html_e( 'Eingabe', 'immo-manager' ); ?></h4>
						<div class="immo-calc-input-row">
							<label class="immo-calc-price-label"><?php esc_html_e( 'Kaufpreis', 'immo-manager' ); ?></label>
							<input type="number" class="immo-calc-price" min="0" step="1" value="<?php echo esc_attr( (string) (int) $base_price ); ?>">
						</div>
					</div>
					<div class="immo-calc-section immo-calc-section--results">
						<h4 class="immo-calc-section-title"><?php esc_html_e( 'Aufstellung', 'immo-manager' ); ?></h4>
						<div class="immo-calc-items"></div>
						<div class="immo-calc-total">
							<span><?php esc_html_e( 'Nebenkosten gesamt', 'immo-manager' ); ?></span>
							<span class="immo-calc-costs-total">0 €</span>
						</div>
						<div class="immo-calc-grand-total">
							<span><?php esc_html_e( 'Gesamtkosten (inkl. Kauf)', 'immo-manager' ); ?></span>
							<span class="immo-calc-grand-total-value">0 €</span>
						</div>
					</div>
				</div>

				<p class="immo-calc-disclaimer">
					<?php esc_html_e( 'Unverbindliche Schätzung – ersetzt keine Bank-/Steuerberatung.', 'immo-manager' ); ?>
				</p>
			</div>
		</div>
	</div>
<?php endif; ?>

<?php if ( $enable_financing ) : ?>
	<div class="immo-accordion immo-accordion--calculator">
		<button class="immo-accordion-header" aria-expanded="false">
			<?php esc_html_e( '📊 Finanzierungsrechner', 'immo-manager' ); ?>
			<span class="immo-accordion-icon" aria-hidden="true"></span>
		</button>
		<div class="immo-accordion-body" hidden>
			<div class="immo-calculator"
				data-mode="financing"
				data-base-price="<?php echo esc_attr( (string) $base_price ); ?>"
				data-commission-free="<?php echo esc_attr( $commission_free ? '1' : '0' ); ?>"
				<?php if ( $units_json ) : ?> data-units="<?php echo esc_attr( $units_json ); ?>"<?php endif; ?>>

				<?php $render_unit_select(); ?>

				<div class="immo-calculator-panel" data-panel="financing">
					<div class="immo-calc-section immo-calc-section--inputs">
						<h4 class="immo-calc-section-title"><?php esc_html_e( 'Eingabe', 'immo-manager' ); ?></h4>

						<div class="immo-calc-input-row">
							<label class="immo-calc-price-label"><?php esc_html_e( 'Kaufpreis', 'immo-manager' ); ?></label>
							<input type="number" class="immo-calc-price" min="0" step="1" value="<?php echo esc_attr( (string) (int) $base_price ); ?>">
						</div>

						<div class="immo-calc-input-row">
							<label>
								<?php esc_html_e( 'Eigenkapital', 'immo-manager' ); ?>
								<span class="immo-calc-equity-toggle" role="group" aria-label="<?php esc_attr_e( 'Einheit', 'immo-manager' ); ?>">
									<button type="button" data-mode="eur"><?php esc_html_e( '€', 'immo-manager' ); ?></button>
									<button type="button" data-mode="pct" class="active"><?php esc_html_e( '%', 'immo-manager' ); ?></button>
								</span>
							</label>
							<div>
								<input type="number" class="immo-calc-equity" min="0" step="1" value="<?php echo esc_attr( (string) $default_eq ); ?>">
								<span class="immo-calc-equity-display">→ <span class="immo-calc-equity-eur">0 €</span></span>
							</div>
						</div>

						<div class="immo-calc-input-row">
							<label><?php esc_html_e( 'Zinssatz (% p.a.)', 'immo-manager' ); ?></label>
							<input type="number" class="immo-calc-interest" min="0" max="20" step="0.1" value="<?php echo esc_attr( (string) $default_int ); ?>">
						</div>
						<div class="immo-calc-input-row">
							<label><?php esc_html_e( 'Laufzeit (Jahre)', 'immo-manager' ); ?></label>
							<input type="number" class="immo-calc-term" min="1" max="50" step="1" value="<?php echo esc_attr( (string) $default_term ); ?>">
						</div>
						<div class="immo-calc-input-row">
							<label><?php esc_html_e( 'Sondertilgung (€/Jahr)', 'immo-manager' ); ?></label>
							<input type="number" class="immo-calc-extra" min="0" step="1" value="<?php echo esc_attr( (string) $default_extra ); ?>">
						</div>
					</div>

					<div class="immo-calc-section immo-calc-section--results">
						<h4 class="immo-calc-section-title"><?php esc_html_e( 'Ergebnis', 'immo-manager' ); ?></h4>

						<div class="immo-calc-row">
							<span class="immo-calc-row-label">
								<?php esc_html_e( 'Finanzierungsbedarf (Kauf + Nebenkosten)', 'immo-manager' ); ?>
							</span>
							<span class="immo-calc-row-amount immo-calc-need">0 €</span>
						</div>
						<div class="immo-calc-row">
							<span class="immo-calc-row-label"><?php esc_html_e( 'Kreditsumme', 'immo-manager' ); ?></span>
							<span class="immo-calc-row-amount immo-calc-loan">0 €</span>
						</div>

						<div class="immo-calc-results">
							<div class="immo-calc-row">
								<span class="immo-calc-row-label"><?php esc_html_e( 'Monatliche Rate', 'immo-manager' ); ?></span>
								<span class="immo-calc-row-amount immo-calc-monthly">0 €</span>
							</div>
							<div class="immo-calc-row">
								<span class="immo-calc-row-label"><?php esc_html_e( 'Gesamt-Zinsen', 'immo-manager' ); ?></span>
								<span class="immo-calc-row-amount immo-calc-total-interest">0 €</span>
							</div>
							<div class="immo-calc-row">
								<span class="immo-calc-row-label"><?php esc_html_e( 'Gesamtaufwand', 'immo-manager' ); ?></span>
								<span class="immo-calc-row-amount immo-calc-total-payment">0 €</span>
							</div>
							<div class="immo-calc-row">
								<span class="immo-calc-row-label"><?php esc_html_e( 'Tilgung-Ende', 'immo-manager' ); ?></span>
								<span class="immo-calc-row-amount immo-calc-end-year">–</span>
							</div>
						</div>

						<?php if ( $show_table ) : ?>
							<button type="button" class="immo-amortization-toggle" aria-expanded="false">
								<?php esc_html_e( '▼ Tilgungsplan anzeigen', 'immo-manager' ); ?>
							</button>
							<div class="immo-amortization-table-wrap" hidden>
								<table class="immo-amortization-table">
									<thead>
										<tr>
											<th><?php esc_html_e( 'Jahr', 'immo-manager' ); ?></th>
											<th><?php esc_html_e( 'Zinsen', 'immo-manager' ); ?></th>
											<th><?php esc_html_e( 'Tilgung', 'immo-manager' ); ?></th>
											<th><?php esc_html_e( 'Restschuld', 'immo-manager' ); ?></th>
										</tr>
									</thead>
									<tbody class="immo-amortization-rows"></tbody>
								</table>
							</div>
						<?php endif; ?>
					</div>
				</div>

				<p class="immo-calc-disclaimer">
					<?php esc_html_e( 'Unverbindliche Schätzung – ersetzt keine Bank-/Steuerberatung.', 'immo-manager' ); ?>
				</p>
			</div>
		</div>
	</div>
<?php endif; ?>
