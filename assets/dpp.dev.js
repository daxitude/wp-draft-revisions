
jQuery(document).ready(function ($) {
		
	var displayText = 'Revision Draft';
	var statusValue = 'apd_draft';
	var select = $('#post_status');
	var copy = select.children().first().clone();
	
	copy.val(statusValue).text(displayText).attr('selected', 'selected').appendTo(select);
	
	$('#post-status-display').text(displayText);
	
});