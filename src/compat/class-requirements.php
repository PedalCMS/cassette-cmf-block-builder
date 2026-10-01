<?php
/**
 * Requirements guard for Cassette-CMF Blocks
 *
 * Verifies the parent library (pedalcms/cassette-cmf) and the running
 * WordPress version meet this library's floor before anything registers.
 * On failure the library must register nothing at all — a half-registered
 * block registry can corrupt content — and surface an admin notice instead
 * of fataling.
 *
 * @package PedalCMS\CassetteCMFBlocks
 * @since 0.1.0
 */

namespace PedalCMS\CassetteCMFBlocks\Compat;

use PedalCMS\CassetteCMF\Field\Field_Factory;
use PedalCMS\CassetteCMF\Field\Field_Interface;

/**
 * Class Requirements
 *
 * Static requirement checks, run once on plugins_loaded priority 5.
 */
class Requirements {

	/**
	 * Minimum WordPress version this library targets.
	 *
	 * @var string
	 */
	public const MIN_WP_VERSION = '6.8';

	/**
	 * Minimum pedalcms/cassette-cmf version this library targets.
	 *
	 * @var string
	 */
	public const MIN_CMF_VERSION = '0.1.0';

	/**
	 * Cached result of the last check() call.
	 *
	 * @var array<string, mixed>|null
	 */
	private static ?array $result = null;

	/**
	 * Run all requirement checks.
	 *
	 * @return array<string, mixed> {
	 *     @type bool     $ok     Whether all checks passed.
	 *     @type string[] $errors Human-readable failure reasons, empty when $ok is true.
	 * }
	 */
	public static function check(): array {
		if ( null !== self::$result ) {
			return self::$result;
		}

		$errors = [];

		if ( ! self::cmf_classes_present() ) {
			$errors[] = 'Cassette-CMF Blocks requires pedalcms/cassette-cmf to be installed and loaded.';
		} elseif ( ! self::cmf_interface_compatible() ) {
			$errors[] = 'Cassette-CMF Blocks requires a version of pedalcms/cassette-cmf whose Field_Interface exposes get_schema().';
		} elseif ( ! self::cmf_version_sufficient() ) {
			$errors[] = sprintf(
				'Cassette-CMF Blocks requires pedalcms/cassette-cmf %s or newer.',
				self::MIN_CMF_VERSION
			);
		}

		if ( ! self::wp_version_sufficient() ) {
			$errors[] = sprintf(
				'Cassette-CMF Blocks requires WordPress %s or newer.',
				self::MIN_WP_VERSION
			);
		}

		self::$result = [
			'ok'     => empty( $errors ),
			'errors' => $errors,
		];

		return self::$result;
	}

	/**
	 * Reset the cached check result. Test-only.
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$result = null;
	}

	/**
	 * Whether the parent library's field-factory classes are loaded.
	 *
	 * @return bool
	 */
	private static function cmf_classes_present(): bool {
		return class_exists( Field_Factory::class ) && interface_exists( Field_Interface::class );
	}

	/**
	 * Whether the loaded Field_Interface exposes the get_schema() method this
	 * library relies on to reuse conditional normalisation.
	 *
	 * @return bool
	 */
	private static function cmf_interface_compatible(): bool {
		return method_exists( Field_Interface::class, 'get_schema' );
	}

	/**
	 * Whether the loaded parent library meets the minimum version floor.
	 *
	 * @return bool
	 */
	private static function cmf_version_sufficient(): bool {
		$version = self::cmf_version();

		return null === $version || version_compare( $version, self::MIN_CMF_VERSION, '>=' );
	}

	/**
	 * The loaded parent library's version string, or null when it can't be
	 * determined (no parent installed, or its composer.json is unreadable —
	 * both treated as "assume compatible rather than blocking registration"
	 * by cmf_version_sufficient() above).
	 *
	 * Reads the version from the parent's own composer.json rather than
	 * trusting a constant, since the parent's installed version can vary
	 * per-site depending on which plugin's autoloader wins. Public (not just
	 * used by cmf_version_sufficient() above): Core\Editor_Payload's cache
	 * key needs this too — a site that upgrades the parent library must
	 * invalidate any cached payload, since the parent's own sanitize()/
	 * conditional-normalisation behaviour could change between versions.
	 *
	 * @return string|null
	 */
	public static function cmf_version(): ?string {
		if ( ! class_exists( Field_Factory::class ) ) {
			return null;
		}

		$reflection    = new \ReflectionClass( Field_Factory::class );
		$composer_path = dirname( $reflection->getFileName(), 3 ) . '/composer.json';

		if ( ! file_exists( $composer_path ) ) {
			return null;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$composer = json_decode( (string) file_get_contents( $composer_path ), true );
		$version  = $composer['version'] ?? null;

		return is_string( $version ) && '' !== $version ? $version : null;
	}

	/**
	 * Whether the running WordPress version meets the minimum floor.
	 *
	 * @return bool
	 */
	private static function wp_version_sufficient(): bool {
		global $wp_version;

		if ( empty( $wp_version ) ) {
			return true;
		}

		return version_compare( $wp_version, self::MIN_WP_VERSION, '>=' );
	}
}
