<?php
	
if ( ! function_exists( 'gambitCache_isFrontEnd' ) ) {
	
	/**
	 * Check whether we are currently in the frontend
	 * Simply doing ! is_admin() doesn't cut it
	 *
	 * @return	void
	 */
	function gambitCache_isFrontEnd() {
		if ( is_admin() ) {
			return false;
		}
		return is_404() ||
			   is_archive() ||
			   is_attachment() ||
			   is_author() ||
			   is_category() ||
			   is_date() ||
			   is_day() ||
			   is_feed() ||
			   is_front_page() ||
			   is_home() ||
			   is_month() ||
			   is_page() ||
			   is_page_template() ||
			   is_preview() ||
			   is_search() ||
			   is_single() ||
			   is_singular() ||
			   is_tag() ||
			   is_tax() ||
			   is_time() ||
			   is_year();
	}
	
}	
?>