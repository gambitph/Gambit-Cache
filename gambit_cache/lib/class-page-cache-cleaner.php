<?php
	
if ( ! class_exists( 'GambitCachePageCacheCleaner' ) ) {
	
class GambitCachePageCacheCleaner {
	
	public $pageCacheEnabled = true;

	function __construct() {
		// Load page cache settings
		add_action( 'tf_done', array( $this, 'gatherSettings' ), 10 );
		
		// Erase the cache for the current page on comment
		add_action( 'wp_insert_comment', array( $this, 'clearOnInsertComment' ), 10, 2 );
		// Delete cache for the post that was updated
		add_action( 'save_post', array( $this, 'clearOnSavePost' ) );
		// Delete cache for posts that have meta data updated
		foreach ( get_post_types() as $postType => $name ) {
			add_action( 'updated_' . $postType . '_meta', array( $this, 'clearOnUpdatedMeta' ), 10, 4 );
		}
	}
	
	public function gatherSettings() {
		$titan = TitanFramework::getInstance( GAMBIT_CACHE );
		
		$this->pageCacheEnabled = $titan->getOption( 'page_cache_enabled' );
	}
	
	public function deleteCache( $url, $postID = false ) {
		global $gambitPageCache;
		if ( ! empty( $gambitPageCache ) ) {
			$pageHash = GambitCachePageCache::getHash( $url );
			
			try {
				$gambitPageCache->delete( $pageHash );
			} catch ( Exception $e ) {
			}
			
			do_action( 'gc_page_cache_deleted', $postID );
			return true;
		}
		return false;
	}
	
	public function clearOnUpdatedMeta( $metaID, $objectID, $metaKey, $metaValue ) {
		if ( $this->pageCacheEnabled ) {
			$this->deleteCache( get_permalink( $objectID ), $objectID );
		}
	}
	
	public function clearOnInsertComment( $id, $comment ) {
		if ( $this->pageCacheEnabled ) {
			$comment = get_comment( $id ); 
			$postID = $comment ? $comment->comment_post_ID : false;
			$this->deleteCache( $_SERVER['HTTP_REFERER'], $postID );
		}
	}
	
	public function clearOnSavePost( $postID ) {
		if ( $this->pageCacheEnabled ) {
			$this->deleteCache( get_permalink( $postID ), $postID );
		}
	}
	
	
}

}