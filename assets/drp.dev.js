
jQuery(document).ready(function ($) {
		
	var displayText = 'Draft Revision';
	var statusValue = 'drp_draft';
	var select = $('#post_status');
	
	if (select.length > 0) {
		var copy = select.children().first().clone();

		copy.val(statusValue).text(displayText).attr('selected', 'selected').appendTo(select);

		$('#post-status-display').text(displayText);
	}
	
	
	$('.editinline').on('click', function () {
		
		if ( ! $(this).parents('tr').hasClass('status-' + statusValue) ) return;
		
		var select = $('select[name=_status]');
		var copy = select.children().first().clone();
		copy.val(statusValue).text(displayText).attr('selected', 'selected').appendTo(select);
		
	});
	
});