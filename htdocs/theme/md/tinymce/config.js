/* Copyright (C) 2026 Eric Seigne <eric.seigne@cap-rel.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Dolibarr toolbar presets for TinyMCE (theme "md").
 * Same mapping as theme/eldy/tinymce/config.js.
 */
window.dolTinymceToolbars = {
	'Full': 'fullscreen | undo redo | styles fontfamily fontsize | bold italic underline strikethrough superscript | forecolor backcolor removeformat | align | numlist bullist outdent indent blockquote | ltr rtl | link unlink | image table hr charmap | pastetext searchreplace visualblocks preview | code',
	'dolibarr_mailings': 'fullscreen | styles fontfamily fontsize | bold italic underline strikethrough forecolor removeformat | numlist bullist outdent indent | align | link unlink image table hr charmap | searchreplace visualblocks preview | code',
	'dolibarr_notes': 'fullscreen | styles fontsize | bold italic underline strikethrough forecolor removeformat | numlist bullist outdent indent | align | link unlink image table hr charmap | searchreplace preview | code',
	'dolibarr_details': 'fullscreen | styles fontsize | bold italic underline strikethrough forecolor removeformat | numlist bullist outdent indent | align | link unlink charmap | code',
	'dolibarr_readonly': 'fullscreen'
};

window.dolTinymcePluginsFor = function (toolbarName) {
	var base = 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen table help wordcount';
	if (toolbarName === 'Full' || toolbarName === 'dolibarr_mailings') {
		base += ' directionality';
	}
	return base;
};
