/**
 * Flexa Wishlist — block editor registration (no build step).
 *
 * Registers the three dynamic blocks client-side to pair with their PHP
 * render_callbacks. Page + Counter preview live via ServerSideRender; the
 * Button needs a product context that the editor lacks, so it shows a
 * placeholder with an optional explicit product id. Plain wp.element.
 */
(function (blocks, element, blockEditor, serverSideRender, components, i18n) {
	'use strict';

	if (!blocks || !element) {
		return;
	}

	var el = element.createElement;
	var __ = i18n.__;
	var ServerSideRender = serverSideRender;
	var useBlockProps = blockEditor.useBlockProps || function () { return {}; };
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var SelectControl = components.SelectControl;
	var TextControl = components.TextControl;
	var Placeholder = components.Placeholder;

	var icon = el(
		'svg',
		{ width: 24, height: 24, viewBox: '0 0 24 24', 'aria-hidden': true },
		el('path', {
			d: 'M12 21s-8-5.3-8-11a4.5 4.5 0 0 1 8-2.8A4.5 4.5 0 0 1 20 10c0 5.7-8 11-8 11z',
			fill: 'currentColor'
		})
	);

	// ---- Wishlist page -----------------------------------------------------

	blocks.registerBlockType('flexa-wishlist/page', {
		apiVersion: 2,
		title: __('Wishlist', 'flexa-wishlist-for-woocommerce'),
		description: __('The full wishlist page.', 'flexa-wishlist-for-woocommerce'),
		category: 'woocommerce',
		icon: icon,
		attributes: { layout: { type: 'string', default: '' } },
		supports: { html: false, align: ['wide', 'full'] },
		edit: function (props) {
			return el(
				'div',
				useBlockProps(),
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: __('Layout', 'flexa-wishlist-for-woocommerce'), initialOpen: true },
						el(SelectControl, {
							label: __('Layout', 'flexa-wishlist-for-woocommerce'),
							value: props.attributes.layout,
							options: [
								{ label: __('Site default', 'flexa-wishlist-for-woocommerce'), value: '' },
								{ label: __('Grid', 'flexa-wishlist-for-woocommerce'), value: 'grid' },
								{ label: __('List', 'flexa-wishlist-for-woocommerce'), value: 'list' }
							],
							onChange: function (v) { props.setAttributes({ layout: v }); }
						})
					)
				),
				el(ServerSideRender, {
					block: 'flexa-wishlist/page',
					attributes: props.attributes
				})
			);
		},
		save: function () { return null; }
	});

	// ---- Wishlist button ---------------------------------------------------

	blocks.registerBlockType('flexa-wishlist/button', {
		apiVersion: 2,
		title: __('Wishlist Button', 'flexa-wishlist-for-woocommerce'),
		description: __('A save-to-wishlist toggle for a product.', 'flexa-wishlist-for-woocommerce'),
		category: 'woocommerce',
		icon: icon,
		attributes: { productId: { type: 'number', default: 0 } },
		supports: { html: false },
		edit: function (props) {
			return el(
				'div',
				useBlockProps(),
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: __('Product', 'flexa-wishlist-for-woocommerce'), initialOpen: true },
						el(TextControl, {
							type: 'number',
							label: __('Product ID', 'flexa-wishlist-for-woocommerce'),
							help: __('Leave 0 to use the current product.', 'flexa-wishlist-for-woocommerce'),
							value: props.attributes.productId || 0,
							onChange: function (v) { props.setAttributes({ productId: parseInt(v, 10) || 0 }); }
						})
					)
				),
				el(
					Placeholder,
					{
						icon: icon,
						label: __('Wishlist Button', 'flexa-wishlist-for-woocommerce'),
						instructions: props.attributes.productId
							? __('Shows the toggle for the selected product on the front end.', 'flexa-wishlist-for-woocommerce')
							: __('Shows the save-to-wishlist toggle for the current product on the front end.', 'flexa-wishlist-for-woocommerce')
					}
				)
			);
		},
		save: function () { return null; }
	});

	// ---- Wishlist counter --------------------------------------------------

	blocks.registerBlockType('flexa-wishlist/counter', {
		apiVersion: 2,
		title: __('Wishlist Counter', 'flexa-wishlist-for-woocommerce'),
		description: __('A link to the wishlist with a saved-count badge.', 'flexa-wishlist-for-woocommerce'),
		category: 'woocommerce',
		icon: icon,
		supports: { html: false },
		edit: function (props) {
			return el(
				'div',
				useBlockProps(),
				el(ServerSideRender, { block: 'flexa-wishlist/counter' })
			);
		},
		save: function () { return null; }
	});
})(
	window.wp && window.wp.blocks,
	window.wp && window.wp.element,
	window.wp && window.wp.blockEditor,
	window.wp && window.wp.serverSideRender,
	window.wp && window.wp.components,
	window.wp && window.wp.i18n
);
