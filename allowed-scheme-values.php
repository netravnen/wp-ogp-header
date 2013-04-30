<?php
/**
 * Return either schema="value" or xml:lang="lang_value"
 * if the function is called
 * 
 * @since WP OGP Header 0.2
 */

 function is_dc_schema_allowed( $field_value, $before = ' ', $after = ' ' ) {
	
	// Allowed schemes to be outputted
	$allowed_scheme_values = array(
		'ISO8601', // Date
		
		'DCMIType', // Indicates a mimetype is given in the dc.format-field, if viewing an attachment
		'IMT',

		'RFC1766',
		'ISO3166',
		'ISO639', 'ISO639-1', 'ISO639-2', 'ISO639-2/T', 'ISO639-2/B', 'ISO639-3', 'ISO639-5', 'ISO639-6'		);
	
	//$allowed_language_standards = array();
	
	// If $field_value is represented in below array(), the 'dcterms:'
	// string is inserted before the $field_value
	$field_values_to_insert_dcterms_for = array(
		'IMT', 'RFC1766' );
	
	// If an schema prefix is required
	in_array( $field_value, $allowed_scheme_values ) ? $field_name = 'schema' : $field_name;
	
	// Gets the blog language 
	/*$language = get_bloginfo('language'); $language = explode('-', $language);
	$language_code = $language[0];
	$language_region = $language[1];*/
	
	// If a language prefix is required
	//in_array($field_value, $allowed_language_standards) ? $field_name = 'xml:lang' : $field_name;
	
	if ( $field_name ) {
		in_array( $field_value, $field_values_to_insert_dcterms_for ) ? $field_value_prefix = 'dcterms:' : $field_value_prefix;
		return $before . $field_name . '="' . $field_value_prefix . $field_value . '"' . $after;
	}
	
	// If nothing is found acceptable, the function returns a single
	// space field. Because when the function is used, there are not
	// supposed to be set any spaces left and right of the function.
	else return $after;
}

?>