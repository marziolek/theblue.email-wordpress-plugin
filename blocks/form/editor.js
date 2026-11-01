/* theblue.email form block: the editor side.
 *
 * Plain wp.element.createElement, no build step. The editor shows a preview
 * of the form's shape, not the live form: the live form is loaded by
 * tbe-embed.js on the published page, from theblue.email.
 */
( function ( wp ) {
	'use strict';

	var el                = wp.element.createElement;
	var useState          = wp.element.useState;
	var useEffect         = wp.element.useEffect;
	var __                = wp.i18n.__;
	var sprintf           = wp.i18n.sprintf;
	var apiFetch          = wp.apiFetch;
	var useBlockProps     = wp.blockEditor.useBlockProps;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody         = wp.components.PanelBody;
	var SelectControl     = wp.components.SelectControl;
	var Button            = wp.components.Button;
	var Notice            = wp.components.Notice;
	var Placeholder       = wp.components.Placeholder;
	var Spinner           = wp.components.Spinner;

	var D = 'theblue-email-forms';

	function useForms() {
		var state = useState( { loading: true, data: null } );
		var value = state[ 0 ];
		var set   = state[ 1 ];

		function load( refresh ) {
			set( { loading: true, data: value.data } );
			apiFetch( { path: '/tbef/v1/forms' + ( refresh ? '?refresh=1' : '' ) } )
				.then( function ( data ) { set( { loading: false, data: data } ); } )
				.catch( function () { set( { loading: false, data: { connected: false, forms: [], error: __( 'Could not load your forms.', D ) } } ); } );
		}

		useEffect( function () { load( false ); }, [] );

		return { loading: value.loading, data: value.data, reload: function () { load( true ); }, set: set };
	}

	function Preview( props ) {
		var form = props.form;
		var bars = [];
		for ( var i = 0; i < ( form.fields || 1 ); i++ ) {
			bars.push( el( 'span', { key: i, className: 'tbef-block-preview__bar' } ) );
		}

		return el( 'div', { className: 'tbef-block-preview' },
			el( 'p', { className: 'tbef-block-preview__title' }, form.name ),
			bars,
			el( 'span', { className: 'tbef-block-preview__bar tbef-block-preview__bar--button' } ),
			el( 'p', { className: 'tbef-block-preview__note' },
				'active' === form.status
					? __( 'Your theblue.email form appears here on the published page.', D )
					: __( 'This form is a draft in theblue.email and is not shown on the page.', D )
			)
		);
	}

	function Edit( props ) {
		var key        = props.attributes.key;
		var blockProps = useBlockProps();
		var forms      = useForms();
		var allowState = useState( '' );
		var allowMsg   = allowState[ 0 ];
		var setAllow   = allowState[ 1 ];

		var data = forms.data;

		if ( forms.loading && ! data ) {
			return el( 'div', blockProps, el( Placeholder, { icon: 'email-alt', label: __( 'theblue.email form', D ) }, el( Spinner ) ) );
		}

		if ( ! data || ! data.connected ) {
			return el( 'div', blockProps,
				el( Placeholder, { icon: 'email-alt', label: __( 'theblue.email form', D ), instructions: __( 'Connect this site to your theblue.email account to choose a form.', D ) },
					data && data.can_manage ? el( Button, { variant: 'primary', href: data.settings_url }, __( 'Connect', D ) ) : null
				)
			);
		}

		var list    = data.forms || [];
		var current = null;
		list.forEach( function ( f ) { if ( f.public_key === key ) { current = f; } } );

		var options = [ { label: __( 'Choose a form', D ), value: '' } ].concat( list.map( function ( f ) {
			/* translators: %s: form name. */
			return { label: 'active' === f.status ? f.name : sprintf( __( '%s (draft)', D ), f.name ), value: f.public_key };
		} ) );

		function allow() {
			setAllow( '' );
			apiFetch( { path: '/tbef/v1/forms/' + key + '/allow', method: 'POST' } )
				.then( function ( res ) { setAllow( res.message || '' ); forms.reload(); } )
				.catch( function ( err ) { setAllow( ( err && err.message ) || __( 'Could not allow this site.', D ) ); } );
		}

		var select = el( SelectControl, {
			label: __( 'Form', D ),
			value: key,
			options: options,
			onChange: function ( value ) { props.setAttributes( { key: value } ); },
			__nextHasNoMarginBottom: true
		} );

		var warning = null;
		if ( current && ! current.allows_site ) {
			warning = el( Notice, { status: 'warning', isDismissible: false },
				/* translators: %s: this site's hostname. */
				sprintf( __( 'This form is not allowed on %s yet, so visitors would see a short message instead of it.', D ), data.site_host ),
				data.can_manage ? el( 'p', null, el( Button, { variant: 'secondary', onClick: allow }, __( 'Allow this site', D ) ) ) : null
			);
		}

		var inspector = el( InspectorControls, null,
			el( PanelBody, { title: __( 'theblue.email form', D ), initialOpen: true },
				select,
				/* translators: %s: name of the theblue.email list. */
				current ? el( 'p', null, sprintf( __( 'List: %s', D ), current.list || '-' ) ) : null,
				warning,
				allowMsg ? el( 'p', null, allowMsg ) : null,
				data.error ? el( Notice, { status: 'error', isDismissible: false }, data.error ) : null,
				el( Button, { variant: 'link', onClick: forms.reload }, __( 'Refresh forms', D ) )
			)
		);

		if ( ! current ) {
			return el( 'div', blockProps, inspector,
				el( Placeholder, {
					icon: 'email-alt',
					label: __( 'theblue.email form', D ),
					instructions: list.length ? __( 'Choose which form to show.', D ) : __( 'There are no forms in your theblue.email account yet. Create one under Forms in theblue.email.', D )
				}, list.length ? select : null )
			);
		}

		return el( 'div', blockProps, inspector, warning, el( Preview, { form: current } ) );
	}

	wp.blocks.registerBlockType( 'theblue-email/form', {
		edit: Edit,
		save: function () { return null; }
	} );
} )( window.wp );
