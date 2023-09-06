<?php namespace IFWP_Pro;

final class Admin_Search_Metadata extends \__Singleton {

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return array
	 */
	static public function _extensions($extensions){
		$dir = plugin_dir_path(__FILE__);
		$dirname = wp_basename($dir);
		$extension = __canonicalize($dirname);
		$extensions[$extension] = 'Admin search metadata (Post and User)';
		return $extensions;
	}

	/**
	 * @return void
	 */
	static public function register_extension(){
		__add_plugin_filter('extensions', [__CLASS__, '_extensions']);
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    /**
     * @return string
     */
    public function _posts_groupby($groupby, $query){
    	global $pagenow, $wpdb;
        if(!is_admin() or !is_search() or 'edit.php' !== $pagenow){
            return $groupby;
        }
        $g = $wpdb->posts . '.ID';
        if(!$groupby){
            $groupby = $g;
        } else {
            $groupby = trim($groupby) . ', ' . $g;
        }
    	return $groupby;
    }

    /**
     * @return string
     */
    public function _posts_join($join, $query){
        global $pagenow, $wpdb;
        if(!is_admin() or !is_search() or 'edit.php' !== $pagenow){
            return $join;
        }
        $j = 'LEFT JOIN ' . $wpdb->postmeta . ' ON ' . $wpdb->posts . '.ID = ' . $wpdb->postmeta . '.post_id';
        if(!$join){
            $join = $j;
        } else {
            $join = trim($join) . ' ' . $j;
        }
        return $join;
    }

    /**
     * @return string
     */
    public function _posts_where($where, $query){
        global $pagenow, $wpdb;
        if(!is_admin() or !is_search() or 'edit.php' !== $pagenow){
            return $where;
        }
        $s = get_query_var('s');
        $s = $wpdb->esc_like($s);
        $s = '%' . $s . '%';
        $str = '(' . $wpdb->posts . '.post_title LIKE %s)';
        $sql = $wpdb->prepare($str, $s);
        $search = $sql;
        $str = '(' . $wpdb->postmeta . '.meta_value LIKE %s)';
        $sql = $wpdb->prepare($str, $s);
        $replace = $search . ' OR ' . $sql;
        $where = str_replace($search, $replace, $where);
        return $where;
    }

    /**
     * @return array|null
     */
    public function _users_pre_query($results, $query){
    	global $pagenow, $wpdb;
        $search = $query->get('search');
        if(!is_admin() or !$search or 'users.php' !== $pagenow or null !== $query->results){
            return $results;
        }
        $j = 'LEFT JOIN ' . $wpdb->usermeta . ' ON ' . $wpdb->users . '.ID = ' . $wpdb->usermeta . '.user_id';
        $query->query_from .= ' ' . $j;
        $s = $search;
        $s = str_replace('*', '%', $s);
        $str = 'user_login LIKE %s';
        $sql = $wpdb->prepare($str, $s);
        $search = $sql;
        $str = 'meta_value LIKE %s';
        $sql = $wpdb->prepare($str, $s);
        $replace = $search . ' OR ' . $sql;
        $query->query_where = str_replace($search, $replace, $query->query_where);
        $query->query_where .= ' GROUP BY ' . $wpdb->users . '.ID';
    	return $results;
    }

	/**
	 * @return void
	 */
	public function load(){
        add_filter('posts_groupby', [$this, '_posts_groupby'], 10, 2);
        add_filter('posts_join', [$this, '_posts_join'], 10, 2);
        add_filter('posts_where', [$this, '_posts_where'], 10, 2);
        add_filter('users_pre_query', [$this, '_users_pre_query'], 10, 2);
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

}
