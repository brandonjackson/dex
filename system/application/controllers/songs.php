<?php

class Songs extends Controller {

	function Songs()
	{
		parent::Controller();
		$this->load->library('songtracker');
		$this->load->helper('url');
	}
	function index()
	{
		$data['title']="New Playlist";
		$data['shows']=$this->songtracker->get_shows();
		$this->load->view('songs/header', $data);
		$this->load->view('songs/episodes', $data);
		$this->load->view('footer', $this->data);
	}
	function playlist()
	{
		$data['title']="Playlist Factory";
		//$data['shows']=$this->songtracker->load_playlist();
		$this->load->view('songs/header', $data);
		$this->load->view('songs/playlist'/*, $data*/);
		$this->load->view('footer', $this->data);
	}
	function admin()
	{
		$data['title'] = "WYBC Meta | Admin Control Panel";
		$this->load->view('songs/header', $data);
		$this->load->view('songs/admin', $data);
		$this->load->view('footer', $this->data);
	}
	function artists()
	{
		$query = $this->db->query("SELECT artist_name, artist_id FROM music_artists ORDER BY artist_name ASC");
		$artist = array();
		$flagged = array();
		foreach ( $query->result() as $val )
		{
			$artist[$val->artist_name] = ($this->songtracker->lastfm_query("artist.getInfo", array("artist"=>$val->artist_name)));
			
			if ( strpos($artist[$val->artist_name]['url'], "+noredirect"))
			{
				$flagged[] = array('artist_name'=>$val->artist_name, 'id' => $val->artist_id, 'url'=>$artist[$val->artist_name]['url'], "new"=>""); 
			}
				
		}

		foreach($flagged as $key=>$val)
		{
			$flagged[$key]['new'] =  $this->songtracker->lastfm_validation($val['artist_name'], $val['url']);
		}
		$this->songtracker->clean_artists($flagged);
		$data['flagged'] = $flagged;
		$data['artists'] = $artist;
		
		$this->load->view("songs/artists",$data);
	}
	function ajaxData()
	{
		switch($this->uri->segment(3))
		{
			case "get_shows":
				return $this->songtracker->get_shows();
				break;
			case "get_episodes":
				return $this->songtracker->get_episodes();
				break;
			case "new_episode":
				return $this->songtracker->new_episode();
				break;
			case "artist_search":
				$_POST['type'] = 'artist';
				return $this->songtracker->live_search();
				break;
			case "song_search":
				$_POST['type'] = 'song';
				return $this->songtracker->live_search();
				break;
			case "add_track":
				return $this->songtracker->prepare_track();
				break;
			case "live_info":
				return $this->songtracker->live_info();
				break;
			case "load_playlist":
				return $this->songtracker->display_playlist($_POST['episode']);
				break;
			case "update_sort":
				return $this->songtracker->update_sort();
				break;
			case "artist_link":
				return $this->songtracker->artist_link();
				break;
			case "start_recording":
				return $this->songtracker->recorder_interface('start');
				break;
			case "final_choice":
				return $this->songtracker->save_final_choice();
				break;
			case "reset_db":
				return $this->songtracker->reset_db();
				break;
		}
	}
	
	function table()
	{
		echo "<table><tr><th>Artist</th><th>Song</th></tr>";
		$query = $this->db->query("SELECT * FROM music_songs AS s INNER JOIN music_artists AS a ON s.artist_id=a.artist_id ORDER BY a.artist_name ASC");
		foreach ( $query->result() as $row )
		{
			echo "<tr><td>".$row->artist_name."</td><td>".$row->song_title."</td></tr>";
		}
		echo "</table>";
	}
	function podcast()
	{
		$data['title'] = "The WYBC Podcaster";
		$this->load->view('songs/podcast', $data);
	}
}
?>