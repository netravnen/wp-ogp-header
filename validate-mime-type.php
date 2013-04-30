<?php

/**
 * Check for specific mimetypes, and can return a general category
 * for the given mimetype based on the following:
 * 
 * audio, document, icon, image, text, video.
 *
 * @since WP OGP Header 0.1.8
 */
function get_attachment_mime_type($mime_type, $mime_cat_to_validate = null)
{
	// Accepted inputs for the variabel $mime_cat_to_validate
	$accepted_mime_cats = array('audio', 'document', 'icon', 'image', 'text', 'video');
	
	// Runs if mimetype to validate for is given
	if ($mime_cat_to_validate) {
		// Detects if checked mimetype is an accepted general value and if not
		// accepted, the function will return a null value and will instead
		// check for which category a given mimetype belongs.
		if (in_array($mime_cat_to_validate, $accepted_mime_cats) == false) {
			return;
		}
	}
	
	// Table of mimetypes for general categories in the format '$mime_{category}'.
	$mime_audio = array(
		'audio/mpeg',
		'audio/ogg',
		'audio/vorbis',
		'audio/wave',
		'audio/webm',
		'audio/x-wav',
		'audio/x-metroska' );
	$mime_document = array(
		'application/msword',
		'application/oda',
		'application/msonenote',
		'application/pdf',
		'application/vnd.ms-excel',
		'application/vnd.ms-powerpoint',
		'application/vnd.oasis.opedocument.graphics',
		'application/vnd.oasis.opedocument.presentation',
		'application/vnd.oasis.opedocument.spreadsheet',
		'application/vnd.oasis.opedocument.text',
		'application/vnd.openxmlformats-officedocument.presentationml.presentation', // .pptx
		'application/vnd.openxmlformats-officedocument.presentationml.slide', // .sldx
		'application/vnd.openxmlformats-officedocument.presentationml.slideshow', // .ppsx
		'application/vnd.openxmlformats-officedocument.presentationml.template', // .potx
		'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // .xlsx
		'application/vnd.openxmlformats-officedocument.spreadsheetml.template', // .xltx
		'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
		'application/vnd.openxmlformats-officedocument.wordprocessingml.template', // .dotx
		'application/x-msmetafile',
		'application/x-publisher',
		'application/rtf' );
	$mime_icon = array(	
		'image/icon',
		'image/vnd.microsoft.icon',
		'image/x-icon' );
	$mime_image = array(
		'image/bmp',
		'image/gif',
		'image/jpeg',
		'image/png',
		'image/svg',
		'image/svg+xml',
		'image/tiff' );
	$mime_text = array(
		'text/css',
		'text/csv',
		'text/html',
		'text/plain',
		'text/richtext',
		'text/tab-seperated-values',
		'text/txt',
		'text/xml' );
	$mime_video = array(
		'video/avi',
		'video/flv',
		'video/mp4',
		'video/mpeg',
		'video/ogg',
		'video/quicktime',
		'video/webm',
		'video/x-flv',
		'video/x-metroska',
		'video/x-msvideo',
		'video/x-sgi-movie' );
	
	if ($mime_cat_to_validate) {
		// Checks for which mimetype general mimetype for return
		// audio, document, image, text, video
		if ($mime_cat_to_validate == $accepted_mime_cats[0]) if (in_array($mime_type, $mime_audio, true))    return true;
		if ($mime_cat_to_validate == $accepted_mime_cats[1]) if (in_array($mime_type, $mime_document, true)) return true;
		if ($mime_cat_to_validate == $accepted_mime_cats[2]) if (in_array($mime_type, $mime_icon, true))     return true;
		if ($mime_cat_to_validate == $accepted_mime_cats[3]) if (in_array($mime_type, $mime_image, true))    return true;
		if ($mime_cat_to_validate == $accepted_mime_cats[4]) if (in_array($mime_type, $mime_text, true))     return true;
		if ($mime_cat_to_validate == $accepted_mime_cats[5]) if (in_array($mime_type, $mime_video, true))    return true;
	}
	else {
		if (in_array($mime_type, $mime_audio, true))    return $accepted_mime_cats[0];
		if (in_array($mime_type, $mime_document, true)) return $accepted_mime_cats[1];
		if (in_array($mime_type, $mime_icon, true))     return $accepted_mime_cats[2];
		if (in_array($mime_type, $mime_image, true))    return $accepted_mime_cats[3];
		if (in_array($mime_type, $mime_text, true))     return $accepted_mime_cats[4];
		if (in_array($mime_type, $mime_video, true))    return $accepted_mime_cats[5];
	}
}

?>