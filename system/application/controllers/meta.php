<?php

class Meta extends Controller {

var $data;
var $member_id;
var $user_id;

	function Meta()
	{
		parent::Controller();
		$this->load->library('songtracker');
		$this->load->model('album');
		$this->member_id = $this->session->userdata('member_id');
		$this->user_id = $this->session->userdata("user_id");
		$this->data['js'] = array();
		$this->data['css'] = array();
		$this->data['js'][] = "songtracker-ui.php";
		$this->data['js'][] = "jquery-autocomplete.js";
		$this->data['js'][] = "jquery-form.php";
		$this->data['css'][] =  "smoothness/jquery-ui.css";
		$this->data['css'][] =  "jquery-autocomplete.css";
		//$this->output->enable_profiler(TRUE);
		
	}
	function index()
	{
		$this->auth->restrict('dj');
		$this->data['title']="WYBC META";
		$this->load->view('header', $this->data);
		$this->load->view('meta/index', $this->data);
		$this->load->view('footer', $this->data);
	}
	function episodes()
	{
		$this->auth->restrict('dj');
		$this->data['title']="New Playlist";
		$this->data['shows']=$this->drupal->list_shows();
		$this->load->view('header', $this->data);
		$this->load->view('meta/episodes', $this->data);
		$this->load->view('footer', $this->data);
	}
	
	/**
	*	Playlist interface
	*	@param int $id		Episode ID
	*/
	function playlist( $id = NULL )
	{
		// No episode selected
		if(empty($id))
		{
			$this->session->set_flashdata('error', 'No episode selected.');
			redirect('meta/episodes');
		}
		else
		{
			$this->data['title']="Playlist Factory";
			$this->data['episode_id'] = $id;
			$this->data['episode'] = $this->drupal->episode_info($id);
			$this->data['js'][] = 'colorbox.js';
			$this->data['ckeditor'] = TRUE;
			$this->data['css'][] = 'colorbox.css';
			//$this->load->view('header', $this->data);
			$this->load->view('alt_header', $this->data);
			$this->load->view('meta/playlist', $this->data);
			$this->load->view('footer', $this->data);

		}
	}
	function admin()
	{
		$this->data['title'] = "WYBC Meta | Admin Control Panel";
		$this->load->view('header', $this->data);
		$this->load->view('meta/admin', $this->data);
		$this->load->view('footer', $this->data);
	}
	function logs()
	{
	//$this->output->enable_profiler(TRUE);
		$this->data['title'] = "WYBC Logs";
		$this->data['js'][] = "jquery-datatables.php";
		$this->data['css'][] = "datatables.css";
		$this->data['logs'] = $this->songtracker->get_logs('count',250);
		$this->load->view('header', $this->data);
		$this->load->view('meta/logs', $this->data);
		$this->load->view('footer', $this->data);
	}
	function charts()
	{
		$this->data['title'] = "WYBC Charts";
		$then = date ("Y-m-d H:i:s",  (time()-(60*60*24*30)));
		$now = date("Y-m-d H:i:s");
		$this->data['charts'] = $this->songtracker->charts($then, $now);
		$this->load->view('header', $this->data);
		switch ( $this->uri->segment(3))
		{
			case "artists":
				$this->load->view('meta/charts_artists', $this->data);
				break;
			case "tracks":
				$this->load->view('meta/charts_tracks', $this->data);
				break;
			case "albums":
				$this->load->view('meta/charts_albums', $this->data);
				break;
			default:
				$this->load->view('meta/charts', $this->data);
				break;
		}
		$this->load->view('footer', $this->data);

	}
	function collage()
	{
		$this->data['title'] = "WYBC Collage";
		$this->data['logs'] = $this->songtracker->get_logs("count", 100);
		$this->load->view('header', $this->data);
		$this->load->view('meta/collage', $this->data);
		$this->load->view('footer', $this->data);
	}
	function stream()
	{
		$this->data['title'] = "WYBC Collage";
		$this->data['logs'] = $this->songtracker->get_logs("count", 100);
		$this->load->view('header', $this->data);
		$this->load->view('meta/stream', $this->data);
		$this->load->view('footer', $this->data);
	}
	function artists()
	{
		$this->data['title'] = "Artists";
		$this->data['js'][] = "jquery-datatables.php";
		$this->data['css'][] = "datatables.css";
		$artists = $this->db->query("SELECT * FROM music_artists");
		$this->data['artists'] = $artists->result_array();
		$this->load->view('header', $this->data);
		$this->load->view('meta/artists', $this->data);
		$this->load->view('footer', $this->data);

	}
	function albums()
	{
		if($this->uri->segment(3)=="add")
		{
			$this->data['title'] = "Add Album";
			$this->load->view('header', $this->data);
			$this->load->view('meta/albums/add', $this->data);
		}
		elseif($this->uri->segment(3)=="edit")
		{
			$this->data['title'] = "Edit Album";
			$this->data['album'] = $this->songtracker->album_info($this->uri->segment(4));
			$this->data['tracks'] = $tracks;
			
			$this->load->view('header', $this->data);
			$this->load->view("meta/albums/edit", $this->data);
		}
		else
		{
			$this->data['js'][] = "jquery-datatables.php";
			$this->data['css'][] = "datatables.css";
			$this->data['title'] = "Albums";
			
			$album_info = $this->db->query("SELECT L.*, A.artist_id, A.artist_name
									FROM music_albums AS L
									JOIN music_artists AS A ON A.artist_id = L.artist_id
									ORDER BY album_title");
			$this->data['albums'] = $album_info->result_array();
			$this->load->view('header', $this->data);
			$this->load->view('meta/albums/index', $this->data);
		}
		$this->load->view('footer', $this->data);
	}
	function flags()
	{

		$this->data['title'] = "Artists";
		$this->data['js'][] = "jquery-datatables.php";
		$this->data['css'][] = "datatables.css";
		$this->data['flags'] = $this->songtracker->load_flags();
		$this->load->view('header', $this->data);
		$this->load->view('meta/flags', $this->data);
		$this->load->view('footer', $this->data);
	}
	function clean_artists()
	{
		$query = $this->db->query("SELECT artist_name, artist_id, valid FROM music_artists ORDER BY artist_name ASC");
		$artist = array();
		$flagged = array();
		foreach ( $query->result() as $val )
		{
			if($val->valid!=1)
			{
				$artist[$val->artist_name] = ($this->songtracker->lastfm_query("artist.getInfo", array("artist"=>$val->artist_name)));
				
				if ( strpos($artist[$val->artist_name]['url'], "+noredirect"))
				{
					$flagged[] = array('artist_name'=>$val->artist_name, 'id' => $val->artist_id, 'url'=>$artist[$val->artist_name]['url'], "new"=>""); 
				}
				else
				{
					$info = $artist[$val->artist_name];
					$veritas = array ( 
						"valid"=>1, 
						"artist_listeners"=>$info['stats']['listeners'],
						"artist_lastfm"=>$info['url']);
					$where = "artist_id=".$val->artist_id;
					$str = $this->db->update_string('music_artists', $veritas, $where);
					$this->db->query($str);
				}
			}
		}

		foreach($flagged as $key=>$val)
		{
			$flagged[$key]['new'] =  $this->songtracker->lastfm_validation($val['artist_name'], $val['url']);
		}
		$this->songtracker->clean_artists($flagged);
		$this->data['flagged'] = $flagged;
		$this->data['artists'] = $artist;
		$this->data['js'][] = "jquery-datatables.php";
		$this->data['css'][] = "datatables.css";
		$this->load->view('header', $this->data);
		$this->load->view("meta/clean_artists",$this->data);
		$this->load->view('footer', $this->data);
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
			case "go_live":
				return $this->songtracker->go_live();
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
			case "remove_log":
				return $this->songtracker->remove_log();
				break;
			case "delete_artist":
				return $this->songtracker->delete_artist();
				break;
			case "save_image":
				return $this->songtracker->save_image();
				break;
			case "track_options":
				$this->data['track'] = $this->songtracker->track_options($this->uri->segment(4));
				$this->data['author_id'] = $this->session->userdata('user_id');
				$this->load->view("meta/ajax_track_options",$this->data);
				break;
			case "add_flag":
				$this->songtracker->add_flag();
				break;
			case "artist_info":
				$this->data['artist'] = $this->songtracker->artist_info($this->uri->segment(4));
				$this->load->view('meta/artist_info', $this->data);
				break;
			case "song_info":
				$this->data['song'] = $this->songtracker->load_data($this->uri->segment(4), 'song');
				$this->data['album'] = $this->songtracker->album_info($this->data['song']['album_id']);
				$this->data['artist'] = $this->songtracker->artist_info($this->data['song']['artist_id']);
				$this->load->view('meta/song_info', $this->data);
				break;
			case "live_warning":
				$this->load->view('meta/live_warning', $this->data);
				break;
			case "remove_track":
				$this->db->where('playlist_track_id', $_POST['playlist_track_id'])->update("music_playlists", array("status"=>"0"));
				echo $this->db->last_query();
				return true;
			case "edit_album":
				$this->songtracker->edit_album();
				redirect('meta/albums/edit/'.$_POST['show_id']);
				break;
			case "resolved_flag":
				print_r($_POST);
				$date = date('Y-m-d H:i:s', time());
				$update = array ( "resolved" => $date );
				$this->db->where('id', $_POST['flag_id'])->update('music_flags', $update);
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
		$this->data['title'] = "The WYBC Podcaster";
		$this->load->view('meta/podcast', $this->data);
	}

	function archives( $id )
	{	
		$dir = "/Volumes/sharkhives/".$id.".mp3";
		$file=$dir;
		header("Content-type: application/force-download");
		header("Content-Transfer-Encoding: Binary");
		header("Content-length: ".filesize($file));
		header("Content-disposition: attachment; filename=".basename($file));
		readfile($file); 
	}
	function episode_notes( $id = NULL)
	{
		if(empty($id)) return false;
		if(!empty($_POST))
		{
			$data = array(	"body"=>$_POST['notes'],
						"teaser"=>$_POST['notes'] );
			$where = 'nid='.$id;
			$sql = $this->db->update_string("node_revisions", $data, $where);
			echo $sql;
			$this->drupal->api_call($sql);
		}
		else
		{
			$this->data['episode_id'] = $id;
			$sql = "SELECT * FROM node AS n JOIN node_revisions AS r ON r.nid = n.nid WHERE n.nid=".$id;
			$q = $this->drupal->api_call($sql);
			$this->data['notes'] = $q[0];
			$this->load->view('meta/episode_notes', $this->data);
		}
	}
	function update_status()
	{
		if(!empty($_POST))
		{
			$this->load->library('member_management');
			$this->member_management->update_status($_POST['status'], $_POST['nid']);
			$this->notify->save("status_updated", $this->member_id, array("status"=>$_POST['status']));
		}
		else
		{
			$profile = $this->drupal->load_profile($this->user_id);
			$this->data['status'] = $profile['status'];
			$this->data['nid'] = $profile['nid'];
			$this->load->view('meta/update_status', $this->data);
		}
	}

}
?>
