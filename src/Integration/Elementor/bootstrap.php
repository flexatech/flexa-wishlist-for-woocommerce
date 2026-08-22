<?php

declare(strict_types=1);

namespace Flexa\Wishlist\Integration\Elementor;

defined( 'ABSPATH' ) || exit;

/*
 * Elementor wiring. Included only after `elementor/loaded`, so `\Elementor\*`
 * base classes are guaranteed present. All Elementor-typed code lives in this
 * directory and is excluded from PHPStan (no stubs); the widgets are thin
 * wrappers over the storefront shortcode renderers (§10.10).
 */

add_action(
	'elementor/elements/categories_registered',
	static function ( $elements_manager ): void {
		$elements_manager->add_category(
			'flexa-wishlist',
			[
				'title' => __( 'Wishlist', 'flexa-wishlist-for-woocommerce' ),
				'icon'  => 'eicon-heart',
			]
		);
	}
);

add_action(
	'elementor/widgets/register',
	static function ( $widgets_manager ): void {
		require_once __DIR__ . '/PageWidget.php';
		require_once __DIR__ . '/ButtonWidget.php';
		require_once __DIR__ . '/CounterWidget.php';

		$widgets_manager->register( new PageWidget() );
		$widgets_manager->register( new ButtonWidget() );
		$widgets_manager->register( new CounterWidget() );
	}
);
