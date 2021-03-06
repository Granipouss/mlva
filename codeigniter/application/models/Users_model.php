<?php
class  Users_model extends CI_Model
{
	protected $table = 'users';
	protected $table_groups = 'groups';
	protected $table_infos = 'users_infos';
	protected $user_has_group = 'user_has_group';
	const USER_GROUP_ID = 4;
	const ADMIN_GROUP_ID = 1;

	public function __construct()
	{
	  parent::__construct();
	}

	public static function getAdminGroupId()
	{
		return self::ADMIN_GROUP_ID;
	}

	public function addToGroup($user_id, $group_id = self::USER_GROUP_ID)
	{
		$this->db->insert($this->user_has_group, array('group_id' => $group_id, 'user_id' => $user_id));
	}

	public function authenticate(array $credentials)
	{
		extract($credentials);
		$result = $this->db->select('id, password')->where('username', $username)->get($this->table, 1, 0)->row_array();

		return password_verify($password, $result['password']) ? $result['id'] : 0;
	}

	public function count($where = array())
	{
		return (int) $this->db->where($where)->count_all_results($this->table);
	}

	public function countGroups($where = array())
	{
		return (int) $this->db->where($where)->count_all_results($this->table_groups);
	}
	/**
	 * Return the number of users of a group
	 */
	public function countOfGroup($group_id = 0)
	{
		return $this->db->where('group_id', $group_id)
										->join($this->user_has_group, 'users.id = user_id')
										->count_all_results($this->table);
	}

	public function create(array $newUser)
	{
		$newUser['password'] = simpleHash($newUser['password']);
		$newUser = array_merge($newUser, ['created_at' => Carbon\Carbon::now(), 'last_login' => Carbon\Carbon::now()]);
		$this->db->insert($this->table, $newUser);
		$id = $this->db->insert_id();
		//$this->addToGroup($id = $this->db->insert_id(), self::USER_GROUP_ID);
		$this->db->insert($this->table_infos, ['user_id' => $id]);
		return $id;
	}

	public function createGroup(array $newGroup)
	{
		$this->db->insert($this->table_groups, $newGroup);
		return $this->db->insert_id();
	}

	public function deleteGroup($group_id)
	{
		//Clean the user_has_group table
		$this->db->where('group_id', $group_id)->delete($this->user_has_group);
		$this->db->where('id', $group_id)->delete($this->table_groups);
	}

	public function deleteUser($user_id)
	{
		//Clean the user_has_group table
		$this->removeFromAllGroups($user_id);
		$this->db->where('user_id', $user_id)->delete($this->table_infos);//delete the user infos
		$this->db->where('id', $user_id)->delete($this->table);
	}

	public function get($id = 0)
	{
		return $this->getWhere(['id' => $id]);
	}

	/**
	 * Get all the users with their groups
	 */
	public function getAll($nb = 10, $start = 0, $order_by = 'userId', $where = array(), $order = 'desc')
	{
		$select = 'users.id As userId, username, first_name, last_name, email, last_login, created_at';
		$group_concat = "GROUP_CONCAT(DISTINCT groups.name	ORDER BY groups.id ASC SEPARATOR '#') AS groups,	GROUP_CONCAT(DISTINCT groups.id ORDER BY groups.id ASC SEPARATOR '#') AS groups_id";
		return $this->db->select($select.','.$group_concat)
									->where($where)
									->join($this->table_infos, $this->table_infos.'.user_id = users.id', 'left')
									->join($this->user_has_group, 'user_has_group.user_id = users.id', 'left')
									->join($this->table_groups, 'groups.id = user_has_group.group_id', 'left')
									->group_by('username')
									->order_by($order_by, $order)
									->get($this->table, $nb, $start)
									->result_array();
	}

	public function getAllGroups($nb =-1, $start = 0, $order_by = 'id', $where = array(), $order = 'asc')
	{
		$query =  $this->db->where($where)->order_by($order_by, $order);
		if ($nb > 0)
		{
			$query = $query->limit($nb, $start);
		}
		return $query->get($this->table_groups)->result_array();
	}

	public function getAllUsers($nb =-1, $start = 0, $order_by = 'id', $where = array(), $order = 'asc')
	{
		$query =  $this->db->where($where)->order_by($order_by, $order);
		if ($nb > 0)
		{
			$query = $query->limit($nb, $start);
		}
		return $query->get($this->table)->result_array();
	}

	public function getGroup($id = 0)
	{
		return $this->db->where('id', $id)
									->get($this->table_groups, 1, 0)
									->row_array();
	}
	/**
	 * Return the first user with a given argument (id, email, username,...)
	 */
	public function getWhere(array $where)
	{
		return $this->db->where($where)
										->join($this->table_infos, $this->table_infos.'.user_id = id', 'left')
										->get($this->table, 1, 0)
										->row_array();
	}
	/**
	 * Return the groups of a given user
	 */
	public function getUserGroups($user_id = 0, $order_by = 'id')
	{
		return $this->db->select('id, name, description, permissions')
									->where('user_id', $user_id)
									->join($this->user_has_group, 'groups.id = group_id')
									->order_by($order_by, 'desc')
									->get($this->table_groups)
									->result_array();
	}
	/**
	 * Return the users od a given group
	 */
	public function getUsersOfGroup($group_id = 0, $nb = -1, $start = 0, $order_by = 'id', $order = 'desc')
	{
		$query =  $this->db->where('group_id', $group_id)
												->join($this->user_has_group, 'users.id = user_id')
												->order_by($order_by, $order);
		if ($nb > 0)
		{
			$query = $query->limit($nb, $start);
		}
		return $query->get($this->table)->result_array();
	}

	/**
	 * Transform the sql concat output of groups to array in the $users array
	 */
	public function groupConcatToArray($users)
	{
		foreach ($users as &$user)
		{
			// [[groups => 'aa#bb#cc'], [groups_id => '2#4#7']] outputs [groups => [2 =>'aa', 4=>'bb', 7=>'cc']]
			$user['groups'] = array_combine(explode("#", $user['groups_id']), explode("#", $user['groups']));
		}
		return $users;
	}

	/**
	 * Remove all users from the group with the id $group_id
	 * Empty a group
	 */
	public function removeAllUsersFromGroup($group_id)
	{
		$this->db->where('group_id', $group_id)->delete($this->user_has_group);
	}

	public function removeFromAllGroups($user_id)
	{
		$this->db->where('user_id', $user_id)->delete($this->user_has_group);
	}
	/**
	 * Update the users of a group given an array containing all the users of this group
	 * Performances can be improve calculting the diff between the previous array of users and the new one
	 */
	public function syncUsersOfGroup(array $users, $group_id)
	{
		$this->removeAllUsersFromGroup($group_id);
		foreach ($users as $user_id)
		{
			$this->addToGroup($user_id, $group_id);
		}
	}

	public function update(array $newValues, $where = array(), $userInfosTable = false)
	{
		$table = $userInfosTable ? $this->table_infos : $this->table;
		$this->db->set($newValues)->where($where)->update($table);
	}

	public function updateGroup(array $newValues, $where = array())
	{
		$this->db->set($newValues)->where($where)->update($this->table_groups);
	}
	public function updateUserGroups(array $groups, $user_id)
	{
		$this->removeFromAllGroups($user_id);
		foreach ($groups as $group => $group_id)
		{
			$this->addToGroup($user_id, $group_id);
		}
	}
}
