<?php
class Databases extends CI_Controller {

	// = CONSTANTS =====
	const PUBLIC_STATE = 1;
	const NB_GROUPS_PER_PAGE = 20;

	// = CONSTRUCT =====
	public function __construct() {
		parent::__construct();
		$this->load->library('Twig');
		$this->load->model('databases_model', 'database');
		$this->load->model('strains_model', 'strain');
		$this->load->model('panels_model', 'panel');
		$this->load->model('users_model', 'user');
	}

	// = REMAP =====
	function _remap( $method, $id ) {
		if ( !empty($id) ) {
			$lvl = $this->authLevel($id[0]);
			if ( $lvl == -1 ) {
				show_404();
			} else {
				switch ($method) {
					case "view":
						( $lvl >= 1 ? $this->view($id[0]) : show_403() );
					break;
					case "map":
						( $lvl >= 2 ? $this->map($id[0]) : show_403() );
					break;
					case "edit":
						( $lvl >= 2 ? $this->edit($id[0]) : show_403() );
					break;
					case "import":
						( $lvl >= 2 ? $this->import($id[0]) : show_403() );
					break;
					case "editPanels":
						( $lvl >= 2 ? $this->editPanels($id[0]) : show_403() );
					break;
					case "export":
						( $lvl >= 1 ? $this->export($id[0]) : show_403() );
					break;
					case "delete":
						( $lvl >= 3 ? $this->delete($id[0]) : show_403() );
					break;
					case "public":
						$this->viewPublic();
					break;
					default:
						show_404();
					break;
				}
			}
		} else {
			if ( isLogged() ) {
				switch ($method) {
					case "index":
					case "user":
						$this->viewUser();
					break;
					case "public":
						$this->viewPublic();
					break;
					case "create":
						$this->create();
					break;
					default:
						$lvl = $this->authLevel($method);
						if ( $lvl >= 1 ) {
							$this->view($method);
						} else if ( $lvl >= 0 ) {
							show_403();
						} else {
							show_404();
						}
					break;
				}
			} else {
				switch ($method) {
					case "index":
					case "public":
						$this->viewPublic();
					break;
					default:
						$lvl = $this->authLevel($method);
						if ( $lvl >= 1 ) {
							$this->view($method);
						} else if ( $lvl >= 0 ) {
							show_403();
						} else {
							show_404();
						}
					break;
				}
			}
		}
	}

	// = VIEW PUBLIC =====
	public function viewPublic() {
		$data = array('bases' => $this->database->getPublic(), 'session' => $_SESSION);
		$this->twig->render('databases/public', $data);
	}

	// = VIEW GROUPS =====
	public function viewUser() {
		$group_data = array();
		foreach($_SESSION['groups'] as &$group) {
			$bases = $this->database->getGroup($group['id']);
			if (count($bases) > 0) {
				$group_data[$group['id']] = array(
					'bases' => $bases,
					'name' => $group['name']
				);
			}
		}
		$data = array('personal' => $this->database->getUserOnly($_SESSION['user']['id']),
					  'groups' => $group_data, 'session' => $_SESSION);
		$this->twig->render('databases/group', array_merge($data, getInfoMessages()));
	}

	// = VIEW =====
	public function view($id) {
		$base = $this->jsonExec($this->database->get($id));
		$strains = array_map(function($o){return $this->jsonExec($o);}, $this->strain->getBase($id));
		$filter = $base['data'];
		$filtername = '';
		if ($this->input->get('panel')) {
			$panel = $this->panel->get( $this->input->get('panel') );
			if ($panel['database_id'] == $id) {
				$filter = json_decode($panel['data']);
				$filtername = $panel['name'];
				$genonums = $this->panel->getGN($panel['id']);
				if ($genonums) {
					$showGN = true;
					foreach($genonums as &$genonum)
						{ $genonum['data'] = json_decode($genonum['data'], true); }
					foreach($strains as &$strain)
						{ $strain['genonum'] = $this->lookForGN($genonums, $filter, $geno); }
				}
			}
		}

		if( $base['group_id'] == -1 ) {
			$owner = $this->user->get($base['user_id']);
			$ownername = $owner['username'];
			$ownerlink = 'users/profile/'.$owner["username"];
		} else {
			$owner = $this->user->getGroup($base['group_id']);
			$ownername = $owner['name'];
			$ownerlink = ""; // ~~~
		}

		$data = array(
			'session' => $_SESSION,
			'base' => $base,
			'group' => $this->user->getGroup($base['group_id']),
			'owner' => $this->user->get($base['user_id']),
			'ownername' => $ownername,
			'ownerlink' => $ownerlink,
			'strains' => $strains,
			'level' => $this->authLevel($id),
			'panels' => $this->panel->getBase($id),
			'filter' => array( 'data' => $filter, 'name' => $filtername ),
			'showGN' => isset($showGN)
		);

		$this->twig->render('databases/view', array_merge($data, getInfoMessages()));
	}

	// = MAP =====
	public function map($id) {
		$base = $this->jsonExec($this->database->get($id));
		$strains = array_map(function($o){return $this->jsonExec($o);}, $this->strain->getBase($id));
		$data = array(
			'session' => $_SESSION,
			'base' => $base,
			'group' => $this->user->getGroup($base['group_id']),
			'owner' => $this->user->get($base['user_id']),
			'strains' => $strains,
			'geoJson' => $this->createGeoJson($strains),
			'level' => $this->authLevel($id),
		);
		$this->twig->render('databases/map', array_merge($data, getInfoMessages()));
	}

	// = EDIT =====
	public function edit($id) {
		$this->load->library('form_validation');
		$base = $this->database->get($id);

		if($this->form_validation->run('edit_db'))
		{
			$group_id = $this->input->post('group');
			if (($group_id != -1) && !inGroup($group_id, true))
			{
				setFlash('error', "You don't have the permission to add this database to this group");
			}
			elseif (($group_id == -1) && !isOwnerById($base['user_id']))
			{
				setFlash('error', "You don't have the permission to set this database as personal");
			}
			else
			{
				$updatedData = [
					'name' => $this->input->post('name'),
					'group_id' => $group_id,
					'state' => ($this->input->post('public') ? 1 : 0)
				];
				$this->database->update($updatedData, ['id' => $id]);
				setFlash('success', lang('auth_success_edit'));
				$base = $this->database->get($id);//Show the updated data
			}
		}

		$data = array(
			'session' => $_SESSION,
			'db' => $base,
		);
		$this->twig->render('databases/edit', array_merge($data, getInfoMessages()));
	}

	// = EDIT PANELS =====
	public function editPanels($base_id) {
		$this->load->library('form_validation');
		$base = $this->jsonExec($this->database->get($base_id));

		if($this->form_validation->run("edit_panel")) {
			$name = $this->input->post('name');
			$mvla = $this->input->post('data');
			$id = $this->input->post('id');
			if($id == -1) {
				$data = array (
					'name' => $name,
					'database_id' => $base_id,
					'data' => json_encode($mvla)
				);
				$this->panel->add($data);
				redirect(base_url('databases/editPanels/'.strval($base_id)));
			} else {
				$panel = $this->panel->get($id);
				if ($panel['database_id'] == $base_id) {
					$data = array (
						'name' => $name,
						'database_id' => $base_id,
						'data' => json_encode($mvla)
					);
					if( $this->input->post('action') == "Update" ) {
						$this->panel->update($id, $data);
					} elseif( $this->input->post('action') == "Delete" ) {
						$this->panel->delete($id);
					} elseif( $this->input->post('action') == "Generate" ) {
						$strains = array_map(function($o){return $this->jsonExec($o);}, $this->strain->getBase($base_id));
						$genonums = $this->panel->getGN($id);
						foreach($genonums as &$genonum) {
							$genonum['data'] = json_decode($genonum['data'], true);
						}
						$filter = json_decode($panel['data']);
						foreach($strains as &$strain) {
							$geno = array();
							foreach($filter as &$head) {
								$geno[$head] = $strain['data'][$head];
							}
							$value = $this->lookForGN($genonums, $geno);
							if ($value == -1) {
								$data = array (
									'panel_id' => $id,
									'data' => $geno,
									'value' => 1 + count($genonums)
								);
								array_push( $genonums, $data );
								$data['data'] = json_encode($data['data']);
								$this->panel->addGN($data);
							}
						}
					}
					redirect(base_url('databases/editPanels/'.strval($base_id)));
				}
			}
		}

		$data = array(
			'session' => $_SESSION,
			'base' => $base,
			'panels' => $this->panel->getBase($base_id)
		);
		$this->twig->render('databases/editPanels', array_merge($data, getInfoMessages()));
	}

	// = CREATE =====
	public function create() {
		$this->load->helper(array('form', 'url'));
		$this->load->library('form_validation');
		$info = [ 'session' => $_SESSION ];
		if ($this->input->post('step') == '1') {
			if ($this->form_validation->run("csv-create1")) {
				$validity = $this->validCSV($_FILES['csv_file']);
				if ($validity[0]) {
					// === Step 1 ===
					list($headers, $rows) = $this->readCSV($validity[1], $this->input->post('csvMode'));
					// list($coltype, $panels, $strains) = $this->sortRows($rows); ~~~
					setFlash('data_csv_upload', $rows); //Save the data in a temporary session variable
					setFlash('head_csv_upload', $headers);
					$data = array(
						'session' => $_SESSION,
						'basename' => explode('.', $_FILES['csv_file']['name'])[0],
						'headers' => $headers,
						'groups' => $_SESSION['groups']
					);
					$this->twig->render('databases/create-2', array_merge($data, getInfoMessages()));
				} else {
					$info['error'] = $validity[1];
					$this->twig->render('databases/create-1', $info);
				}
			} else {
				$this->twig->render('databases/create-1', $info);
			}
		} elseif ($this->input->post('step') == '2') {
			if ($this->form_validation->run("csv-create2")) {
				// === Step 2 ===
				if ($this->input->post('group') == -2) {
					$group_id = $this->createGroupWithDatabase($this->input->post('group_name'), $this->input->post('basename'));
				} else {
					//Make it personal db if the user has entered an invalid group_id
					$group_id = inGroup($this->input->post('group'), true) ? $this->input->post('group') : -1;
				}
				$base_id = $this->database->create( array (
					'name' => $this->input->post('basename'),
					'user_id' => $_SESSION['user']['id'],
					'group_id' => $group_id,
					'marker_num' => count($this->input->post('mlvadata')),
					'metadata' => json_encode($this->input->post('metadata')),
					'data' => json_encode($this->input->post('mlvadata')),
					'state' => ($this->input->post('public') == 'on' ? 1 : 0)
				));
				$strains = getFlash('data_csv_upload');
				$headers = getFlash('head_csv_upload');
				$this->addStrains($base_id, $strains, $headers, $this->input->post('metadata'), $this->input->post('mlvadata'));

				if ($this->input->post('location_key'))
				{
					$strains = array_map(function($o){return $this->jsonExec($o);}, $this->strain->getBase($base_id));
					$this->getGeolocalisationFromLocation($strains, $this->input->post('location_key'));
				}
				redirect(base_url('databases/'.strval($base_id)));
			} else {
				$data = array(
					'session' => $_SESSION,
					'basename' => $this->input->post('basename'),
					'headers' => getFlash('head_csv_upload'),
					'groups' => $_SESSION['groups']
				);
				$this->session->keep_flashdata('data_csv_upload');
				setFlash('head_csv_upload', $data['headers']);
				$this->twig->render('databases/create-2', $data);
			}
		} else {
			$this->twig->render('databases/create-1', $info);
		}
	}

	// = IMPORT =====
	public function import($base_id) {
		$this->load->helper(array('form', 'url'));
		$this->load->library('form_validation');
		$base = $this->jsonExec($this->database->get($base_id));
		$info = [ 'session' => $_SESSION, 'base' => $base ];
		if ($this->input->post('step') == '1') {
			if ($this->form_validation->run("csv-create1")) {
				$validity = $this->validCSV($_FILES['csv_file']);
				if ($validity[0]) {
					list($headers, $strains) = $this->readCSV($validity[1], $this->input->post('csvMode'));
					if (in_array("key", $headers)) {
						// === Step 1 ===
						$newheaders = array_diff($headers, array_merge(array("key"), $base["metadata"], $base["data"]));
						if ($this->input->post('addColumns') && !empty($newheaders)) {
							setFlash('head_csv_upload', $headers);
							setFlash('data_csv_upload', $strains);
							setFlash('addStrains', $this->input->post('addStrains'));
							setFlash('updateStrains', $this->input->post('updateStrains'));
							$data = array(
								'newheaders' => $newheaders,
							);
							$this->twig->render('databases/import-2', array_merge($data, $info, getInfoMessages()));
						} else {
							$toAdd = array (); $toUpdate = array ();
							$key_col = array_search("key", $headers);
							foreach($strains as &$strain) {
								$base_strain = $this->strain->get($base_id, $strain[$key_col]);
								if ($base_strain && $this->input->post('updateStrains'))
									{ array_push($toUpdate, [$base_strain, $strain]); }
								elseif (!$base_strain && $this->input->post('addStrains'))
									{ array_push($toAdd, $strain); }
							}
							$this->addStrains($base_id, $toAdd, $headers, $base["metadata"], $base["data"]);
							$this->updateStrains($base_id, $toUpdate, $headers, $base["metadata"], $base["data"]);
							redirect(base_url('databases/'.strval($base_id)));
						}
					} else {
						$info['error'] = "There must be a key column to recognize strains.";
						$this->twig->render('databases/import-1', $info);
					}
				} else {
					$info['error'] = $validity[1];
					$this->twig->render('databases/import-1', $info);
				}
			} else {
				$this->twig->render('databases/import-1', $info);
			}
		} elseif ($this->input->post('step') == '2') {
			// === Step 2 ===
			$base['metadata'] = array_merge( $this->input->post('metadata'), $base['metadata'] );
			$base['data'] = array_merge( $this->input->post('mlvadata'), $base['data'] );
			$data = array (
				'marker_num' => $base['marker_num'] + count($this->input->post('mlvadata')),
				'metadata' => json_encode($base['metadata']),
				'data' => json_encode($base['data']),
			);
			$this->database->update($data, array('id' => $base_id));
			// === Step 1 ===
			$strains = getFlash('data_csv_upload');
			$headers = getFlash('head_csv_upload');
			$addStrains = getFlash('addStrains');
			$updateStrains = getFlash('updateStrains');
			$toAdd = array (); $toUpdate = array ();
			$key_col = array_search("key", $headers);
			foreach($strains as &$strain) {
				$base_strain = $this->strain->get($base_id, $strain[$key_col]);
				if ($base_strain && $updateStrains)
					{ array_push($toUpdate, [$base_strain, $strain]); }
				elseif (!$base_strain && $addStrains)
					{ array_push($toAdd, $strain); }
			}
			$this->addStrains($base_id, $toAdd, $headers, $base["metadata"], $base["data"]);
			$this->updateStrains($base_id, $toUpdate, $headers, $base["metadata"], $base["data"]);
			redirect(base_url('databases/'.strval($base_id)));
		} else {
			$this->twig->render('databases/import-1', $info);
		}
	}

	// = EXPORT =====
	public function export($id) {
		$this->load->library('form_validation');
		$base = $this->jsonExec($this->database->get($id));
		$strains = array_map(function($o){return $this->jsonExec($o);}, $this->strain->getBase($id));
		if($this->form_validation->run('export_db')) {
			if ( $this->input->post('panel') != -1 ) {
				$panel = $this->panel->get( $this->input->post('panel') );
				if ($panel['database_id'] == $id) {
					$mlvadata = json_decode($panel['data']);
				} else {
					$mlvadata = $base['data'];
				}
			} else {
				$mlvadata = $base['data'];
			}
			$metadata = $this->input->post('metadata');
			// Header ~
			$rows = array( array_merge(array('key'), $metadata, $mlvadata) );
			// $rows = array( );
			// Struct ~
			// $row = array("[key]");
			// foreach($metadata as &$data)
				// { array_push($row, "info"); }
			// foreach($mlvadata as &$data)
				// { array_push($row, "mlva"); }
			// array_push($rows, $row);
			// Panels ~
			// $panels = $this->panel->getBase($id);
			// foreach($panels as &$panel) {
				// $row = array("[panel] ".$panel['name']);
				// $filter = json_decode($panel['data'], true);
				// foreach($metadata as &$data)
					// { array_push($row, ""); }
				// foreach($mlvadata as &$data) {
					// if (in_array($data, $filter) ) {
						// array_push($row, "X");
					// } else {
						// array_push($row, "");
					// }

				// }
				// array_push($rows, $row);
			// }
			// Strains ~
			foreach($strains as &$strain) {
				$row = array($strain['name']);
				foreach($metadata as &$data) {
					if ( array_key_exists($data, $strain['metadata'])) {
						array_push($row, $strain['metadata'][$data]);
					} else {
						array_push($row, "");
					}
				}
				foreach($mlvadata as &$data) {
					if ( array_key_exists($data, $strain['data'])) {
						array_push($row, $strain['data'][$data]);
					} else {
						array_push($row, "");
					}
				}
				array_push($rows, $row);
			}
			header( 'Content-Type: text/csv' );
			header( 'Content-Disposition: attachment;filename="'.$base['name'].'.csv"');
			$fp = fopen('php://output', 'c');
			fputs ($fp, "b");
			foreach($rows as &$row) {
				if ( $this->input->post('csvMode') == 'fr' ) {
					fputcsv($fp, $row, $delimiter = ";", $enclosure = '"');
				} else {
					fputcsv($fp, $row, $delimiter = ",", $enclosure = '"');
				}
			}
			fclose($fp);
		} else {
			$data = array(
				'session' => $_SESSION,
				'panels' => $this->panel->getBase($id),
				'base' => $base,
			);
			$this->twig->render('databases/export', array_merge($data, getInfoMessages()));
		}
	}

	// = DELETE =====
	public function delete($id) {
		//There is a missing check (to be sure that the user triggered this action)
		$this->load->helper('url');
		$base = $this->database->get($id);
		$this->strain->deleteDatabase($id);
		$this->database->delete($id);
		setFlash('info', 'The database '.$base['name'].' (n°'.$id.') has been deleted');
		redirect(base_url('databases/'));
	}

	// = AUTH LEVEL * =====
	function authLevel($id) {
		if ($base = $this->database->get($id)) {
			if ( isAdmin() ) {
				return 4; // Admin
			}
			if ( isLogged() ) {
				if ( isOwnerById($base['user_id']) ) {
					return 3; // Owner
				} else if ( inGroup($base['group_id']) ) {
					return 2; // Member
				}
			}
			if ($base['state'] == self::PUBLIC_STATE) {
				return 1; // Public
			}
			return 0; // Not Allowed
		} else {
			return -1; // Not Found
		}
	}

	// = JSON EXEC * =====
	function jsonExec($obj) {
		$obj['data'] = json_decode($obj['data'], true);
		$obj['metadata'] = json_decode($obj['metadata'], true);
		return $obj;
	}

	// ===========================================================================
	//  - STRAINS  -
	// ===========================================================================

	// = LOOK FOR GN * =====
	function dataDistance($ref, $data, $ignore = false) {
		$geno = array();
		$dist = 0;
		foreach ($ref as $key => $value) {
			if( !in_array($value, ["", -1]) ) {
				if(array_key_exists($key, $data)) {
					if ( $value != $data[$key] ) {
						if ( in_array($value, ["", -1]) )
							{ $dist += $ignore ? 0 : 1 ; }
						else
							{ $dist += 1; }
					}
				} else {
					$dist += $ignore ? 0 : 1 ;
				}
			}
		}
		return $dist;
	}

	// = LOOK FOR GN * =====
	function lookForGN($genonums, $filter, $strain) {
		$geno = array();
		foreach($filter as &$head)
			{ $geno[$head] = $strain['data'][$head]; }
		foreach ($genonums as $genonum) {
			$samplegeno = $genonum['data'];
			if ( $this->dataDistance($geno, $samplegeno) == 0 ) {
				return $genonum['value'];
			}
			// $diff = array_diff_assoc($samplegeno, $geno);
			// if ( empty($diff) )
				// { return $genonum['value']; }
		}
		return -1;
	}

	// = ADD STRAINS * =====
	function addStrains ($base_id, $strains, $headers, $metaheads, $mlvaheads) {
		foreach($strains as &$strain) {
			$strain_name = $strain[array_search($this->input->post('name'), $headers)];
			if ( substr($strain_name, 0, 1) != "[") { // ~~~
				$metadata = array (); $heads = $metaheads;
				foreach($heads as &$head)
					{ $metadata[$head] = utf8_encode(strval($strain[array_search($head, $headers)])); }
				$mlvadata = array (); $heads = $mlvaheads;
				foreach($heads as &$head)
					{ $mlvadata[$head] = intval($strain[array_search($head, $headers)]); }
				$data = array (
					'name' => $strain_name,
					'database_id' => $base_id,
					'metadata' => json_encode($metadata),
					'data' => json_encode($mlvadata)
				);
				$this->strain->add($data);
			}
		}
	}

	// = UPDATE STRAINS * =====
	function updateStrains ($base_id, $strains, $headers, $metaheads, $mlvaheads) {
		foreach($strains as &$strain_data) {
			list($base_strain, $strain) = $strain_data;
			$new_strain = $this->jsonExec($base_strain);
			$metadata = array (); $heads = $metaheads;
			foreach($heads as &$head)
				{ $new_strain['metadata'][$head] = utf8_encode(strval($strain[array_search($head, $headers)])); }
			$mlvadata = array (); $heads = $mlvaheads;
			foreach($heads as &$head)
				{ $new_strain['data'][$head] = intval($strain[array_search($head, $headers)]); }
			$this->strain->update($new_strain['id'], array(
				'metadata' => json_encode($new_strain['metadata']),
				'data' => json_encode($new_strain['data'])
			));
		}
	}

	// ===========================================================================
	//  - CSV -
	// ===========================================================================

	// = VALID CSV * =====
	function validCSV($file) {
		$mimes = array('application/vnd.ms-excel', 'text/plain', 'text/csv', 'text/tsv');
		if ( $file['name'] != "" && in_array($file['type'], $mimes) ) {
			if ( ($handle = fopen($file['tmp_name'], "r")) !== FALSE ) {
				return [true, $handle];
			} else {
				return [false, "That file is not valid."];
			}
		} else {
			return [false, "You must choose a CSV file to upload."];
		}
	}

	// = READ CSV * =====
	function readCSV($handle, $mode) {
		$delimiter = ($mode == 'fr') ? ";" : ",";
		$headers =  fgetcsv($handle, 0, $delimiter=$delimiter, $enclosure='"');
		$rows = array ();
		while (($data = fgetcsv($handle, 0, $delimiter=$delimiter, $enclosure='"')) !== FALSE)
		{
			array_push($rows, $data);
		}
		fclose($handle);
		return array ($headers, $rows);
	}

	// ===========================================================================

	/**
	 * Create the json oject for displaying the strains on a map
	 */
	private function createGeoJson($strains)
	{
		$geoJson = [];
		$i = 0;
		foreach ($strains as $strain)
		{
			if (!empty($strain['metadata']['lon']) && !empty($strain['metadata']['lat'])) {
				$geoJson[$i]['name'] = $strain['name'];
				$geoJson[$i]['lat'] = $strain['metadata']['lat'];
				$geoJson[$i]['lng'] = $strain['metadata']['lon'];
				$i++;
			}
		}
		return json_encode($geoJson);
	}
	/**
	 * Create a new group with the upload of a db and add the uploader to this group
	 */
	private function createGroupWithDatabase($groupName, $databaseName='')
	{
		$groupName = !empty($groupName) && alpha_dash_spaces($groupName) ? removeAllSpaces($groupName) : $databaseName.'_Group';
		$inputs = ['name' => $groupName, 'permissions' => '{"database.view":1}'];
		$group_id = $this->user->createGroup($inputs);
		$this->user->addToGroup($user_id = $this->session->user['id'], $group_id);
		//Reload the user's groups
		$_SESSION['groups'] = $this->user->getUserGroups($user_id);
		setFlash('info', lang('auth_group_created'));
		return $group_id;
	}

	/**
	 * Get the latitude and longitude from simple location (ex: Paris)
	 */
	private function getGeolocalisationFromLocation($strains, $locationKey)
	{
		$this->load->helper('curl');
		$url = 'http://nominatim.openstreetmap.org/search.php?format=json&limit=1&q=';
		foreach ($strains as &$strain)
		{
			if ((empty($strain['metadata']['lon']) && empty($strain['metadata']['lat'])) && !empty($strain['metadata'][$locationKey]))
			{
				list($lat, $lon) = ['', ''];
				if($response = json_decode(curl_get($url.urlencode($strain['metadata'][$locationKey]))))
				{
					list($lat, $lon) = [$response[0]->lat, $response[0]->lon];
				}
				$strain['metadata']['lat'] = $lat;
				$strain['metadata']['lon'] = $lon;
				$this->strain->update($strain['id'], ['metadata' => json_encode($strain['metadata'])]);
			}
		}
	}
}
