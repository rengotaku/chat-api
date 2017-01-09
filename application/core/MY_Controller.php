<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class MY_Controller extends CI_Controller 
{

    function __construct()
    {
        parent::__construct();

		$this->config->load('my_config');
	}
	
	/**
	 * トークンが存在するか確認する。
	 * 正しいトークンがない場合は、falseを返却する。
	 */
	protected function _exist_token() {
		$this->config->load('my_config');

		$token = $this->input->get_request_header('X-ChatToken');

		// トークンが含まれていない場合
		if ($this->config->item('chat_token') != $token) {
			$data = array (
				'error' => 'you have to set X-ChatToken to http header.'
			);
			$this->output->set_content_type('application/json')->set_output(json_encode($data));
			return false;
		}

		// トークンが違う場合		
		if ($this->config->item('chat_token') != $token) {
			$data = array (
				'error' => 'you have not authority.'
			);
			$this->output->set_content_type('application/json')->set_output(json_encode($data));
			return false;
		}
		
		return true;
	}
}