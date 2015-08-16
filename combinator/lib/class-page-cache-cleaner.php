<?php
	
if ( ! class_exists( 'GambitCachePageCacheCleaner' ) ) {
	
class GambitCachePageCacheCleaner {

	function __construct() {
		// Erase the cache for the current page on comment
		add_action( 'wp_insert_comment', array( $this, 'clearOnInsertComment' ), 10, 2 );
		// Delete cache for the post that was updated
		add_action( 'save_post', array( $this, 'clearOnSavePost' ) );
		// Delete cache for posts that have meta data updated
		foreach ( get_post_types() as $postType => $name ) {
			add_action( 'updated_' . $postType . '_meta', array( $this, 'clearOnUpdatedMeta' ), 10, 4 );
		}
	}
	
	public function deleteCache( $url ) {
		global $gambitPageCache;
		if ( ! empty( $gambitPageCache ) ) {
			$pageHash = GambitCachePageCache::getHash( $url );
			$gambitPageCache->delete( $pageHash );
			return true;
		}
		return false;
	}
	
	public function clearOnUpdatedMeta( $metaID, $objectID, $metaKey, $metaValue ) {
		$this->deleteCache( get_permalink( $objectID ) );
	}
	
	public function clearOnInsertComment( $id, $comment ) {
		$this->deleteCache( $_SERVER['HTTP_REFERER'] );
	}
	
	public function clearOnSavePost( $postID ) {
		$this->deleteCache( get_permalink( $postID ) );
	}
	
	
}

}