
jQuery(document).ready(function ($) {

	var displayText = 'Draft Revision';
	var statusValue = 'dpr_draft';
	var select, copy, numCols;
	
	// edit.php
	select = $('#post_status');
	if (select.length > 0) {
		copy = select.children().first().clone();

		copy.val(statusValue).text(displayText).attr('selected', 'selected').appendTo(select);

		$('#post-status-display').text(displayText);
	}
	
	// post.php quick edit
	$('.editinline').on('click', function () {
		
		if ( ! $(this).parents('tr').hasClass('status-' + statusValue) ) return;
		
		select = $('select[name=_status]');
		copy = select.children().first().clone();
		
		copy.val(statusValue).text(displayText).attr('selected', 'selected').appendTo(select);
		
	});
	
	// update the row in edit.php after quick edit to indicate post successfully published
	numCols = $('#the-list').children('tr').first().children('td').length;
	$('#the-list').on('setColSpan', '.dpr-title-cell', function () {
		$(this).attr('colspan', numCols);
	});
	
	// are you sure message on Publish-button
	if ($('body').hasClass('post-status_dpr_draft')) {

        $('#publishing-action input').click(function () {
            var retVal = confirm(objectL10n.confirmMessage);
            if (retVal == true) {
                return true;
            }
            else {
                return false;
            }

        });
    }
	
	
		
});


