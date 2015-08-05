<?php

require_once( 'class-files.php' );
	
if ( ! class_exists( 'GambitCombinatorCSS' ) ) {
	
class GambitCombinatorCSS extends GambitCombinatorFiles {
	
	public static function compile( $code ) {
		
		// Remove comments
		$code = preg_replace( '!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $code );

		// Remove space after colons
		$code = str_replace( ': ', ':', $code );

		// Remove whitespace
		$code = preg_replace( '/[\t\r\n]+/', '', $code );
		$code = preg_replace('/[\s]{2,}/', ' ', $code );

		// Remove spaces that might still be left where we know they aren't needed
		$code = preg_replace( "/\s*([\{\}>~:;,])\s*/", "$1", $code );
	
		// Remove last semi-colon in blocks
		$code = preg_replace( "/;\}/", "}", $code );

		// Shorten colors
		$code = preg_replace( "/#([0-9a-fA-F])\\1([0-9a-fA-F])\\2([0-9a-fA-F])\\3/", "#$1$2$3", $code );

		// Convert content CSS to glyphs
		// $code = preg_replace_callback( "/(?<=content:[\"'])(\\\[0-9a-fA-F]+)/", 'combinator_css_content_hex_to_glyph', $code );
		
		return $code;
	}
	
	public static function combineSources( $sources, $type = 'css', $inlineCode = '' ) {
		return parent::combineSources( $sources, 'css', $inlineCode );
	}
	
}

}

if ( ! function_exists( 'combinator_css_content_hex_to_glyph' ) ) {

function combinator_css_content_hex_to_glyph( $matches ) {
	return html_entity_decode( '&#x' . trim( $matches[1], '\\' ) . ';', 0, 'UTF-8' );
}

}