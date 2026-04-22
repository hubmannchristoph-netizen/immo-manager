<?php
/**
 * Wizard Step 6: Medien (Bilder & Dokumente).
 * @package ImmoManager
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="immo-wizard-step-header">
	<h2 class="immo-wizard-step-title"><?php esc_html_e( 'Medien & Dokumente', 'immo-manager' ); ?></h2>
	<p class="immo-wizard-step-sub"><?php esc_html_e( 'Lade hier alle relevanten Bilder und Dokumente wie Exposés oder Grundrisse hoch.', 'immo-manager' ); ?></p>
</div>

<div class="immo-wizard-section">
	<h3><?php esc_html_e( 'Fotos hochladen', 'immo-manager' ); ?></h3>
	<?php if ( is_user_logged_in() ) : ?>
		<div class="immo-wizard-upload" id="immo-wizard-upload">
			<div class="immo-upload-drop-zone" id="immo-drop-zone">
				<span class="immo-upload-icon">📸</span>
				<p><?php esc_html_e( 'Bilder hierher ziehen oder klicken zum Auswählen', 'immo-manager' ); ?></p>
				<p class="immo-upload-hint"><?php esc_html_e( 'JPEG, PNG, WebP – max. 8 MB pro Bild', 'immo-manager' ); ?></p>
				<input type="file" id="immo-file-input" multiple accept="image/*" style="display:none">
				<button type="button" class="immo-btn immo-btn-secondary immo-upload-btn"><?php esc_html_e( 'Bilder auswählen', 'immo-manager' ); ?></button>
			</div>
			<div class="immo-upload-preview" id="immo-upload-preview"></div>
			<input type="hidden" name="gallery_ids" id="immo-gallery-ids" value="<?php echo esc_attr( implode( ',', array_map( 'absint', (array) ( $prefill['_immo_gallery'] ?? array() ) ) ) ); ?>">
			<p class="immo-upload-hint"><?php esc_html_e( 'Das erste Bild wird als Titelbild verwendet.', 'immo-manager' ); ?></p>
		</div>
	<?php endif; ?>
</div>

<div class="immo-wizard-section">
	<h3><?php esc_html_e( 'Dokumente & Exposé', 'immo-manager' ); ?></h3>
	<?php if ( is_user_logged_in() ) : ?>
		<div class="immo-wizard-upload" id="immo-doc-upload">
			<div class="immo-upload-drop-zone" id="immo-doc-drop-zone">
				<span class="immo-upload-icon">📄</span>
				<p><?php esc_html_e( 'Exposés, Grundrisse oder PDFs hierher ziehen', 'immo-manager' ); ?></p>
				<input type="file" id="immo-doc-file-input" multiple accept=".pdf,.doc,.docx,.xls,.xlsx" style="display:none">
				<button type="button" class="immo-btn immo-btn-secondary immo-doc-upload-btn"><?php esc_html_e( 'Dokumente auswählen', 'immo-manager' ); ?></button>
			</div>
			<div class="immo-upload-preview" id="immo-doc-preview"></div>
			<input type="hidden" name="document_ids" id="immo-document-ids" value="<?php echo esc_attr( implode( ',', array_map( 'absint', (array) ( $prefill['_immo_documents'] ?? array() ) ) ) ); ?>">
		</div>
	<?php endif; ?>
</div>