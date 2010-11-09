<?php

/**
 * 	Class definition for albums
 * 	@author Brandon Jackson
 */

class Album extends MY_Model {
	
	/**
	*	Constructor. 
	*	$data can either be a number or array.
	*	
	*	@param mixed $data
	*/
	function __construct( $data = NULL ) 
	{
		parent::__construct( $data );
	}	
}