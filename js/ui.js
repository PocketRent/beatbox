/**
 * Standard UI Functionality
 */
(function ($, window) {
	// Most importantly, set the date
	var today = new Date();
	$('svg .todaysDate').text(today.getDate());
	$('#Main').on('click', '.section .sectionToggle', function(e) {
		e.preventDefault();
		e.stopPropagation();
		$(this).toggleClass('open').next('.sectionTogglable').slideToggle('fast');
	});
})(jQuery, this);
