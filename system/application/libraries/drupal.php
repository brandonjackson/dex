<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Drupal
{
	// Basic properties
	protected $ci;
	var $u;
	var $errors;
	var $db;
	
	// Drupal properties
	/*
	var $config = array (	'hostname' => 'localhost',
						'database' => 'drupal',
						'username' => 'root',
						'password' => '',
						'dbdriver' => 'mysql',
						'dbprefix' => ''
					);
	*/
	
	var $config = array (	'hostname' => '70.32.94.95',
						'database' => 'wybcx6',
						'username' => 'meta',
						'password' => 'nargo^%',
						'dbdriver' => 'mysql',
						'dbprefix' => ''
					);
						

	public function __construct()
	{
		$this->ci =& get_instance();
	//	$this->db = $this->ci->load->database($this->config, TRUE);

	}
	public function db()
	{
		return $this->db;
	}
	public function api_call ( $sql, $array = TRUE )
	{
		$data = http_build_query( array ( "password"=>"nargo^%",
					"sql" => $sql) );
		$json = utf8_decode($this->ci->util->do_post_request("http://dev.wybcx.com/infinity.php", $data,NULL, TRUE));
		$start = substr($json, 0,1);
		if($start=="{"||$start=="[") return json_decode($json, $array);
		else return $json;
	}
	public function table_call ( $sql )
	{
		$data = http_build_query( array ( "password"=>"nargo^%",
					"sql" => $sql,
					"table" => "TRUE") );
		$table = $this->ci->util->do_post_request("http://dev.wybcx.com/infinity.php", $data,NULL, TRUE);
		echo $table;
	}
	
	/**
	*	List a user's shows
	*	@param int $user_id
	*/
	public function list_shows( $user_id= NULL)	
	{
		if(empty($user_id)) $user_id = $this->ci->session->userdata('user_id');
		$sql =     "SELECT * FROM node AS n 
				JOIN content_field_additional_djs AS s 
				ON n.nid = s.nid
				WHERE s.field_additional_djs_uid = ".$this->ci->db->escape_str($user_id);
				
		return $this->api_call($sql);
	}
	
	/**
	*	List episodes of a show
	*	@param int $show_id
	*/
	public function list_episodes ( $show_id )
	{
		$sql = 	"SELECT * FROM node AS n
				JOIN content_type_episode AS e 
				ON e.nid = n.nid
				JOIN content_field_episode_time AS t 
				ON t.nid = n.nid
				WHERE e.field_show_nid = ".$this->ci->db->escape_str($show_id)."
				ORDER BY t.field_episode_time_value ASC";
		return $this->api_call($sql);
	}
	
	public function episode_info ( $episode_id )
	{
		/*
		$q = $this->db->from("node AS n ")
					->join("content_type_episode AS e ", "e.nid = n.nid")
					->join("content_field_episode_time AS t ", "t.nid = n.nid")
					->where("n.nid",$episode_id)
					->get();
		$r = $q->row_array();
		*/
		
		$sql = 	"SELECT * FROM node AS n
				JOIN content_type_episode AS e
				ON e.nid = n.nid
				JOIN content_field_episode_time AS t
				ON t.nid = n.nid
				
				JOIN content_field_additional_djs AS s 
				ON s.nid = n.nid
				
				WHERE n.nid = ".$this->ci->db->escape_str($episode_id);
		$q = $this->api_call($sql, TRUE);
		$r = $q[0];
		
		
		$r['start'] = strtotime($r['field_episode_time_value'])-(60*60*5);
		$r['stop'] = strtotime($r['field_episode_time_value2'])-(60*60*5);
		return $r;
	}
	
	public function get_roles ( $user_id )
	{
		$sql = 	"SELECT * FROM users_roles
				WHERE uid = ".$user_id;
		return $this->api_call($sql, FALSE);
	}
	public function verify_user ( $email, $password )
	{
		$sql = 	"SELECT * FROM users
				WHERE mail = '".$this->ci->db->escape_str($email)."' AND pass = '".$this->ci->db->escape_str($password)."'";
		$r = $this->api_call($sql, FALSE);
		if(count($r)!=1) return false;
		return $r; 
	}
	
	public function add_user ( $roster_id, $uid )
	{
	/*
		$one_data = array ( "uid" => $uid,
						"name" => 
		$one = 
	*/
	}
	public function load_profile( $user_id )
	{
		$sql = "SELECT n.nid AS nid, r.title AS name, r.body AS bio, p.field_status_value AS status, p.field_hometown_value AS hometown FROM node AS n JOIN content_type_profile AS p ON n.nid = p.nid JOIN node_revisions AS r ON r.nid = n.nid WHERE n.uid = ".$user_id;
		$r = $this->api_call($sql, TRUE);
		if(count($r)!=1) return false;
		return $r[0];
	}
}
