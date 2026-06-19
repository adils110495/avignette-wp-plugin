(function ($) {
	'use strict';

	/**
	 * All of the code for your admin-facing JavaScript source
	 * should reside in this file.
	 *
	 * Note: It has been assumed you will write jQuery code here, so the
	 * $ function reference has been prepared for usage within the scope
	 * of this function.
	 *
	 * This enables you to define handlers, for when the DOM is ready:
	 *
	 * $(function() {
	 *
	 * });
	 *
	 * When the window is loaded:
	 *
	 * $( window ).load(function() {
	 *
	 * });
	 *
	 * ...and/or other possibilities.
	 *
	 * Ideally, it is not considered best practise to attach more than a
	 * single DOM-ready or window-load handler for a particular page.
	 * Although scripts in the WordPress core, Plugins and Themes may be
	 * practising this, we should strive to set a better example in our own work.
	 */

	document.addEventListener("DOMContentLoaded", function () {
		// Event handler for the upload image button
		$('.upload_image_button').click(function (e) {
			console.log("working");
			e.preventDefault();

			let button = $(this);
			let targetInput = $('#' + button.data('target'));

			let frame = wp.media({
				title: 'Select or Upload an Image',
				button: {
					text: 'Use this image'
				},
				multiple: false
			});

			frame.on('select', function () {
				let attachment = frame.state().get('selection').first().toJSON();
				targetInput.val(attachment.url);
				button.siblings('.image-preview').remove();
				button.after(
					`<div class="image-preview" style="margin-top: 10px;">
						<img src="${attachment.url}" alt="Preview" style="max-width: 100px; max-height: 100px;">
					</div>`
				);
			});

			frame.open();
		});
	});

})(jQuery);
