<?php
namespace ImmoManager\OpenImmo\Export;

defined( 'ABSPATH' ) || exit;

/**
 * Erzeugt resizte JPEG-Kopien (max. 1920px, Q85) für Listing-Bilder.
 */
class ImageProcessor {

	public const MAX_WIDTH = 1920;
	public const QUALITY   = 85;

	private string $tmp_base;

	/** @var array<int,array{att_id:int,message:string}> */
	private array $errors = array();

	public function __construct( string $tmp_base ) {
		$this->tmp_base = rtrim( $tmp_base, '/\\' );
		if ( ! is_dir( $this->tmp_base ) ) {
			wp_mkdir_p( $this->tmp_base );
		}
	}

	/**
	 * @return array<int,array{att_id:int,message:string}>
	 */
	public function get_errors(): array {
		return $this->errors;
	}

	/**
	 * Resize alle Bilder eines Listings.
	 *
	 * @return array<int,array{relpath:string,tmppath:string,gruppe:string,att_id:int}>
	 */
	public function resize_attached( ListingDTO $listing ): array {
		$this->errors = array();
		$output       = array();
		$idx          = 0;

		// Featured Image als TITELBILD.
		if ( $listing->featured_image_id ) {
			$rel = sprintf( 'images/%s-%03d.jpg', $listing->external_id, ++$idx );
			$res = $this->resize_one( (int) $listing->featured_image_id, $rel );
			if ( $res ) {
				$res['gruppe'] = 'TITELBILD';
				$output[]      = $res;
			}
		}

		// Galerie als BILD.
		foreach ( $listing->gallery_image_ids as $att_id ) {
			$rel = sprintf( 'images/%s-%03d.jpg', $listing->external_id, ++$idx );
			$res = $this->resize_one( (int) $att_id, $rel );
			if ( $res ) {
				$res['gruppe'] = 'BILD';
				$output[]      = $res;
			}
		}

		return $output;
	}

	/**
	 * @return array{relpath:string,tmppath:string,gruppe:string,att_id:int}|null
	 */
	private function resize_one( int $attachment_id, string $relpath ): ?array {
		$orig_path = get_attached_file( $attachment_id );
		if ( ! $orig_path || ! file_exists( $orig_path ) ) {
			$this->errors[] = array( 'att_id' => $attachment_id, 'message' => 'cannot read attachment file' );
			return null;
		}

		$editor = wp_get_image_editor( $orig_path );
		if ( is_wp_error( $editor ) ) {
			$this->errors[] = array( 'att_id' => $attachment_id, 'message' => $editor->get_error_message() );
			return null;
		}

		$size = $editor->get_size();
		if ( ! empty( $size['width'] ) && (int) $size['width'] > self::MAX_WIDTH ) {
			$editor->resize( self::MAX_WIDTH, null, false );
		}
		$editor->set_quality( self::QUALITY );

		$tmp_path = $this->tmp_base . '/' . str_replace( '/', '-', $relpath );
		$saved    = $editor->save( $tmp_path, 'image/jpeg' );

		if ( is_wp_error( $saved ) || empty( $saved['path'] ) ) {
			$save_message = is_wp_error( $saved ) ? $saved->get_error_message() : 'save failed';
			$this->errors[] = array( 'att_id' => $attachment_id, 'message' => $save_message );
			return null;
		}

		return array(
			'relpath' => $relpath,
			'tmppath' => $saved['path'],
			'gruppe'  => 'BILD',
			'att_id'  => $attachment_id,
		);
	}

	public function cleanup(): void {
		$this->rrmdir( $this->tmp_base );
	}

	private function rrmdir( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$items = scandir( $dir );
		if ( ! is_array( $items ) ) {
			return;
		}
		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}
			$path = $dir . '/' . $item;
			if ( is_dir( $path ) ) {
				$this->rrmdir( $path );
			} else {
				@unlink( $path );  // phpcs:ignore WordPress.PHP.NoSilencedErrors
			}
		}
		@rmdir( $dir );  // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}
}
