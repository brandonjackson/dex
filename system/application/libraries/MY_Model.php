<?php

/**
 * A base model to provide the basic CRUD 
 * actions for all models that inherit from it.
 *
 * @package CodeIgniter
 * @subpackage MY_Model
 * @license GPLv3 <http://www.gnu.org/licenses/gpl-3.0.txt>
 * @link http://github.com/philsturgeon/codeigniter-base-model
 * @version 1.3
 * @author Jamie Rumbelow <http://jamierumbelow.net>
 * @modified Phil Sturgeon <http://philsturgeon.co.uk>
 * @modified Dan Horrigan <http://dhorrigan.com>
 * @modified Brandon Jackson <http://brandonsdesign.com>
 * @copyright Copyright (c) 2009, Jamie Rumbelow <http://jamierumbelow.net>
 */
class MY_Model extends Model
{
	/**
	 * The database table to use, only
	 * set if you want to bypass the magic
	 *
	 * @var string
	 */
	protected $_table;

	/**
	 * only useful for overriding if the classname is different from the database tablename
	 *
	 */
	 
	protected $_class_name = null;
		
	/**
	 * The primary key, by default set to
	 * `id`, for use in some functions.
	 *
	 * @var string
	 */
	protected $primary_key = 'id';
	
	/**
	 * An array of functions to be called before
	 * a record is created.
	 *
	 * @var array
	 */
	protected $before_create = array();
	
	/**
	 * An array of functions to be called after
	 * a record is created.
	 *
	 * @var array
	 */
	protected $after_create = array();

	/**
	 * An array of validation rules
	 *
	 * @var array
	 */
	protected $validate = array();

	/**
	 * Skip the validation
	 *
	 * @var bool
	 */
	protected $skip_validation = FALSE;

	/**
	* Stores object properties or an array of arrays
	* @var array
	*/
	public $data = array();

	/**
	*	Array of methods to call when instantiating related
	*	objects
	*	@var array
	*/
	public $batch_callback;

	/**
	* 	DB object, so that caching only occurs locally, i.e. I can run queries 
	*	on this model mid-query on other models. Seems way better to me.
	*/
	public $MY_db; 

	public $CI;
	/**
	* Wrapper to __construct for when loading
	* class is a superclass to a regular controller,
	* i.e. - extends Base not extends Controller.
	* 
	* @return void
	* @author Jamie Rumbelow
	*/
	public function MY_Model() { $this->__construct(); }

	/**
	 * The class constructer, tries to guess
	 * the table name.
	 *
	 * @author Jamie Rumbelow
	 */
	public function __construct( $data = NULL )
	{
		parent::Model();
		$this->load->helper('inflector');


		$this->MY_db = $this->load->database('', true);

//		$this->MY_db = new CI_DB('', true);
		$this->_fetch_table();
		$this->CI =& get_instance();
		if ($data && is_array($data)) 
		{
			$this->_set_properties($data);
			$this->data = $data;
		}
		
		else if (is_numeric($data) && $data) //interesting-- seems "get() is trouble" and will select the first entry if you send in "0". should inspect what it returns if you send in a different non-existent value!!! TO DO!
		{
			$this->get($data);
		}
	}
	
	/**
	 * Get a single record by creating a WHERE clause with
	 * a value for your primary key
	 *
	 * @param string $primary_value The value of your primary key
	 * @return object
	 * @author Phil Sturgeon
	 * @modified Brandon Jackson
	 */
	public function get($primary_value = NULL) 
	{
		if( !empty($primary_value) && is_numeric($primary_value))
			$this->MY_db->where($this->primary_key, $primary_value);
		$this->data =  $this->MY_db	->get($this->_table)
									->row_array();
		$this->_set_properties($this->data);
		return $this;
	}

	public function get_array($primary_value = NULL) 
	{
		if( !empty($primary_value) && is_numeric($primary_value))
			$this->MY_db->where($this->primary_key, $primary_value);
		$this->data =  $this->MY_db	->get($this->_table)
									->row_array();
		return $this->data;
	}
	
	
	
	/**
	 * Get a single record by creating a WHERE clause with
	 * the key of $key and the value of $val.
	 *
	 * @param string $key The key to search by 
	 * @param string $val The value of that key
	 * @return object
	 * @author Phil Sturgeon
	 */
	public function get_by() 
	{
		$where =& func_get_args();
		$this->_set_where($where);
		
		$this->data = $this->MY_db->get($this->_table)
			->row_array();
		$this->_set_properties($this->data);
//		if ($is_array)
//			return $this->data;
		return $this;
	}
	
	/**
	 * Similar to get_by(), but returns a result array of
	 * many result objects.
	 *
	 * @param string $key The key to search by
	 * @param string $val The value of that key
	 * @return array
	 * @author Phil Sturgeon
	 */
	public function get_many_by()
	{
		$where =& func_get_args();
		$this->_set_where($where);
		
		return $this->get_all();
	}
	

	public function get_class_name() {
		if( function_exists("get_called_class") ) {
			$class = get_called_class();
		} else if ($this->_class_name) {
			$class = $this->_class_name;
		} else {
			$class = singular($this->_table);
		}
		return $class;	
	}

	/**
	 * Get all records in the database
	 *
	 * @return array
	 * @author Jamie Rumbelow
	 */
	public function get_all($as_array = false, $key = false)
	{
		//just in case the only argument passed is "key"
		if (is_string($as_array)) {
			$key = $as_array;
			$as_array = false;
		}
	
		$this->data = $this->MY_db->get($this->_table)
								->result_array();
		
		if ($as_array)
			return $this->data;
		
		$batch = array();
		if( function_exists("get_called_class") ) {
			$class = get_called_class();
		} else if ($this->_class_name) {
			$class = $this->_class_name;
		} else {
			$class = singular($this->_table);
		}
		
		foreach($this->data as $val)
		{
			$New = new $class($val);
			
			// batch callback allows us to run a function on an object after it 
			// has been created
			if(!empty($this->batch_callback))
			{
				foreach( $this->batch_callback as $callback )
				{
					if( method_exists($class, $callback) )
					{
						$New->{$callback}();
					}
				}
			}
			
			if ($key) {
				$batch[$New->data[$key]] = $New;
			} else {
				$batch[] = $New;			
			}
		}
		$this->all = $batch;
		return $this->all;
	}
	
	/**
	 * Similar to get_by(), but returns a result array of
	 * many result objects.
	 *
	 * @param string $key The key to search by
	 * @param string $val The value of that key
	 * @return array
	 * @author Phil Sturgeon
	 */
	public function count_by()
	{
		$where =& func_get_args();
		$this->_set_where($where);
		
		return $this->MY_db->count_all_results($this->_table);
	}
	
	/**
	*	Should return number of matching results in this object. Maybe not.
	*	@author Brandon Jackson
	*/
	public function count()
	{
		return count($this->data);
	}
	
	/**
	 * Get all records in the database
	 *
	 * @return array
	 * @author Phil Sturgeon
	 */
	public function count_all()
	{
		return $this->MY_db->count_all($this->_table);
	}
	
	public function count_all_results()
	{
		return $this->MY_db->count_all_results( $this->_table );
	}
	
	public function exists()
	{
		if( $this->count() == 0 ) return false;
		return true;
	}
	
	/**
	* 	Fetch a specific field / property
	*
	* 	@param string $field
	* 	@return mixed
	*	@author Brandon Jackson
	*/
	public function fetch( $field )
	{
		if (isset($this->data[ $field ] ) )
		{
			return $this->data[ $field ];
		}
		
		return NULL;
	}

	/**
	 * Insert a new record into the database,
	 * calling the before and after create callbacks.
	 * Returns the object.
	 *
	 * @param array $data Information
	 * @return self
	 * @author Jamie Rumbelow
	 * @modified Dan Horrigan
	 */
	public function create($data, $skip_validation = FALSE)
	{
		$valid = TRUE;
		if($skip_validation === FALSE)
		{
			$valid = $this->_run_validation($data);
		}

		if($valid)
		{
			$data = $this->_run_before_create($data);
				$this->MY_db->insert($this->_table, $data);
			$this->_run_after_create($data, $this->MY_db->insert_id());
			$this->skip_validation = FALSE;

			$this->data = $data;
			$this->data['id'] = $this->MY_db->insert_id();
			return $this;
		}
		else
		{
			return FALSE;
		}
	}
	
	
	/**
	 * Insert a new record into the database,
	 * calling the before and after create callbacks.
	 * Returns the insert ID.
	 *
	 * @param array $data Information
	 * @return integer
	 * @author Jamie Rumbelow
	 * @modified Dan Horrigan
	 */
	public function insert($data, $skip_validation = FALSE)
	{
		$valid = TRUE;
		if($skip_validation === FALSE)
		{
			$valid = $this->_run_validation($data);
		}

		if($valid)
		{
			$data = $this->_run_before_create($data);
				$this->MY_db->insert($this->_table, $data);
			$this->_run_after_create($data, $this->MY_db->insert_id());

			$this->skip_validation = FALSE;
			return $this->MY_db->insert_id();
		}
		else
		{
			return FALSE;
		}
	}
	
	/**
	 * Similar to insert(), just passing an array to insert
	 * multiple rows at once. Returns an array of insert IDs.
	 *
	 * @param array $data Array of arrays to insert
	 * @return array
	 * @author Jamie Rumbelow
	 */
	public function insert_many($data, $skip_validation = FALSE)
	{
		$ids = array();

		foreach ($data as $row)
		{
			$valid = TRUE;
			if($skip_validation === FALSE)
			{
				$valid = $this->_run_validation($data);
			}

			if($valid)
			{
				$data = $this->_run_before_create($row);
					$this->MY_db->insert($this->_table, $row);
				$this->_run_after_create($row, $this->MY_db->insert_id());

				$ids[] = $this->MY_db->insert_id();
			}
			else
			{
				$ids[] = FALSE;
			}
		}

		$this->skip_validation = FALSE;
		return $ids;
	}
	
	/**
	 * Update a record, specified by an ID.
	 *
	 * @param integer $id The row's ID
	 * @param array $array The data to update
	 * @return bool
	 * @author Jamie Rumbelow
	 */
	public function update($primary_value, $data, $skip_validation = FALSE)
	{
		$valid = TRUE;
		if($skip_validation === FALSE)
		{
			$valid = $this->_run_validation($data);
		}

		if($valid)
		{
			$this->skip_validation = FALSE;
			$this->data = array_merge($this->data, $data);
			//update: eli
			return $this->MY_db->where($this->primary_key, $primary_value)
				->set($data)
				->update($this->_table);
		}
		else
		{
			return FALSE;
		}
	}
	
	/**
	 * Update a record, specified by $key and $val.
	 *
	 * @param string $key The key to update with
	 * @param string $val The value
	 * @param array $array The data to update
	 * @return bool
	 * @author Jamie Rumbelow
	 */
	public function update_by()
	{
		$args =& func_get_args();
		$data = array_pop($args);
		$this->_set_where($args);
		
		if($this->_run_validation($data))
		{
			$this->skip_validation = FALSE;
			return $this->MY_db->set($data)
				->update($this->_table);
		}
		else
		{
			return FALSE;
		}
	}
	
	/**
	 * Updates many records, specified by an array
	 * of IDs.
	 *
	 * @param array $primary_values The array of IDs
	 * @param array $data The data to update
	 * @return bool
	 * @author Phil Sturgeon
	 */
	public function update_many($primary_values, $data, $skip_validation)
	{
		$valid = TRUE;
		if($skip_validation === FALSE)
		{
			$valid = $this->_run_validation($data);
		}
			
		if($valid)
		{
			$this->skip_validation = FALSE;
			return $this->MY_db->where_in($this->primary_key, $primary_values)
				->set($data)
				->update($this->_table);
	
		}
		else
		{
			return FALSE;
		}
	}
	
	/**
	 * Updates all records
	 *
	 * @param array $data The data to update
	 * @return bool
	 * @since 1.1.3
	 * @author Phil Sturgeon
	 */
	public function update_all($data)
	{
		return $this->MY_db->set($data)
			->update($this->_table);
	}
	
	/**
	 * Delete a row from the database table by the
	 * ID.
	 *
	 * @param integer $id 
	 * @return bool
	 * @author Jamie Rumbelow
	 */
	public function delete($id)
	{
		return $this->MY_db->where($this->primary_key, $id)
			->delete($this->_table);
	}
	
	/**
	 * Delete a row from the database table by the
	 * key and value.
	 *
	 * @param string $key
	 * @param string $value 
	 * @return bool
	 * @author Phil Sturgeon
	 */
	public function delete_by()
	{
		$where =& func_get_args();
		$this->_set_where($where);
		
		return $this->MY_db->delete($this->_table);
	}
	
	/**
	 * Delete many rows from the database table by 
	 * an array of IDs passed.
	 *
	 * @param array $primary_values 
	 * @return bool
	 * @author Phil Sturgeon
	 */
	public function delete_many($primary_values)
	{
		return $this->MY_db->where_in($this->primary_key, $primary_values)
			->delete($this->_table);
	}

	
	function dropdown()
	{
		$args =& func_get_args();
		
		if(count($args) == 2)
		{
			list($key, $value) = $args;
		}

		else
		{
			$key = $this->primary_key;
			$value = $args[0];
		}
		
		$query = $this->MY_db->select(array($key, $value))
			->get($this->_table);
		
		$options = array();
		foreach ($query->result() as $row)
		{
			$options[$row->{$key}] = $row->{$value};
		}

		return $options;
	}
	
	/**
	* Orders the result set by the criteria,
	* using the same format as CI's AR library.
	*
	* @param string $criteria The criteria to order by
	* @return void
	* @since 1.1.2
	* @author Jamie Rumbelow
	*/
	public function order_by($criteria, $order = NULL ) 
	{
		if(empty($order))
		{
			$this->MY_db->order_by($criteria);
		}
		else
		{
			$this->MY_db->order_by($criteria, $order);
		}
		return $this;
	}
	
	/**
	* Limits the result set by the integer passed.
	* Pass a second parameter to offset.
	*
	* @param integer $limit The number of rows
	* @param integer $offset The offset
	* @return void
	* @since 1.1.1
	* @author Jamie Rumbelow
	*/
	public function limit($limit, $offset = 0)
	{
		$this->MY_db->limit($limit, $offset);
		return $this;
	}
	
	public function offset( $offset = 0 )
	{
		if(!empty($offset)&&$offset>0)
			$this->MY_db->offset($offset);
		return $this;
	}

	public function where( $key, $value = NULL )
	{
		if( is_array($key) && count($key)> 0)
		{
			$this->MY_db->where($key);
		}
		elseif( !empty( $value ) )
		{
			$this->MY_db->where($key, $value);
		}
		else
		{
			$this->MY_db->where($key);
		}
		return $this;
	}
	/**
	 * Tells the class to skip the insert validation
	 *
	 * @return void
	 * @author Dan Horrigan
	 */
	public function skip_validation()
	{
		$this->skip_validation = TRUE;
		return $this;
	}
	
	/**
	*	Dumps the data, enclosed in < pre > tags
	*
	*/
	 public function dump( $do_die = true ) 
	 {
 		dump($this->data, $do_die);
 		return $this;
 	}
	
	/**
	*
	* Allow models to use other models
	*
	* This is a substitute for the inability to load models
	* inside of other models in CodeIgniter.  Call it like
	* this:
	*
	* $salaries = model_load_model('salary');
	* ...
	* $salary = $salaries->get_salary($employee_id);
	*
	* @param string $model_name The name of the model that is to be loaded
	*
	* @return object The requested model object
	*
	*/
	function load_model($model_name)
	{
		$CI =& get_instance();
		$CI->load->model($model_name);
		return $CI->$model_name;
	}

	/**
	*	Defines a method to run on newly instantiated related object arrays
	*	@param string $method
	*	@return boolean
	*/
	function add_batch_callback( $method )
	{
		$this->batch_callback[] = $method;
		return TRUE;
	}
	
	/**
	 * Runs the before create actions.
	 *
	 * @param array $data The array of actions
	 * @return void
	 * @author Jamie Rumbelow
	 */
	private function _run_before_create($data)
	{
		foreach ($this->before_create as $method)
		{
			$data = call_user_func_array(array($this, $method), array($data));
		}
		
		return $data;
	}
	
	/**
	 * Runs the after create actions.
	 *
	 * @param array $data The array of actions
	 * @return void
	 * @author Jamie Rumbelow
	 */
	private function _run_after_create($data, $id)
	{
		foreach ($this->after_create as $method)
		{
			call_user_func_array(array($this, $method), array($data, $id));
		}
	}

	/**
	 * Runs validation on the passed data.
	 *
	 * @return bool
	 * @author Dan Horrigan
	 */
	private function _run_validation($data)
	{
		if($this->skip_validation)
		{
			return TRUE;
		}
		if(!empty($this->validate))
		{
			foreach($data as $key => $val)
			{
				$_POST[$key] = $val;
			}
			$this->load->library('form_validation');
			if(is_array($this->validate))
			{
				$this->form_validation->set_rules($this->validate);
				return $this->form_validation->run();
			}
			else
			{
				$this->form_validation->run($this->validate);
			}
		}
		else
		{
			return TRUE;
		}
	}
	/**
	*	Returns NULL if inaccessible property is requested
	*
	*	@param string $name
	*	@return NULL
	*	@author Brandon Jackson
	*/
   	 public function __get($name)
   	 {
   	 	if (isset($this->data[$name])) {
   	 		return stripslashes($this->data[$name]);
		}
   	 	return NULL;
   	 }
   	 
	/**
	 * Fetches the table from the pluralised model name.
	 *
	 * @return void
	 * @author Jamie Rumbelow
	 */
	private function _fetch_table()
	{
		if ($this->_table == NULL)
		{
			$class = preg_replace('/(_m|_model)?$/', '', get_class($this));
			
			$this->_table = plural(strtolower($class));
		}
	}

	
	/**
	 * Sets where depending on the number of parameters
	 *
	 * @return void
	 * @author Phil Sturgeon
	 */
	private function _set_where($params)
	{
		if(count($params) == 1)
		{
			$this->MY_db->where($params[0]);
		}
		
		else
		{
			$this->MY_db->where($params[0], $params[1]);
		}
	}
	
	/**
	*	Creates dynamic properties for each field of the data array
	*	@param array $data
	*	@return boolean
	*	@author Brandon Jackson
	*/
	protected function _set_properties( $data )
	{
		foreach($data as $key=>$val)
		{
			$this->$key = stripslashes($val);
		}
		return true;
	}
	
	
	
	/**
	*
	*	This few functions simplify the process of performing a complex, paginated search. 
	*	This one starts a db query, and restricts to the franchise if passed franchise_id
	*
	* @return self
	* @author Eli Luberoff
	*/


	public function start_results_query($franchise_id = null) {
		$this->MY_db->flush_cache();
		$this->MY_db->start_cache();
		if ($franchise_id) {
			$this->where("franchise_id", $franchise_id);
		}
		return $this;	
	}

	/**
	*	This finishes off a complex, paginated search. 
	* 	assumes that "count_all_results()" has already been called
	*	limits by "results_per_page" and offsets appropriately
	* 	returns a paginated list
	*	stops cache
	*
	* @return array of $model objects
	* @author Eli Luberoff
	*/

	public function get_paginated_results($results_per_page = null, $page = null) {
		if (!$page) {
			$page = 1;
			if (isset($_GET['page']))
				$page = $_GET['page'];
		}

		$limit = ($results_per_page ? $results_per_page : 30);
		$this->limit($limit);
		$this->offset($limit * ($page - 1));
		$results = $this->get_all();

		$this->MY_db->stop_cache();
		$this->MY_db->flush_cache();

		return $results;
		
	}
	
	/**
	* 	referenced by models to limit their results. Takes $_GET['q'], splits at the spaces, and returns as an array
	*
	* @return array of strings (pieces of the query)
	* @author Eli Luberoff
	*/
	
	public function get_query_string() {
		if (!isset($_GET['q'])) {
			return $this;
		}
		$pre_screened_qs = explode(" ", $_GET['q']);
		$qs = array();
		foreach($pre_screened_qs as $q):
			$q = addslashes(trim($q));
			if ($q && strlen($q) >1 )
				//defined in each model
				$this->add_query_string($q);
		endforeach;
		return $this;
	}
	
	
	public function get_id_list($q = null, $conditions = null, $field_name = null) {
		
		$this->MY_db->flush_cache();
		
		if (!$field_name)
			$this->MY_db->select("id");
		else
			$this->MY_db->distinct()->select($field_name);
			
		if ($conditions)
			$this->where($conditions);
		if ($q)
			$this->add_query_string($q, true);
		
		$results = $this->get_all(true);
		return $results;
	}
	
	
}