<?php
/**
 * Block_Manager class for Cassette-CMF Blocks
 *
 * Central registry for block configuration. Mirrors the parent library's
 * Core\Manager singleton pattern (register_from_array / register_from_json,
 * an id-suffixed filter on the whole config, late-registration handling).
 *
 * Two stores are kept deliberately separate:
 *   - $blocks holds raw, unvalidated config arrays as supplied by the consumer.
 *   - $registry (Block_Registry) holds compiled Block_Definition instances,
 *     built lazily and only once each field/attribute config has passed
 *     Field_Collection/Attribute_Schema_Mapper validation.
 *
 * @package PedalCMS\CassetteCMFBlocks
 * @since 0.2.0
 */

namespace PedalCMS\CassetteCMFBlocks\Core;

use PedalCMS\CassetteCMFBlocks\Binding\Binding_Source_Registrar;
use PedalCMS\CassetteCMFBlocks\Binding\Meta_Registrar;
use PedalCMS\CassetteCMFBlocks\Compat\Requirements;
use PedalCMS\CassetteCMFBlocks\Json\Block_Schema_Validator;
use PedalCMS\CassetteCMFBlocks\Rest\Config_Controller;
use PedalCMS\CassetteCMFBlocks\Rest\Transform_Controller;

/**
 * Class Block_Manager
 */
class Block_Manager {

	/**
	 * Every boot option with its default. The key order is significant: the
	 * resolved options are compared with === to detect a conflicting second
	 * boot() call.
	 *
	 * - prefix (string): REQUIRED in practice. Derives every identifier that
	 *   must not collide between two copies of the library (see Identity). The
	 *   default is only the fallback used, with a _doing_it_wrong() notice,
	 *   when no valid prefix is given.
	 * - bindings_source (bool): register the block bindings source named by
	 *   Identity::binding_source_name(). Default false (opt-in).
	 *
	 * @var array<string, mixed>
	 */
	private const DEFAULT_OPTIONS = [
		'prefix'          => Identity::FALLBACK_PREFIX,
		'bindings_source' => false,
	];

	/**
	 * The singleton instance.
	 *
	 * @var Block_Manager|null
	 */
	private static ?Block_Manager $instance = null;

	/**
	 * Boot options, or null while the library has not been booted yet.
	 *
	 * Set by boot() or, when register_from_*() / init() runs first, by the
	 * implicit boot with defaults. Adding an option means adding one entry to
	 * DEFAULT_OPTIONS (and, when it needs validation beyond the default
	 * type check, one case in sanitize_option()).
	 *
	 * @var array<string, mixed>|null
	 */
	private static ?array $options = null;

	/**
	 * Raw block configuration, keyed by block name.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $blocks = [];

	/**
	 * Compiled block definitions.
	 *
	 * @var Block_Registry
	 */
	private Block_Registry $registry;

	/**
	 * Block names that have already been passed to register_block_type()
	 * successfully. WP_Block_Type_Registry::register() refuses (and
	 * _doing_it_wrong()s) a second registration of the same name, so this
	 * makes register_blocks() idempotent across repeated calls.
	 *
	 * @var array<string, bool>
	 */
	private array $registered_names = [];

	/**
	 * Accumulated top-level "block_categories" entries across every
	 * register_from_array() call, in declaration order.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $block_categories = [];

	/**
	 * Accumulated top-level "editor_scope" entries across every
	 * register_from_array() call, in declaration order.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $editor_scopes = [];

	/**
	 * Accumulated top-level "patterns" entries across every
	 * register_from_array() call, in declaration order.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $patterns = [];

	/**
	 * Accumulated top-level "pattern_categories" entries across every
	 * register_from_array() call, in declaration order.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $pattern_categories = [];

	/**
	 * Private constructor to prevent direct instantiation.
	 */
	private function __construct() {
		$this->registry = new Block_Registry();

		if ( function_exists( 'add_action' ) ) {
			add_action( 'init', [ $this, 'register_blocks' ], 10 );
		}

		Asset_Loader::register();
		Category_Registrar::register();
		Editor_Scope_Registrar::register();
		Meta_Registrar::register();
		Binding_Source_Registrar::register();
		Pattern_Registrar::register();
		Config_Controller::register();
		Transform_Controller::register();
	}

	/**
	 * Get the singleton instance, creating it if necessary.
	 *
	 * @return Block_Manager
	 */
	public static function init(): Block_Manager {
		if ( null === self::$options ) {
			// Implicit boot: no boot() call came first, so there is no prefix.
			self::doing_it_wrong(
				__METHOD__,
				sprintf(
					'Cassette-CMF Blocks was used without CassetteCMFBlocks::boot( [ \'prefix\' => \'your-prefix\' ] ); the fallback prefix "%s" is used, which can collide with another plugin that does the same. Call boot() with your own prefix before registering blocks.',
					Identity::FALLBACK_PREFIX
				)
			);

			self::$options = self::DEFAULT_OPTIONS;
		}

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * The booted singleton, or null when none exists yet. Unlike init() it
	 * never creates the instance and never raises the missing-prefix notice.
	 *
	 * @return Block_Manager|null
	 */
	public static function get_instance(): ?Block_Manager {
		return self::$instance;
	}

	/**
	 * Boot the library with options. Call it before register_from_*().
	 *
	 * Unknown option keys, and values of the wrong type, trigger
	 * _doing_it_wrong() and are ignored. If the library is already booted
	 * (explicitly, or implicitly because register_from_*() / init() ran first)
	 * with different options, the call triggers _doing_it_wrong() and is
	 * ignored: the first boot wins.
	 *
	 * @api
	 * @since 0.2.0
	 * @param array<string, mixed> $options {
	 *     Optional. Boot options.
	 *
	 *     @type string $prefix          Required. Lowercase letters, digits and hyphens, 3-32 characters,
	 *                                   starting with a letter. Derives the handles, REST namespace, binding
	 *                                   source name and JS global (see Identity). Missing or invalid: a
	 *                                   _doing_it_wrong() notice and the fallback prefix.
	 *     @type bool   $bindings_source Register the block bindings source. Default false.
	 * }
	 * @return Block_Manager
	 */
	public static function boot( array $options = [] ): Block_Manager {
		$resolved = self::resolve_options( $options );

		if ( null === self::$options ) {
			self::$options = $resolved;
		} elseif ( self::$options !== $resolved ) {
			self::doing_it_wrong(
				__METHOD__,
				'Cassette-CMF Blocks was already booted with different options (call boot() before register_from_*() or init()). The new options are ignored.'
			);
		}

		return self::init();
	}

	/**
	 * Read one boot option. Returns the default while not booted.
	 *
	 * @api
	 * @since 0.2.0
	 * @param string $key Option key, e.g. "bindings_source".
	 * @return mixed The option value, or null for an unknown key.
	 */
	public static function get_option( string $key ) {
		$options = self::$options ?? self::DEFAULT_OPTIONS;

		return $options[ $key ] ?? null;
	}

	/**
	 * Reset the singleton and the boot state. Test-only.
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$instance = null;
		self::$options  = null;
	}

	/**
	 * Merge caller options over the defaults, rejecting unknown keys and
	 * wrongly-typed values.
	 *
	 * @param array<string, mixed> $options Caller options.
	 * @return array<string, mixed>
	 */
	private static function resolve_options( array $options ): array {
		$resolved = self::DEFAULT_OPTIONS;

		if ( ! array_key_exists( 'prefix', $options ) ) {
			self::doing_it_wrong(
				__METHOD__,
				sprintf(
					'The "prefix" boot option is required (for example CassetteCMFBlocks::boot( [ \'prefix\' => \'acme\' ] )); the fallback prefix "%s" is used, which can collide with another plugin that does the same.',
					Identity::FALLBACK_PREFIX
				)
			);
		}

		foreach ( $options as $key => $value ) {
			if ( ! is_string( $key ) || ! array_key_exists( $key, self::DEFAULT_OPTIONS ) ) {
				self::doing_it_wrong(
					__METHOD__,
					sprintf( 'Unknown boot option "%s" ignored. Known options: %s.', (string) $key, implode( ', ', array_keys( self::DEFAULT_OPTIONS ) ) )
				);
				continue;
			}

			if ( 'prefix' === $key && ! Identity::is_valid_prefix( $value ) ) {
				self::doing_it_wrong(
					__METHOD__,
					sprintf(
						'Boot option "prefix" must match %1$s (lowercase letters, digits and hyphens, 3-32 characters, starting with a letter); the fallback "%2$s" is used.',
						Identity::PREFIX_PATTERN,
						Identity::FALLBACK_PREFIX
					)
				);
				continue;
			}

			if ( gettype( $value ) !== gettype( self::DEFAULT_OPTIONS[ $key ] ) ) {
				self::doing_it_wrong(
					__METHOD__,
					sprintf( 'Boot option "%1$s" must be of type %2$s; the default is used.', $key, gettype( self::DEFAULT_OPTIONS[ $key ] ) )
				);
				continue;
			}

			$resolved[ $key ] = $value;
		}

		return $resolved;
	}

	/**
	 * Raise a developer-facing _doing_it_wrong() notice when WordPress is loaded.
	 *
	 * @param string $function_name Calling function or method.
	 * @param string $message       Notice text.
	 * @return void
	 */
	private static function doing_it_wrong( string $function_name, string $message ): void {
		if ( function_exists( '_doing_it_wrong' ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- _doing_it_wrong() messages are developer-facing debug notices, not page output.
			_doing_it_wrong( $function_name, $message, '0.2.0' );
		}
	}

	/**
	 * Register configuration from a PHP array.
	 *
	 * @api
	 * @since 0.2.0
	 * @param array<string, mixed> $config Configuration array with a 'blocks' key.
	 * @return Block_Manager
	 * @throws \InvalidArgumentException If a block entry is missing 'id'.
	 */
	public function register_from_array( array $config ): Block_Manager {
		if ( function_exists( 'apply_filters' ) ) {
			/**
			 * Filters the whole blocks configuration before registration.
			 *
			 * @param array<string, mixed> $config Configuration array.
			 */
			$config = apply_filters( 'cassette_cmf_blocks_register_config', $config );
		}

		foreach ( $config['blocks'] ?? [] as $block_config ) {
			if ( empty( $block_config['id'] ) ) {
				throw new \InvalidArgumentException( 'Block config must include "id".' );
			}

			$id = (string) $block_config['id'];

			if ( function_exists( 'apply_filters' ) ) {
				$normalized_id = $this->normalize_filter_id( $id );

				/**
				 * Filters a single block's configuration.
				 *
				 * @param array<string, mixed> $block_config Block configuration.
				 * @param string               $id           Block name.
				 */
				$block_config = apply_filters( 'cassette_cmf_blocks_block_config', $block_config, $id );
				$block_config = apply_filters( "cassette_cmf_blocks_block_config_{$normalized_id}", $block_config );
			}

			$this->blocks[ $id ] = $block_config;
		}

		foreach ( $config['block_categories'] ?? [] as $category ) {
			if ( is_array( $category ) ) {
				$this->block_categories[] = $category;
			}
		}

		if ( ! empty( $config['editor_scope'] ) && is_array( $config['editor_scope'] ) ) {
			$this->editor_scopes[] = $config['editor_scope'];
		}

		foreach ( $config['patterns'] ?? [] as $pattern ) {
			if ( is_array( $pattern ) ) {
				$this->patterns[] = $pattern;
			}
		}

		foreach ( $config['pattern_categories'] ?? [] as $category ) {
			if ( is_array( $category ) ) {
				$this->pattern_categories[] = $category;
			}
		}

		if ( function_exists( 'do_action' ) ) {
			/**
			 * Fires after a batch of block configuration has been registered.
			 *
			 * @param Block_Manager $manager The manager instance.
			 */
			do_action( 'cassette_cmf_blocks_registered', $this );
		}

		$this->maybe_register_late();

		return $this;
	}

	/**
	 * Register configuration from a JSON file path or a raw JSON string.
	 *
	 * @api
	 * @since 0.2.0
	 * @param string $path_or_json Path to a JSON file, or a JSON string.
	 * @param bool   $validate     Whether to validate against the block schema. Default true.
	 * @return Block_Manager
	 * @throws \InvalidArgumentException If the JSON cannot be decoded, or (when $validate is true) fails validation.
	 */
	public function register_from_json( string $path_or_json, bool $validate = true ): Block_Manager {
		if ( file_exists( $path_or_json ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$json = (string) file_get_contents( $path_or_json );
		} else {
			$json = $path_or_json;
		}

		$config = json_decode( $json, true );

		if ( ! is_array( $config ) ) {
			throw new \InvalidArgumentException( 'Unable to decode block configuration JSON.' );
		}

		if ( $validate ) {
			$validator = new Block_Schema_Validator();
			if ( ! $validator->validate( $config ) ) {
				// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages don't need escaping.
				throw new \InvalidArgumentException(
					"Block configuration failed validation:\n" . $validator->get_error_message()
				);
				// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
		}

		return $this->register_from_array( $config );
	}

	/**
	 * Get all registered raw block configuration.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_blocks(): array {
		return $this->blocks;
	}

	/**
	 * Get a single block's raw configuration.
	 *
	 * @param string $id Block name, e.g. "acme/callout".
	 * @return array<string, mixed>|null
	 */
	public function get_block_config( string $id ): ?array {
		return $this->blocks[ $id ] ?? null;
	}

	/**
	 * Whether a block with the given name is registered.
	 *
	 * @param string $id Block name.
	 * @return bool
	 */
	public function has_block( string $id ): bool {
		return isset( $this->blocks[ $id ] );
	}

	/**
	 * The registry of compiled Block_Definition instances.
	 *
	 * @return Block_Registry
	 */
	public function get_registry(): Block_Registry {
		return $this->registry;
	}

	/**
	 * Every top-level "block_categories" entry accumulated across every
	 * register_from_array() call, in declaration order.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_block_categories(): array {
		return $this->block_categories;
	}

	/**
	 * Every top-level "editor_scope" entry accumulated across every
	 * register_from_array() call, in declaration order.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_editor_scopes(): array {
		return $this->editor_scopes;
	}

	/**
	 * Every top-level "patterns" entry accumulated across every
	 * register_from_array() call, in declaration order.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_patterns(): array {
		return $this->patterns;
	}

	/**
	 * Every top-level "pattern_categories" entry accumulated across every
	 * register_from_array() call, in declaration order.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_pattern_categories(): array {
		return $this->pattern_categories;
	}

	/**
	 * Compile a single block's raw config into a Block_Definition, caching
	 * the result in the registry.
	 *
	 * @param string $id Block name.
	 * @return Block_Definition
	 * @throws \InvalidArgumentException If the block is unknown, or its config is invalid
	 *                                   (unknown/banned control type, illegal nesting, duplicate field name, ...).
	 */
	public function compile_block( string $id ): Block_Definition {
		$existing = $this->registry->get( $id );
		if ( null !== $existing ) {
			return $existing;
		}

		$config = $this->blocks[ $id ] ?? null;
		if ( null === $config ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages don't need escaping.
			throw new \InvalidArgumentException( sprintf( 'No block registered with id "%s".', $id ) );
		}

		$definition = new Block_Definition( $config );
		$this->registry->add( $definition );

		$this->check_disabled_binding_source( $id, $definition->get_fields()->get_tree() );

		return $definition;
	}

	/**
	 * Warn when a field declares a binding to this library's block bindings
	 * source while the source is not enabled (it would silently never resolve).
	 *
	 * @param string                           $id     Block name.
	 * @param array<int, array<string, mixed>> $fields Field tree (containers hold nested "fields").
	 * @return void
	 */
	private function check_disabled_binding_source( string $id, array $fields ): void {
		if ( true === self::get_option( 'bindings_source' ) ) {
			return;
		}

		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$source = is_array( $field['binding'] ?? null ) ? ( $field['binding']['source'] ?? null ) : null;

			if ( Identity::binding_source_name() === $source ) {
				self::doing_it_wrong(
					__METHOD__,
					sprintf(
						'Field "%1$s" of block "%2$s" binds to the "%3$s" block bindings source, which is disabled. Call CassetteCMFBlocks::boot( [ \'bindings_source\' => true ] ) before registering blocks to enable it.',
						is_string( $field['name'] ?? null ) ? $field['name'] : '?',
						$id,
						Identity::binding_source_name()
					)
				);
			}

			if ( is_array( $field['fields'] ?? null ) ) {
				$this->check_disabled_binding_source( $id, $field['fields'] );
			}
		}
	}

	/**
	 * Compile every registered block and call register_block_type() for
	 * each one not already registered.
	 *
	 * Guarded by Compat\Requirements::check(): if the parent library or the
	 * running WordPress version doesn't meet this library's floor, nothing
	 * is registered and an admin notice is shown instead of a fatal error
	 * or a half-registered block registry.
	 *
	 * A block whose config fails to compile (Field_Collection/Control_Mapper
	 * validation) is skipped with a _doing_it_wrong() rather than taking
	 * down every other block on the same "init" pass.
	 *
	 * @return void
	 */
	public function register_blocks(): void {
		$requirements = Requirements::check();

		if ( ! $requirements['ok'] ) {
			$this->show_requirements_notice( $requirements['errors'] );
			return;
		}

		$is_late = function_exists( 'did_action' ) && function_exists( 'doing_action' )
			&& did_action( 'init' ) && ! doing_action( 'init' );

		foreach ( array_keys( $this->blocks ) as $id ) {
			if ( isset( $this->registered_names[ $id ] ) ) {
				continue;
			}

			try {
				$definition = $this->compile_block( $id );
			} catch ( \InvalidArgumentException $e ) {
				if ( function_exists( '_doing_it_wrong' ) ) {
					// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- _doing_it_wrong() messages are developer-facing debug notices, not page output.
					_doing_it_wrong(
						__METHOD__,
						sprintf( 'Block "%1$s" could not be registered: %2$s', $id, $e->getMessage() ),
						'0.2.0'
					);
					// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				continue;
			}

			if ( function_exists( 'register_block_type' ) ) {
				$result = register_block_type( $definition->get_name(), $definition->to_block_type_args() );

				if ( false !== $result ) {
					$this->registered_names[ $id ] = true;
				}
			} else {
				$this->registered_names[ $id ] = true;
			}

			if ( $is_late && function_exists( '_doing_it_wrong' ) ) {
				// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- _doing_it_wrong() messages are developer-facing debug notices, not page output.
				_doing_it_wrong(
					__METHOD__,
					sprintf(
						'Block "%s" was registered after the "init" hook had already fired. Existing editor sessions, or content already parsed during this request, may show it as unavailable ("core/missing"). Register block configuration before or during "init" priority 10.',
						$id
					),
					'0.2.0'
				);
				// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}
	}

	/**
	 * If "init" has already fired, register newly-added blocks immediately
	 * rather than waiting for a hook that has already run.
	 *
	 * @return void
	 */
	private function maybe_register_late(): void {
		if ( function_exists( 'did_action' ) && function_exists( 'doing_action' )
			&& did_action( 'init' ) && ! doing_action( 'init' ) ) {
			$this->register_blocks();
		}
	}

	/**
	 * Show an admin notice listing why registration was skipped entirely.
	 *
	 * @param string[] $errors Human-readable failure reasons.
	 * @return void
	 */
	private function show_requirements_notice( array $errors ): void {
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}

		add_action(
			'admin_notices',
			static function () use ( $errors ) {
				foreach ( $errors as $error ) {
					printf(
						'<div class="notice notice-error"><p>%s</p></div>',
						esc_html( $error )
					);
				}
			}
		);
	}

	/**
	 * Normalize a block name into a hook-tag-safe id.
	 *
	 * "/" and "-" are legal in a filter tag but hostile in practice, so
	 * "acme/field-select" becomes "acme_field_select" for id-suffixed hooks.
	 *
	 * @param string $id Block name.
	 * @return string
	 */
	private function normalize_filter_id( string $id ): string {
		return (string) str_replace( [ '/', '-' ], '_', $id );
	}
}
