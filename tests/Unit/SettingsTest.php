<?php

declare(strict_types=1);

namespace Flexa\Wishlist\Tests\Unit;

use Flexa\Wishlist\Support\Settings;
use PHPUnit\Framework\TestCase;

final class SettingsTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['__fw_options'] = [];
	}

	protected function tearDown(): void {
		unset( $GLOBALS['__fw_options'] );
	}

	public function test_defaults_expose_every_group(): void {
		$defaults = Settings::defaults();

		foreach ( [ 'general', 'appearance', 'button', 'page', 'sharing', 'counter', 'notifications', 'analytics', 'advanced' ] as $group ) {
			$this->assertArrayHasKey( $group, $defaults );
		}
	}

	public function test_all_returns_defaults_when_nothing_stored(): void {
		$this->assertTrue( (bool) Settings::get( 'general', 'enabled' ) );
		$this->assertSame( 'flexa', Settings::get( 'appearance', 'preset' ) );
	}

	public function test_partial_merge_preserves_other_groups(): void {
		$merged = Settings::sanitize_merge( [ 'general' => [ 'enabled' => false ] ] );

		$this->assertFalse( $merged['general']['enabled'] );
		// A one-key save must not wipe unrelated groups/keys.
		$this->assertSame( 'flexa', $merged['appearance']['preset'] );
		$this->assertSame( 'heart', $merged['appearance']['icon'] );
	}

	public function test_unknown_group_is_dropped(): void {
		$merged = Settings::sanitize_merge( [ 'bogus' => [ 'x' => 1 ] ] );

		$this->assertArrayNotHasKey( 'bogus', $merged );
	}

	public function test_unknown_key_within_group_is_dropped(): void {
		$merged = Settings::sanitize_merge( [ 'general' => [ 'evil' => 'inject' ] ] );

		$this->assertArrayNotHasKey( 'evil', $merged['general'] );
	}

	public function test_enum_rejects_invalid_and_accepts_valid(): void {
		$bad = Settings::sanitize_merge( [ 'appearance' => [ 'icon' => 'triangle' ] ] );
		$this->assertSame( 'heart', $bad['appearance']['icon'] );

		$good = Settings::sanitize_merge( [ 'appearance' => [ 'icon' => 'star' ] ] );
		$this->assertSame( 'star', $good['appearance']['icon'] );
	}

	public function test_int_values_are_clamped(): void {
		$high = Settings::sanitize_merge( [ 'general' => [ 'retention_days' => 999999 ] ] );
		$this->assertSame( 3650, $high['general']['retention_days'] );

		$low = Settings::sanitize_merge( [ 'page' => [ 'per_page' => 0 ] ] );
		$this->assertSame( 1, $low['page']['per_page'] );
	}

	/**
	 * @dataProvider bool_provider
	 */
	public function test_bool_coercion( mixed $input, bool $expected ): void {
		$merged = Settings::sanitize_merge( [ 'general' => [ 'enabled' => $input ] ] );

		$this->assertSame( $expected, $merged['general']['enabled'] );
	}

	/**
	 * @return array<string,array{mixed,bool}>
	 */
	public static function bool_provider(): array {
		return [
			'string false' => [ 'false', false ],
			'string no'    => [ 'no', false ],
			'string off'   => [ 'off', false ],
			'zero string'  => [ '0', false ],
			'empty string' => [ '', false ],
			'one string'   => [ '1', true ],
			'bool true'    => [ true, true ],
			'int one'      => [ 1, true ],
		];
	}

	public function test_color_validation(): void {
		$valid = Settings::sanitize_merge( [ 'appearance' => [ 'accent_color' => '#ff0000' ] ] );
		$this->assertSame( '#ff0000', $valid['appearance']['accent_color'] );

		// Invalid hex is ignored — the stored value stays as it was (default '').
		$invalid = Settings::sanitize_merge( [ 'appearance' => [ 'accent_color' => 'not-a-color' ] ] );
		$this->assertSame( '', $invalid['appearance']['accent_color'] );

		// Empty explicitly clears back to theme inheritance.
		$cleared = Settings::sanitize_merge( [ 'appearance' => [ 'accent_color' => '' ] ] );
		$this->assertSame( '', $cleared['appearance']['accent_color'] );
	}

	public function test_custom_css_sanitizer_strips_dangerous_sequences(): void {
		$merged = Settings::sanitize_merge(
			[
				'appearance' => [
					'custom_css' => '.x{color:red} </style><script>alert(1)</script> a{background:expression(x)}',
				],
			]
		);

		$css = $merged['appearance']['custom_css'];
		$this->assertStringNotContainsString( '</style', $css );
		$this->assertStringNotContainsString( '<script', $css );
		$this->assertStringNotContainsString( 'expression(', $css );
	}

	public function test_sharing_channels_intersect_allowed_set(): void {
		$merged = Settings::sanitize_merge(
			[
				'sharing' => [ 'channels' => [ 'email', 'telegram', 'x' ] ],
			]
		);

		$this->assertSame( [ 'email', 'x' ], array_values( $merged['sharing']['channels'] ) );
	}
}
