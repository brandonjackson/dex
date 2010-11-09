<?php

class Api extends Controller {

var $data;

function __construct()
	{
		parent::__construct();
		parse_str($_SERVER['QUERY_STRING'],$_GET); // parse get variables
		$this->load->library('songtracker');
	}
	
	function index()
	{
		$this->data['title'] = "The API";
		$this->load->view('header');
		$this->load->view('api/index');
	}
	
	function episode($episode_id)
	{
		if ( empty($episode_id))
		{
			if( empty($_GET['episode_id']) )
			{
				echo "Missing 'episode_id' parameter. Please consult documentation.";
				return;
			}
			else
			$episode_id = $_GET['episode_id'];
		}
		$this->data['episode_id'] = $episode_id;
		$this->data['tracks'] = $this->songtracker->load_data($episode_id,'playlist');
		$this->load->view('api/episode',$this->data);
		$this->output->set_header("Content-Type:text/xml");
	}
	
	function live( $limit = 25 )
	{
		$this->data['episode_id'] = $episode_id;
		$this->data['logs'] = $this->songtracker->get_logs('count', $limit);
		foreach($this->data['logs'] as $key=>$val)
		{
			$shows[$val['episode_id']][] = $val;
		}
		$this->load->library('drupal');
		foreach($shows as $key=>$val)
		{
			//$ep = $this->drupal->episode_info($key);
			//$shows[$key]['show_id'] = $ep['field_show_nid'];
			//$shows[$key]['show_title'] = $ep['title'];
		}
		$this->data['logs'] = $shows;
		$this->load->view('api/live',$this->data);
		$this->output->set_header("Content-Type:text/xml");
	}
	
	function daily( $year = NULL, $month = NULL , $day = NULL )
	{
		if(empty($year)||empty($month)||empty($day))
		{
			echo "Error: You have entered a malformed URL. The URL must be in the format of /api/daily/year/month/day. <br>Example: <br>/api/daily/2010/03/31";
		}
		$start=  '12:00am '.$month.'/'.$day.'/'.$year;
		$end =  '11:59pm '.$month.'/'.$day.'/'.$year;
		$this->data['date'] = mktime(0,0,0,$month,$day,$year);
		$this->data['logs'] = $this->songtracker->get_logs('dates', $start, $end, 'asc');
		//echo $this->db->last_query();
		//print_r($this->data['logs']);
		$this->load->view('api/daily', $this->data);
		$this->output->set_header("Content-Type:text/xml");

	}

	function episode_info( $id )
	{
		$this->load->library('drupal');
		$data =$this->drupal->episode_info($id);
		var_dump($data);
		foreach($data as $key=>$val)
		{
			if(is_numeric($key))
			{
				unset($data[$key]);
			}
			trim($data[$key]);
		}
		echo json_encode($data);
	}
	function listeners()
	{
		echo file_get_contents('http://quickstat1.simplecdn.com/quickstat1/4lx8dbx3pm6y7cjj6k73tz34py64pd77');
	}
}