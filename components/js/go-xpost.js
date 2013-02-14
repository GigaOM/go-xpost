(function( $ ) {
	$(function() {
		function go_xpost_admin_get_numbers()
		{
			var go_xpost_admin_numbers = '';
			
			$('ul.go-xpost-settings li .number').each(function() {
				 $(this).attr('value');
				go_xpost_admin_numbers += $(this).attr('value') + ',';
			});
						
			return go_xpost_admin_numbers.replace(/,$/, '');
		} // END go_xpost_admin_get_numbers
		
		
		$('.go-xpost-add-endpoint').click(function(event) {
			var number   = parseInt($('ul.go-xpost-settings li:last .number').attr('value')) + 1;			
			var new_item = $('.go-xpost-setting-template').html();
			
			new_item = new_item.replace(/keynum/g, number);

			$('ul.go-xpost-settings').append('<li>' + new_item + '</li>')
			
			$('.setting-numbers').attr('value', go_xpost_admin_get_numbers());
			
			event.preventDefault();
		});
		
		$('ul.go-xpost-settings').on('click', '.go-xpost-delete-endpoint', function(event){
			$(this).closest('li').remove();
			$('.setting-numbers').attr('value', go_xpost_admin_get_numbers());
			event.preventDefault();
		});
	});
})(jQuery);