<?php

declare(strict_types=1);

namespace Flexa\Wishlist\Rest;

use Flexa\Wishlist\Support\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Registers every REST controller in one place. Extensions add their own
 * controllers by hooking `flexa_wishlist/rest/register_routes`, which fires
 * last. A controller that is not registered here is dead — so every new
 * controller must be added to register_routes().
 */
final class RegisterFacade {
	use SingletonTrait;

	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		( new SettingsController() )->register_routes();
		( new StateController() )->register_routes();
		( new ListsController() )->register_routes();
		( new ItemsController() )->register_routes();
		( new CartController() )->register_routes();
		( new ShareController() )->register_routes();
		( new AdminController() )->register_routes();

		do_action( 'flexa_wishlist/rest/register_routes' );
	}
}
