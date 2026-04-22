<?php
/**
 * Wizard Step 7: Kontakt, Titel & Zusammenfassung.
 * @package ImmoManager
 */
defined( 'ABSPATH' ) || exit;
$p = $prefill;
?>
<div class="immo-wizard-step-header">
	<h2 class="immo-wizard-step-title"><?php esc_html_e( 'Abschluss & Zusammenfassung', 'immo-manager' ); ?></h2>
	<p class="immo-wizard-step-sub"><?php esc_html_e( 'Kontaktdaten, optionaler Titel und Überblick aller Eingaben.', 'immo-manager' ); ?></p>
</div>

<div class="immo-wizard-section">
	<h3><?php esc_html_e( 'Anzeige-Titel (optional)', 'immo-manager' ); ?></h3>
	<input type="text" name="title" class="immo-wizard-input immo-input"
		value="<?php echo esc_attr( (string) ( $p['title'] ?? '' ) ); ?>"
		placeholder="<?php esc_attr_e( 'Leer lassen → wird automatisch aus Typ + Ort generiert', 'immo-manager' ); ?>">
</div>

<div class="immo-wizard-section">
	<h3><?php esc_html_e( 'Ansprechpartner / Agent', 'immo-manager' ); ?></h3>
	<div class="immo-wizard-fields">
		<div class="immo-field immo-field--full">
			<label for="wiz_contact_name"><?php esc_html_e( 'Name', 'immo-manager' ); ?></label>
			<input type="text" id="wiz_contact_name" name="_immo_contact_name" class="immo-wizard-input immo-input"
				value="<?php echo esc_attr( (string) ( $p['_immo_contact_name'] ?? '' ) ); ?>" autocomplete="off">
		</div>
		<div class="immo-field immo-field--half">
			<label for="wiz_contact_email"><?php esc_html_e( 'E-Mail', 'immo-manager' ); ?></label>
			<input type="email" id="wiz_contact_email" name="_immo_contact_email" class="immo-wizard-input immo-input"
				value="<?php echo esc_attr( (string) ( $p['_immo_contact_email'] ?? '' ) ); ?>" autocomplete="off">
		</div>
		<div class="immo-field immo-field--half">
			<label for="wiz_contact_phone"><?php esc_html_e( 'Telefon', 'immo-manager' ); ?></label>
			<input type="tel" id="wiz_contact_phone" name="_immo_contact_phone" class="immo-wizard-input immo-input"
				value="<?php echo esc_attr( (string) ( $p['_immo_contact_phone'] ?? '' ) ); ?>" autocomplete="off">
		</div>
	</div>
</div>

<div class="immo-wizard-section">
	<h3><?php esc_html_e( 'Makler Foto', 'immo-manager' ); ?></h3>
	<p class="description"><?php esc_html_e( 'Ein persönliches Foto schafft Vertrauen bei den Interessenten.', 'immo-manager' ); ?></p>
	
	<div class="immo-field immo-field--full">
		<?php
		$agent_img_id  = (int) ( $p['_immo_contact_image_id'] ?? 0 );
		$agent_img_url = $agent_img_id ? wp_get_attachment_image_url( $agent_img_id, 'thumbnail' ) : '';
		?>
		<div class="immo-wizard-agent-image-wrapper" style="display: flex; align-items: center; gap: 15px;">
			<div class="immo-agent-image-preview" style="width: 80px; height: 80px; border-radius: 50%; border: 2px dashed #ccc; display: flex; align-items: center; justify-content: center; overflow: hidden; background: #f9fafb;">
				<img src="<?php echo esc_url( $agent_img_url ); ?>" style="width: 100%; height: 100%; object-fit: cover; <?php echo $agent_img_url ? '' : 'display: none;'; ?>">
				<span class="immo-agent-image-placeholder" style="font-size: 24px; color: #ccc; <?php echo $agent_img_url ? 'display: none;' : ''; ?>">👤</span>
			</div>
			<div>
				<!-- Workaround: als Text-Feld laden und via CSS verstecken, damit das JS es beim Speichern nicht ignoriert -->
				<input type="text" id="_immo_contact_image_id" name="_immo_contact_image_id" value="<?php echo esc_attr( $agent_img_id ); ?>" class="immo-wizard-input" tabindex="-1" style="display:none;">
				
				<button type="button" class="immo-btn immo-btn-secondary immo-wizard-agent-upload-btn" style="margin-bottom: 5px;">
					<?php esc_html_e( 'Foto auswählen', 'immo-manager' ); ?>
				</button>
				
				<button type="button" class="immo-btn immo-btn-secondary immo-wizard-agent-remove-btn" style="margin-bottom: 5px; color: #d63638; border-color: #fadcdc; background: #fcf0f1; <?php echo $agent_img_url ? '' : 'display: none;'; ?>">
					<?php esc_html_e( 'Entfernen', 'immo-manager' ); ?>
				</button>
			</div>
		</div>
	</div>
</div>

<div class="immo-wizard-section">
	<h3><?php esc_html_e( 'Zusammenfassung', 'immo-manager' ); ?></h3>
	<div class="immo-wizard-summary" id="immo-wizard-summary">
		<p class="immo-summary-hint"><?php esc_html_e( 'Zusammenfassung wird angezeigt, sobald du alle Schritte ausgefüllt hast.', 'immo-manager' ); ?></p>
	</div>
</div>

<script>
jQuery(document).ready(function($){
	var agentFrame;
	$('.immo-wizard-agent-upload-btn').on('click', function(e) {
		e.preventDefault();
		var btn = $(this);
		if ( agentFrame ) { agentFrame.open(); return; }
		agentFrame = wp.media({
			title: '<?php esc_js( __( 'Makler Foto auswählen', 'immo-manager' ) ); ?>',
			button: { text: '<?php esc_js( __( 'Verwenden', 'immo-manager' ) ); ?>' },
			multiple: false,
			library: { type: 'image' }
		});
		agentFrame.on('select', function() {
			var attachment = agentFrame.state().get('selection').first().toJSON();
			$('#_immo_contact_image_id').val(attachment.id).trigger('change');
			var imgUrl = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
			btn.closest('.immo-wizard-agent-image-wrapper').find('img').attr('src', imgUrl).show();
			btn.closest('.immo-wizard-agent-image-wrapper').find('.immo-agent-image-placeholder').hide();
			btn.siblings('.immo-wizard-agent-remove-btn').show();
		});
		agentFrame.open();
	});
	$('.immo-wizard-agent-remove-btn').on('click', function(e) {
		e.preventDefault();
		$('#_immo_contact_image_id').val('0').trigger('change');
		$(this).closest('.immo-wizard-agent-image-wrapper').find('img').attr('src', '').hide();
		$(this).closest('.immo-wizard-agent-image-wrapper').find('.immo-agent-image-placeholder').show();
		$(this).hide();
	});
});
</script>