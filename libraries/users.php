<?php
/** no direct access **/
defined('_WPLEXEC') or die('Restricted access');

_wp_import('wp-includes.pluggable');
_wpl_import('libraries.items');
_wpl_import('libraries.flex');

/**
 * Users Library
 * @author Howard R <howard@realtyna.com>
 * @since WPL1.0.0
 * @date 03/01/2013
 */
class wpl_users
{
	/**
		@input {category}, [kind] and [enabled]
		@return boolean result
		@description for deleting user from wpl
		@author Howard
	**/
	public static function get_plisting_fields($category = '', $kind = 2, $enabled = 1)
	{
		return wpl_flex::get_fields($category, $enabled, $kind, 'plisting', '1');
	}
	
	/**
		@input {user_id}
		@return boolean result
		@description for deleting user from wpl
		@author Howard
	**/
	public static function delete_user_from_wpl($user_id)
	{
		$query = "DELETE FROM `#__wpl_users` WHERE `id`='$user_id'";
		$result = wpl_db::q($query);
		
        /** trigger event **/
		wpl_global::event_handler('user_deleted_from_wpl', array('id'=>$user_id));
        
		return $result;
	}
    
    /**
     * Adds WordPress user to WPL
     * @author Howard <howard@realtyna.com>
     * @static
     * @param int $user_id
     * @param int $group_id
     * @return boolean
     */
	public static function add_user_to_wpl($user_id, $group_id = -1)
	{
		/** first validation **/
		if(wpl_users::get_wpl_user($user_id)) return true;
		
		$user_data = wpl_users::get_user($user_id);
		$default_data = wpl_users::get_wpl_data($group_id);
		
		$forbidden_fields = array('id', 'first_name', 'last_name', 'main_email', 'blog_id');
		$auto_query1 = '';
		$auto_query2 = '';
		
		foreach($default_data as $key=>$value)
		{
			if(in_array($key, $forbidden_fields)) continue;
			
			$auto_query1 .= "`$key`,";
			$auto_query2 .= "'$value',";
		}
		
		if($user_data)
		{
			$auto_query1 .= "`first_name`,`last_name`,`main_email`,";
			$auto_query2 .= "'".$user_data->data->meta['first_name']."','".$user_data->data->meta['last_name']."','".$user_data->data->user_email."',";
		}
		
		$auto_query1 = trim($auto_query1, ', ');
		$auto_query2 = trim($auto_query2, ', ');
		
		$query = "INSERT INTO `#__wpl_users` (`id`, ".$auto_query1.") VALUES ('".$user_id."', ".$auto_query2.")";
		$result = wpl_db::q($query);
		
        /** trigger event **/
		wpl_global::event_handler('user_added_to_wpl', array('id'=>$user_id));
        
        /** finalize user **/
        wpl_users::finalize($user_id);
        
		return $result;
	}
	
	/**
		@input {args} and [add_data]
		@return users object
		@description for getting all wordpress users (included wpl data)
		@author Howard
	**/
	public static function get_all_wp_users($args, $add_data = false)
	{
		$users = get_users($args);
		if(!$add_data) return $users;
		
		foreach($users as $key=>$user)
		{
			$users[$key]->meta = self::get_user_meta($user->ID);
			$users[$key]->wpl_data = self::get_wpl_data($user->ID);
		}
		
		return $users;
	}
	
	/**
		@input {args} and [add_data]
		@return query result
		@description for getting wordpress users
		@author Howard
	**/
	public static function get_wp_users($query = '')
	{
		$query = "SELECT * FROM `#__users` AS u LEFT JOIN `#__wpl_users` AS wpl ON u.ID = wpl.id WHERE 1 $query";
		$users = wpl_db::select($query);
		
		return $users;
	}
	
	/**
		@input {args} and [add_data]
		@return query result
		@description for getting wordpress users included in WPL
		@author Howard
	**/
	public static function get_wpl_users($query = '')
	{
		$query = "SELECT * FROM `#__users` AS u INNER JOIN `#__wpl_users` AS wpl ON u.ID = wpl.id WHERE 1 $query";
		$users = wpl_db::select($query);
		
		return $users;
	}
	
	/**
		@input [user_id]
		@return query result
		@description for getting all user data (included wordpress and wpl data)
		@author Howard
	**/
	public static function get_user($user_id = '')
	{
		/** fetch currenr user data if user id is empty **/
		if(trim($user_id) == '') $user_id = self::get_cur_user_id();
		
		/** fetch user data **/
		$user_data = get_userdata($user_id);
		
		$user_data->meta = self::get_user_meta($user_id);
		$user_data->wpl_data = self::get_wpl_data($user_id);
		
		return $user_data;
	}
	
	/**
		@input [user_id]
		@return query result
		@description for getting user data (just wpl data)
		@author Howard
	**/
	public static function get_wpl_user($user_id = '')
	{
		/** load current user **/
		if(trim($user_id) == '') $user_id = self::get_cur_user_id();
		
		$query = "SELECT * FROM `#__wpl_users` WHERE `id`='$user_id'";
		return wpl_db::select($query, 'loadObject');
	}
	
	/**
		@input [user_id], [key] and [single]
		@return query result
		@description for getting user meta data
		@author Howard
	**/
	public static function get_user_meta($user_id = '', $key = '', $single = '')
	{
		$rendered_meta = array();
		
		if(trim($user_id) == '') $user_id = self::get_cur_user_id();
		if(!$user_id) return false;
		
		$user_meta = get_user_meta($user_id, $key, $single);
		
		foreach($user_meta as $key=>$meta)
		{
			if(count($meta) == 1) $rendered_meta[$key] = $meta[0];
			else $rendered_meta[$key] = $meta;
		}
		
		return $rendered_meta;
	}
	
	/**
		@input [user_id]
		@return query result
		@description for getting user meta data
		@author Howard
	**/
	public static function get_wpl_data($user_id = '')
	{
		if(trim($user_id) == '') $user_id = self::get_cur_user_id();
		return wpl_db::get('*', 'wpl_users', 'id', $user_id);
	}
	
	/**
		@input void
		@return int user_id
		@description for getting current user id
		@author Howard
	**/
	public static function get_cur_user_id()
	{
		return get_current_user_id();
	}
	
	/**
		@input {username}
		@return user id
		@description for getting user id by username
		@author Howard
	**/
	public static function get_id_by_username($username)
	{
        /** first validation **/
        if(!trim($username)) return false;
        
		$query = "SELECT * FROM `#__users` WHERE `user_login`='$username'";
		$user = wpl_db::select($query, 'loadObject');
		
		if($user) return $user->ID;
        else return 0;
	}
	
	/**
		@input {username}
		@return user id
		@description for getting user id by username
		@author Howard
	**/
	public static function get_id_by_email($email)
	{
        /** first validation **/
        if(!trim($email)) return false;
        
		$query = "SELECT * FROM `#__users` WHERE `user_email`='$email'";
		$user = wpl_db::select($query, 'loadObject');
		
		if($user) return $user->ID;
        else return 0;
	}
	
	/**
		@input void
		@return string ip
		@description for getting current ip
		@author Howard
	**/
	public static function get_current_ip()
	{
		return wpl_request::getVar('REMOTE_ADDR', '', 'SERVER');
	}
	
	/**
		@input [user_id]
		@return wp role of user
		@description for getting user role (wp feature)
		@author Howard
	**/
	public static function get_role($user_id = '', $superadmin_role = true)
	{
		$user_data = self::get_user($user_id);
		$role = $user_data->roles[0];
        
        /** check network admin **/
        if($superadmin_role and wpl_users::is_super_admin($user_id)) $role = 'superadmin';
        
        return $role;
	}
	
	/**
		@input void
		@return array roles
		@author Howard
	**/
	public static function get_wpl_roles()
	{
		$roles = array();
        $roles['superadmin'] = 'superadmin';
		$roles['admin'] = 'administrator';
		$roles['editor'] = 'editor';
		$roles['agent'] = 'author';
		$roles['Contributor'] = 'Contributor';
		$roles['subscriber'] = 'subscriber';
		$roles['guest'] = 'guest';
		
		return $roles;
	}
	
	/**
		@input {role}
		@return role point
		@author Howard
	**/
	public static function get_role_point($role)
	{
		/** get all roles **/
		$roles = self::get_wpl_roles();
		
		/** role validation **/
		if(!in_array($role, $roles)) $role = 'guest';
		
		$roles_point = array();
        $roles_point['superadmin'] = 6;
		$roles_point['administrator'] = 5;
		$roles_point['editor'] = 4;
		$roles_point['author'] = 3;
		$roles_point['Contributor'] = 2;
		$roles_point['subscriber'] = 1;
		$roles_point['guest'] = 0;
		
		return $roles_point[$role];
	}
	
	/**
		@input {caps} and {user_id}
		@return boolean
		@description for checking if user have the capability or not
		@author Howard
	**/
	public static function is($caps, $user_id)
	{
		$result = false;
		
		if(is_array($caps))
		{
			foreach($caps as $cap)
			{
				if(self::is($cap, $user_id)) return true;
			}
			
			return false;
		}
		
		$user_data = self::get_user($user_id);
		if(!$user_data) return false;
		
		if($user_data->caps[$caps]) $result = true;
		return $result;
	}
	
	/**
		@input void
		@return object memberships
		@description for getting wpl memberships
		@author Morgan
	**/
	public static function get_wpl_memberships($condition = NULL)
	{
		$query = "SELECT * FROM `#__wpl_users` WHERE 1 AND `id` < 0 ".(trim($condition) ? $condition : '')." ORDER BY `index` ASC";
		return wpl_db::select($query);
	}
    
    /**
     * Use wpl_users::get_user_types instead
     * @deprecated since version 1.8.3
     * @return array of objects
     */
	public static function get_membership_types()
	{
		return self::get_user_types();
	}
	
    /**
     * Returns WPL user types
     * @author Howard <howard@realtyna.com>
     * @static
     * @param int $enabled
     * @return array of objects
     */
    public static function get_user_types($enabled = 1, $return_type = 'loadObjectList')
    {
        $query = "SELECT * FROM `#__wpl_user_group_types` WHERE `enabled`>='$enabled' ORDER BY `index` ASC";
		return wpl_db::select($query, $return_type);
    }
    
    /**
     * Returns one user type record
     * @author Howard <howard@realtyna.com>
     * @static
     * @param int $id
     * @param int $enabled
     * @return object
     */
    public static function get_user_type($id = 1, $enabled = 0)
    {
        $query = "SELECT * FROM `#__wpl_user_group_types` WHERE `id`='$id' AND `enabled`>='$enabled'";
		return wpl_db::select($query, 'loadObject');
    }
    
    /**
     * Creates a new user type and returns its id
     * @author Howard <howard@realtyna.com>
     * @static
     * @return int $id
     */
    public static function create_default_user_type()
    {
        $id = self::get_new_user_type_id();
        wpl_db::q("INSERT INTO `#__wpl_user_group_types` (`id`,`editable`,`deletable`,`index`,`enabled`) VALUES ('$id','2','1','$id','1')", 'INSERT');
        
        return $id;
    }
    
    /**
     * Returns id for new user type
     * @author Howard <howard@realtyna.com>
     * @static
     * @return int
     */
    public static function get_new_user_type_id()
    {
        $query = "SELECT MAX(id) as max_id FROM `#__wpl_user_group_types`";
		$result = wpl_db::select($query, 'loadResult');
		$id = max($result, 100);
        
		/** generate new user type id **/
		return ($id+1);
    }
    
    /**
     * Removes user types
     * @author Howard <howard@realtyna.com>
     * @static
     * @param int $id
     * @return boolean
     */
    public static function remove_user_type($id)
	{
        $user_type = self::get_user_type($id);
        
		/** don't remove undeletable user types **/
		if(!$user_type or ($user_type and !$user_type->deletable)) return false;
        
        /** trigger event **/
		wpl_global::event_handler('user_type_removed', array('id'=>$id));
        
		wpl_db::q("DELETE FROM `#__wpl_user_group_types` WHERE `id`='$id'", 'DELETE');
        wpl_db::q("UPDATE `#__wpl_users` SET `membership_type`='' WHERE `membership_type`='$id'", 'UPDATE');
        
		return true;
	}
    
	/**
		@input void
		@return in membership id
		@description for getting unique id for new membership
		@author Morgan
	**/
	public static function get_membership_id()
	{
		$query = "SELECT MIN(id) as min_id FROM `#__wpl_users`";
		$result = wpl_db::select($query, 'loadResult');
		
		/** generate new membership id **/
		return ($result-1);
	}
	
	/**
		@input {membership_id}
		@return boolean
		@description for removing a membership
		@author Howard
	**/
	public static function remove_membership($membership_id)
	{
		/** don't remove default and guest membership **/
		if(in_array($membership_id, array(-1, -2))) return false;
		
		$query = "UPDATE `#__wpl_users` SET `membership_id`='-1' WHERE `membership_id`='$membership_id'";
		wpl_db::q($query);
		
		$query = "DELETE FROM `#__wpl_users` WHERE `id`='$membership_id'";
		wpl_db::q($query);
		
        /** trigger event **/
		wpl_global::event_handler('membership_removed', array('id'=>$membership_id));
        
		return true;
	}
	
	/**
		@input {membership_id}
		@return object membership_data
		@description for getting a membership data
		@author Morgan
	**/
	public static function get_membership($membership_id)
	{
		$query = "SELECT * FROM `#__wpl_users` WHERE `id`='$membership_id'";
		return wpl_db::select($query, 'loadObject');
	}
		
	/**
		@input {table}, {key}, {unit_id} and [value]
		@return boolean result
		@author Howard
	**/
	public static function update($table = 'wpl_users', $id, $key, $value = '')
	{
		/** first validation **/
		if(trim($table) == '' or trim($id) == '' or trim($key) == '') return false;
		return wpl_db::set($table, $id, $key, $value);
	}
	
	/**
		@input {user_id}
		@return boolean result
		@description for checking if a user is wordpress admin or not
		@author Howard
	**/
	public static function is_administrator($user_id = '')
	{
		/** get current user id **/
		if(!trim($user_id)) $user_id = wpl_users::get_cur_user_id();
		if($user_id == 0 or $user_id == '') return false;
		
		$administrator = wpl_global::has_permission('administrator', $user_id);
		$super_admin = wpl_users::is_super_admin();
		
		if($super_admin) return true;
		return $administrator;
	}
	
	/**
		@input {user_id}
		@return boolean result
		@description for checking if a user is wordpress network admin or not
            USE is_administrator FUNCTION IF YOU WANT TO CHECK ADMIN. this function is checking super admin (Network admin)
		@author Howard
	**/
	public static function is_super_admin($user_id = '')
	{
		/** get current user id **/
		if(!trim($user_id)) $user_id = wpl_users::get_cur_user_id();
		if($user_id == 0 or $user_id == '') return false;
		
		return is_super_admin($user_id);
	}
	
	/**
		@input {user_id}
		@return boolean result
		@description for checking if a user is added to wpl or not
		@author Howard
	**/
	public static function is_wpl_user($user_id = '')
	{
		/** get current user id **/
		if(!trim($user_id)) $user_id = wpl_users::get_cur_user_id();
		if($user_id == 0 or $user_id == '') return false;
		
		$result = wpl_users::get_wpl_user($user_id);
		
		if(!$result) return false;
		else return true;
	}
	
	/**
		@input {user_id} and {membership_id}
		@return void
		@description use this function for changing membership of a user
		@author Howard
	**/
	public static function change_membership($user_id, $membership_id = -1, $trigger_event = true)
	{
		$user_data = wpl_users::get_wpl_data($user_id);
        
        /** Add user to WPL if not added **/
        if(!$user_data)
        {
            wpl_users::add_user_to_wpl($user_id);
            $user_data = wpl_users::get_wpl_data($user_id);
        }
        
        $method = (isset($user_data->membership_id) and $user_data->membership_id != $membership_id) ? 'membership_changed' : 'access_updated';
        
		$membership_data = wpl_users::get_wpl_data($membership_id);
		$query1 = '';
		
		foreach($membership_data as $key=>$value)
		{
			if(substr($key, 0, 7) != 'access_' and substr($key, 0, 8) != 'maccess_') continue;
			$query1 .= "`$key`='$value', ";
		}
		
		$query1 = trim($query1, ', ');
		$query = "UPDATE `#__wpl_users` SET ".$query1.", `membership_id`='".$membership_id."' WHERE `id`='".$user_id."'";
		wpl_db::q($query);
		
        /** user assigned to a new group **/
        if($method == 'membership_changed' and wpl_global::check_addon('membership'))
        {
            /** Import library **/
            _wpl_import('libraries.addon_membership');
            
            $membership = new wpl_addon_membership();
            $membership->update_expiry_date($user_id, NULL, true);
        }
        
		/** trigger event **/
        $params = array('user_id'=>$user_id, 'previous_membership'=>$user_data->membership_id, 'new_membership'=>$membership_id);
        
		if($trigger_event and $method == 'access_updated') wpl_global::event_handler('user_access_updated', $params);
		elseif($trigger_event and $method == 'membership_changed') wpl_global::event_handler('user_membership_changed', $params);
	}
	
	/**
		@input {access}, [owner_id] and [user_id]
		@return void
		@description use this function for changing membership of a user
		@author Howard
	**/
	public static function check_access($access, $owner_id = 0, $user_id = '')
	{
		/** get current user id **/
		if(trim($user_id) == '') $user_id = wpl_users::get_cur_user_id();
        
		$user_data = wpl_users::get_wpl_data($user_id);
        
        /** user is registered in WordPress but not in WPL so we choose guest user for accesses **/
        if(!$user_data) $user_data = wpl_users::get_wpl_data(0);
		
		if($access == 'edit')
		{
			if($owner_id == $user_id or wpl_users::is_administrator($user_id)) return true;
		}
		elseif($access == 'add')
		{
			$num_prop_limit = $user_data->maccess_num_prop;
			$num_prop = wpl_users::get_users_properties_count($user_id);
			
			if($num_prop_limit == '-1') return true; # unlimited
			if($num_prop_limit <= $num_prop and !wpl_users::is_administrator($user_id)) return false;
			else return true;
		}
		elseif($access == 'delete')
		{
			if($user_data->access_delete and ($owner_id == $user_id or wpl_users::is_administrator($user_id))) return true;
		}
		elseif($access == 'confirm')
		{
			if($user_data->access_confirm and ($owner_id == $user_id or wpl_users::is_administrator($user_id))) return true;
		}
		else
		{
			return isset($user_data->{'access_'.$access}) ? $user_data->{'access_'.$access} : 0;
		}
		
		return false;
	}
	
	/**
		@input {user_id} and [condition]
		@return void
		@description use this function for getting count of properties
		@author Howard
	**/
	public static function get_users_properties_count($user_id = '', $condition = '')
	{
		/** get current user id **/
		if(trim($user_id) == '') $user_id = wpl_users::get_cur_user_id();
		
		$query = "SELECT COUNT(id) FROM `#__wpl_properties` WHERE `user_id`='$user_id' ".$condition;
		return wpl_db::select($query, 'loadResult');
	}
	
	/**
		@inputs {start}, {limit}, {orderby}, {order}, {where}
		@param int $start
		@param int $limit
		@param string $orderby
		@param string $order
		@param array $where
		@return void
		@description for start property model use this function for configuration
		@author Howard
	**/
	public function start($start, $limit, $orderby, $order, $where)
    {
		/** start time of model **/
		$this->start_time = microtime(true);
		
		/** pagination and order options **/
		$this->start = $start;
		$this->limit = $limit;
		$this->orderby = $orderby;
		$this->order = $order;
		
		/** main table **/
		$this->main_table = "`#__wpl_users` AS p";
		
		/** queries **/
		$this->join_query = $this->create_join();
		$this->groupby_query = $this->create_groupby();
		
		/** generate where condition **/
		$where = (array) $where;
		$this->where = wpl_db::create_query($where);
		
		/** generate select **/
		$this->select = '*';
    }
	
	/**
		@inputs void
		@return string $quert
		@description this functions creates complete query
		@author Howard
	**/
	public function query()
    {
		$this->query  = "SELECT ".$this->select;
        $this->query .= " FROM ".$this->main_table;
        $this->query .= $this->join_query;
		$this->query .= " WHERE 1 ".$this->where;
		$this->query .= $this->groupby_query;
        $this->query .= " ORDER BY ".$this->orderby." ".$this->order;
        $this->query .= " LIMIT ".$this->start.", ".$this->limit;
		$this->query  = trim($this->query, ', ');
		
		return $this->query;
    }
	
	/** [TODO] **/
	public function create_join()
	{
		return '';
	}
	
	/** [TODO] **/
	public function create_groupby()
	{
		return '';
	}
	
	/**
		@inputs string $query
		@return object $properties
		@description use this function for running query and fetch the result
		@author Howard
	**/
	public function search($query = '')
    {
        if(!trim($query)) $query = $this->query;
		
        $users = wpl_db::select($query);
        return $users;
    }
	
	/**
		@inputs void
		@return int $time_taken
		@description this function is for calculating token time and total result
		@author Howard
	**/
	public function finish()
	{
		$this->finish_time = microtime(true);
        $this->time_taken = $this->finish_time - $this->start_time;
		$this->total = $this->get_users_count();
		
		return $this->time_taken;
	}
	
	/**
     * @return number of users according to query condition
     * @author Howard
     */
    public function get_users_count()
    {
        $query = "SELECT COUNT(*) AS count FROM `#__wpl_users` WHERE 1 " . $this->where;
        return wpl_db::select($query, 'loadResult');
    }
	
	/**
		@inputs {user_id}, [target_id]
		@return profile_show full link
		@author Howard
	**/
	public static function get_profile_link($user_id = '', $target_id = 0)
	{
		/** fetch currenr user data if user id is empty **/
		if(trim($user_id) == '') $user_id = self::get_cur_user_id();
        
        $user_data = self::get_user($user_id);
        $home_type = wpl_global::get_wp_option('show_on_front', 'posts');
        $home_id = wpl_global::get_wp_option('page_on_front', 0);
            
		if(!$target_id) $target_id = wpl_request::getVar('wpltarget', 0);
		if($target_id)
        {
            $url = wpl_global::add_qs_var('uid', $user_id, wpl_sef::get_page_link($target_id));
            
            if($home_type == 'page' and $home_id == $target_id) $url = wpl_global::add_qs_var('wplview', 'profile_show', $url);
        }
		else
        {
            $url = wpl_sef::get_wpl_permalink(true);
            $nosef = wpl_sef::is_permalink_default();
            $wpl_main_page_id = wpl_sef::get_wpl_main_page_id();
            
            if($nosef or ($home_type == 'page' and $home_id == $wpl_main_page_id))
            {
                $url = wpl_global::add_qs_var('wplview', 'profile_show', $url);
                $url = wpl_global::add_qs_var('uid', $user_id, $url);
            }
            else $url .= urlencode($user_data->data->user_login).'/';
        }
		
        return $url;
    }
	
	/**
		@inputs [params]
		@return array or html
		@description Use this function for generating sort options
		@author Howard
	**/
	public function generate_sorts($params = array())
	{
		include _wpl_import('views.basics.sorts.profile_listing', true, true);
		return $result;
	}
	
	/**
		@inputs {property data}, [fields] and [finds]
		@param property data should be raw data from wpl_properties table
		@param fields should be an object of dbst fields
		@param finds detected files array
		@return rendered data
		@author Howard
	**/
	public static function render_profile($profile, $fields, &$finds = array(), $material = false)
	{
		_wpl_import('libraries.property');
		return wpl_property::render_property($profile, $fields, $finds, $material);
	}
	
    /**
     * Register a new user into the WordPress
     * @author Howard <howard@realtyna.com>
     * @static
     * @param string $username
     * @param string $email
     * @param string $password
     * @return mixed user id or error object
     */
    public static function register_user($username, $email, $password = NULL)
    {
        /** first validation **/
        if(!trim($username) or !trim($email)) return false;
        
        return wp_create_user($username, $password, $email);
    }
    
	/**
		@inputs {user_id}
		@description This function finalizes user profile and triggering events
		@author Howard
	**/
	public static function finalize($user_id)
	{
		/** create folder **/
		$folder_path = wpl_items::get_path($user_id, 2);
		if(!wpl_folder::exists($folder_path)) wpl_folder::create($folder_path);
		
		wpl_users::update_text_search_field($user_id);
        wpl_users::update_location_text_search_field($user_id);
		wpl_users::generate_email_files($user_id);
		
		/** generate rendered data **/
		if(wpl_settings::get('cache')) wpl_users::generate_rendered_data($user_id);
		
        /** throwing event **/
        wpl_events::trigger('user_finalized', $user_id);
		
		return true;
    }
	
	/**
		@inputs {user_id}
		@description This function is for updating location text search field
		@author Howard
	**/
	public static function update_location_text_search_field($user_id)
	{
        $user_data = (array) wpl_users::get_wpl_user($user_id);
        
        /** User Data is invalid **/
        if(!isset($user_data['location1_name'])) return false;
        
		$location_text = $user_data['location7_name'].', '.$user_data['location6_name'].', '.$user_data['location5_name'].', '.
						 $user_data['location4_name'].', '.$user_data['location3_name'].', '.$user_data['location2_name'].', '.$user_data['location1_name'];
		
		$location_text = $user_data['zip_name'].', '.trim($location_text, ', ');
		wpl_db::set('wpl_users', $user_id, 'location_text', trim(wpl_db::escape($location_text), ', '));
    }
	
	/**
		@inputs {user_id}
		@description This function is for updating textsearch field
		@author Howard
	**/
	public static function update_text_search_field($user_id)
	{
        $user_data = (array) wpl_users::get_wpl_user($user_id);
		
		/** get text_search fields **/
		$fields = wpl_flex::get_fields('', 1, 2, 'text_search', '1');
		$rendered = self::render_profile($user_data, $fields);
		
		$text_search_data = array();
		
		foreach($rendered as $data)
		{
			if((isset($data['type']) and !trim($data['type'])) or (isset($data['value']) and !trim($data['value']))) continue;
			
			/** default value **/
			$value = isset($data['value']) ? $data['value'] : '';
			$value2 = '';
			$type = isset($data['type']) ? $data['type'] : '';
			
			if($type == 'text' or $type == 'textarea')
			{
				$value = $data['name'] .' '. $data['value'];
			}
			elseif($type == 'locations' and isset($data['locations']) and is_array($data['locations']))
			{
				$location_value = '';
				foreach($data['locations'] as $location_level=>$value)
				{
					$location_value .= $data['keywords'][$location_level] .' ';
					$location_value .= $value . ' ';
				}
				
				$value = $location_value;
			}
			elseif(isset($data['value']))
			{
				$value = $data['name'] .' '. $data['value'];
				if(is_numeric($data['value']))
				{
					$value2 = $data['name'] .' '. wpl_global::number_to_word($data['value']);
				}
			}
			
			/** set value in text search data **/
			$text_search_data[] = $value;
			if(trim($value2) != '') $text_search_data[] = $value2;
		}
		
		wpl_db::set('wpl_users', $user_id, 'textsearch', implode(' ', wpl_db::escape($text_search_data)));
    }
	
	/**
		@inputs {user_id}
		@description This function is for generating email files of user
		@author Howard
	**/
	public static function generate_email_files($user_id)
	{
		/** import library **/
		_wpl_import('libraries.images');
		
        $user_data = (array) wpl_users::get_user($user_id);
		$path = wpl_items::get_path($user_id, 2);
        
        /** delete images **/
        if(wpl_file::exists($path.'main_email.png')) wpl_file::delete($path.'main_email.png');
        if(wpl_file::exists($path.'second_email.png')) wpl_file::delete($path.'second_email.png');
        
		if(is_object($user_data['data']) and trim($user_data['data']->wpl_data->main_email) != '') wpl_images::text_to_image($user_data['data']->wpl_data->main_email, '000000', $path.'main_email.png');
		if(is_object($user_data['data']) and trim($user_data['data']->wpl_data->secondary_email) != '') wpl_images::text_to_image($user_data['data']->wpl_data->secondary_email, '000000', $path.'second_email.png');
    }
	
	/**
		@inputs {user_id}
		@description this function will generate rendered data of user and save them into db
		@author Howard
	**/
	public static function generate_rendered_data($user_id)
	{
		_wpl_import('libraries.render');
		
		/** get user data **/
		$user_data = (array) wpl_users::get_wpl_user($user_id);
		
		/** location text **/
		$location_text = wpl_users::generate_location_text($user_data);
		
        /** render data **/
        $find_files = array();
        $rendered_fields = self::render_profile($user_data, wpl_users::get_plisting_fields(), $find_files, true);
        
		$result = json_encode(array('rendered'=>$rendered_fields['ids'], 'materials'=>$rendered_fields['columns'], 'location_text'=>$location_text));
		$query = "UPDATE `#__wpl_users` SET `rendered`='".wpl_db::escape($result)."', `location_text`='".wpl_db::escape($location_text)."' WHERE `id`='$user_id'";
		
		/** update **/
		wpl_db::q($query, 'update');
        
        return $result;
	}
	
	/**
		@inputs [user_data], {user_id} and {glue}
		@return string location_text
		@author Howard
	**/
	public static function generate_location_text($user_data, $user_id = 0, $glue = ',')
	{
		/** fetch user data if user id is setted **/
		if($user_id) $user_data = (array) wpl_users::get_wpl_user($user_id);
		
		$locations = array();
        if(isset($user_data['location7_name']) and trim($user_data['location7_name']) != '') $locations['location7_name'] = $user_data['location7_name'];
        if(isset($user_data['location6_name']) and trim($user_data['location6_name']) != '') $locations['location6_name'] = $user_data['location6_name'];
		if(isset($user_data['location5_name']) and trim($user_data['location5_name']) != '') $locations['location5_name'] = $user_data['location5_name'];
        if(isset($user_data['location4_name']) and trim($user_data['location4_name']) != '') $locations['location4_name'] = $user_data['location4_name'];
        if(isset($user_data['location3_name']) and trim($user_data['location3_name']) != '') $locations['location3_name'] = $user_data['location3_name'];
        if(isset($user_data['location2_name']) and trim($user_data['location2_name']) != '') $locations['location2_name'] = $user_data['location2_name'];
        if(isset($user_data['zip_name']) and trim($user_data['zip_name']) != '') $locations['zip_name'] = $user_data['zip_name'];
        if(isset($user_data['location1_name']) and trim($user_data['location1_name']) != '') $locations['location1_name'] = $user_data['location1_name'];
        
        $location_pattern = wpl_global::get_setting('user_location_pattern');
        if(trim($location_pattern) == '') $location_pattern = '[location5_name][glue][location4_name][glue][location3_name][glue][location2_name][glue][location1_name] [zip_name]';
        
		$location_text = '';
        $location_text = isset($locations['location7_name']) ? str_replace('[location7_name]', $locations['location7_name'], $location_pattern) : str_replace('[location7_name]', '', $location_pattern);
        $location_text = isset($locations['location6_name']) ? str_replace('[location6_name]', $locations['location6_name'], $location_pattern) : str_replace('[location6_name]', '', $location_pattern);
        $location_text = isset($locations['location5_name']) ? str_replace('[location5_name]', $locations['location5_name'], $location_pattern) : str_replace('[location5_name]', '', $location_pattern);
        $location_text = isset($locations['location4_name']) ? str_replace('[location4_name]', $locations['location4_name'], $location_text) : str_replace('[location4_name]', '', $location_text);
        $location_text = isset($locations['location3_name']) ? str_replace('[location3_name]', $locations['location3_name'], $location_text) : str_replace('[location3_name]', '', $location_text);
        $location_text = isset($locations['location2_name']) ? str_replace('[location2_name]', $locations['location2_name'], $location_text) : str_replace('[location2_name]', '', $location_text);
        $location_text = isset($locations['zip_name']) ? str_replace('[zip_name]', $locations['zip_name'], $location_text) : str_replace('[zip_name]', '', $location_text);
        $location_text = isset($locations['location1_name']) ? str_replace('[location1_name]', $locations['location1_name'], $location_text) : str_replace('[location1_name]', '', $location_text);
        $location_text = str_replace('[glue]', $glue, $location_text);
        
        $final = '';
        $ex = explode($glue, $location_text);
        
        foreach($ex as $value)
        {
            if(trim($value) == '') continue;
            
            $final .= trim($value).$glue.' ';
        }
        
		return trim($final, ', ');
    }
	
	/**
		@inputs {user}
		@return array full render of user
		@description This is a very useful function for rendering whole data of user. you need to just pass user_id and get everything!
		@author Howard
	**/
	public static function full_render($user_id, $plisting_fields = NULL, $profile = NULL, $params = array())
	{
		/** get plisting fields **/
		if(!$plisting_fields) $plisting_fields = self::get_plisting_fields();
		
		$raw_data = (array) self::get_wpl_user($user_id);
		if(!$profile) $profile = (object) $raw_data;
		
        /** generate rendered data if rendered data is empty **/
        if(!trim($raw_data['rendered']) and wpl_settings::get('cache')) $rendered = json_decode(wpl_users::generate_rendered_data($user_id), true);
        elseif(!wpl_settings::get('cache')) $rendered = array();
        else $rendered = json_decode($raw_data['rendered'], true);
        
		$result = array();
		$result['data'] = (array) $profile;
		$result['items'] = wpl_items::get_items($profile->id, '', 2, '', 1);
		$result['raw'] = $raw_data;
		
        if(!isset($rendered['rendered']) or !isset($rendered['materials']))
        {
            /** render data on the fly **/
            $find_files = array();
            $rendered_fields = self::render_profile($profile, $plisting_fields, $find_files, true);
        }
        
		if(isset($rendered['rendered'])) $result['rendered'] = $rendered['rendered'];
		else $result['rendered'] = $rendered_fields['ids'];
        
        if(isset($rendered['materials']) and $rendered['materials']) $result['materials'] = $rendered['materials'];
		else $result['materials'] = $rendered_fields['columns'];
        
		/** location text **/
		if(isset($rendered['location_text'])) $result['location_text'] = $rendered['location_text'];
		else $result['location_text'] = self::generate_location_text($raw_data);
		
		/** profile full link **/
        $target_id = isset($params['wpltarget']) ? $params['wpltarget'] : 0;
		$result['profile_link'] = self::get_profile_link($profile->id, $target_id);
		
		/** profile picture **/
		if(trim($raw_data['profile_picture']) != '')
		{
			$result['profile_picture'] = array(
				'url'=>wpl_items::get_folder($profile->id, 2).$raw_data['profile_picture'],
				'path'=>wpl_items::get_path($profile->id, 2).$raw_data['profile_picture'],
				'name'=>$raw_data['profile_picture']
			);
		}
		
		/** company logo **/
		if(trim($raw_data['company_logo']) != '')
		{
			$result['company_logo'] = array(
				'url'=>wpl_items::get_folder($profile->id, 2).$raw_data['company_logo'],
				'path'=>wpl_items::get_path($profile->id, 2).$raw_data['company_logo'],
				'name'=>$raw_data['company_logo']
			);
		}
		
		/** Emails url **/
		if(wpl_file::exists(wpl_items::get_path($profile->id, 2).'main_email.png'))
			$result['main_email_url'] = wpl_items::get_folder($profile->id, 2).'main_email.png';
		
		if(wpl_file::exists(wpl_items::get_path($profile->id, 2).'second_email.png'))
			$result['second_email_url'] = wpl_items::get_folder($profile->id, 2).'second_email.png';
		
		return $result;
	}
	
	/**
		@input string $username, string $password
		@return result
	**/
	public static function authenticate($username, $password)
	{
		$wp_auth = wp_authenticate($username, $password);
		$result = array();
		
		if(get_class($wp_auth) == 'WP_User')
		{
			$result['status'] = 1;
			$result['uid'] = $wp_auth->ID;
		}
		else
		{
			$result['status'] = 0;
			$result['uid'] = 0;
		}
		
		return $result;
	}
	
	/**
		Developed by : Chris
		Inputs : none
		Outputs : boolean
		Date : 2014-06-29
		Description : This Conditional Tag checks if the current visitor is logged in. This is a boolean function, meaning it returns either TRUE or FALSE
	**/
	public static function check_user_login()
	{
		if(is_user_logged_in()) return true;
		else return false;
	}
	
	/**
		Developed by : Chris
		Inputs : {user_data}[array]
		Outputs : if successful, returns the newly-created user's user_id, otherwise returns a WP_Error object
		Date : 2014-06-29
		Description : Insert a user into the database. 
	**/
	public static function insert_user($user_data)
	{
		$acceptable_fileds = array('ID', 'user_pass', 'user_login', 'user_nicename', 'user_url', 'user_email', 'display_name', 'nickname', 'first_name', 'last_name', 'description', 'rich_editing', 'user_registered', 'role', 'jabber', 'aim', 'yim');
		$insert_data = array();
        
		if((array_key_exists('user_login', $user_data)) and (array_key_exists('user_email', $user_data)) and (array_key_exists('user_pass', $user_data)))
		{
			foreach($user_data as $key=>$value)
			{
				if(!in_array($key, $acceptable_fileds)) continue;
				$insert_data[$key] = $value;
			}
            
			return wp_insert_user($insert_data);
		}
		else
		{
			return new WP_Error('broke', __("ERROR: Required fileds are invalid!", WPL_TEXTDOMAIN));
		}
	}
	
	/**
		Developed by : Chris
		Inputs : {user_data}[array]
		Outputs : if successful, returns the newly-created user's user_id, otherwise returns a WP_Error object
		Date : 2014-06-29
		Description : Authenticates a user with option to remember credentials
	**/
	public static function login_user($user_data, $secure_cookie = false)
	{
		$acceptable_fileds = array('user_login', 'user_password', 'remember');
		$login_data = array();
        
		if((is_array($user_data)) && (array_key_exists('user_login', $user_data)) && (array_key_exists('user_password', $user_data)) && (array_key_exists('remember', $user_data)))
		{
			foreach($user_data as $key=>$value)
			{
				if(!in_array($key, $acceptable_fileds)) continue;
                $login_data[$key] = $value;
			}
            
			return wp_signon($login_data, $secure_cookie);
		}
		else
		{
			return new WP_Error('broke', __('ERROR: Required fileds are invalid!', WPL_TEXTDOMAIN));
		}
	}
    
    /**
     * Get user data by field and data. The possible fields are shown below with the corresponding columns in the wp_users database table. 
     * @author Chris <chris@realtyna.com>
     * @param string $field
     * @param string $data
     * @return mixed WP_User object or false if no user is found. Will also return false if $field does not exist.
     */
	public static function get_user_by($field, $data)
	{
		$acceptable_fileds = array('id', 'slug', 'email', 'login');
		if(in_array($field, $acceptable_fileds)) return get_user_by($field, trim($data));
	}
    
    /**
     * Removes user thumbnails
     * @author Howard R <Howard@realtyna.com>
     * @static
     * @param int $user_id
     * @return boolean
     */
    public static function remove_thumbnails($user_id)
	{
        /** first validation **/
        if(!trim($user_id)) return false;
        
		$ext_array = array('jpg', 'jpeg', 'gif', 'png');
        $path = wpl_items::get_path($user_id, 2);
        $thumbnails = wpl_folder::files($path, 'th.*\.('.implode('|', $ext_array).')$', 3, true);

        foreach($thumbnails as $thumbnail)
        {
            wpl_file::delete($thumbnail);
        }
        
        return true;
	}
    
    /**
     * Wrapper for WordPress wp_logout function
     * @author Howard <howard@realtyna.com>
     * @static
     */
    public static function wp_logout()
    {
        wp_logout();
    }
    
    /**
     * Validate activation key
     * @author Howard <howard@realtyna.com>
     * @static
     * @param string $key
     * @return int User ID
     */
    public static function validate_activation_key($key)
    {
        /** first validation **/
        if(!trim($key)) return 0;
        
        $query = "SELECT `id` FROM `#__users` WHERE `user_activation_key`='$key'";
        $id = wpl_db::select($query, 'loadResult');
        
        if($id) return $id;
        return 0;
    }
    
    /**
     * Wrapper for WordPress wp_set_password function
     * @author Howard <howard@realtyna.com>
     * @static
     * @param int $user_id
     * @param string $password
     * @return void
     */
    public static function set_password($user_id, $password)
    {
        wp_set_password($password, $user_id);
    }
    
    /**
     * Wrapper for WordPress update_user_option function
     * @author Howard <howard@realtyna.com>
     * @static
     * @param int $user_id
     * @param string $option_name
     * @param mixed $newvalue
     * @param boolean $global
     * @return boolean
     */
    public static function update_user_option($user_id, $option_name, $newvalue, $global = false)
    {
        return update_user_option($user_id, $option_name, $newvalue, $global);
    }
    
    /**
     * Check if username exists or not
     * @author Howard <howard@realtyna.com>
     * @static
     * @param string $username
     * @return boolean
     */
    public static function username_exists($username)
    {
        /** first validation **/
        if(!trim($username)) return true;
        
        return username_exists($username);
    }
    
    /**
     * Check if email exists or not
     * @author Howard <howard@realtyna.com>
     * @static
     * @param string $email
     * @return boolean
     */
    public static function email_exists($email)
    {
        /** first validation **/
        if(!trim($email)) return true;
        
        return email_exists($email);
    }
    
    /**
     * Wrapper function for WordPress wp_insert_user
     * @author Howard R <Howard@realtyna.com>
     * @static
     * @param array $userdata
     * @return mixed
     */
    public static function wp_insert_user($userdata = array())
    {
        return wp_insert_user($userdata);
    }
    
    /**
     * Wrapper function for WordPress wp_set_password function
     * @author Howard R <Howard@realtyna.com>
     * @static
     * @param string $password
     * @param int $user_id
     */
    public static function wp_set_password($password, $user_id)
    {
        wp_set_password($password, $user_id);
    }
    
    /**
     * Check user/blog access to WPL menus
     * @author Howard R <Howard@realtyna.com>
     * @static
     * @param string $menu_slug
     * @param int $user_id
     * @return boolean
     */
    public static function has_menu_access($menu_slug, $user_id = NULL)
    {
        return true;
    }
}