<?php
/**
 * Mount node for the React admin app. Enqueue.php enqueues the bundled assets.
 *
 * @package Flexa\Wishlist
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;
?>
<div class="flexa-wishlist-wrap">
	<h1 class="screen-reader-text"><?php echo esc_html__( 'Flexa Wishlist', 'flexa-wishlist-for-woocommerce' ); ?></h1>
	<div id="flexa-wishlist-admin-root"></div>
</div>
