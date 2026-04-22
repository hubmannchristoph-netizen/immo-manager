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
				<div style="display:flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
					<button type="button" class="immo-btn immo-btn-secondary immo-upload-btn"><?php esc_html_e( 'Bilder hochladen', 'immo-manager' ); ?></button>
					<button type="button" class="immo-btn immo-btn-secondary immo-media-btn"><?php esc_html_e( 'Aus Mediathek wählen', 'immo-manager' ); ?></button>
				</div>
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
				<div style="display:flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
					<button type="button" class="immo-btn immo-btn-secondary immo-doc-upload-btn"><?php esc_html_e( 'Dokumente hochladen', 'immo-manager' ); ?></button>
					<button type="button" class="immo-btn immo-btn-secondary immo-doc-media-btn"><?php esc_html_e( 'Aus Mediathek wählen', 'immo-manager' ); ?></button>
				</div>
			</div>
			<div class="immo-upload-preview" id="immo-doc-preview"></div>
			<input type="hidden" name="document_ids" id="immo-document-ids" value="<?php echo esc_attr( implode( ',', array_map( 'absint', (array) ( $prefill['_immo_documents'] ?? array() ) ) ) ); ?>">
		</div>
	<?php endif; ?>
</div>

<div class="immo-wizard-section">
	<h3><?php esc_html_e( 'Video & Virtuelle Tour', 'immo-manager' ); ?></h3>
	<div class="immo-field" style="margin-bottom: 20px;">
		<label for="_immo_video_url"><?php esc_html_e( 'YouTube / Vimeo Link (optional)', 'immo-manager' ); ?></label>
		<input type="url" name="_immo_video_url" id="_immo_video_url" class="immo-wizard-input" value="<?php echo esc_attr( $prefill['_immo_video_url'] ?? '' ); ?>" placeholder="https://www.youtube.com/watch?v=..." style="width: 100%;">
	</div>
	
	<?php if ( is_user_logged_in() ) : ?>
		<div class="immo-wizard-upload" id="immo-video-upload">
			<label style="display:block; font-weight:600; margin-bottom: 8px;"><?php esc_html_e( 'Oder: Video hochladen / auswählen', 'immo-manager' ); ?></label>
			<div class="immo-upload-drop-zone" id="immo-video-drop-zone" style="padding: 1.5rem 1rem;">
				<span class="immo-upload-icon">🎥</span>
				<div style="display:flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
					<button type="button" class="immo-btn immo-btn-secondary immo-video-media-btn"><?php esc_html_e( 'Video aus Mediathek wählen', 'immo-manager' ); ?></button>
				</div>
			</div>
			<div class="immo-upload-preview" id="immo-video-preview" style="margin-top: 10px;"></div>
			<input type="hidden" name="_immo_video_id" id="immo-video-id" class="immo-wizard-input" value="<?php echo esc_attr( (string) ( $prefill['_immo_video_id'] ?? '' ) ); ?>">
		</div>
	<?php endif; ?>
</div>