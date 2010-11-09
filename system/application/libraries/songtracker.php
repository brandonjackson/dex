<?php
error_reporting(0);
class Songtracker {

	var $title   = '';
	var $content = '';
	var $date    = '';
	var $artist_id;
	var $artist = array();
	var $song_id;
	var $song = array();
	var $episode_id;
	var $track = array();
	var $playlist = array();
	var $albums;
	var $album_count = 0;
	var $album_prediction;
	var $duplicate_song_id;
	var $song_lastfm_data;
	var $lastfm_count = 0;
	var $mb_count = 0;
	
	var $CI;

	function __construct()
	{
		$this->CI =& get_instance();
		$this->CI->load->helper('url');
		$this->initialize_lastfm();
		$this->episode_id = $this->CI->uri->segment(4);
	}
	
	// Show & Episode Management
	function get_shows()
	{
		$query = $this->CI->db->query('SELECT * FROM music_shows WHERE show_status=1');
		$shows = array();
		foreach ( $query->result() as $val )
		{
			$shows[$val->show_id]=$val->show_title;
		}
		return $shows;
	}
	function get_episodes()
	{
		$this->CI->load->library('drupal');
		$episodes = $this->CI->drupal->list_episodes($_POST['show_id']);
		if ($_POST['format'] == 'ul')
		{
			if(count($episodes)==0)
			{
				echo "No episodes found. Talk to the programming director.</a>";
			}
			else
			{
				echo "<ul class='list'>";
				foreach ($episodes as $key=>$val)
				{
					echo "<li><a href='".site_url('meta/playlist/'.$val['nid'])."' class='episode_editor'>".$val['title']." ".date("j F Y", strtotime($val['field_episode_time_value'])-(60*60*5))."</a></li>";
				}
				echo "</ul>";
			}
		}
		else
		{
			return $episodes;
		}
	}
	public function new_episode()
	{
		$start = date("Y-m-d H:i:s",strtotime($_POST['episode_date']." ".$_POST['start_hour'].":".$_POST['start_minutes'].$_POST['start_am']));
		$stop = date("Y-m-d H:i:s",strtotime($_POST['episode_date']." ".$_POST['stop_hour'].":".$_POST['stop_minutes'].$_POST['stop_am']));
		$data = array(	'episode_title' => $_POST['episode_title'], 
						'episode_desc' => $_POST['episode_desc'], 
						'episode_start' => $start, 
						'episode_stop' => $stop, 
						'show_id' => $_POST['show_id']);
		$str = $this->CI->db->insert_string('music_episodes', $data); 
		$this->CI->db->query($str);
		echo $this->CI->db->insert_id();
	}
	function load_data( $param, $type = 'playlist' )
	{
		if($type=='track')
		{
			$this->CI->db->join("music_playlists AS p ","p.song_id = s.song_id", "INNER");
			$this->CI->db->where("p.playlist_track_id",$param);
			$this->CI->db->order_by("p.sort_order","ASC");
		}
		elseif($type=='playlist')
		{
			$this->CI->db->join("music_playlists AS p ", "p.song_id = s.song_id", "inner");
			$this->CI->db->where("p.episode_id",$param);
			$this->CI->db->where("p.status",1);
			$this->CI->db->order_by("p.sort_order","ASC");
		}
		elseif($type=='song')
		{
			$this->CI->db->where("s.song_id",$param);
		}
		if($type!="song")
		{
			$this->CI->db->select("p.playlist_track_id, p.episode_id, p.sort_order, p.date_added, p.status,s.song_id, s.song_title,  s.song_track, a.artist_id, a.artist_name, r.album_id, r.album_title, r.album_date, r.label_id");
		}
		else
		{
			$this->CI->db->select("s.song_id, s.song_title,  s.song_track, a.artist_id, a.artist_name, r.album_id, r.album_title, r.album_date, r.label_id");
		}
		$this->CI->db->from("music_songs AS s ");
		$this->CI->db->join("music_artists AS a ","s.artist_id = a.artist_id", "inner");
		$this->CI->db->join("music_albums AS r ","r.album_id=s.album_id", "left");
		$query = $this->CI->db->get();

		if($type!='playlist')
		{
			$row = $query->result_array();
			if($type=="track")
			{
				$filename = $this->album_url($row[0]['album_id'], TRUE);
				if(file_exists($filename))
					$row[0]['album_url'] = $this->album_url($row[0]['album_id']);
				else
					$row[0]['album_url'] = $this->album_url();
			}
			return $row[0];
		}
		else
		{
			return $query->result_array();
		}

	}
	public function display_playlist ( $episode )
	{
		
		$this->playlist = $this->load_data( $episode );
		foreach ($this->playlist as $row)
			echo $this->display_track($row);
	}

	public function display_track( $track )
	{
		$r = "<li id='".$track['playlist_track_id']."'>";
		$r .= "  <span class='play_track' />";
		$r .= "  <span class='track_artist'>".$track['artist_name']."</span>";
		$r .= "  <span class='track_song'>".stripslashes($track['song_title'])."</span>";
		$r .= "  <div class='track_menu'>";
		$r .= "	   <a class='iframe track_options' href='".site_url('meta/ajaxData/track_options/'.$track['playlist_track_id'])."'>Options</a>";
		$r .= "    <span class='sort_handle'><img src='".$this->CI->config->item('url_img')."sort_handle.png' /></span>";
		$r .= "  </div>";
		$r .= "  <input type='hidden' class='album_title' value='".$track['album_title']."' />";
		$r .= "  <input type='hidden' class='album_id' value='".$track['album_id']."' />";
		$r .= "  <input type='hidden' class='artist_id' value='".$track['artist_id']."' />";
		$r .= "  <input type='hidden' class='song_id' value='".$track['song_id']."' />";
		$r .= " <br style='clear: both;' />";
		$r .= "</li>";
		return $r;
	}
	public function get_logs($type = 'count', $param_1=50, $param_2=0, $order_by = NULL)
	{
		if ( $type == "count" )
			$this->CI->db->limit(($param_2+$param_1), $param_2);
		elseif ($type == "dates")
			$this->CI->db->where("l.log_time BETWEEN '".date("Y-m-d H:i:s", strtotime($param_1))."' AND '".date("Y-m-d H:i:s", strtotime($param_2))."'");
		$this->CI->db	->select("l.log_id, l.log_time, l.playlist_track_id, p.song_id, s.song_title, a.artist_id, a.artist_name, al.album_id, al.album_title, p.episode_id")
				->from('music_log AS l ')
				->join('music_playlists AS p ', 'l.playlist_track_id = p.playlist_track_id')
				->join('music_songs AS s ', 'p.song_id = s.song_id')
				->join('music_artists AS a ', 's.artist_id = a.artist_id')
				->join('music_albums AS al ', 's.album_id = al.album_id', 'left');
		
		if($order_by == "asc")
			$this->CI->db->order_by('l.log_id', 'ASC');
		else
			$this->CI->db->order_by('l.log_id', 'DESC');
		$query = $this->CI->db->get();
		return $query->result_array();
	}
    	public function load_choices ( $type , $data )
	{
		$choices = array();
		switch ( $type ) 
		{
			case "existing":
				$choices[] = "existing";
				foreach ( $data as $val)
				{
					
					$info = $this->load_data($val, 'song');
					
					$album_info = $this->lastfm_query("album.getInfo", array("album"=>$info['album_title'], "artist"=>$info['artist_name']));
					$toSave = array("album"=>$info['album_title'], 
									"song"=>$info['song_title'], 
									"artist"=>$info['artist_name'],
									"song_id"=>$info['song_id'], 
									"art"=>$album_info['image']['large']);

					$choices[] = $toSave;
				}
				break;
			case "new":
				//echo "Data: \n";
				//print_r($data);
				//echo "\n Songdata: \n";
				//print_r($this->song);
				$choices[] = "new";
				foreach ($data as $info)
				{
					//echo "\nInfo: \n";
					//print_r($info);
					$toSave = array("album"=>$info['name'], 
									"song"=>$this->song['song_title'], 
									"artist"=>$this->song['artist_name'],
									"song_id"=>$this->song_id, 
									"art"=>$info['image']['large']);
					$choices[] = $toSave;
				}
				break;
		}
		return $choices;
	}
	public function display_choices ( $choices )
	{
		$r = "<div id='choices'>";
		$r .= "<h2>Which album would you like?</h2>";
		$r .= "<ul>";
		$type = $choices[0];
		unset($choices[0]);
		foreach ($choices as $key=>$val)
		{
			if ( $type == "existing" )
			{
				$rel = htmlentities("existing&&&".$val['song_id']);
			}
			elseif ( $type == "new" )
			{
				$rel = htmlentities("new&&&".$this->song_id."&&&".$val['album']);
			}
			$r .= "<li>";
			$r .= " <img src='".$val['art']."' rel=\"".$rel."\" />";
			$r .= " <span>".$val['album']."</span>";
			$r .= "</li>";
		}
		$r .= "</ul><br style='clear: both;' />";
		$r .= "</div>";
		return $r;
	
	}
	
	// Outputs track info back to playlist via AJAX
	function add_track()
	{
		$n = array();
		foreach($this->albums as $key=>$val)
			$n[] = $val['name'];
		$this->playlist_track_id = $this->save_to_playlist();
		echo json_encode(array(	"status"=>"success", 
								"html"=>$this->display_track($this->track),
								"album"=>$n,
								"artist"=>$this->artist['artist_name'], 
								"artist_id"=>$this->artist_id, 
								"song"=>$this->song['song_title'],
								"playlist_track_id"=>$this->playlist_track_id));
	}
	function prepare_track()
	{

		/////////////////////
		// FIND THE ARTIST//
		/////////////////////
		
		// get artist_id, save
		$this->artist_id = $this->find_artist( $_POST['artist'] );
		
		// load database info on artist, save
		$artist_query = $this->CI->db->where('artist_id', $this->artist_id)->get('music_artists');
		$this->artist = $artist_query->row_array();

		////////////////////////////
		// FIND THE SONG//
		////////////////////////////
		$this->CI->benchmark->mark('find_song_start');
		$this->song_lastfm_data = $this->lastfm_query("track.getInfo", array("track"=>$_POST['song'], "artist"=>$this->artist['artist_name']));
		
		if(isset($this->song_lastfm_data['name'])) $song = $this->song_lastfm_data['name'];
		else $song=$_POST['song'];
		// Find the song
		$this->song_id = $this->find_song($song, $this->artist_id);
				
		// If this song appears on multiple albums in the database, output some choices
		if ( $this->album_count > 1)
		{
			echo json_encode(array(	"status"=>"choices", 
									"html"=>$this->display_choices($this->load_choices("existing",explode("&&",$this->song_id)))
									));
		}
		// If this song appears on only one album in the database, finish and output results
		elseif( $this->album_count == 1 )
		{
			$this->add_track();
		}
		
		// If this song doesn't appear on any existing albums in the database, find the right one
		else
		{
			$this->song = $this->load_data($this->song_id, 'song');


			// Gather possible albums' information
			
			$this->albums = $this->find_album($this->artist['artist_name'], $this->song['song_title']);
			

			// How many possible albums are there?			
			// One album result
			if ( count($this->albums)==1 )
			{
				// Check to see if album exists.
				$existing_album = 	$this->CI->db->query("SELECT * FROM music_albums WHERE album_title= ? AND artist_id = ?", 
												array(	$this->albums[0]['name'], 
														$this->artist_id));
				
				// If yes, add album_id to song DB entry
				if ( $existing_album->num_rows==1 )
				{
					$ex_row = $existing_album->row();
					$this->CI->db->query("UPDATE music_songs SET album_id= ? AND flag = 0 WHERE song_id = ?",
									array(	$ex_row->album_id, 
											$this->song_id));
				}
				
				
				// If not, create album, then add album_id to song DB entry
				else
				{
					$album_id = $this->save_album($this->albums[0], $this->artist_id, 0);
					
					if (isset($this->duplicate_song_id)&&$this->duplicate_song_id!="")
					{
						$this->CI->db->query("DELETE FROM music_songs WHERE song_id = ?",
									array($this->song_id));
						$this->CI->db->query("UPDATE music_songs SET song_id= ? WHERE song_id = ?",
									array($this->song_id, $this->duplicate_song_id));
					}
				
				}
				
				$this->add_track();
			}
			// no album results
			elseif ( count($this->albums)==0)
			{				
				/*$html = "<div id='choices'><p>No results were found.</p></div>";
				echo json_encode(array(	"status"=>"choices", 
										"html"=>$html
										));
										*/
				$this->CI->db->query("UPDATE music_songs SET flag=1 WHERE song_id = ?",
							array( 	$this->song_id ));
				$this->add_track();
			}
			// Or more than one album result
			else
			{
				echo json_encode(array(	"status"=>"choices", 
									"html"=>$this->display_choices($this->load_choices("new", $this->albums))
									));
			}
		}
		
		$this->CI->benchmark->mark('find_album_end');
		$this->CI->benchmark->elapsed_time('find_album_start', 'find_album_end');
	}
	function save_final_choice()
	{
		$array = explode("&&&", $_POST['song_id']);
		if ($array[0] == "new")
		{
			$this->song_id = $array[1];
			$this->song = $this->load_data($this->song_id, "song");
			$album = $this->lastfm_query("album.getInfo", array("album"=>$array[2], "artist"=>$this->song['artist_name']));
			$this->save_album($album, $this->song['artist_id']);
			if(!empty($this->duplicate_song_id))
			{
				$this->CI->db->where('song_id', $this->song_id)
					    ->delete('music_songs');
				$this->CI->db->where('song_id', $this->duplicate_song_id)
				->update('music_songs', array('song_id'=>$this->song_id));
		
			}
			$this->add_track();
		}
		elseif ($array[0]=="existing")
		{
			$this->song_id = $array[1];
			$this->add_track();
		}
	}
	function find_artist ( $artist )
	{
		// We don't know the artist! We must find the artist.
		// Step 1: search DB for artists similar to the name submitted
		// Step 2: Search for special artist rule
		// Step 3: search API for artist with name provided
		// Step 3a: if good match is found, add name to DB
		// Step 3b: if no good match is found, throw error but add anyways

		$artist_search = $this->CI->db->where('artist_name',$artist)
						  ->get('music_artists');
		
		// Step 1 Success: Artist exists in database
		if ( $artist_search->num_rows == 1 )
		{
			$row = $artist_search->row();
			return $row->artist_id;
		}
		
		// Step 1 Failure: Artist doesn't already exist.
		else
		{
			
			// Step 2: Search for special artist rule in music_exceptions 
			//            (ie to see if someone has made this mistake before)
			if ( $exception = $this->exceptions("artist-to-id", $artist))
			{	
				$exception_search = $this->CI->db->where('artist_id',$exception)->get('music_artists');
				if($exception_search->num_rows==1)
					return $exception;
				else $this->log_error("Bad exception. Input: ".$artist.", Output: ".$exception.".");
			}
			
			// Step 3: Search API for similar artist
			
			// Get a validated name from last.fm
			$valid = $this->lastfm_validation($artist);
			// Check to see if the valid artist already exists
			$existing = $this->CI->db->query("SELECT * FROM music_artists WHERE artist_name= ?", array ($valid));
			
			//if not, add to DB...
			if($existing->num_rows==0)
			{
				$id = $this->save_artist($valid,0);
			}
			// but if the valid artist already exists, return that ID 
			elseif($existing->num_rows==1)
			{
				$row = $existing->row();
				$id = $row->artist_id;
			}
			// and penultimately add exception if valid name isn't the same as the original input
			if ($valid!=$artist)
			{
				$this->new_exception('artist_to_id',$artist,$id);
			}
			//finally, return the proper id
			return $id;
		}
	}
	function find_song ( $song, $artist_id )
	{
		// We don't know the song id! We must find the song id or add a new one.
		// Step 2: search API for song names with name provided
		// Step 2a: if good match is found, add name to DB
		// Step 2b: if no good match is found, throw error but add anyways
		
		// Step 1: search DB for song names similar to the name submitted
		$song_search = $this->CI->db->select('song_id, album_id')
						->where('song_title', $song)
						->where('artist_id',$artist_id)
						->get('music_songs');
		foreach ( $song_search->result_array() as $val)
		{
			if ( $val['album_id'] != 0 )
			{
				$this->album_count++;
			}
		}
			
		if ( $song_search->num_rows == 1 )
		{
			$row = $song_search->row();
			return $row->song_id;
		}
		
		// Multiple song matches - most likely from songs that are on multiple albums
		elseif ( $song_search->num_rows>1)
		{
			$n = array();
			foreach ( $song_search->result_array() as $row )
				$n[] = $row['song_id']	;
			return implode("&&",$n);
		}
		
		// No good database results found. Adding song to database, returning new id.
		else
		{
			return $this->save_song($song,$this->artist_id, 0, 0, 0, 1);
		}
	
	}
	function find_album( $artist, $song, $album_title = NULL, $hide_live = TRUE )
	{	
		$albums = array();
		if($album_title == NULL)
		{
			if($this->song_lastfm_data['album']['title']!="")
			{
				$this->album_prediction = $this->song_lastfm_data['album'];
			}
			$this->song_lastfm_data['artist']['mb_name'] = $this->exceptions("lastfm-to-musicbrainz",$this->song_lastfm_data['artist']['name']);
			$xml = $this->musicbrains_query("track", array("artist"=>$this->song_lastfm_data['artist']['mb_name'], "title"=>$this->song_lastfm_data['name']));
			foreach ( $xml->{'track-list'}->track as $track )
			{
				$type = (string) $track->{'release-list'}->release['type'];
				if ( $type!="Compilation" && $type!="Live" && $hide_live == TRUE)
				{
					$albums[] = array("name"=> (string) $track->{'release-list'}->release->title, 
												"mb_id"=>  (string) $track->{'release-list'}->release['id']);
					
				}
			}
		}
		else
		{
			$xml = $this->musicbrains_query("release", array("artist"=>$artist, "title"=>$album_title));	
			foreach($xml->{'release-list'}->release as $release)
			{
				$type = (string) $release['type'];
				if ( $type!="Compilation" && $type!="Live" && $hide_live == TRUE)
				{
					$albums[] = array("name"=> (string) $release->title, 
												"mb_id"=>  (string) $release['id']);
				}
				
			}
		}
		
		$last_resort = false;
		if ( count($albums)>0)
		{
			$albums = $this->array_duplicate_remover($albums);
			
			foreach ( $albums as $key=>$val)
			{
				$albums[] = array_merge($this->lastfm_query("album.getInfo", array("album"=>$val['name'], "artist"=>$artist)), array("mb_id"=>$val['mb_id']));
				unset($albums[$key]);
			}
			$this->array_sorter($albums, "listeners");
			// Array contents (1st level): name, artist, lastfmid, mbid, url, releasedate,image [array: small, medium and 
			// large], listeners,playcount, toptags[ 0 [name, url] ++ ]
	
			$stop=false;
			if($album_title==NULL)
			{
				foreach ( $albums as $key=>$val )
				{
					$ratio = ($val['listeners'] / $this->song_lastfm_data['listeners'] );
					if(!$stop&&$ratio > .05)
					{
						$pop = ( $val['listeners'] / $this->song_lastfm_data['listeners'] ) / ( $albums[0]['listeners'] / $this->song_lastfm_data['listeners'] );
						//echo $val['name']." (".$ratio.", ".$pop.")\n";
						if($pop < .2&&$pop!=0)
						{
							$stop=true;
							unset($albums[$key]);
						}
					}
					else
						unset($albums[$key]);
				}
			}
			// if after all this, there are no valid albums, set a flag that will save the album prediction
			if ( count($albums)==0) $last_resort = true;
		}
		else
		{
			$last_resort=true;
		}
		if($last_resort&&isset($this->album_prediction))
		{
			$lfm = $this->lastfm_query("album.getInfo", array("album"=>$this->album_prediction['title'], "artist"=>$this->album_prediction['title']));
			if($lfm['name']=="")
			{
				return;
			}
			$albums[0] = $lfm;
		}
		return $albums;
	}
	function go_live()
	{
		$track = $_POST['id'];
		$this->track = $this->load_data( $track,'track' );
		$live_data = array (	"album_img" => $this->album_url($this->track['album_id']),
						"song" => $this->track['song_title'],
						"artist_id" => $this->track['artist_id'],
						"artist_name" => $this->track['artist_name'],
						"track_id" => $this->track['playlist_track_id'],
						"instructions"=>$this->CI->util->page('go_live_instructions')
						);
		$live = $this->CI->load->view("meta/live_info", $live_data, TRUE);
		$this->save_to_log();
		
        if ($this->CI->config->item('use_recording')) {
            $this->CI->load->library('recording');
            $url = $this->CI->recording->whatis('go_live_default');
            $extension = "." . $this->CI->recording->get_extension($url);
            $duration['hours'] = 1; $duration['minutes'] = 0; $duration['seconds'] = 0;
            // TODO: figure out how long the show actually is instead of guessing 1 hour
            $this->CI->recording->record($url, $_POST['episode'] . $extension, $duration);
           // TODO: make the filenames something reasonable and stop using the POST data
        }
		echo json_encode(array("live"=>$live, "hide_controls"=>"false", "status" => "posted", "alert"=>$alert ));
	}
	function live_info( $live = FALSE )
	{
		$time = TRUE;
		$onair = TRUE;
		$hide_controls = FALSE;
		$this->CI->load->library('drupal');
		/*
		Validation pseudocode.
		
		IF ( another show on the air )
		{
			IF ( We are within an appropriate time window to play this show )
				Show "go live" dialog. 
			ELSE 
				Load current track. Disable controls.
				Show "it is not showtime yet" error.
		}
		ELSE
		{
			IF ( We are within an appropriate time window to play this show )
				Add track
			ELSE
				Load current track. Disable controls.
				Show "it is not showtime anymore" error.
		}
		*/
		
				
		// VALIDATION ROUTINES
		// Is this show already on the air?
		$log = $this->get_logs("count", 1);
		
		$track = $log[0]['playlist_track_id'];
		if($log[0]['episode_id'] != $_POST['episode'])
		{
			$hide_controls = TRUE;
			$onair = FALSE;
			$othershow_data = $this->CI->drupal->episode_info($log[0]['episode_id']);
			$othershow = $othershow_data['title'];
		}
		// Time Validation
		
		$episode = $this->CI->drupal->episode_info($_POST['episode']);
		
		// Adjust for daylight savings time.
		$remove_hour = strtotime("Second Sunday March 0");  
		$add_hour = strtotime("First Sunday November 0");  
		$time  = time();  
		if( $time >= $remove_hour && $time < $add_hour )  
		{  

			$episode['start'] += 3600;
			$episode['stop'] += 3600;	
		}  
		else  
		{  
			$episode['start'] -= 3600;
			$episode['stop'] -= 3600;	
		}  
		
		if ( ( ( $episode['start'] - time() ) > ( 60 * 10 ) ) )
		{
			$time = FALSE;
			$status = "early";
			$alert = $this->CI->load->view("meta/timing_error", array("type"=>$status), TRUE);
		}
		elseif ( ( time() - $episode['stop'] ) > ( 60 * 10 ) ) 
		{
			$time = FALSE;
			$status = "tardy";
			$alert = $this->CI->load->view("meta/timing_error", array("type"=>$status), TRUE);
		}
		// END OF VALIDATION ROUTINES
		/*if ( $live == TRUE)
		{
			$onair = TRUE;
			$track = $_POST['id'];
			$this->track = $this->load_data( $track,'track' );
			$status = "posted";
			$live_data = array (	
							"album_img" => $this->path_img."albums/".$this->track['album_id'].".jpg",
							"song" => $this->track['song_title'],
							"artist_id" => $this->track['artist_id'],
							"artist_name" => $this->track['artist_name'],
							"track_id" => $this->track['playlist_track_id'] );
			$live = $this->load->view("meta/live_info", $live_data, TRUE);
			
			if( $status == "posted") $this->save_to_log();
			echo json_encode(array("live"=>$live, "hide_controls"=>$hide_controls, "status" => $status, "alert"=>$alert ));
			exit;
		}*/
		
		// BEGIN ROUTING
		
		// IF SIMPLY AN INFO REQUEST
		if ( $_POST['type'] == "current" )
		{
			$status = "current";
		}
		
		// IF VALID TIMESLOT BUT NOT YET ON THE AIR
		elseif ( $time && !$onair )
		{
			$status = "golive";
			$live_data = array ( "title" => "Go Live",
							"id" => $_POST['id'],
							"episode"=>$_POST['episode']);
			$alert = $this->CI->load->view("meta/go_live", $live_data, TRUE);
		}
		
		// IF VALID, GET TRACK ID's 
		elseif ( $time && $onair )
		{
			$status = "posted";
			// Get track ID for next/previous requests
			if($_POST['type'] != "play")
			{
				$this->playlist = $this->load_data($_POST['episode']);
				foreach ( $this->playlist as $key=>$val )
				{
					if($val['playlist_track_id']==$_POST['id'])
					{
						if($_POST['type']=="next")
						{
							$track = $this->playlist[($key+1)]['playlist_track_id'];
						}
						elseif($_POST['type']=='previous')
						{
							$track = $this->playlist[($key-1)]['playlist_track_id'];
						}
					}
				}
			}
			// Get track ID for play requests
			elseif( $_POST['type'] == "play" )
			{
				$track = $_POST['id'];
			}
		}
		if(!$early||!$tardy)
			$this->track = $this->load_data( $track,'track' );
		
		
		$live_data = array (	"othershow" => $othershow,
						"album_img" => $this->album_url($this->track['album_id']),
						"song" => $this->track['song_title'],
						"song_id"=>$this->track['song_id'],
						"artist_id" => $this->track['artist_id'],
						"artist_name" => $this->track['artist_name'],
						"track_id" => $this->track['playlist_track_id'] );
		$live = $this->CI->load->view("meta/live_info", $live_data, TRUE);
		
		if( $status == "posted") $this->save_to_log();
		echo json_encode(array("live"=>$live, "hide_controls"=>$hide_controls, "status" => $status, "alert"=>$alert ));
	}
	function live_search()
	{
		$q = strtolower($_POST["q"]);
		$type = $_POST['type'];
		if($type=='song') $artist = $_POST['artist'];
		
		if (!$q) return;
		
		if ($type == "song")
		{
			$query = "SELECT * FROM music_songs AS s 
					INNER JOIN music_artists AS a ON a.artist_id = s.artist_id
					LEFT JOIN music_albums AS l ON s.album_id = l.album_id";
			if ( isset($artist)&&$artist!="" )
				 $query .= " WHERE artist_name= ? ";
				 $data[] = $artist;
		}
		elseif ($type == "artist")
			$query = "SELECT * FROM music_artists";
			
		$result = $this->CI->db->query($query, $data);
		if ($type == "song") echo "{ 'songs' : [";
		foreach ($result->result_array() as $key=>$value) 
		{
			if ($type == "song")
			{
				if (strpos(strtolower($value['song_title']), $q) !== false)
				{
					//echo "<img src='".base_url()."assets/images/albums/".$value['album_id'].".jpg style='width: 50px;' />".$value['song_title']."|".$value['song_id']."\n";
					//echo $value['song_title']."&&".$value['album_id']."&&".$value['album_title']."|".$value['song_id']."\n";
					echo "{ album: '".addslashes($value['album_title'])."', album_id: '".$value['album_id']."', song_id: '".$value['song_id']."', song: '".addslashes($value['song_title'])."' },\n";
				}
			}
			elseif ($type == "artist")
			{
				if (strpos(strtolower($value['artist_name']), $q) !== false)
					echo $value['artist_name']."|".$value['artist_id']."\n";
			}
		}
		if($type == "song") echo "]}";
	}
	
	// Save-To-Database Functions
	function save_artist( $name, $flag = 0)
	{
		$str = array("artist_name"=>utf8_encode($name), "flag"=>$flag, "date_added"=>date("Y-m-d H:i:s"));
		$query = $this->CI->db->insert('music_artists',$str);
		return $this->CI->db->insert_id();
	}
	function save_song ( $name, $artist_id, $album_id = 0, $song_track = 0, $mbid = 0, $flag = 0 )
	{
		$str = array("song_title"=>$name, "artist_id"=>$artist_id, "flag"=>$flag, "date_added"=>date("Y-m-d H:i:s"), "song_mbid"=>$mbid, "album_id"=>$album_id, "song_track"=>$song_track);
		$query = $this->CI->db->insert_string('music_songs',$str);
		$this->CI->db->query($query);
		return $this->CI->db->insert_id();
	}
	function save_album ( $array, $artist_id, $flag = 0 )
	{
	/*
		sample $array =    [name]  [artist]  [lastfmid]  [mbid] [url]  [releasedate]  [image] [listeners]  [playcount]   [toptags]
	*/
		if(empty($artist_id))
		{
			$q = $this->CI->db->where('artist_name', $array['artist'])->get('music_artists');
			$temprow = $q->row();
			$artist_id = $temprow->artist_id;
		}
		if(!isset($array['mb_id'])||$array['mb_id']=="")
		{
			$find_album = $this->musicbrains_query("release", array("artist"=>$array['artist'], "title"=>$array['name']));
			$album = $find_album->{'release-list'}->{'release'}[0];
			$mbid = (string) $album['id'];
		}
		else $mbid=$array['mb_id'];
		if(empty($mbid))
		{
			$str = array("album_title"=>$array['name'], "artist_id"=>$artist_id, "flag"=>$flag, "date_added"=>date("Y-m-d H:i:s"));
			if($array['releasedate']!="") $str['album_date'] = date("Y-m-d",$array['releasedate']);
			$this->CI->db->insert('music_albums', $str);
			$album_id = $this->CI->db->insert_id();
			$update = array("album_id"=>$album_id);
			$this->CI->db->where('song_id', $this->song['song_id'])->update('music_songs', $update);
			return $this->CI->db->insert_id();
		}
		else
		{
			$tracklisting = $this->musicbrains_query('release/'.$mbid,array("inc"=>"tracks"));
			//echo $tracklisting->asXML();
			
			// test for multiple disks.
			if(strpos((string) $tracklisting->release->title, "(disc")==false)
			{
				$str = array("album_title"=>$array['name'], "artist_id"=>$artist_id, "flag"=>$flag, "date_added"=>date("Y-m-d H:i:s"));
				if($array['releasedate']!="") $str['album_date'] = date("Y-m-d",$array['releasedate']);
				$query = $this->CI->db->insert_string('music_albums',$str);
				$this->CI->db->query($query);
				$album_id = $this->CI->db->insert_id();
				$i=1;
				$similar = array();
				foreach ( $tracklisting->release->{'track-list'}->track as $track)
				{
					$id = $this->save_song((string) $track->title, $artist_id, $album_id, $i, (string) $track['id'],0);
					similar_text((string) $track->title, $this->song['song_title'], $similar[$id]);
					$i++;
				}
				arsort($similar);
				foreach($similar as $key=>$val)
				{
					$this->duplicate_song_id = $key;
					break;
				}
				
				
				
				$this->CI->benchmark->mark('save_image_start');			
				$url = $array['image']['large'];
				$img = $this->album_url($album_id, TRUE);
				$this->CI->util->do_post_request(site_url('meta/ajaxData/save_image'), http_build_query(array("url"=>$url, "dest"=>$img)));
				$this->CI->benchmark->mark('save_image_end');
				$this->CI->benchmark->elapsed_time('save_image_start', 'save_image_end');
				
				$this->CI->benchmark->mark('save_album_end');
				$this->CI->benchmark->elapsed_time('save_album_start', 'save_album_end');
	
				return $album_id;
			}
		}
	}
	function save_to_playlist ()
	{
		$sort_query = $this->CI->db->query("SELECT * FROM music_playlists WHERE episode_id= ? ORDER BY sort_order DESC",
									array(	$this->episode_id ) );
		if ($sort_query->num_rows() > 0)
		{
		   $row = $sort_query->row();
		   $sort = $row->sort_order + 1;
		}
		else $sort = 0;

		$str = array("song_id"=>$this->song_id, "episode_id"=>$this->episode_id, "date_added"=>date("Y-m-d H:i:s"), "sort_order"=>$sort);
		$query = $this->CI->db->insert_string('music_playlists',$str);
		$this->CI->db->query($query);	
		$track_id = $this->CI->db->insert_id();
		$this->track = $this->load_data($track_id,'track');
		return $track_id;
	}
	function save_to_log ()
	{
		$data = array(	"playlist_track_id"=>$this->track['playlist_track_id'] );
		$insertion = $this->CI->db->insert_string("music_log", $data);
		$this->CI->db->query($insertion);
	}
	
	
	// API Functions
	private function initialize_lastfm()
	{
		require('lastfmapi/lastfmapi.php');
		
		// Set the API key
		$authVars['apiKey'] = '2f787cc64488c1c5f9fb0e6d61ed736f';
		
		// Pass the apiKey to the auth class to get a none fullAuth auth class1
		$this->lastfm_auth = new lastfmApiAuth('setsession', $authVars);
		$this->apiClass = new lastfmApi();
	}
	function lastfm_query( $type, $filters )
	{
		$marker = "lastfm_query_".$this->lastfm_count."_";
		$this->CI->benchmark->mark($marker.'start');
		
		// Prepare parameters
		$methodVars = array( 'page' => 1, 'limit' => 20);
		$methodVars = array_merge($methodVars,$filters);
		$class = explode(".",$type);
		
		// Load appropriate class
		switch($class[0])
		{
			case "album":
				$this->lastfm_album = $this->apiClass->getPackage($this->lastfm_auth, 'album');
				break;
			case "artist":
				$this->lastfm_artist = $this->apiClass->getPackage($this->lastfm_auth, 'artist');
				break;
			case "track":
				$this->lastfm_track = $this->apiClass->getPackage($this->lastfm_auth, 'track');
				break;
		}
		
		// Execute appropriate method
		switch($type)
		{
			case "album.getInfo":
				$album=array();
				if ( $results = $this->lastfm_album->getInfo($methodVars) ) 
				{
					$this->CI->benchmark->mark($marker.'end');
					$this->CI->benchmark->elapsed_time($marker.'start', $marker.'end');
					$this->lastfm_count++;
					return $results;
				}
				break;
			case "track.getInfo":
				if ( $results = $this->lastfm_track->getInfo($methodVars) )
				{
					$this->CI->benchmark->mark($marker.'end');
					$this->CI->benchmark->elapsed_time($marker.'start', $marker.'end');
					$this->lastfm_count++;
					return $results;
				}
				break;
			case "artist.getInfo" :
				if ( $results = $this->lastfm_artist->getInfo($methodVars) )
				{
					$this->CI->benchmark->mark($marker.'end');
					$this->CI->benchmark->elapsed_time($marker.'start', $marker.'end');
					$this->lastfm_count++;
					return $results;
				}
				break;
		}
		$this->CI->benchmark->mark($marker.'end');
		$this->CI->benchmark->elapsed_time($marker.'start', $marker.'end');
		$this->lastfm_count++;
	}
	function lastfm_validation( $artist, $url = '')
	{
		$data = $this->lastfm_query("artist.getInfo", array("artist"=>$artist));
		if ( $url == "" )
		{
			$url = $data['url'];
		}
		if ( strpos($url, "+noredirect") )
		{
			$url = str_replace("+noredirect/", "", $url );
			$headers = get_headers($url, 1);
			$new = str_replace("http://www.last.fm/music/", "", $headers['Location']);
			$new = urldecode(utf8_decode(urldecode($new)));
			return $new;
		}
		else
		{
			return $data['name'];
		}
		
	}
	function clean_artists ( $flagged )
	{
		foreach ( $flagged as $key=>$val )
		{
			// Store an exception for later use
			$data = array ( 	"type"=>"artist-to-id", 
								"input"=>$val['artist_name'], 
								"output"=>$val['id'] );
			$exceptions = $this->CI->db->insert_string('music_exceptions', $data );
			$this->CI->db->query($exceptions);
			
			// Check to see if this artist already exists.
			$existing = $this->CI->db->query("SELECT * FROM music_artists WHERE artist_name= ? COLLATE utf8_general_ci", array ($val['new']));
			if($existing->num_rows==0)
			{
				// If not, change the name of the database record
				$data2 = array('artist_name' => $val['new'], 'valid'=>1);
				$where = "artist_id = ".$val['id'];
				$update = $this->CI->db->update_string('music_artists', $data2, $where); 
				$this->CI->db->query($update);
			}
			elseif($existing->num_rows==1)
			{
				// If so, change all references to that artist id to the existing record's
				$row = $existing->row();
				$this->merge("artist",$row->artist_id, $val['id']);
			}
		}
	}
	function merge($object, $valid, $invalid)
	{
		switch($object)
		{
			case "artist":
			
				//Add an exception so this never happens again.
				$name_query = $this->CI->db->query("SELECT artist_name FROM music_artists WHERE artist_id = ?", array($invalid));
				$name = $name_query->row(); 
				$exception_data = array("type"=>"artist-to-id", "input"=>$name->artist_name, "output"=>$valid); 
				$this->CI->db->query($this->CI->db->insert_string("music_exceptions", $exception_data));
				
				$data = array ("artist_id"=>$valid);
				$where = "artist_id=".$invalid;
				// update the song table
				$this->CI->db->query($this->CI->db->update_string('music_songs',$data, $where));
				// update the album table
				$this->CI->db->query($this->CI->db->update_string('music_albums',$data, $where));
				// delete invalid artist row
				$this->CI->db->query("DELETE FROM music_artists WHERE artist_id=".$invalid);
				break;
		}
	}
			
	private function musicbrains_query( $type, $filters)
	{
	
		$marker = "mb_query_".$this->mb_count."_";
		$this->CI->benchmark->mark($marker.'start');
		$url = "http://musicbrainz.org/ws/1/".$type."/?type=xml";
		foreach ( $filters as $key=>$val)
		{
			$val = str_replace(" ","+",$val);
			$url .= "&".$key."=".$val;
		}
		//echo $url."\n";
		$xml = new SimpleXMLElement($url, NULL, TRUE);
		$this->CI->benchmark->mark($marker.'end');
		$this->CI->benchmark->elapsed_time($marker.'start', $marker.'end');
		$this->mb_count++;
		return ($xml);
	}
	private function exceptions($type, $item)
	{
		switch ($type)
		{
			case "lastfm-to-musicbrainz":
				$query = $this->CI->db->query('SELECT * FROM music_exceptions WHERE type= ? AND input = ?',
									array(	$type, $item ) );
				if ( $query->num_rows==1 )
				{
					$row=$query->row();
					return $row->output;
				}
				else
					return $item;
				break;
			case "artist-to-id":
				$query = $this->CI->db->query('SELECT * FROM music_exceptions WHERE type= ? AND input = ?', 
									array($type, $item));
				if ( $query->num_rows==1 )
				{
					$row=$query->row();
					return $row->output;
				}
				else
					return false;
				break;
				
				
		}
	
	}
	function update_sort()
	{
		$order = explode(",",$_POST['order']);
		foreach($order as $key=>$val)
		{
			$this->CI->db->query("UPDATE music_playlists SET sort_order= ? WHERE playlist_track_id= ?",
						array( $key, $val));
		}
	}
	
	// Utilities
	static function array_sorter(&$arr, $key, $order = SORT_DESC)
	{ 
  		$sort_col = array(); 
  		foreach ($arr as $sub) $sort_col[] = $sub[$key]; 
  		array_multisort($sort_col, SORT_DESC, SORT_REGULAR, $arr); 
	}
	static function array_duplicate_remover( $array )
	{
		foreach ( $array as $key=>$val )
		{
			foreach ( $array as $key2=>$val2 )
			{
				if ( ( $key!=$key2 ) && ($val['name'] == $val2['name'] ) )
				{
					unset($array[$key]);
				}
			}	
		}
		return $array;
	}
	function reset_db()
	{
		$this->CI->db->query("DELETE FROM music_songs");
		$this->CI->db->query("DELETE FROM music_albums");
		$this->CI->db->query("DELETE FROM music_playlists");
	}
	function remove_log()
	{
		$this->CI->db->query("DELETE FROM music_log WHERE log_id=".$_POST['log_id']);
	}
	function delete_artist()
	{
		switch($_POST['type'])
		{
			case "stats":
				$songs_query = $this->CI->db->query("SELECT * FROM music_songs WHERE artist_id= ?", 
											array($_POST['artist_id']));
				$songs = $songs_query->num_rows();
				
				$albums_query = $this->CI->db->query("SELECT * FROM music_albums WHERE artist_id= ?", 
											array($_POST['artist_id']));
				$albums= $albums_query->num_rows();
				
				$playlists_query = $this->CI->db->query("SELECT * FROM music_playlists AS p INNER JOIN music_songs AS s ON s.song_id = p.song_id WHERE artist_id= ?", 
											array($_POST['artist_id']));
				$playlists= $playlists_query->num_rows();
				echo "<p>Deleting this artist will also delete:</p>";
				echo "<ul>";
				echo "<li>".$songs." songs</li>";
				echo "<li>".$albums." albums</li>";
				echo "<li>".$playlists." playlist tracks</li>";
				echo "</ul>";
				break;
			case "merge-list":
				echo "<p>Merge this artist into:</p>";
				echo "<select id='merge_options' style='font-size: 12px;'>";
				$artist_query = $this->CI->db->query("SELECT * FROM music_artists ORDER BY artist_name ASC");
				foreach($artist_query->result_array() as $info)
				{
					echo "<option value='".$info['artist_id']."'>".$info['artist_name']."</option>";
				}
				echo "</select>";
				break;
			case "merge":
				$this->merge("artist",$_POST['new_artist_id'], $_POST['artist_id']);
				break;
			case "delete":
				$this->CI->db->delete('music_exceptions', array('output'=>$_POST['artist_id']));
				$this->CI->db->query("DELETE FROM music_albums WHERE artist_id = ?",array($_POST['artist_id']));
				$this->CI->db->query("DELETE FROM music_artists WHERE artist_id = ?",array($_POST['artist_id']));
				$tracks_query = $this->CI->db->query("SELECT * FROM music_playlists AS p INNER JOIN music_songs AS s ON p.song_id = s.song_id WHERE s.artist_id = ?",array($_POST['artist_id']));
				$tracks = array();
				foreach ( $tracks_array->result_array() as $row)
				{
					$tracks[] = $row['playlist_track_id'];
				}
				foreach ( $tracks as $val)
					$this->CI->db->query("DELETE FROM music_playlists WHERE playlist_track_id = ?", array($val));
				break;
		}
	}
	function charts ($start, $stop )
	{
		$logs = $this->get_logs("dates", $start, $stop);
		$artists = array();
		$songs = array();
		$albums = array();
		foreach($logs as $val)
		{
			$artists[$val['artist_id']]['count']++;
			$artists[$val['artist_id']]['name'] = $val['artist_name'];
			$artists[$val['artist_id']]['id'] = $val['artist_id']; 
			$songs[$val['song_id']]['count']++;
			$songs[$val['song_id']]['title'] = $val['song_title'];
			$songs[$val['song_id']]['artist'] = $val['artist_name'];
			$songs[$val['song_id']]['id'] = $val['song_id'];
			$albums[$val['album_id']]['count']++;
			$albums[$val['album_id']]['title'] = $val['album_title'];
			$albums[$val['album_id']]['artist'] = $val['artist_name'];
			$albums[$val['album_id']]['id'] = $val['album_id'];
		}
		$this->array_sorter($artists,'count');
		$this->array_sorter($songs, 'count');
		$this->array_sorter($albums, 'count');
		$charts = array ( "artists"=>$artists, "albums"=>$albums, "songs"=>$songs);
		return $charts;
	}
	
	function save_image()
	{
		file_put_contents($_POST['dest'], file_get_contents($_POST['url']));
	}

	function track_options( $id )
	{
		return $this->load_data($id, 'track');
		
	}
	
	function add_flag()
	{
		$data = $this->load_data($_POST['playlist_track_id'], "track");
		if ( $_POST['type']=="song" )
			$item_id = $data['song_id'];
		elseif( $_POST['type'] == "artist" )
			$item_id = $data['artist_id'];
		elseif($_POST['type'] == "album")
			$item_id = $data['album_id'];
		$d = array(	"type"=>$_POST['type'],
					"item_id"=>$item_id,
					"correction"=>$_POST['correction'],
					"author_id"=>$_POST['author_id'],
					"created"=>date("Y-m-d H:i:s"));
		$this->CI->db->insert("music_flags",$d);
	}
	function edit_album()
	{
		foreach ( $_POST as $key=>$val)
		{
			if (strpos($key, "song_track_") !== FALSE )
			{
				$id = substr($key, 11);
				$update = array ("song_track"=>$val);
			}
			elseif (strpos($key, "song_title_") !== FALSE )
			{
				$id = substr($key, 11);
				$update = array ("song_title"=>$val);
			}
			elseif( strpos($key, "delete_") !== FALSE )
			{
				$id = substr($key, 7);
				$this->delete_song($id);
			}
			if(!empty($id)&&!empty($update))
			{
				$this->CI->db->where('song_id',$id)->update('music_songs',$update);
			}
		}
	}
	function delete_song( $id )
	{
		$p = $this->CI->db->where('song_id',$id)->get('music_playlists');
		$playlists = $p->num_rows;
		if($playlists==0)
		{
			$this->CI->db->where('song_id', $id)->delete('music_songs');
		}
		else
		{	
			$update = array ( "album_id"=>NULL);
			$this->CI->db->where('song_id', $id)->update('music_songs', $update);
		}
	}
	function album_info( $id, $tracks = TRUE, $stats = TRUE )
	{
		if(empty($id)) return false;
		
		$album_query = $this->CI->db->from("music_albums AS L ")
								->join("music_artists AS A ", "A.artist_id = L.artist_id")
								->where('album_id', $id)
								->get();
		$album_data = $album_query->row_array();
		
		$tracks_query = $this->CI->db->where('album_id', $id)->order_by("song_track", "ASC")->get('music_songs');
		$tracks_data = $tracks_query->result_array();
		
		$album = array ( 	"id" => $id,
						"title" => $album_data['album_title'],
						"artist" => $album_data['artist_name'],
						"artist_id" => $album_data['artist_id'],
						"released" => $album_data['album_date'],
						"date_added" => $album_data['date_added'],
						"img"=>$this->album_url($id)
					);
		if ( $stats )
		{
			$album['stats'] =  array ( 	"week" => $this->tabulate("album", $id, "-1 week"),
								"month" => $this->tabulate("album", $id, "-1 month"),
								"year" => $this->tabulate("album", $id, "-1 year"),
								"total" => $this->tabulate("album", $id, "January 1 2010"));
		}
		if ( $tracks )
		{
			$album['tracks'] = array();
			foreach ( $tracks_data AS $key=>$val )
			{
				$stats = array ( 	"week" => $this->tabulate("song", $val['song_id'], "-1 week"),
								"month" => $this->tabulate("song", $val['song_id'], "-1 month"),
								"year" => $this->tabulate("song", $val['song_id'], "-1 year"),
								"total" => $this->tabulate("song", $val['song_id'], "January 1 2010"));
				$album['tracks'][] = array (	"track" => $val['song_track'],
										"title" => $val['song_title'],
										"date_added" => $val['date_added'],
										"stats" => $stats );
			}
		}
		
		return $album;
		
		/* SAMPLE OUTPUT
		Array
		(
		    [id] => 283
		    [title] => All Hour Cymbals
		    [artist] => Yeasayer
		    [artist_id] => 674
		    [released] => 2007-10-23
		    [date_added] => 2009-12-20 13:08:00
		    [img] => http://localhost/wybc/assets/images/albums/283.jpg
		    [stats] => Array
			(
			    [week] => 0
			    [month] => 0
			    [year] => 0
			    [total] => 0
			)
		
		    [tracks] => Array
			(
			    [0] => Array
				(
				    [track] => 1
				    [title] => Sunrise
				    [date_added] => 2010-01-25 19:27:30
				    [stats] => Array
					(
					    [week] => 0
					    [month] => 0
					    [year] => 0
					    [total] => 0
					)
		
				)
		
			    [1] => Array
				(
				    [track] => 10
				    [title] => Waves
				    [date_added] => 2010-01-25 19:27:30
				    [stats] => Array
					(
					    [week] => 0
					    [month] => 0
					    [year] => 0
					    [total] => 0
					)
		
				)
		
			    [2] => Array
				(
				    [track] => 11
				    [title] => Red Cave
				    [date_added] => 2010-01-25 19:27:30
				    [stats] => Array
					(
					    [week] => 0
					    [month] => 0
					    [year] => 0
					    [total] => 0
					)
		
				)
		
			    [3] => Array
				(
				    [track] => 2
				    [title] => Wait for the Summer
				    [date_added] => 2010-01-25 19:27:30
				    [stats] => Array
					(
					    [week] => 0
					    [month] => 0
					    [year] => 0
					    [total] => 0
					)
		
				)
		
			    [4] => Array
				(
				    [track] => 3
				    [title] => 2080
				    [date_added] => 2010-01-25 19:27:31
				    [stats] => Array
					(
					    [week] => 0
					    [month] => 0
					    [year] => 0
					    [total] => 0
					)
		
				)
		
			    [5] => Array
				(
				    [track] => 4
				    [title] => Germs
				    [date_added] => 2010-01-25 19:27:30
				    [stats] => Array
					(
					    [week] => 0
					    [month] => 0
					    [year] => 0
					    [total] => 0
					)
		
				)
		
			    [6] => Array
				(
				    [track] => 5
				    [title] => Ah, Weir
				    [date_added] => 2010-01-25 19:27:30
				    [stats] => Array
					(
					    [week] => 0
					    [month] => 0
					    [year] => 0
					    [total] => 0
					)
		
				)
		
			    [7] => Array
				(
				    [track] => 6
				    [title] => No Need to Worry
				    [date_added] => 2010-01-25 19:27:30
				    [stats] => Array
					(
					    [week] => 0
					    [month] => 0
					    [year] => 0
					    [total] => 0
					)
		
				)
		
			    [8] => Array
				(
				    [track] => 7
				    [title] => Forgiveness
				    [date_added] => 2010-01-25 19:27:30
				    [stats] => Array
					(
					    [week] => 0
					    [month] => 0
					    [year] => 0
					    [total] => 0
					)
		
				)
		
			    [9] => Array
				(
				    [track] => 8
				    [title] => Wait for the Wintertime
				    [date_added] => 2010-01-25 19:27:30
				    [stats] => Array
					(
					    [week] => 0
					    [month] => 0
					    [year] => 0
					    [total] => 0
					)
		
				)
		
			    [10] => Array
				(
				    [track] => 9
				    [title] => Worms
				    [date_added] => 2010-01-25 19:27:30
				    [stats] => Array
					(
					    [week] => 0
					    [month] => 0
					    [year] => 0
					    [total] => 0
					)
		
				)
		
			)
		
		)
		*/
	}
	function artist_info ( $id )
	{
		$q = $this->CI->db->where('artist_id', $id)->get('music_artists');
		$row = $q->row();
		$row->info = $this->lastfm_query("artist.getInfo", array("artist"=>$row->artist_name));
		return $row;
		/* SAMPLE OUTPUT
		stdClass Object
		(
		    [artist_id] => 462
		    [artist_name] => Passion Pit
		    [artist_lastfm] => http://www.last.fm/music/Passion+Pit
		    [artist_desc] => 
		    [flag] => 0
		    [valid] => 1
		    [artist_listeners] => 281229
		    [date_validated] => 2009-12-20 14:09:04
		    [date_added] => 2009-12-20 13:08:00
		    [info] => Array
			(
			    [name] => Passion Pit
			    [mbid] => 
			    [url] => http://www.last.fm/music/Passion+Pit
			    [image] => Array
				(
				    [small] => http://userserve-ak.last.fm/serve/34/39757931.png
				    [medium] => http://userserve-ak.last.fm/serve/64/39757931.png
				    [large] => http://userserve-ak.last.fm/serve/126/39757931.png
				)
		
			    [streamable] => 1
			    [stats] => Array
				(
				    [listeners] => 333678
				    [playcount] => 
				)
		
			    [similar] => Array
				(
				    [0] => Array
					(
					    [name] => Miike Snow
					    [url] => http://www.last.fm/music/Miike+Snow
					    [image] => Array
						(
						    [small] => http://userserve-ak.last.fm/serve/34/33650671.jpg
						    [medium] => http://userserve-ak.last.fm/serve/64/33650671.jpg
						    [large] => http://userserve-ak.last.fm/serve/126/33650671.jpg
						)
		
					)
		
				    [1] => Array
					(
					    [name] => Discovery
					    [url] => http://www.last.fm/music/Discovery
					    [image] => Array
						(
						    [small] => http://userserve-ak.last.fm/serve/34/36091507.png
						    [medium] => http://userserve-ak.last.fm/serve/64/36091507.png
						    [large] => http://userserve-ak.last.fm/serve/126/36091507.png
						)
		
					)
		
				    [2] => Array
					(
					    [name] => Phoenix
					    [url] => http://www.last.fm/music/Phoenix
					    [image] => Array
						(
						    [small] => http://userserve-ak.last.fm/serve/34/39632783.png
						    [medium] => http://userserve-ak.last.fm/serve/64/39632783.png
						    [large] => http://userserve-ak.last.fm/serve/126/39632783.png
						)
		
					)
		
				    [3] => Array
					(
					    [name] => Ra Ra Riot
					    [url] => http://www.last.fm/music/Ra+Ra+Riot
					    [image] => Array
						(
						    [small] => http://userserve-ak.last.fm/serve/34/13640753.jpg
						    [medium] => http://userserve-ak.last.fm/serve/64/13640753.jpg
						    [large] => http://userserve-ak.last.fm/serve/126/13640753.jpg
						)
		
					)
		
				    [4] => Array
					(
					    [name] => Vampire Weekend
					    [url] => http://www.last.fm/music/Vampire+Weekend
					    [image] => Array
						(
						    [small] => http://userserve-ak.last.fm/serve/34/19765431.jpg
						    [medium] => http://userserve-ak.last.fm/serve/64/19765431.jpg
						    [large] => http://userserve-ak.last.fm/serve/126/19765431.jpg
						)
		
					)
		
				)
		
			    [tags] => Array
				(
				    [0] => Array
					(
					    [name] => electronic
					    [url] => http://www.last.fm/tag/electronic
					)
		
				    [1] => Array
					(
					    [name] => indie
					    [url] => http://www.last.fm/tag/indie
					)
		
				    [2] => Array
					(
					    [name] => indie pop
					    [url] => http://www.last.fm/tag/indie%20pop
					)
		
				    [3] => Array
					(
					    [name] => pop
					    [url] => http://www.last.fm/tag/pop
					)
		
				    [4] => Array
					(
					    [name] => falsetto
					    [url] => http://www.last.fm/tag/falsetto
					)
		
				)
		
			    [bio] => Array
				(
				    [published] => Sun, 10 Jan 2010 09:50:34 +0000
				    [summary] => Passion Pit is an electronic band that formed in Cambridge, Massachusetts, United States in 2007. They consist of Michael Angelakos (vocals, keyboards), Ian Hultquist (keyboards, guitar), Ayad Al Adhamy (synth, samples), Jeff Apruzzese (bass, keyboard) and Nate Donmoyer (drums). Passion Pit was a vocab word used in a class Mike took in school. It's a slang word for a drive-in movie theatre where kids used to go to make out.  
				    [content] => Passion Pit is an electronic band that formed in Cambridge, Massachusetts, United States in 2007. They consist of Michael Angelakos (vocals, keyboards), Ian Hultquist (keyboards, guitar), Ayad Al Adhamy (synth, samples), Jeff Apruzzese (bass, keyboard) and Nate Donmoyer (drums). Passion Pit was a vocab word used in a class Mike took in school. It's a slang word for a drive-in movie theatre where kids used to go to make out.
		 
		 Michael played his first show alone sitting with his laptop and a microphone. Later, Ian approached Mike and asked him if he wanted to start a band. After a few line-up changes, Passion Pit was born.
		 
		 The band's debut Chunk Of Change EP originally had four songs and was recorded by Angelakos on his laptop. It was intended as a belated Valentine's Day gift to his girlfriend. This set of songs was passed around Emerson College, where Angelakos was attending and caught the attention of many blogs. It was then released on September 16th, 2008 on Frenchkiss Records with two extra songs. The band's debut album, entitled Manners, was released on May 19, 2009.
		 
		 The song Sleepyhead contains samples of the song "Oro Mo Bhaidin" by Irish harpist Mary O'Hara. This song has been used in a US advertisment for the Palm Pixi, Canadian PSP advertisement, MTVs 'What the Flip?' campaign and the television shows, Skins and Gossip Girl.
		 
		 Passion Pit made their television debut on Late Night with Jimmy Fallon on Wednesday July 29, 2009 performing their song "The Reeling" from the album Manners.
				)
		
			)
		
		)
		*/
	}
	function tabulate ( $type, $id, $start, $stop = NULL)
	{
		if ( $stop == NULL ) $stop = date("Y-m-d H:i:s");
		
		if ($type == "song") $type = "p.song_id";
		elseif($type == "album") $type = "al.album_id";
		elseif($type == "artist") $type = "a.artist_id";
		
		
		$where = "WHERE ( l.log_time BETWEEN '".date("Y-m-d H:i:s", strtotime($start))."' AND '".date("Y-m-d H:i:s")."' )";
		$where .= " AND ( ".$type." = ".$id." )";
		$log_query = $this->CI->db->query("SELECT l.log_id, l.log_time, l.playlist_track_id
                    			FROM music_log AS l
                   			INNER JOIN music_playlists AS p ON l.playlist_track_id = p.playlist_track_id
                    			INNER JOIN music_episodes AS e ON p.episode_id = e.episode_id
                    			INNER JOIN music_shows AS sh ON sh.show_id = e.show_id
                    			INNER JOIN music_songs AS s ON p.song_id = s.song_id
                    			INNER JOIN music_artists AS a ON s.artist_id = a.artist_id
                    			INNER JOIN music_albums AS al ON s.album_id = al.album_id ".$where."
                    			ORDER BY l.log_id DESC");
		return $log_query->num_rows;
	}
	function album_url( $id = NULL, $show_path = FALSE )
	{
		if($show_path)
			$path = $this->CI->config->item('path_img')."albums/";
		else
			$path =  $this->CI->config->item('url_img')."albums/";
		if(!empty($id))
			return $path.$id.".jpg";
		else
			return $path."default.png";
	}
	function load_flags()
	{
		$q = $this->CI->db->where("resolved", "0000-00-00 00:00:00")->get("music_flags");
		$r = $q->result_array();
		$f = array();
		foreach( $r as $key=>$val)
		{
			if($val['type']=="song")
			{
				$iq = $this->CI->db->where('song_id', $val['item_id'])->get('music_songs');
				$ir = $iq->row_array();
				$val['item'] = $ir['song_title'];
			}
			elseif($val['type']=="artist")
			{
				$iq = $this->CI->db->where('artist_id', $val['item_id'])->get('music_artists');
				$ir = $iq->row_array();
				$val['item'] = $ir['artist_name'];
			}
			elseif($val['type'] == "album")
			{
				$iq = $this->CI->db->where('album_id', $val['item_id'])->get('music_albums');
				$ir = $iq->row_array();
				$val['item'] = $ir['album_title'];
			}
			$f[] = $val;
		}
		return $f;
	}
	function log_error($error)
	{
		$data = array(	"error"=>$error,
					"url"=>$this->CI->uri->uri_string(),
					"date"=>date("Y-m-d H:i:s"));
		$this->CI->db->insert('errors',$data);
	}
	function new_exception($type, $in, $out)
	{
		//check for duplicates
		$q = $this->CI->db->where('type',"artist-to-id")->where('input',$in)->get('music_exceptions');
		
		// if found
		if($q->num_rows!=0)
		{
			// load info about old entry
			$qr = $q->row();
			//log error
			$this->log_error("Duplicate exception. Exception ID: ".$qr->exception_id.". Input: ".$in.". Old Output: ".$qr->output.". New Output: ".$out.".");
			// update old exception
			$data = array ( "output"=>$out);
			$this->CI->db->where('exception_id', $qr->exception_id)->update('music_exceptions', $data);
		}
		else
		{
			$data = array("type"=>$type, "input"=>$in, "output"=>$out);
			$str = $this->CI->db->insert("music_exceptions", $data);
		}
	}	
}
?>
