<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Panels_model extends CI_Model {

	protected $table = 'panels';
	protected $genonum = 'genotype_number';

	const VALID_STATE = 1;

    function __construct() {
        // Call the Model constructor
        parent::__construct();
    }

	// = GET (BY ID) =====
	function get($id) {
		$panel = $this->db->select('*')
				->from($this->table)
				->where('id', $id)
				->get()
				->row_array();
		if ($panel) $panel['data'] = json_decode($panel['data'], true);
		return $panel;
	}

	// = GET BASE =====
	//	 <- $base_id (Int)
	//	-> List of Arrays ( all ), get all the panels from database $base_id
	function getBase($base_id) {
		$panels = $this->db->select('*')
				->from($this->table)
				->where('database_id', $base_id)
				->get()
				->result_array();
		foreach ($panels as &$panel) $panel['data'] = json_decode($panel['data'], true);
		return $panels;
	}

	// = ADD =====
	//	 <- $data (Array)
	//	 -> $id of the strain created with $data
	function add($data) {
		$this->db->insert($this->table, $data);
		return $this->db->insert_id();
	}

	// = DELETE DATABASE =====
	//	 <- $base_id (Int)
	//	 -> delete all the panels of the database $base_id
	function deleteDatabase($base_id) {
		$this->db->where('database_id', $base_id)
			->delete($this->table);
	}

	// = DELETE =====
	//	 <- $id (Int)
	//	 -> delete database $id
	function delete($id) {
		$this->db->where('id', $id)
			->delete($this->table);
	}

	// = UPDATE =====
	//	 <- $id (Int), $data (Array)
	//	 -> update the panel $id with $data
	function update($id, $data) {
		$this->db->where('id', $id)
			->update($this->table, $data);
	}

	// = EXIST =====
	//	 <- $where (Array)
	//	 -> List of ( id ) of panels $where
	function exist($where) {
		return $this->db->select('id')
				->from($this->table)
				->where($where)
				->get()
				->result_array();
	}

	// = GET GN =====
	//	 <- $panel_id (Int)
	//	-> List of Arrays ( all ), get all the genotype numbers of panel $base_id
	function getGN($panel_id) {
		$genonums = $this->db->select('*')
				->from($this->genonum)
				->where('panel_id', $panel_id)
				->get()
				->result_array();
		foreach ($genonums as &$gn) $gn['data'] = json_decode($gn['data'], true);
		return $genonums;
	}

	// = GET GN =====
	//	 <- $panel_id (Int)
	//	-> List of Arrays ( all ), get the genotype numbers imported by a user (not generated) of panel $base_id
	function getValidGN($panel_id) {
		return $this->db->select('*')
				->from($this->genonum)
				->where('panel_id', $panel_id)
				->where('state', self::VALID_STATE)
				->get()
				->result_array();
	}

	// = ADD GN =====
	//	 <- $data (Array)
	//	 -> $id of the genotype number created with $data
	function addGN($data) {
		$this->db->insert($this->genonum, $data);
		return $this->db->insert_id();
	}

	// = ADD GN =====
	//	 <- $data (Array)
	//	 -> $id of the genotype number created with $data
	function setGN($where, $value, $state = 1) {
		$gn = $this->db->select('state')
				->from($this->genonum)
				->where($where)
				->get();
		if ($gn->num_rows() > 0) {
			$this->db->where($where)
				->update($this->genonum, ['value' => $value, 'state' => $state]);
		} else {
			$this->db->insert( $this->genonum, array_merge($where, ['value' => $value, 'state' => $state]) );;
		}
	}

	// = UPDATE GN =====
	function updateGN($panel_id, $new_value, $old_value = null, $data = []) {
		if ($old_value) {
			$this->db
				->where('panel_id', $panel_id)
				->where('value', $old_value)
				->set([ 'value' => $new_value ])
				->update($this->genonum);
		} else {
			$this->db->insert($this->genonum, [
				'panel_id' => $panel_id,
				'value' => $new_value,
				'data' => json_encode($data),
			]);
		}
	}

}
