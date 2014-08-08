if ( 'undefined' == typeof go_xpost ) {
	var go_xpost = {};
}//end if

( function( $ ) {
	'use strict';

	go_xpost.init = function() {
		$( '.go-xpost-add-endpoint' ).on( 'click', function( event ) {
			var number   = parseInt( $( 'ul.go-xpost-settings li:last .number' ).attr( 'value' ) ) + 1;

			if ( number = 'NAN' ) {
				number = 1;
			}

			var new_item = $( '.go-xpost-setting-template' ).html();

			new_item = new_item.replace( /keynum/g, number );

			$( 'ul.go-xpost-settings' ).append( '<li>' + new_item + '</li>' );

			$( '.go-xpost-setting-numbers' ).attr( 'value', go_xpost.get_numbers() );

			event.preventDefault();
		});

		$( 'ul.go-xpost-settings' ).on( 'click', '.go-xpost-delete-endpoint', function( event ){
			$(this).closest( 'li' ).remove();
			$( '.go-xpost-setting-numbers').attr( 'value', go_xpost.get_numbers() );
			event.preventDefault();
		});
	};

	go_xpost.get_numbers = function() {
		var go_xpost_admin_numbers = '';

		$( 'ul.go-xpost-settings li .number' ).each(function() {
			go_xpost_admin_numbers += $(this).attr( 'value' ) + ',';
		});

		return go_xpost_admin_numbers.replace( /,$/, '' );
	};
})( jQuery );

jQuery(function($) {
	go_xpost.init();
});