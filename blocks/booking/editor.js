/* OSPITANO Booking block editor UI. No build step: plain wp.* globals. */
( function ( blocks, element, blockEditor, components, i18n ) {
	var el = element.createElement;
	var __ = i18n.__;

	blocks.registerBlockType( 'ospitano/booking', {
		edit: function ( props ) {
			var a = props.attributes;
			return [
				el( blockEditor.InspectorControls, { key: 'inspector' },
					el( components.PanelBody, { title: __( 'Booking form', 'ospitano-booking' ) },
						el( components.TextControl, {
							label: __( 'Hotel code (empty = from settings)', 'ospitano-booking' ),
							value: a.hotel,
							onChange: function ( v ) { props.setAttributes( { hotel: v } ); }
						} ),
						el( components.SelectControl, {
							label: __( 'Language', 'ospitano-booking' ),
							value: a.lang,
							options: [
								{ label: __( 'Same as the page', 'ospitano-booking' ), value: 'auto' },
								{ label: 'Български', value: 'bg' }, { label: 'English', value: 'en' },
								{ label: 'Română', value: 'ro' }, { label: 'Deutsch', value: 'de' }, { label: 'Русский', value: 'ru' }
							],
							onChange: function ( v ) { props.setAttributes( { lang: v } ); }
						} )
					)
				),
				el( 'div', Object.assign( { key: 'preview' }, blockEditor.useBlockProps( {
					style: { padding: '24px', border: '1px dashed #A98A5F', borderRadius: '8px', background: '#FAF7F2', textAlign: 'center' }
				} ) ),
					el( 'strong', null, 'OSPITANO Booking' ),
					el( 'p', { style: { margin: '8px 0 0' } }, __( 'The booking form appears here on the live page.', 'ospitano-booking' ) )
				)
			];
		},
		save: function () { return null; }
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.i18n );
