<?php
	
if ( ! class_exists( 'GambitCacheCleaner' ) ) {
	
class GambitCacheCleaner {

	function __construct() {
		// Erase the cache for the current page on comment
		add_action( 'wp_insert_comment', array( $this, 'commentInserted' ), 10, 2 );
		
		// Delete cache for the post that was updated
		add_action( 'save_post', array( $this, 'savePost' ) );
		
		// Delete cache for posts that have meta data updated
		foreach ( get_post_types() as $postType => $name ) {
			add_action( 'updated_' . $postType . '_meta', array( $this, 'metaUpdated' ), 10, 4 );
		}
	}
	
	public function deleteCache( $url ) {
		$pageHash = GambitCombinator::getHash( $url );
		__c( "files" )->delete( $pageHash );
	}
	
	public function metaUpdated( $metaID, $objectID, $metaKey, $metaValue ) {
		$this->deleteCache( get_permalink( $objectID ) );
	}
	
	public function commentInserted( $id, $comment ) {
		$this->deleteCache( $_SERVER['HTTP_REFERER'] );
	}
	
	public function savePost( $postID ) {
		$this->deleteCache( get_permalink( $postID ) );
	}
	
}

}