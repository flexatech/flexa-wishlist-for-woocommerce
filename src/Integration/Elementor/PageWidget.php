<?php

declare(strict_types=1);

namespace Flexa\Wishlist\Integration\Elementor;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use Flexa\Wishlist\Frontend\Shortcodes;

defined( 'ABSPATH' ) || exit;

/**
 * Elementor "Wishlist" widget — renders the full wishlist page via the shared
 * shortcode renderer. Excluded from PHPStan (extends a stub-less base class).
 */
final class PageWidget extends Widget_Base {
	public function get_name(): string {
		return 'flexa_wishlist_page';
	}

	public function get_title(): string {
		return __( 'Wishlist', 'flexa-woocommerce-wishlist' );
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
		return [ 'wishlist', 'woocommerce', 'favorites', 'flexa' ];
	}

	protected function register_controls(): void {
		$this->start_controls_section(
			'section_content',
			[ 'label' => __( 'Wishlist', 'flexa-woocommerce-wishlist' ) ]
		);

		$this->add_control(
			'layout',
			[
				'label'   => __( 'Layout', 'flexa-woocommerce-wishlist' ),
				'type'    => Controls_Manager::SELECT,
				'default' => '',
				'options' => [
					''     => __( 'Site default', 'flexa-woocommerce-wishlist' ),
					'grid' => __( 'Grid', 'flexa-woocommerce-wishlist' ),
					'list' => __( 'List', 'flexa-woocommerce-wishlist' ),
				],
			]
		);

		$this->end_controls_section();
	}

	protected function render(): void {
		$settings = $this->get_settings_for_display();
		$layout   = isset( $settings['layout'] ) ? (string) $settings['layout'] : '';

		// Shortcode output is already escaped at source (renderer builds safe markup).
		echo Shortcodes::instance()->page( [ 'layout' => $layout ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
