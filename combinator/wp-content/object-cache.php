<?php

require_once( 'gambit-cache/lib/phpfastcache.php' );

/**
 * Init cache
 *
 * @return void
 */
function wp_cache_init() {
    $GLOBALS['wp_object_cache'] = new GambitObjectCache();
}

/**
 * Close cache
 *
 * @return boolean
 */
function wp_cache_close() {
	global $wp_object_cache;
	$wp_object_cache->stats();
    return true;
}

/**
 * Get cache
 *
 * @param string $id
 * @param string $group
 * @return mixed
 */
function wp_cache_get( $id, $group = 'default' ) {
    global $wp_object_cache;

    return $wp_object_cache->get( $id, $group );
}

/**
 * Set cache
 *
 * @param string $id
 * @param mixed $data
 * @param string $group
 * @param integer $expire
 * @return boolean
 */
function wp_cache_set( $id, $data, $group = 'default', $expire = 0 ) {
    global $wp_object_cache;

    return $wp_object_cache->set( $id, $data, $group, (int) $expire );
}

/**
 * Delete from cache
 *
 * @param string $id
 * @param string $group
 * @return boolean
 */
function wp_cache_delete( $id, $group = 'default' ) {
    global $wp_object_cache;

    return $wp_object_cache->delete( $id, $group );
}

/**
 * Add data to cache
 *
 * @param string $id
 * @param mixed $data
 * @param string $group
 * @param integer $expire
 * @return boolean
 */
function wp_cache_add( $id, $data, $group = 'default', $expire = 0 ) {
    global $wp_object_cache;

    return $wp_object_cache->add( $id, $data, $group, (int) $expire );
}

/**
 * Replace data in cache
 *
 * @param string $id
 * @param mixed $data
 * @param string $group
 * @param integer $expire
 * @return boolean
 */
function wp_cache_replace( $id, $data, $group = 'default', $expire = 0 ) {
    global $wp_object_cache;

    return $wp_object_cache->replace( $id, $data, $group, (int) $expire );
}

/**
 * Reset cache
 *
 * @return boolean
 */
function wp_cache_reset() {
	_deprecated_function( __FUNCTION__, '3.5' );
	
    global $wp_object_cache;

    return $wp_object_cache->reset();
}

/**
 * Flush cache
 *
 * @return boolean
 */
function wp_cache_flush() {
    global $wp_object_cache;

    return $wp_object_cache->flush();
}

/**
 * Add global groups
 *
 * @param array $groups
 * @return void
 */
function wp_cache_add_global_groups( $groups ) {
    global $wp_object_cache;

    $wp_object_cache->add_global_groups( $groups );
}

/**
 * Add non-persistent groups
 *
 * @param array $groups
 * @return void
 */
function wp_cache_add_non_persistent_groups( $groups ) {
    global $wp_object_cache;

    $wp_object_cache->add_nonpersistent_groups( $groups );
}

/**
 * Increment numeric cache item's value
 *
 * @param int|string $key The cache key to increment
 * @param int $offset The amount by which to increment the item's value. Default is 1.
 * @param string $group The group the key is in.
 * @return bool|int False on failure, the item's new value on success.
 */
function wp_cache_incr( $key, $offset = 1, $group = 'default' ) {
    global $wp_object_cache;

    return $wp_object_cache->increment( $key, $offset, $group );
}

/**
 * Decrement numeric cache item's value
 *
 * @param int|string $key The cache key to increment
 * @param int $offset The amount by which to decrement the item's value. Default is 1.
 * @param string $group The group the key is in.
 * @return bool|int False on failure, the item's new value on success.
 */
function wp_cache_decr( $key, $offset = 1, $group = 'default' ) {
    global $wp_object_cache;

    return $wp_object_cache->decrement( $key, $offset, $group );
}

/**
 * Switch the internal blog id.
 *
 * This changes the blog id used to create keys in blog specific groups.
 *
 * @param int $blog_id Blog ID
 */
function wp_cache_switch_to_blog( $blog_id ) {
    global $wp_object_cache;

    return $wp_object_cache->switch_to_blog( $blog_id );
}




class GambitObjectCache {
	
	// We are essentially keeping track of 3 things
	// 1. Non-global groups (ones using the current blog id)
	// 2. Global groups (ones using blog id = 0)
	// 3. Non-persistent groups (ignored)

    /**
     * Config
     *
     * @var W3_Config
     */
	// public $global_groups = array();
	public $nonpersistentGroups = array( 'comment', 'counts' );
	
	// public $multisite = false;
	// public $blogID = 0;
	
	public $cacher = null;
	public $nonPersistCache = array();

// public $cache_misses = 0;
	// public $cache_hits = 0;
	public $totalTime = 0;
	


	/**
	 * Holds the cached objects
	 *
	 * @var array
	 * @access private
	 * @since 2.0.0
	 */
	private $cache = array();

	/**
	 * The amount of times the cache data was already stored in the cache.
	 *
	 * @since 2.5.0
	 * @access private
	 * @var int
	 */
	private $cache_hits = 0;

	/**
	 * Amount of times the cache did not have the request in cache
	 *
	 * @var int
	 * @access public
	 * @since 2.0.0
	 */
	public $cache_misses = 0;

	/**
	 * List of global groups
	 *
	 * @var array
	 * @access protected
	 * @since 3.0.0
	 */
	protected $global_groups = array();

	/**
	 * The blog prefix to prepend to keys in non-global groups.
	 *
	 * @var int
	 * @access private
	 * @since 3.5.0
	 */
	private $blog_prefix;

	/**
	 * Holds the value of `is_multisite()`
	 *
	 * @var bool
	 * @access private
	 * @since 3.5.0
	 */
	private $multisite;

	/**
	 * Make private properties readable for backwards compatibility.
	 *
	 * @since 4.0.0
	 * @access public
	 *
	 * @param string $name Property to get.
	 * @return mixed Property.
	 */
	public function __get( $name ) {
		return $this->$name;
	}

	/**
	 * Make private properties settable for backwards compatibility.
	 *
	 * @since 4.0.0
	 * @access public
	 *
	 * @param string $name  Property to set.
	 * @param mixed  $value Property value.
	 * @return mixed Newly-set property.
	 */
	public function __set( $name, $value ) {
		return $this->$name = $value;
	}

	/**
	 * Make private properties checkable for backwards compatibility.
	 *
	 * @since 4.0.0
	 * @access public
	 *
	 * @param string $name Property to check if set.
	 * @return bool Whether the property is set.
	 */
	public function __isset( $name ) {
		return isset( $this->$name );
	}

	/**
	 * Make private properties un-settable for backwards compatibility.
	 *
	 * @since 4.0.0
	 * @access public
	 *
	 * @param string $name Property to unset.
	 */
	public function __unset( $name ) {
		unset( $this->$name );
	}
	
	
	
	
    function __construct() {
		global $blog_id;
		
		$this->blog_id = $blog_id;
		$this->initCache();


		/**
		 * @todo This should be moved to the PHP4 style constructor, PHP5
		 * already calls __destruct()
		 */
		register_shutdown_function( array( $this, '__destruct' ) );
    }
	
	public function initCache() {
		$config = array(
			'default_chmod' => 0755,
			"storage" => 'auto',
			"fallback" => "files", // Doesn't work anymore, see if statement below

			"securityKey" => "auto",
			"htaccess" => true,
			"path" => ABSPATH . "wp-content/uploads/combinator-cache",
		
			// TODO Caching settings e.g. memcached
			"memcache" => array(
				array( "127.0.0.1", 11211, 1 ),
			),

			"redis" => array(
				"host" => "127.0.0.1",
				"port" => "",
				"password" => "",
				"database" => "",
				"timeout" => ""
			),
		);
				
		phpFastCache::setup( $config );

		if ( class_exists( 'Redis' ) ) {
			$this->cacher = phpFastCache( 'redis' );
		} else if ( class_exists( 'Memcache' ) ) {
			$this->cacher = phpFastCache( 'memcache' );
		} else if ( class_exists( 'Memcached' ) ) {
			$this->cacher = phpFastCache( 'memcached' );
		} else if ( extension_loaded( 'apc' ) && ini_get( 'apc.enabled' ) ) {
			$this->cacher = phpFastCache( 'apc' );
		} else if ( function_exists( "wincache_ucache_set" ) ) {
			$this->cacher = phpFastCache( 'wincache' );
		} else if ( function_exists( "xcache_get" ) ) {
			$this->cacher = phpFastCache( 'xcache' );
		} else {
			
			if ( ! @file_exists( $config['path'] ) ) {
				@mkdir( $config['path'], 0755 );
			}
            if ( ! @is_writable( $config['path'] ) ) {
                @chmod( $config['path'], 0755 );
            }
			
			$this->cacher = phpFastCache( 'files' );
		}
		
	}
	
	public function stats() {
		// $cache->stats();
		var_dump($this->cacher->stats());
		var_dump($this->cache_hits, $this->cache_misses, $this->totalTime);
	}
	
	public function getKey( $id, $group = 'default' ) {
		if ( empty( $group ) ) {
			$group = 'default';
		}

        $blogID = $this->blog_id;
        if ( in_array( $group, $this->global_groups ) ) {
            $blogID = 0;
		}
		
		if ( in_array( $group, $this->nonpersistentGroups ) ) {
			return false;
		}
		
		return $blogID . '-' . $group . '-' . $id;
	}

    /**
     * Get from the cache
     *
     * @param string $id
     * @param string $group
     * @return mixed
     */
    public function get( $id, $group = 'default' ) {
		$startTime = microtime();
		
		$key = $this->getKey( $id, $group );
		
		if ( $key === false || defined( 'WP_ADMIN' ) ) {
			$this->cache_hits++;
			return false;
		}
		
		if ( isset( $this->cache[ $key ] ) ) {
			$value = $this->cache[ $key ];
		} else {
			$value = $this->cacher->get( $key );
		}
		
		if ( $value === null ) {
			$this->cache_misses++;
			$value = false;
		} else {
			$this->cache_hits++;
		}
		
		$this->totalTime += microtime() - $startTime;
		// var_dump($key, $value);
		
		return is_object( $value ) ? clone $value : $value;
    }

    /**
     * Set to the cache
     *
     * @param string $id
     * @param mixed $data
     * @param string $group
     * @param integer $expire
     * @return boolean
     */
    public function set( $id, $data, $group = 'default', $expire = 0 ) {
		if ( wp_suspend_cache_addition() )
			return false;
		
		$key = $this->getKey( $id, $group );
		
		if ( $key === false || defined( 'WP_ADMIN' ) ) {
			return false;
		}
		
        if ( is_object( $data ) ) {
            $data = clone( $data );
        }
		
		$this->cache[ $key ] = $data;
		
		$this->cacher->set( $key, $data, $expire );
		return true;
    }

    /**
     * Delete from the cache
     *
     * @param string $id
     * @param string $group
     * @param bool $force
     * @return boolean
     */
    public function delete( $id, $group = 'default', $force = false ) {
		$key = $this->getKey( $id, $group );
		
		if ( $key === false ) {
			return true;
		}
		
		if ( isset( $this->cache[ $key ] ) ) {
			unset( $this->cache[ $key ] );
		}
		
		$this->cacher->delete( $key );
		
		return true;
    }

    /**
     * Add to the cache
     *
     * @param string $id
     * @param mixed $data
     * @param string $group
     * @param integer $expire
     * @return boolean
     */
    public function add( $id, $data, $group = 'default', $expire = 0 ) {
		$this->set( $id, $data, $group, $expire );
		return true;
    }

    /**
     * Replace in the cache
     *
     * @param string $id
     * @param mixed $data
     * @param string $group
     * @param integer $expire
     * @return boolean
     */
    public function replace( $id, $data, $group = 'default', $expire = 0 ) {
		return $this->set( $id, $data, $group, $expire );
    }

    /**
     * Reset keys
     *
     * @return boolean
     */
    public function reset() {
		
		do_action( 'gambit_objectcache_reset' );
		
		$this->cacher->clean();
		return true;
    }

    /**
     * Flush cache
     *
     * @return boolean
     */
    public function flush() {
		return $this->reset();
    }

    /**
     * Add global groups
     *
     * @param array $groups
     * @return void
     */
    public function add_global_groups($groups) {
        if ( ! is_array( $groups ) ) {
            $groups = (array) $groups;
        }

        $this->global_groups = array_merge( $this->global_groups, $groups );
        $this->global_groups = array_unique( $this->global_groups );
    }

    /**
     * Add non-persistent groups
     *
     * @param array $groups
     * @return void
     */
    public function add_nonpersistent_groups($groups) {
        if ( ! is_array( $groups ) ) {
            $groups = (array) $groups;
        }

        $this->nonpersistentGroups = array_merge( $this->nonpersistentGroups, $groups );
        $this->nonpersistentGroups = array_unique( $this->nonpersistentGroups );
    }
	

    /**
     * Decrement numeric cache item's value
     *
     * @param int|string $id The cache key to increment
     * @param int $offset The amount by which to decrement the item's value. Default is 1.
     * @param string $group The group the key is in.
     * @return bool|int False on failure, the item's new value on success.
     */
    public function decrement( $id, $offset = 1, $group = 'default' ) {
		$key = $this->getKey( $id, $group );
		
		if ( $key === false ) {
			return false;
		}
		
		$value = $this->get( $id, $group );
		
		if ( $value ) {
			$this->set( $id, --$value, $group );
			return $value;
		}
		return false;
    }

    /**
     * Increment numeric cache item's value
     *
     * @param int|string $id The cache key to increment
     * @param int $offset The amount by which to increment the item's value. Default is 1.
     * @param string $group The group the key is in.
     * @return false|int False on failure, the item's new value on success.
     */
    public function increment( $id, $offset = 1, $group = 'default' ) {
		$key = $this->getKey( $id, $group );
		
		if ( $key === false ) {
			return false;
		}
		
		$value = $this->get( $id, $group );
		
		if ( $value ) {
			$this->set( $id, ++$value, $group );
			return $value;
		}
		return false;
    }

    public function switch_to_blog( $blogID ) {
		$this->blog_id = $blogID;
		return true;
    }

	/**
	 * Will save the object cache before object is completely destroyed.
	 *
	 * Called upon object destruction, which should be when PHP ends.
	 *
	 * @since  2.0.8
	 *
	 * @return bool True value. Won't be used by PHP
	 */
	public function __destruct() {
		return true;
	}
}
