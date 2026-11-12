/* Perimetry Cloud plugin: while a free check runs, the page reads it again every few seconds. */
( function () {
	var el = document.querySelector( '[data-perimetry-refresh]' );
	if ( el ) {
		window.setTimeout( function () {
			window.location.reload();
		}, 1000 * Math.max( 5, parseInt( el.getAttribute( 'data-perimetry-refresh' ), 10 ) || 8 ) );
	}
} )();
