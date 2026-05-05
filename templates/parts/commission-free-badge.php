<?php
/**
 * Template-Part: „Provisionsfrei"-Badge.
 *
 * @var bool   $show     Ob das Badge gerendert werden soll.
 * @var string $label    Beschriftung (aus Settings).
 * @var string $variant  'patch' = Sticker auf Bild | 'icon' = Inline-Pill.
 *
 * @package ImmoManager
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $show ) ) {
	return;
}

$label   = isset( $label ) && '' !== $label ? (string) $label : __( 'Provisionsfrei', 'immo-manager' );
$variant = isset( $variant ) && 'icon' === $variant ? 'icon' : 'patch';
$class   = 'patch' === $variant ? 'immo-cf-patch' : 'immo-cf-icon';
?>
<span class="immo-cf-badge <?php echo esc_attr( $class ); ?>" aria-label="<?php echo esc_attr( $label ); ?>" title="<?php echo esc_attr( $label ); ?>">
	<?php echo esc_html( $label ); ?>
</span>
