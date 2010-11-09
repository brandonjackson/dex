<?php

class Root extends Controller {

	var $data;

	function __construct()
	{
		parent::__construct();
		$this->load->helper('url');
	}
	
	function index()
	{
		$this->load->library('simplepie');
		$link = 'http://dex.wybc.com/blog/feed';
		$this->data['feed'] = new SimplePie();
		$this->data['feed']->set_feed_url($link);
		$this->data['feed']->enable_cache(false);
		$this->data['feed']->init();
		$this->data['feed']->handle_content_type();
		$this->data['title'] = 'WYBC';
		$this->data['heading'] = 'Welcome to WYBC';
		$this->data['title']="WYBC DEX";
		$this->load->view('header', $this->data);
		$this->load->view('index', $this->data);
		$this->load->view('footer', $this->data);
	}
}