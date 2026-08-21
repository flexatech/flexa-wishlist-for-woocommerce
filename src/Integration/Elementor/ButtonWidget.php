<?php

declare(strict_types=1);

namespace Flexa\Wishlist\Integration\Elementor;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use Flexa\Wishlist\Frontend\Shortcodes;

defined( 'ABSPATH' ) || exit;

/**
 * Elementor "Wishlist Button" widget — a save-to-wishlist toggle for the current
 * product, or an explicit product id. Renders via the shared shortcode.
 * Excluded from PHPStan (extends a stub-less base class).
 */
final class ButtonWidget extends Widget_Base {
	public function get_name(): string {
		return 'flexa_wishlist_button';
	}

	public function get_title(): string {
		return __( 'Wishlist Button', 'flexa-woocommerce-wishlist' );
	}

	public function get_icon(): string {
		return 'eicon-heart';
	}

	/**
	 * @return string[]
	 */
	public function get_categories(): array {
		return [ 'flexa-wishlist' ];
	}

	/**
	 * @return string[]
	 */
	public function get_keywords(): array {
		return [ 'wishlist', 'button', 'add to wishlist', 'favorites' ];
	}

	protected function register_controls(): void {
		$this->start_controls_section(
			'section_content',
			[ 'label' => __( 'Button', 'flexa-woocommerce-wishlist' ) ]
		);

		$this->add_control(
			'product_id',
			[
				'label'       => __( 'Product ID', 'flexa-woocommerce-wishlist' ),
				'type'        => Controls_Manager::NUMBER,
				'default'     => 0,
				'min'         => 0,
				'description' => __( 'Leave 0 to use the current product.', 'flexa-woocommerce-wishlist' ),
			]
		);

		$this->end_controls_section();
	}

	protected function render(): void {
		$settings   = $this->get_settings_for_display();
		$product_id = isset( $settings['product_id'] ) ? (int) $settings['product_id'] : 0;

		// Shortcode output is already escaped at source (renderer builds safe markup).
		echo Shortcodes::instance()->button( [ 'product_id' => (string) $product_id ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
