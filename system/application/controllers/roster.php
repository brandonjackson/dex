<?php

class Roster extends Controller {

var $data;

	function Roster()
	{
		parent::Controller();
		$this->load->library('member_management');
		$this->data['js'] = array();
		$this->data['css'] = array();
		$this->data['js'][] = "jquery-quicksearch.js";
		$this->data['js'][] = "fancybox.js";
		$this->data['css'][] =  "smoothness/jquery-ui.css";
		$this->data['css'][] =  "fancybox.css";
	}
	function index()
	{
		if($this->auth->validate('editor'))
		{
			$this->main();
		}
		else
		{
			$this->phonebook();
		}
	}
	
	function add( $type = NULL )
	{
		$this->auth->restrict('editor');
		
		// Select application
		if(empty($type))
		{
			$this->data['title'] = 'Select Application';
			$this->load->view('header', $this->data);
			$this->load->view('roster/select_app_internal', $this->data);
		}
		// Add New Member form
		else
		{
			// Manage Application Type
			$this->data['undergrad'] = FALSE;
			$this->data['associate'] = FALSE;
			$this->data['affiliate'] = FALSE;		
			if($type=="full")
				$this->data['undergrad'] = TRUE;
			elseif($type=="affiliate")
				$this->data['affiliate'] = TRUE;
			elseif($type=="associate")
				$this->data['associate'] = TRUE;
			
			$this->data['title'] = 'Add A New Member | WYBC';
			$this->data['heading'] = 'Add A New Member';
			$this->data['js'][] = 'songtracker-ui.php';
			$this->data['js'][] = 'jquery-ui-accordion.js';
			$this->data['statii'] = $this->member_management->lister('status'); //load statuses
			$this->data['teams'] = $this->member_management->lister('teams'); //load list of squads
			$this->data['edit'] = TRUE; // gives us editing priveleges
			$this->data['add'] = TRUE; // sets to add new member mode, not edit mode
			$this->load->view('header', $this->data);
			$this->load->view('roster/edit', $this->data);
			$this->load->view('footer', $this->data);
		}
	}
	function save()
	{
		/*
		This function handles all application saving and editing functionality. 
		What this function does depends on the value of the $_POST['app_type'] variable.
		There are four cases.
			1	update
			2	apply
			3	edit
			4	new
		*/
	
		// DJ updates their own data
		if ( $_POST['app_type']=='update' )
		{
			// Save the update
			$member_id = $this->member_management->save('update');
			
			// Send notifications
			$this->notify->save('member_self_update', $member_id);
			
			// Set flashdata
			$this->session->set_flashdata('success', 'Thanks! Your data was successfully updated.');
			
			// Redirect member back to settings page
			redirect('settings/data');
		}
		
		// New Application
		elseif ( $_POST['app_type']=='apply' )
		{
			// Save the application
			$member_id = $this->member_management->save('apply');
			
			// Send notifications
			$this->notify->save("new_application", $member_id);
			
			// Redirect applicant to success page
			redirect('roster/app_submitted');
		}
		
		// Admin edits existing Member's Data
		elseif($_POST['app_type']=="edit")
		{
			// Restrict access
			$this->auth->restrict('editor');
			
			// Save the changes
			$member_id = $this->member_management->save('edit');
			
			// Send notifications
			$this->notify->save("member_data_edited", $member_id);
			
			// Set success message
			$message = $_POST['first_name']." ".$_POST['last_name']." edited successfully.";
			$this->session->set_flashdata('success', $message);
			
			// Redirect
			redirect('/roster/main');
		}
		
		// Admin adds new Member
		elseif($_POST['app_type']=="new")
		{
			// Restrict access
			$this->auth->restrict('editor');
			
			// Save the changes
			$member_id = $this->member_management->save('new');
			
			// Send notifications
			$this->notify->save("new_member", $member_id);
			
			// Set flashdata
			$message = "New member ".$_POST['first_name']." ".$_POST['last_name']." created successfully";
			$this->session->set_flashdata('success', $message);
			
			// Redirect
			redirect('/roster/main');
		}
		elseif($_POST['app_type']=="approved")
		{

		}
	}
	function update()
	{
		$this->auth->restrict('editor');
		$this->data['title'] = 'Member Updates | WYBC';
		$this->load->view('header', $this->data); 
		$query = $this->db->query("SELECT * FROM member_updates ORDER BY update_time DESC");
		echo "<h1>Member Updates</h1>";
		echo "<table>";
		foreach ($query->result() as $row )
		{
			echo "<tr><td>";
			echo "<img src='".base_url()."/assets/images/icon_";
			if($row->update_type=="0") echo "check.png";
			else echo "plus.png";
			echo "' style='height: 20px;' /></td>";
			echo "<td>".$row->update_desc."</td><td style='color: #ccc;'>".date("m/d/Y g:iA",strtotime($row->update_time))."</td></tr>";
		}
		echo "</ul>";
		
	}
	function main()
	{
		$this->auth->restrict('editor');
		$this->data['title'] = 'View Members | WYBC';
		$this->data['heading'] = 'WYBC Roster';
		$this->data['heading_img'] = 'memberroster.png';
		$this->data['js'][] = "jquery-datatables.php";
		$this->data['css'][] = "datatables.css";
		$this->data['edit'] = TRUE;
		$this->data['roster'] = $this->member_management->roster("");
		$this->data['filters'] = $this->member_management->lister('filters');
		$this->data['status'] = $this->member_management->lister('status');
		$this->load->view('header', $this->data);
		$this->load->view('roster/roster', $this->data);
		$this->load->view('footer', $this->data);
	}
	function phonebook()
	{
		$this->auth->restrict('dj');
		$this->data['js'][] = "jquery-datatables.php";
		$this->data['css'][] = "datatables.css";
		$this->data['title'] = 'Roster';
		$this->load->view('header', $this->data);
		$this->load->view('roster/phonebook', $this->data);
		$this->load->view('footer', $this->data);
	}

	function edit( $id = NULL ) 
	{
		$this->auth->restrict('editor');
		
		// If no member selected, display error
		if ( empty ( $id ) )
		{
			$this->session->set_flashdata('error', 'No user selected to edit.');
			redirect('roster/main');
		}
		// Edit member form
		else
		{
			$this->data['title'] = 'Edit A Member | WYBC';
			$this->data['heading'] = 'Edit A Member';
			$this->data['heading_img'] = 'editanexistingmember.png';
			$this->data['wrapper'] = 'narrow';
			$this->data['teams'] = $this->member_management->lister('teams');
			$this->data['interests'] = $this->member_management->lister('interests');
			$this->data['statii'] = $this->member_management->lister('status');
			$this->data['revisions'] = $this->member_management->lister('revisions', $id);
			$this->data['attendence'] = $this->member_management->fetch_attendence($id);
			$this->data['edit'] = TRUE;
			$this->data['add'] = FALSE;
			
			$this->data['js'][] = 'songtracker-ui.php';
			$this->data['js'][] = 'jquery-ui-accordion.js';
			if ( $member = $this->uri->segment(3) )
			{
				$this->data['members'] = $this->member_management->member_data($member);
			}
			
			
			// Application type
			$this->data['undergrad'] = FALSE;
			$this->data['associate'] = FALSE;
			$this->data['affiliate'] = FALSE;
			if ( $this->data['members']['undergrad'] == 1 ) $this->data['undergrad'] = TRUE;
			elseif ( $this->data['members']['undergrad'] == 2 ||  $this->data['members']['undergrad'] == 3 ) $this->data['affiliate'] = TRUE;
			elseif ( $this->data['members']['undergrad'] == 0 ) $this->data['associate'] = TRUE;
	
			
			$this->load->view('header', $this->data);
			$this->load->view('roster/edit', $this->data);
			$this->load->view('footer', $this->data);
		}
		
	}
	function view()
	{
		$this->auth->restrict('editor');
		$this->data['title'] = 'View a Member | WYBC';
		$this->data['heading'] = 'View A Member';
		$this->data['wrapper'] = 'narrow';
		$this->data['member_data'] = $this->member_management->member_data($this->uri->segment(3));
		$this->load->view('header', $this->data);
		$this->load->view('roster/view', $this->data);
		$this->load->view('footer', $this->data);
	}

	function export()
	{
		$this->member_management->export($this->uri->segment(3),$this->uri->segment(4));
	}
	function printable()
	{
		$this->data['exportable'] = $this->member_management->printable();
		$this->load->view('roster/export',$this->data);
	}
	
	function apply( $app = NULL, $type = NULL )
	{
		if(empty($app))
		{
			$this->data['title'] = "The WYBC Application";
			$this->load->view('roster/application_header', $this->data);
			$this->load->view('roster/select_app_external', $this->data);
			$this->load->view('footer', $this->data);

		}
		elseif($app=="info")
		{
			$this->data['type'] = $type;
			$this->data['title'] = "Instructions | The WYBC Application";
			
			//Instructions & assorted instructional items
			if($type=="associate"||$type=="affiliate")
				$this->data['instructions'] = $this->util->page('app_instructions_associate');
			else
				$this->data['instructions'] = $this->util->page('app_instructions_full');
			$this->data['restrictions'] = $this->util->page('app_restrictions');
			$this->data['training'] = $this->util->page('app_training_schedule');
			
			$this->load->view('roster/application_header', $this->data);
			$this->load->view('roster/app_info', $this->data);	
			$this->load->view('footer', $this->data);
		}
		else
		{
			$this->data['title'] = 'The WYBC Application';
			$this->data['js'][] = "jquery-validate.php";
			$this->data['help'] = TRUE;
			$this->data['teams'] = $this->member_management->lister('teams');
			$this->data['training'] = $this->member_management->get_training();
			$this->data['intro_blurb'] = $this->util->page('app_begin_instructions');
			
			// Application type
			$this->data['undergrad'] = FALSE;
			$this->data['associate'] = FALSE;
			$this->data['affiliate'] = FALSE;
			if ( $app == "full" ) $this->data['undergrad'] = TRUE;
			elseif ( $app == "affiliate" ) $this->data['affiliate'] = TRUE;
			elseif ( $app == "associate" ) $this->data['associate'] = TRUE;
			else $this->data['undergrad'] = TRUE;
			
			
			$this->load->helper('form');
			$this->load->view('roster/application_header', $this->data);
			$this->load->view('roster/add', $this->data);
			$this->load->view('footer', $this->data);
		}
	}
	function app_submitted()
	{
		$this->load->view('roster/application_header', $this->data);
		$this->load->view('roster/app_submitted', $this->data);
		$this->load->view('footer', $this->data);
	}
	
	function revisions ( $id = NULL )
	{
		// List pending revisions
		if ( empty($id) )
		{
			$this->data['title'] = "Pending Revisions";
			$q = $this->db->from('member_revisions AS r ')->where('approved', 0)->where('rejected', 0)->join('member_data AS d ','d.member_id=r.member_id')->order_by('id', 'desc')->get();
			$this->data['revisions'] = $q->result_array();
			$this->load->view('header', $this->data);
			$this->load->view('roster/revisions_list', $this->data);
			$this->load->view('footer', $this->data);
		}
		elseif($id=="approved")
		{
			$this->auth->restrict('editor');
			$revision_id = $_POST['revision_id'];
			$member_id = $_POST['member_id'];

			//Save changes
			$member_id = $this->member_management->save('approved', $revision_id);
			
			// Send notifications
			$this->notify->save("revision_approved", $member_id, array('revision_id'=>$revision_id, 'type'=>'data'));
			
			// Set the flashdata
			$this->session->set_flashdata('success', "Revision approved.");
			
			// Redirect
			redirect('roster/revisions');
		}
		elseif($id=="approved_profile")
		{
			$this->auth->restrict('editor');
			$revision_id = $_POST['revision_id'];
			$member_id = $_POST['member_id'];

			//Save changes
			$member_id = $this->member_management->save('approved_profile', $revision_id);
			
			// Send notifications
			$this->notify->save("revision_approved", $member_id, array('revision_id'=>$revision_id, 'type'=>'profile'));
			
			// Set the flashdata
			$this->session->set_flashdata('success', "Revision approved.");
			
			// Redirect
			redirect('roster/revisions');
		}
		elseif($id=="reject")
		{
			$this->auth->restrict('editor');
			$revision_id = $_POST['revision_id'];
			$member_id = $_POST['member_id'];

			// Save changes
			$this->db->where('id', $revision_id)->update('member_revisions', array("rejected"=>1));
			
			// Send notifications
			$this->notify->save("revision_rejected", $member_id, array('revision_id'=>$revision_id));
			
			// Set the flashdata
			$this->session->set_flashdata('success', "Revision rejected.");
			
			// Redirect
			redirect('roster/revisions', 'refresh');		
		
		}
		else
		{
			$q = $this->db->from('member_revisions AS r ')->where('r.id', $id)->join('member_data AS d ','d.member_id=r.member_id')->get();
			$r = $q->row_array();
			$this->data['revision'] = $r;
			$this->data['type'] = $r['type'];
			$this->data['changes'] = $this->member_management->revision_changes( $id );
			$this->data['title'] = "Approve Member Edits";
			$this->load->view('header', $this->data);
			$this->load->view('roster/revisions_review', $this->data);
			$this->load->view('footer', $this->data);
		}
	}

	function drupal()
	{
		if($this->uri->segment(3)=="update")
		{
			
			$this->load->library('drupal');
			$member_query = $this->db->where('status_id','6')->where('drupal_id', 0)->get('member_data');
			$members = $member_query->result();
			foreach($members as $obj)
				$this->_add_drupal_account ( $obj);
			redirect('roster/drupal');
		}
		else
		{
			$this->auth->restrict('dj');
			$this->data['title'] = 'Roster';
			$query = $this->db->where('status_id','6')->order_by("last_name", "ASC")->get('member_data');
			$this->data['members'] = $query->result();
			$this->data['member_count'] = $query->num_rows;
			$query2 = $this->db->where('status_id','6')->where('drupal_id', 0)->get('member_data');
			$this->data['accountless_count'] = $query2->num_rows;
			$this->load->view('header', $this->data);
			$this->load->view('roster/drupal', $this->data);
			$this->load->view('footer', $this->data);
		}
	}
	function _add_drupal_account( $user ) //passed user DB object
	{
		if(!empty($user->email_yale)) $email = $user->email_yale;
		else $email = $user->email_personal;
		$ser_data = serialize( array( "contact"=>"1", "form_build_id"=>"form-".uniqid()));
		$pw = $user->first_name."1340";
		$users_data = array ( 	"name"=>$user->first_name." ".$user->last_name,
							"pass" => md5($pw),
							"mail" => $email,
							"created" => time(),
							"status" => "1",
							"data" => $ser_data);
		$users_sql = $this->db->insert_string('users', $users_data);
		$uid = $this->drupal->api_call($users_sql);
		$node_data = array ( 	"type"=>"profile",
							"title"=>$user->first_name." ".$user->last_name,
							"uid"=>$uid,
							"created"=>time(),
							"changed"=>time());
		$node_sql = $this->db->insert_string('node', $node_data);
		$nid = $this->drupal->api_call($node_sql);
		
		$profile_data = array ( 	"nid"=>$nid,
							"vid" => $nid,
							"field_hometown_value"=>$user->home_city.", ".$user->home_state);
		$profile_sql = $this->db->insert_string("content_type_profile", $profile_data);
		
		$this->drupal->api_call($profile_sql);
		$roles_sql = $this->db->insert_string("users_roles", array("uid"=>$uid, "rid"=>"3"));
		$this->drupal->api_call($roles_sql);
		$this->db->where('member_id',$user->member_id)->
				update('member_data', array('drupal_id'=>$uid));
		
	}
	
	function delete()
	{
		$this->member_management->delete_member($this->uri->segment(3));
		$this->session->set_flashdata('success','Member successfully deleted.');
		redirect('roster/main');
	}

	function ajax( $method )
	{
		switch ( $method )
		{
			case "fetch_roster_rows":
				echo $this->member_management->lister('roster');
				break;
			case "delete_event":
				$this->member_management->delete_event( $_POST['event_id']);
				break;
			case "attendence":
				$this->member_management->submit_attendence();
				break;
			case "request_update":
				$str = $this->uri->segment(4);
				$bits = explode("-",$str);
				array_pop($bits);
				$this->notify->save('request_update', NULL, array('count'=>count($bits), 'member_list'=>serialize($bits)));
				$this->notify->mass("request_data_update", $bits);
				$this->session->set_flashdata('success', 'Data update request sent to '.count($bits).' members.');
				redirect('roster/main');
				break;
		}
	}
	
	function events( $task = 'list', $id = NULL )
	{
		if($task=="new")
		{
			$this->_events_new();
		}
		elseif ($task == "view" )
		{
			if($id!=NULL) $this->_events_view( $id );
			else $this->_events_list();
		}
		else
		{
			$this->_events_list();
		}
	}
	
	function _events_new()
	{
		if(!empty($_POST))
		{
			$start_date  = $_POST['start_hour'].":".$_POST['start_minutes'].$_POST['start_ampm']." ".$_POST['start_date'];
			$end_date  = $_POST['end_hour'].":".$_POST['end_minutes'].$_POST['end_ampm']." ".$_POST['start_date'];
			$data = array(	"title"=>$_POST['title'],
						"description"=>$_POST['description'],
						"type"=>$_POST['type'],
						"start_date"=>date("Y-m-d H:i:s",strtotime($start_date)),
						"end_date"=>date("Y-m-d H:i:s",strtotime($end_date)),
						"created"=>date("Y-m-d H:i:s"));
			$this->db->insert("event_data", $data);
			$event_id = $this->db->insert_id();
			if(!empty($_POST['all_active']))
				$this->member_management->invite_group("active", $event_id);
			redirect('roster/events');
		}
		else
		{
			$this->data['title'] = "New Event";
			$this->load->view('roster/events/new', $this->data);
			$this->load->view('footer', $this->data);
		}
	}
	function _events_list()
	{
		$this->data['title'] = "Events";
		$this->data['js'][] = "jquery-datatables.php";
		$this->data['js'][] = 'colorbox.js';
		$this->data['js'][] = 'songtracker-ui.php';
		$this->data['css'][] = 'colorbox.css';
		$this->data['css'][] = "datatables.css";
		$q = $this->db->order_by('start_date', 'desc')->get('event_data');
		$this->data['events'] = $q->result();
		$this->load->view('header', $this->data);
		$this->load->view('roster/events/list', $this->data);
		$this->load->view('footer', $this->data);
	}
	
	function _events_view( $id = NULL)
	{
		$this->data['title'] = "View Event";
		$this->data['js'][] = "jquery-datatables.php";
		$this->data['js'][] = 'jquery-form.js';
		$this->data['css'][] = "datatables.css";
		$q = $this->db->where('event_id', $id)->get('event_data');
		$this->data['info'] = $q->row();
		$q2 = $this->db->from('event_attendence AS a ')->where('a.event_id', $id)->join('member_data AS m ', 'a.member_id = m.member_id' )->get();
		$this->data['attendees'] = $q2->result();
		$this->load->view('header', $this->data);
		$this->load->view('roster/events/view', $this->data);
		$this->load->view('footer', $this->data);

	}
	
	function notifications( $type, $member_id, $otherdata = NULL )
	{
		$this->load->library('notify');
		$update = TRUE;
		if(!empty($member_id)) $member_data = $this->member_management->member_data( $member_id );
		switch($type)
		{
			case "member_data_edited":
				$update_data['title'] = "Member Data Updated";
				$update_data['message'] = "An admin updated ".$member_data['first_name']." ".$member_data['last_name']."'s data.";
				
				break;
			
			case "member_self_update":
				$update_data['title'] = "Member Self-Update";
				$update_data['message'] = $member_data['first_name']." ".$member_data['last_name']." updated their own data.";
				
				// Send email
				$subject = $member_data['first_name']." ".$member_data['last_name']." has updated their data";
				$message = $member_data['first_name']." ".$member_data['last_name']." has edited their data. It is awaiting your review. You may view it at " . $this->util->setting('url')."/roster/edit/" . $member_id;
		
				$email[]= array(	"to"=>$this->util->setting('email_station_manager'),
								"subject"=>$subject,
								"message"=>$message);
				break;
			
			case "new_application":
				// Create update
				$update_data['title'] = "New Application";
				$update_data['message'] = $member_data['first_name']." ".$member_data['last_name']." submitted a new application.";
				
				// Send emails
				$subject = "New Application";
				$message_one = $member_data['first_name']." ".$member_data['last_name']." has submitted a new application. It is awaiting your review. You may view it at " . $this->util->setting('url')."/roster/edit/" . $member_id;
				
				// Load messages for the members based on app type
				if ( $member_data['undergrad'] == 0 || $member_data['undergrad'] == 2 || $member_data['undergrad'] == 3) 
					$message_two = $this->util->page('email_associate_app_received');
				else
					$message_two = $this->util->page('email_full_app_received');
				
				$email[]= array(	"to"=>$this->util->setting('email_station_manager'),
								"subject"=>$subject,
								"message"=>$message_one );
				$email[]= array(	"to"=>$this->util->setting('email_training_director'),
								"subject"=>$subject,
								"message"=>$message_one );
				$email[] = array(	"to"=> $member_data['email'],
								"subject" => "Application Received!",
								"message" => $message_two);
				break;
			
			case "new_member":
				$update_data['title'] = "New Member";
				$update_data['message'] = "An admin added ".$member_data['first_name']." ".$member_data['last_name']." as a member.";
				break;
				
			case "request_update":
				$update_data['title'] = "Updates Requested";
				$update_data['message'] = "An admin requested an update from ".count($otherdata)." members.";
				
				$message = $this->util->page('email_request_update');
				
				foreach($otherdata as $key=>$val)
				{
					$data = $this->member_management->member_data($val);
					$email[] = array(	"to"=> $data['email'],
									"subject" => "Please Update Your Member Data",
									"message" => $message);
				}
				$this->session->set_flashdata('success', 'Requested Data Updates from '.count($otherdata).' members.');
				$redirect = "roster/main";
				break;
			case "revision_approved":
				$update = FALSE;
				break;
			case "revision_rejected":
				$update = FALSE;
				break;
		}
		
		if($update)
		{
			$this->notify->update('roster', $type, $update_data['title'], $update_data['message']);
		}
		if(!empty($email))
		{
			foreach($email as $key=>$val)
			{
				$this->notify->robot_email($val['to'], $val['subject'], $val['message']);
			}
		}
		if(!empty($redirect))
		{
			redirect($redirect);
		}
	}	
	
	function squads( $method = NULL, $id = NULL )
	{
		if($method == "edit")
		{
			if(!empty($_POST))
			{
				$data = array(		"team_title"=>$_POST['team_title'],
								"team_description"=>$_POST['team_description'],
								"team_leader"=>$_POST['team_leader'] );
								
				$this->db->where('team_id', $id)->update('team_data', $data);
				$this->notify->save('squad_edited', NULL, array('team_id'=>$id));
				$this->session->set_flashdata('success', $_POST['team_title']." has been updated.");
				redirect('roster/squads');
			}
			else
			{
				if(empty($id)) return false;
				$team_query = $this->db->from('team_data AS t ')
										->join('member_data AS m ', 'm.member_id = t.team_leader', 'left')
										->where('team_id', $id)
										->where('team_status',1)
										->order_by('sort_order', 'asc')
										->get();
				$this->data['squad'] = $team_query->row_array();
				$this->data['edit'] = TRUE;
				$member_query = $this->db->where('status_id', 6)->order_by('last_name', 'asc')->get('member_data');
				$this->data['members'] = $member_query->result_array();
				$this->load->view('roster/squads/edit', $this->data);
			}

		}
		elseif($method == "new")
		{
			if(!empty($_POST))
			{
				$sort_query = $this->db->limit(1)->order_by('sort_order','desc')->get('team_data');
				$sort = $sort_query->row();
				$highest = (int) $sort->sort_order;
				$new_sort = $highest + 1;
				$data = array(		"team_title"=>$_POST['team_title'],
								"team_description"=>$_POST['team_description'],
								"team_leader"=>$_POST['team_leader'],
								"sort_order"=>$new_sort,
								"team_status"=>1);
				$this->db->insert('team_data', $data);
				$team_id = $this->db->insert_id();
				$this->notify->save('new_squad', NULL, array('team_id'=>$team_id));
				$this->session->set_flashdata('success', $_POST['team_title']." has been created.");
				redirect('roster/squads');
			}
			else
			{
				$this->data['edit'] = FALSE;
				$member_query = $this->db->where('status_id', 6)->order_by('last_name', 'asc')->get('member_data');
				$this->data['members'] = $member_query->result_array();
				$this->load->view('roster/squads/edit', $this->data);
			}

		}
		elseif( $method == "delete")
		{
			if(empty($id)) return false;
			$this->auth->restrict('editor');
			$this->db->where('team_id', $id)->update('team_data', array('team_status'=>0));
			$this->notify->save('squad_deleted', NULL, array('team_id'=>$id));
			$this->session->set_flashdata('success', "Squad deleted successfully.");
			
			redirect('roster/squads');
		}
		else
		{
			$this->data['title'] = "Squads";
			$team_query = $this->db->from('team_data AS t ')->join('member_data AS m ', 'm.member_id = t.team_leader', 'left')->where('team_status',1)->order_by('sort_order', 'asc')->get();
			$this->data['squads'] = $team_query->result_array();
			$this->data['js'][] = "colorbox.js";
			$this->data['css'][] = "colorbox.css";
			$this->data['css'][] = "datatables.css";
			$this->data['js'][] = 'jquery-datatables.php';
			$this->load->view('header', $this->data);
			$this->load->view('roster/squads/admin', $this->data);
			$this->load->view('footer', $this->data);
		}
	}	
}
