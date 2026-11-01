/* Copy buttons on Settings, theblue.email. */
( function () {
	'use strict';

	document.addEventListener( 'click', function ( e ) {
		var button = e.target.closest ? e.target.closest( '.tbef-copy' ) : null;
		if ( ! button || ! navigator.clipboard ) {
			return;
		}

		var label = button.textContent;
		navigator.clipboard.writeText( button.getAttribute( 'data-copy' ) || '' ).then( function () {
			button.textContent = '✓';
			setTimeout( function () { button.textContent = label; }, 1500 );
		} );
	} );
} )();
