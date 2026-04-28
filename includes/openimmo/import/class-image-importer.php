<?php
/**
 * Importiert Bilder aus dem ZIP in die WP Media Library.
 *
 * @package ImmoManager
 */

namespace ImmoManager\OpenImmo\Import;

defined( 'ABSPATH' ) || exit;

class ImageImporter {

	public const HASH_META_KEY = '_immo_openimmo_image_hash';

	/**
	 * @param array<int,array{relpath:string, gruppe:string}> $raw_images
	 * @return array<int,array{att_id:int, gruppe:string, hash:string}>
	 */
	public function import_all( array $raw_images, string $images_dir ): array {
		if ( '' === $images_dir || ! is_dir( $images_dir ) ) {
			return array();
		}

		$out = array();
		foreach ( $raw_images as $img ) {
			$relpath = $img['relpath'] ?? '';
			$gruppe  = $img['gruppe'] ?? 'BILD';
			if ( '' === $relpath ) {
				continue;
			}
			$local_path = $images_dir . '/' . basename( $relpath );
			if ( ! file_exists( $local_path ) ) {
				continue;
			}
			$hash = sha1_file( $local_path );
			if ( false === $hash ) {
				continue;
			}

			$existing = $this->find_by_hash( $hash );
			if ( null !== $existing ) {
				$out[] = array( 'att_id' => $existing, 'gruppe' => $gruppe, 'hash' => $hash );
				continue;
			}

			$att_id = $this->sideload( $local_path, basename( $relpath ) );
			if ( null === $att_id ) {
				continue;
			}
			update_post_meta( $att_id, self::HASH_META_KEY, $hash );
			$out[] = array( 'att_id' => $att_id, 'gruppe' => $gruppe, 'hash' => $hash );
		}
		return $out;
	}

	private function find_by_hash( string $hash ): ?int {
		$query = new \WP_Query( array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'no_found_rows'  => true,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'   => self::HASH_META_KEY,
					'value' => $hash,
				),
			),
		) );
		if ( empty( $query->posts ) ) {
			return null;
		}
		return (int) $query->posts[0];
	}

	/**
	 * Lädt eine lokale Datei in die Media Library via wp_handle_sideload.
	 */
	private function sideload( string $local_path, string $desired_filename ): ?int {
		if ( ! function_exists( 'wp_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$tmp_copy = wp_tempnam( $desired_filename );
		if ( ! copy( $local_path, $tmp_copy ) ) {
			return null;
		}

		$file_array = array(
			'name'     => $desired_filename,
			'tmp_name' => $tmp_copy,
		);

		$overrides = array(
			'test_form' => false,
			'test_size' => true,
		);

		$sideload = wp_handle_sideload( $file_array, $overrides );
		if ( isset( $sideload['error'] ) ) {
			@unlink( $tmp_copy );  // phpcs:ignore WordPress.PHP.NoSilencedErrors
			return null;
		}

		$attachment = array(
			'post_mime_type' => $sideload['type'],
			'post_title'     => sanitize_file_name( pathinfo( $desired_filename, PATHINFO_FILENAME ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);
		$att_id = wp_insert_attachment( $attachment, $sideload['file'] );
		if ( is_wp_error( $att_id ) || 0 === (int) $att_id ) {
			return null;
		}
		$meta = wp_generate_attachment_metadata( $att_id, $sideload['file'] );
		wp_update_attachment_metadata( $att_id, $meta );

		return (int) $att_id;
	}
}
