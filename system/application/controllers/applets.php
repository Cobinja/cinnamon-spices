<?php
class Applets extends Controller{

	function Applets() {
		parent::Controller();
		$this->load->helper('url');
		$this->load->helper('form');
		$this->load->helper('date');	
		$this->load->library('email');			
	}
	
	function index() {
		$this->welcome();
	}
	
	function welcome() {							                
		$this->db->order_by('score DESC, name ASC');
		$data['popular'] = $this->db->get('applets');
		
		$this->db->order_by('last_edited DESC, name ASC');
		$this->db->limit(5);
		$data['latest'] = $this->db->get('applets');
        				
		$this->load->view('header', $data);			
		$this->load->view('applets', $data);
		$this->load->view('footer', $data);
	}	
	
	function view($id) {
		$id = intval($id);
		$this->db->select('applets.*, users.id as user_id, users.username, users.signature, users.biography');		
		$this->db->where('applets.id', $id);		
		$this->db->join('users', 'users.id = applets.user');
		$records = $this->db->get('applets');
		if ($records->num_rows() > 0) {
			$data = $records->row_array();
			
			$this->db->select('applets_comments.*, users.username');
			$this->db->where('applets_comments.applet', $id);
			$this->db->join('users', 'users.id = applets_comments.user');
			$this->db->order_by('timestamp');
			$data['comments'] = $this->db->get('applets_comments');
			
			$data['rating'] = 0;
			if ($this->dx_auth->is_logged_in()) {
				$this->db->where('user', $this->dx_auth->get_user_id());
				$this->db->where('applet', $id);
				$records = $this->db->get('applets_ratings');
				if ($records->num_rows > 0) {
					$data['rating'] = $records->row()->rating;
				}
			}
									
			$this->load->view('header');
			$this->load->view('applet', $data);
			$this->load->view('footer');
		}
		else {
			$data["error"] = "Not found";
			$data["details"] = "This applet does not exist.";
			$this->load->view("header");	
			$this->load->view('error', $data);
			$this->load->view("footer");
		}
	}
    
	function _json() {
		$this->db->select('applets.*, users.id as user_id, users.username, users.signature, users.biography');
		$this->db->join('users', 'users.id = applets.user');
		$spices = $this->db->get('applets');
		foreach ($spices->result() as $spice) {
            $json[$spice->uuid] = array(
                'spices-id' => $spice->id,
                'uuid' => $spice->uuid,
                'name' => $spice->name,
                'description' => $spice->description,
                'score' => $spice->score,
                'created' => $spice->created,
                'last_edited' => $spice->last_edited,
                'file' => $spice->file,
                'icon' => $spice->icon,
                'screenshot' => $spice->screenshot,
                'author_id' => $spice->user,
                'author_user' => $spice->username
            );
        }
		$fp = fopen('/var/www/cinnamon-spices.linuxmint.com/json/applets.json', 'w');
		fwrite($fp, json_encode($json));
		fclose($fp);
	}
	
	function rate($id, $rating) {
		$id = intval($id);
		$rating = intval($rating);
		if ($this->dx_auth->is_logged_in() && $rating >= 1 && $rating <= 5) {    			
			$this->db->where('user', $this->dx_auth->get_user_id());
			$this->db->where('applet', $id);
			$this->db->delete('applets_ratings');						
			$this->db->set('user', $this->dx_auth->get_user_id());
			$this->db->set('applet', $id);
			$this->db->set('rating', $rating);
			$this->db->insert('applets_ratings');			
			$this->db->query("UPDATE applets SET score = (SELECT SUM(rating-3) FROM applets_ratings WHERE applet = $id) WHERE id = $id");
			$this->_json();
		}
		redirect("/applets/view/$id", "location");
	}
	
	function edit($id, $error="") {
		$id = intval($id);
		if ($this->dx_auth->is_logged_in()) {			
			$this->db->where('id', $id);					
			$this->db->where('user', $this->dx_auth->get_user_id());
			$records = $this->db->get('applets');
			if ($records->num_rows() > 0) {	
				$data = $records->row_array();
				$data['error'] = $error;
				$this->load->view('header');
				$this->load->view('applet_edit', $data);
				$this->load->view('footer');
			}			
		}		
	}
	
	function edit_save($id) {
		$id = intval($id);
        if ($this->dx_auth->is_logged_in()) {    
			$this->db->where('id', $id);					
			$this->db->where('user', $this->dx_auth->get_user_id());
			$records = $this->db->get('applets');
			if ($records->num_rows() > 0) {	
				$data = $records->row_array();
			
				$this->db->where('id', $id);					
				$this->db->where('user', $this->dx_auth->get_user_id());
				$this->db->set('name', $_POST['name']);
				$this->db->set('uuid', $_POST['uuid']);
				$this->db->set('version', $_POST['version']);
				$this->db->set('website', $_POST['website']);
				$this->db->set('description', $_POST['description']);
				$this->db->set('last_edited', now());
				$this->db->update('applets');	
				
				$this->load->library('upload');
				if ($_FILES['file']['error'] != 4) {	
					#Upoad the new file										
					$filename = $this->genRandomString();
					$config['upload_path'] = FCPATH.'uploads/applets/';
					$config['file_name'] = $filename;
					$config['overwrite'] = TRUE;
					$config['allowed_types'] = 'zip';				
					$config['max_size']	= '10240';				
					$this->upload->initialize($config);								
					if (!$this->upload->do_upload('file')) {					
						$this->edit($id, $this->upload->display_errors());
						return;
					}
					else {										
						$upload_data = $this->upload->data();
						$filename = $upload_data['file_name'];
						chmod( FCPATH.'uploads/applets/'.$filename , 0644 );						
						#Delete the old file
						unlink(FCPATH.$data['file']);
						#Update entry in DB
						$this->db->where('id', $id);					
						$this->db->where('user', $this->dx_auth->get_user_id());
						$this->db->set('file', "/uploads/applets/".$filename);						
						$this->db->update('applets');
					}
				}
				
				if ($_FILES['screenshot']['error'] != 4){
					#Upoad the new file										
					$filename = $this->genRandomString();
					$config['upload_path'] = FCPATH.'uploads/applets/';
					$config['file_name'] = $filename;
					$config['overwrite'] = TRUE;
					$config['allowed_types'] = 'png';				
					$config['max_size']	= '1024';				
					$this->upload->initialize($config);						
					if (!$this->upload->do_upload('screenshot')) {					
						$this->edit($id, $this->upload->display_errors());
						return;
					}
					else {										
						$upload_data = $this->upload->data();
						$filename = $upload_data['file_name'];
						chmod( FCPATH.'uploads/applets/'.$filename , 0644 );						
						#Delete the old file
						unlink(FCPATH.$data['screenshot']);
						#Update entry in DB
						$this->db->where('id', $id);					
						$this->db->where('user', $this->dx_auth->get_user_id());						
						$this->db->set('screenshot', "/uploads/applets/".$filename);
						$this->db->update('applets');
					}
				}	
				
				if ($_FILES['icon']['error'] != 4){
					#Upoad the new file										
					$filename = $this->genRandomString();
					$config['upload_path'] = FCPATH.'uploads/applets/';
					$config['file_name'] = $filename;
					$config['overwrite'] = TRUE;
					$config['allowed_types'] = 'png';				
					$config['max_size']	= '100';				
					$this->upload->initialize($config);						
					if (!$this->upload->do_upload('icon')) {					
						$this->edit($id, $this->upload->display_errors());
						return;
					}
					else {										
						$upload_data = $this->upload->data();
						$filename = $upload_data['file_name'];
						chmod( FCPATH.'uploads/applets/'.$filename , 0644 );						
						#Delete the old file
						unlink(FCPATH.$data['icon']);
						#Update entry in DB
						$this->db->where('id', $id);					
						$this->db->where('user', $this->dx_auth->get_user_id());						
						$this->db->set('icon', "/uploads/applets/".$filename);
						$this->db->update('applets');
					}
				}
				$this->_json();					
			}	                                            					                                                
        }
        redirect("/applets/view/$id", "location");
    }
	
	function delete($id) {
		$id = intval($id);
		if ($this->dx_auth->is_logged_in()) {			
			$this->db->where('id', $id);					
			$this->db->where('user', $this->dx_auth->get_user_id());
			$records = $this->db->get('applets');
			if ($records->num_rows() > 0) {	
				$data = $records->row_array();
				
				#Delete the files
				unlink(FCPATH.$data['screenshot']);						
				unlink(FCPATH.$data['icon']);						
				unlink(FCPATH.$data['file']);						
				
				$this->db->where('applet', $id);
				$this->db->delete('applets_comments');
											
				$this->db->where('id', $id);
				$this->db->where('user', $this->dx_auth->get_user_id());
				$this->db->delete('applets');

				$this->_json();
			}		
		}
		redirect("/applets", "location");
	}
	
	function comment($id) {
		$id = intval($id);
		if ($this->dx_auth->is_logged_in()) {
			$this->db->set('user', $this->dx_auth->get_user_id());
			$this->db->set('applet', $id);
			$this->db->set('timestamp', now());
			$this->db->set('body', $_POST['body']);	
			$this->db->insert('applets_comments');
		}
		redirect("/applets/view/$id", "location");
	}
			
	function create_new($error="") {
		if ($this->dx_auth->is_logged_in()) {	
			$data["error"] = $error;		
			$this->load->view('header');			
			$this->load->view('applets_new', $data);
			$this->load->view('footer');
		}
	}
		    
    function create_new_save() {
        if ($this->dx_auth->is_logged_in()) {    
			$this->load->library('form_validation');
			$this->form_validation->set_rules('name', 'Name', 'required');
			$this->form_validation->set_rules('uuid', 'UUID', 'required');
			$this->form_validation->set_rules('description', 'Description', 'required');
			$this->form_validation->set_rules('version', 'Version', 'required');						
			$this->form_validation->set_rules('website', 'Website', 'required');
			                                                   
            if ($this->form_validation->run() == FALSE) {
				$this->create_new();
				return;
			}
			
			if ($_FILES['file']['error'] == 4) {
				$this->create_new("A file is required");
				return;
			}
			
			if ($_FILES['screenshot']['error'] == 4){
				$this->create_new("A screenshot is required");
				return;
			}
			
			if ($_FILES['icon']['error'] == 4){
				$this->create_new("An icon is required");
				return;
			}			
			
			$this->load->library('upload');
			
			// Upload the file
			$filename = $this->genRandomString();
			$config['upload_path'] = FCPATH.'uploads/applets/';
			$config['file_name'] = $filename;
			$config['overwrite'] = TRUE;
			$config['allowed_types'] = 'zip';				
			$config['max_size']	= '10240';				
			$this->upload->initialize($config);
										
			if (!$this->upload->do_upload('file')) {					
				$this->create_new($this->upload->display_errors());
				return;
			}
			else {										
				$upload_data = $this->upload->data();
				$file_file_name = $upload_data['file_name'];
				chmod( FCPATH.'uploads/applets/'.$file_file_name , 0644 );  					
				$filename = $this->genRandomString();
				$config['upload_path'] = FCPATH.'uploads/applets/';
				$config['file_name'] = $filename;
				$config['overwrite'] = TRUE;
				$config['allowed_types'] = 'png';				
				$config['max_size']	= '1024';				
				$this->upload->initialize($config);
				
				if (!$this->upload->do_upload('screenshot')) {			
					//Delete applet file
					if (file_exists(FCPATH.'uploads/applets/'.$file_file_name)) {			 
						unlink(FCPATH.'uploads/applets/'.$file_file_name);
					}
								       
					//Reload form
					$this->create_new($this->upload->display_errors());
					return;
				}
				else {													
					$upload_data = $this->upload->data();
					$screenshot_file_name = $upload_data['file_name'];
					chmod( FCPATH.'uploads/applets/'.$screenshot_file_name , 0644 );  	
					$filename = $this->genRandomString();
					$config['upload_path'] = FCPATH.'uploads/applets/';
					$config['file_name'] = $filename;
					$config['overwrite'] = TRUE;
					$config['allowed_types'] = 'png';				
					$config['max_size']	= '100';				
					$this->upload->initialize($config);
					
					if (!$this->upload->do_upload('icon')) {			
						//Delete applet files
						if (file_exists(FCPATH.'uploads/applets/'.$file_file_name)) {			 
							unlink(FCPATH.'uploads/applets/'.$file_file_name);
						}
						if (file_exists(FCPATH.'uploads/applets/'.$screenshot_file_name)) {			 
							unlink(FCPATH.'uploads/applets/'.$screenshot_file_name);
						}
										   
						//Reload form
						$this->create_new($this->upload->display_errors());
						return;
					}
					else {													
						$upload_data = $this->upload->data();
						$icon_file_name = $upload_data['file_name'];
						chmod( FCPATH.'uploads/applets/'.$icon_file_name , 0644 ); 
										
						$this->db->set("user", $this->dx_auth->get_user_id());
						$this->db->set("name", $_POST["name"]);
						$this->db->set("uuid", $_POST["uuid"]);
						$this->db->set("version", $_POST["version"]);
						$this->db->set("website", $_POST["website"]);
						$this->db->set("description", $_POST["description"]);
						$this->db->set("file", "/uploads/applets/".$file_file_name);
						$this->db->set("screenshot", "/uploads/applets/".$screenshot_file_name);
						$this->db->set("icon", "/uploads/applets/".$icon_file_name);
						$this->db->set("created", now());
						$this->db->set("last_edited", now());
						$this->db->insert("applets");				
						$id = $this->db->insert_id();			

						$this->_json();
											
						redirect("/applets/view/$id", "location");
					}
				}										
			}                                            					                                                  
        }
    }
    
    function genRandomString() {
        $length = 10;
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $string = '';
        for ($p = 0; $p < 4; $p++) {
            $string .= $characters[mt_rand(0, strlen($characters) -1)];
        }
        $string .= '-';
        for ($p = 0; $p < 4; $p++) {
            $string .= $characters[mt_rand(0, strlen($characters) -1)];
        }
        $string .= '-';
        for ($p = 0; $p < 4; $p++) {
            $string .= $characters[mt_rand(0, strlen($characters) -1)];
        }        
        return $string;
    }
		
}
?>
