<?php
/**
 * Heizungs-Eingabe: Dropdown mit fester Options-Liste + „Sonstige…"-Freitext-Fallback.
 *
 * Wird im Wizard (Schritt 3) und in der Property-Metabox eingebunden. Speichert
 * weiterhin den finalen Heizungs-String in `_immo_heating` (Schema-kompatibel mit
 * dem bisherigen Freitext-Feld).
 *
 * Erwartete Variablen (vom inkludierenden Template gesetzt):
 *   $heating_field = array(
 *       'current_value' => string,   // aktueller _immo_heating-Wert
 *       'input_name'    => string,   // z. B. "_immo_heating" (Wizard) oder
 *                                    // "immo_meta[_immo_heating]" (Metabox)
 *       'field_id'      => string,   // eindeutige ID-Basis, z. B. "wiz_heating"
 *       'input_class'   => string,   // optional, CSS-Klassen
 *   );
 *
 * @package ImmoManager
 */

defined( 'ABSPATH' ) || exit;

$ctx        = isset( $heating_field ) && is_array( $heating_field ) ? $heating_field : array();
$value      = (string) ( $ctx['current_value'] ?? '' );
$input_name = (string) ( $ctx['input_name']    ?? '_immo_heating' );
$field_id   = (string) ( $ctx['field_id']      ?? 'immo_heating' );
$input_cls  = (string) ( $ctx['input_class']   ?? 'regular-text' );

$options    = \ImmoManager\MetaFields::heating_options();
$is_custom  = '' !== $value && ! array_key_exists( $value, $options );
?>
<div class="immo-heating-field" data-immo-heating>
	<select id="<?php echo esc_attr( $field_id . '_select' ); ?>"
		class="<?php echo esc_attr( $input_cls ); ?>"
		data-immo-heating-select>
		<option value=""<?php selected( '', $value ); ?>><?php esc_html_e( '— bitte wählen —', 'immo-manager' ); ?></option>
		<?php foreach ( $options as $opt_value => $opt_label ) : ?>
			<option value="<?php echo esc_attr( $opt_value ); ?>"<?php selected( ! $is_custom && $opt_value === $value ); ?>>
				<?php echo esc_html( $opt_label ); ?>
			</option>
		<?php endforeach; ?>
		<option value="__custom__"<?php selected( true, $is_custom ); ?>><?php esc_html_e( 'Sonstige …', 'immo-manager' ); ?></option>
	</select>

	<input type="text"
		id="<?php echo esc_attr( $field_id . '_custom' ); ?>"
		class="<?php echo esc_attr( $input_cls ); ?>"
		data-immo-heating-custom
		value="<?php echo esc_attr( $is_custom ? $value : '' ); ?>"
		placeholder="<?php esc_attr_e( 'Bitte Heizungsart eingeben', 'immo-manager' ); ?>"
		style="margin-top:6px;<?php echo $is_custom ? '' : 'display:none;'; ?>">

	<?php // Klasse auch aufs Hidden-Feld – sonst sammelt der Wizard-Submit den Wert nicht ein. ?>
	<input type="hidden"
		id="<?php echo esc_attr( $field_id ); ?>"
		name="<?php echo esc_attr( $input_name ); ?>"
		value="<?php echo esc_attr( $value ); ?>"
		class="<?php echo esc_attr( $input_cls ); ?>"
		data-immo-heating-hidden>
</div>

<?php // Inline-JS: einmal pro Page registrieren (globaler Guard + per-Element-Guard). ?>
<script>
(function () {
	if (window.__immoHeatingFieldInit) { return; }
	window.__immoHeatingFieldInit = true;

	function bindRoot(root) {
		if (root.__immoBound) { return; }
		root.__immoBound = true;
		var sel    = root.querySelector('[data-immo-heating-select]');
		var custom = root.querySelector('[data-immo-heating-custom]');
		var hidden = root.querySelector('[data-immo-heating-hidden]');
		if (!sel || !custom || !hidden) { return; }
		function sync() {
			if (sel.value === '__custom__') {
				custom.style.display = '';
				hidden.value = custom.value;
			} else {
				custom.style.display = 'none';
				hidden.value = sel.value;
			}
		}
		sel.addEventListener('change', sync);
		custom.addEventListener('input', sync);
	}

	function initAll() {
		document.querySelectorAll('[data-immo-heating]').forEach(bindRoot);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initAll);
	} else {
		initAll();
	}

	// MutationObserver für Felder, die per JS nachgeladen werden (z. B. Wizard-Step-Wechsel).
	if (typeof MutationObserver !== 'undefined') {
		new MutationObserver(initAll).observe(document.body, { childList: true, subtree: true });
	}
})();
</script>
