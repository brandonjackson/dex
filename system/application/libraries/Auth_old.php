<?php
ob_start();
/**
* The Authentication Library
*
* @package Authentication
* @category Libraries
* @author Adam Griffiths
* @link http://adamgriffiths.co.uk
* @version 1.0.6
* @copyright Adam Griffiths 2009
*
* Auth provides a powerful, lightweight and simple interface for user authentication 
*/

class Auth
{
	
	var $CI; // The CI object
	var $config; // The config items
	var $user_table; // The user table (prefix + config)
	var $group_table; // The group table (prefix + config)
	
	/** 
	* Auth constructor
	*
	* @access public
	* @param string
	*/
	function Auth($config)
	{
		
		$this->CI =& get_instance();
		$this->CI->benchmark->mark('AuthConstructor_start');
		$this->config = $config;
		
		$this->CI->load->database();
		$this->CI->load->helper(array('form', 'url', 'email'));
		$this->CI->load->library('form_validation');
		$this->CI->load->library('session');
		
		$this->CI->lang->load('auth', 'english');
		
		$this->user_table = $this->CI->db->dbprefix($this->config['auth_user_table']);
		$this->group_table = $this->CI->db->dbprefix($this->config['auth_group_table']);
		
		if($this->logged_in())
		{
			$this->_verify_cookie();
		}
		else
		{
			if(!array_key_exists('login_attempts', $_COOKIE))
			{
				setcookie("login_attempts", 0, time()+900, '/');
			}
		}
		$this->CI->benchmark->mark('AuthConstructor_end');
		$this->CI->benchmark->elapsed_time('AuthConstructor_start', 'AuthConstructor_end');
		$this->CI->output->enable_profiler(TRUE);
	} 
	
	
	// function Auth()
	
	/** 
	* Restricts access to a page
	*
	* Takes a user level (e.g. admin, user etc) and restricts access to that user and above.
	* Example, users can access a profile page, but so can admins (who are above users)
	*
	* @access public
	* @param string
	* @return bool
	*/
	function restrict($group = NULL, $single = NULL)
	{
		if($group === NULL)
		{
			if($this->logged_in() == TRUE)
			{
				return TRUE;
			}
			else
			{
				show_error($this->CI->lang->line('insufficient_privs'));
			}
		}
		elseif($this->logged_in() == TRUE)
		{
			$level = $this->config['auth_groups'][$group];
			$user_level = $this->CI->session->userdata('group_id');
			
			if($user_level > $level OR $single == TRUE && $user_level !== $level)
			{
				show_error($this->CI->lang->line('insufficient_privs'));
			}
			
			return TRUE;
		}
		else
		{
			redirect($this->config['auth_incorrect_login'], 'refresh');
		}
	} // function restrict()
	
	
	/** 
	* Log a user in
	*
	* Log a user in a redirect them to a page specified in the $redirect variable
	*
	* @access public
	* @param string
	*/
	function login($redirect = NULL)
	{
		if($redirect === NULL)
		{
			$redirect = $this->config['auth_login'];
		}
/*
			
		$this->CI->form_validation->set_rules('username', 'Username', 'trim|required|min_length[4]|max_length[40]|callback_username_check');
		$this->CI->form_validation->set_rules('password', 'Password', 'trim|required|min_length[4]|max_length[12]');
		$this->CI->form_validation->set_rules('remember', 'Remember Me');

		if($this->CI->form_validation->run() == FALSE)
		{
			if((array_key_exists('login_attempts', $_COOKIE)) && ($_COOKIE['login_attempts'] >= 5))
			{
				echo $this->CI->lang->line('max_login_attempts_error');
			}
			else
			{
				$this->view('login');
			}
		}
		else
		{
		*/
			$username = set_value('username');
			$auth_type = $this->_auth_type($username);
			$password = set_value('password');
			$email = set_value('email');

			if(!$this->_verify_details($auth_type, $username, $password))
			{
				show_error($this->CI->lang->line('login_details_error'));
				$redirect = $this->config['auth_incorrect_login'];
				redirect($redirect);
			}

			// Get Userdata from database... can be abstracted to include other CMS login systems
			if($config['source']!="drupal")
			{
				$userdata = $this->CI->db->query("SELECT * FROM `$this->user_table` WHERE `$auth_type` = '$username'");
				$row = $userdata->row_array();
				
				$data = array(
							$auth_type => $username,
							'username' => $row['username'],
							'user_id' => $row['id'],
							'group_id' => $row['group_id'],
							'logged_in' => TRUE
							);
			}
			else
			{
				$prefix = $config['source_prefix'];
				$userdata = $this->CI->db->query("SELECT u.*, r.rid FROM ".$prefix."users AS u INNER JOIN ".$prefix."users_roles AS r WHERE u.uid=r.uid LIMIT 1");
				$row = $userdata->row_array();
				
				$data = array(
							'username' => $row['name'],
							'email' => $row['mail'],
							'user_id' => $row['uid'],
							'group_id' => $row['rid'],
							'logged_in' => TRUE
							);
			}
			$this->CI->session->set_userdata($data);

			if($this->config['auth_remember'] === TRUE)
			{
				$this->_generate();
			}

			redirect($redirect);
		//}
	} // function login()
	
	
	/** 
	* Logout - logs a user out
	*
	* @access public
	*/
	function logout()
	{
		$this->CI->session->sess_destroy();
		$this->view('logout');
	} // function logout()
	
	
	/** 
	* Check to see if a user is logged in
	*
	* Look in the session and return the 'logged_in' part
	*
	* @access public
	* @param string
	*/
	function logged_in()
	{
		if($this->CI->session->userdata('logged_in') == TRUE)
		{
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	} // function logged_in()


	/** 
	* Check to see if a user is logging in with their username or their email
	*
	* @access private
	* @param string
	*/
	function _auth_type($str)
	{
		if(valid_email($str))
		{
			return 'email';
		}
		else
		{
			return 'username';
		}
	} // function _auth_type()
	
	
	/** 
	* Salt the users password
	*
	* @access private
	* @param string
	*/
	function _salt($str)
	{
		return sha1($this->CI->config->item('encryption_key').$str);
	} // function _salt()
	
	
	/** 
	* Verify that their username/email and password is correct
	*
	* @access private
	* @param string
	*/
	function _verify_details($auth_type, $username, $password)
	{
		if($this->config['source']!="drupal")
		{
			$password = $this->salt($password);
			$query = $this->CI->db->query("SELECT * FROM `$this->user_table` WHERE `$auth_type` = '$username' AND `password` = '$password'");
		}
		else
		{
			echo $auth_type;
			if($auth_type=="email") $auth_type="mail";
			elseif($auth_type=="username") $auth_type="name";
			$str = "SELECT * FROM ".$this->config['source_prefix']."users WHERE `$auth_type` = '$username' AND `pass` = '".md5($password)."'";
			$query = $this->CI->db->query($str);
			echo $str;
		}
		
		if($query->num_rows != 1)
		{
			$attempts = $_COOKIE['login_attempts'] + 1;
			setcookie("login_attempts", $attempts, time()+900, '/');
			return FALSE;
		}
		
		return TRUE;
	} // function _verify_details()
	
	
	/** 
	  * Generate a new token/identifier from random.org
	  *
	  * @access private
	  * @param string
	  */
	  function _generate()
	  {
	    $username = $this->CI->session->userdata('username');

	    $length = 20;
	        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
	        $token = '';    
	        for ($i = 0; $i < $length; $i++) {
	            $token .= $characters[mt_rand(0, strlen($characters)-1)];
	        }
	    //$token = "12345678901234567890";

	    $identifier = $username . $token;
	    $identifier = $this->_salt($identifier);

	    $this->CI->db->query("UPDATE `$this->user_table` SET `identifier` = '$identifier', `token` = '$token' WHERE `username` = '$username'");

	    setcookie("logged_in", $identifier, time()+3600, '/');
	  }
	
	
	/** 
	* Verify that a user has a cookie, if not generate one. If the cookie doesn't match the database, log the user out and show them an error.
	*
	* @access private
	* @param string
	*/
	function _verify_cookie()
	{
		if((array_key_exists('login_attempts', $_COOKIE)) && ($_COOKIE['login_attempts'] >= 5))
		{
			$username = $this->CI->session->userdata('username');
			$userdata = $this->CI->db->query("SELECT * FROM `$this->user_table` WHERE `username` = '$username'");
			
			$result = $userdata->row();

			$identifier = $result->username . $result->token;
			$identifier = $this->_salt($identifier);
			
			if($identifier !== $_COOKIE['logged_in'])
			{
				$this->CI->session->sess_destroy();
				
				show_error($this->CI->lang->line('logout_perms_error'));
			}
		}
		else
		{
			$this->_generate();
		}
	}
	
	/** 
	* Load an auth specific view
	*
	* @access private
	* @param string
	*/
	function view($page, $params = NULL)
	{
		if($params !== NULL)
		{
			$data['data'] = $params;
		}
		
		$data['page'] = $page;
		switch ( $page )
		{
			case "login":
				$data['wrapper'] = 'ultra_narrow';
				$data['title'] = 'Login | WYBC';
				break;
			case "logout":
				$data['wrapper'] = 'ultra_narrow';
				$data['title'] = "You Are Now Logged Out | WYBC";
				break;
			case "register":
				$data['wrapper'] = 'ultra_narrow';
				$data['title'] = "You Are Now Logged Out | WYBC";
				break;
			default:
				$data['wrapper'] = 'narrow';
				break;
		}
		$this->CI->load->view($this->config['auth_views_root'].'index', $data);
	}
} // class Auth

?>