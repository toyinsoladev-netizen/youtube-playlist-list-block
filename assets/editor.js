( function ( wp ) {
	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var __ = wp.i18n.__;
	var registerBlockType = wp.blocks.registerBlockType;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var TextControl = wp.components.TextControl;
	var RangeControl = wp.components.RangeControl;
	var ToggleControl = wp.components.ToggleControl;
	var Placeholder = wp.components.Placeholder;
	var Button = wp.components.Button;
	var ServerSideRender = wp.serverSideRender;

	var BLOCK_NAME = 'playlist-list-block/playlist';

	function Edit( props ) {
		var attributes = props.attributes;
		var setAttributes = props.setAttributes;
		var blockProps = useBlockProps();
		var draftState = useState( '' );
		var draft = draftState[ 0 ];
		var setDraft = draftState[ 1 ];

		var inspector = el(
			InspectorControls,
			null,
			el(
				PanelBody,
				{ title: __( 'Playlist settings', 'playlist-list-block' ), initialOpen: true },
				el( TextControl, {
					label: __( 'Playlist ID', 'playlist-list-block' ),
					help: __( 'The part after list= in the playlist URL. You can also paste the full URL.', 'playlist-list-block' ),
					value: attributes.playlistId,
					onChange: function ( value ) {
						setAttributes( { playlistId: value } );
					},
					__nextHasNoMarginBottom: true
				} ),
				el( RangeControl, {
					label: __( 'Number of videos', 'playlist-list-block' ),
					value: attributes.maxResults,
					min: 1,
					max: 50,
					onChange: function ( value ) {
						setAttributes( { maxResults: value || 10 } );
					},
					__nextHasNoMarginBottom: true
				} ),
				el( ToggleControl, {
					label: __( 'Show durations', 'playlist-list-block' ),
					checked: attributes.showDurations,
					onChange: function ( value ) {
						setAttributes( { showDurations: value } );
					},
					__nextHasNoMarginBottom: true
				} )
			)
		);

		// Empty state: ask for the playlist ID.
		if ( ! attributes.playlistId ) {
			return el(
				'div',
				blockProps,
				inspector,
				el(
					Placeholder,
					{
						icon: 'playlist-video',
						label: __( 'YouTube Playlist List', 'playlist-list-block' ),
						instructions: __( 'Enter a playlist ID (or paste the playlist URL).', 'playlist-list-block' )
					},
					el(
						'form',
						{
							className: 'plb-placeholder-form',
							onSubmit: function ( event ) {
								event.preventDefault();
								if ( draft.trim() ) {
									setAttributes( { playlistId: draft.trim() } );
								}
							}
						},
						el( TextControl, {
							value: draft,
							placeholder: 'PLxxxxxxxxxxxxxxxx',
							onChange: setDraft,
							__nextHasNoMarginBottom: true
						} ),
						el( Button, { variant: 'primary', type: 'submit' }, __( 'Load playlist', 'playlist-list-block' ) )
					)
				)
			);
		}

		// Live preview rendered by the same PHP that powers the front end.
		return el(
			'div',
			blockProps,
			inspector,
			el( ServerSideRender, { block: BLOCK_NAME, attributes: attributes } )
		);
	}

	registerBlockType( BLOCK_NAME, {
		edit: Edit,
		save: function () {
			return null;
		}
	} );
} )( window.wp );
