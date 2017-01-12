<?php
defined('BASEPATH') OR exit ('No direct script access allowed');

class Rooms_controller extends MY_Controller {

    public function __construct()
    {
        parent::__construct();
		
		$this->lang->load('form_validation');
		$this->load->library(array('form_validation', 'encrypt', 'classLoad'));
		$this->load->helper('hash');
		$this->load->model(array('user', 'stream_message', 'user_message', 'info_message', 'room', 'read'));
    }	

	/**
	 * チャットの名前を取得
	 * GET
	 */
	public function select_room($room_hash) {
		// ルームＩＤをデコードする
		$room_data = room_hash_decode($room_hash);
		$room_id = $room_data['room_id'];
		
		$this->load->database();

		if (!$this->room->exit_room($room_id)) {
			$this->output->set_json_error_output(array('room_hash' => $this->lang->line('exist_room'))); return;
		}

		$row = $this->room->select_room($room_id);

		$data = array ();
		$data['name'] = $row->name;
		$data['description'] = $row->description;
		$data['last_message_id'] = $this->stream_message->max_message_id($room_id);

		$this->output->set_json_output($data);
	}

	/**
	 * チャットのメンバー一覧を取得(トークンがあるなしで返却値が変わります)
	 * GET
	 */
	public function select_users($room_hash) {
		// ルームＩＤをデコードする
		$room_data = room_hash_decode($room_hash);
		$room_id = $room_data['room_id'];

		if (!$this->room->exit_room($room_id)) {
			$this->output->set_json_error_output(array('room_hash' => $this->lang->line('exist_room'))); return;
		}

		$sql_result = $this->db->from('users')->where(array ('room_id' => $room_id))->get()->result();

		$data = array ();
		foreach ($sql_result as $row) {
			$temp_row = array ();
			$temp_row['name'] = $row->name;

			$data[] = $temp_row;
		}

		//$dataをJSONにして返す
		$this->output->set_json_output($data);
	}

	/**
	 * チャットのメンバー情報を取得
	 * GET
	 */
	public function select_user($room_hash) {
		// ルームＩＤをデコードする
		$room_data = room_hash_decode($room_hash);

		$room_id = $room_data['room_id'];
		$user_id = $room_data['user_id'];

		if(!$this->user->exist_user($room_id, $user_id)){
			$this->output->set_json_error_output(array('room_hash' => $this->lang->line('exist_user'))); return;
		}

		$row = $this->user->find($user_id);

		$data = array ();
		$data['name'] = $row->name;
		$data['sex'] = $row->sex;
		$data['icon'] = $row->icon_id;
		$data['message_count'] = $this->stream_message->message_count($room_id, $user_id);
		$data['begin_message_id'] = $row->begin_message_id;
		$data['last_create_time'] = $row->created_at;

		//$dataをJSONにして返す
		$this->output->set_json_output($data);
	}

	/**
	 * チャットのメッセージ一覧を取得。前回取得分からの差分を返します。(SSE対応)
	 * 送信は直打ちとしている。Codeigniterの方法だと連続で送信できなかったりするため。
	 * GET
	 */
	public function select_messages_sse($room_hash) {
		ini_set("max_execution_time", $this->config->item('sse_succession_time'));

		header("Content-Type: text/event-stream; charset=UTF-8");
		header('Cache-Control: no-cache');
		header("Connection: keep-alive");

		// 接続状態にするため、ダミー値を返却する。
		ob_flush();
		flush();

		// ルームＩＤをデコードする
		$room_data = room_hash_decode($room_hash);
		$room_id = $room_data['room_id'];
		$user_id = $room_data['user_id'];

		if(!$this->user->exist_user($room_id, $user_id)){
			$this->output->set_json_error_output(array('room_hash' => $this->lang->line('exist_user'))); return;
		}

		// 処理を終えないように待機させる。
		while(true){
			$col = $this->stream_message->unread_messages($room_id, $user_id);

			if (count ($col) == 0) {
				sleep($this->config->item('sse_sleep_time'));
				continue;
			}

			// デバッグ用
			//$this->output->set_json_error_output(array($this->db->last_query())); return;

			$data = array ();
			$last_message_id = null;
			foreach ($col as $row) {
				$temp_row = array ();
				$temp_row['message_id'] = $row->message_id;
				$temp_user_info = array ();
				$temp_user_info['name'] = $row->name;
				$temp_user_info['who'] = $row->user_id == $user_id ? UserWho::SELF_USER : UserWho::OTHER_USER;
				$temp_user_info['icon'] = $row->icon_id;
				$temp_user_info['sex'] = $row->sex;
				$temp_user_info['hash'] = $row->user_hash;
				$temp_row['user'] = $temp_user_info;
				$temp_row['body'] = (string)$row->body;
				$temp_row['type'] = $row->type;
				$temp_row['send_time'] = $row->created_at;

				$data[] = $temp_row;
				$last_message_id = $row->message_id;
			}

			if (empty ($last_message_id)) {
				$this->output->set_json_output(array ()); return;
			}

			// 取得した最後のメッセージを既読済にする
			$this->db->trans_start();

			$this->read->insert(array (
				'message_id' => $last_message_id,
				'user_id' => $user_id,
				'room_id' => $room_id
			));

			$this->db->trans_complete();

			$this->output->set_sse_output('messages', $data);

			ob_flush();
			flush();
			sleep($this->config->item('sse_sleep_time'));
		}
	}

	/**
	 * チャットのメッセージ一覧を取得。前回取得分からの差分を返します。
	 * GET
	 */
	public function select_messages($room_hash) {
		// ルームＩＤをデコードする
		$room_data = room_hash_decode($room_hash);
		$room_id = $room_data['room_id'];
		$user_id = $room_data['user_id'];

		if(!$this->user->exist_user($room_id, $user_id)){
			$this->output->set_json_error_output(array('room_hash' => $this->lang->line('exist_user'))); return;
		}

		$col = $this->stream_message->unread_messages($room_id, $user_id);

		// デバッグ用
		$this->output->set_json_error_output(array($this->db->last_query())); return;

		$data = array ();
		$last_message_id = null;
		foreach ($col as $row) {
			$temp_row = array ();
			$temp_row['message_id'] = $row->message_id;
			$temp_user_info = array ();
			$temp_user_info['name'] = $row->name;
			$temp_user_info['who'] = $row->user_id == $user_id ? UserWho::SELF_USER : UserWho::OTHER_USER;
			$temp_user_info['icon'] = $row->icon_id;
			$temp_user_info['sex'] = $row->sex;
			$temp_user_info['hash'] = $row->user_hash;
			$temp_row['user'] = $temp_user_info;
			$temp_row['body'] = (string)$row->body;
			$temp_row['type'] = $row->type;
			$temp_row['send_time'] = $row->created_at;

			$data[] = $temp_row;
			$last_message_id = $row->message_id;
		}

		if (empty ($last_message_id)) {
			$this->output->set_json_output(array ()); return;
		}

		// 取得した最後のメッセージを既読済にする
		$this->db->trans_start();

		$this->read->insert(array (
			'message_id' => $last_message_id,
			'user_id' => $user_id,
			'room_id' => $room_id
		));

		$this->db->trans_complete();

		$this->output->set_json_output($data);
	}

	/**
	 * チャットの指定のメッセージ一を取得。
	 * GET
	 */
	public function select_message($room_hash, $message_id) {
		// ルームＩＤをデコードする
		$room_data = room_hash_decode($room_hash);
		$room_id = $room_data['room_id'];
		$user_id = $room_data['user_id'];

		if(!$this->user->exist_user($room_id, $user_id)){
			$this->output->set_json_error_output(array('room_hash' => $this->lang->line('exist_user'))); return;
		}

		$row = $this->stream_message->specific_message($room_id, $message_id);

		// デバッグ用
		//$this->output->set_json_error_output(array($this->db->last_query())); return;

		$data = array ();
		if (!empty($row)) {
			$data['message_id'] = $row->message_id;
			$temp_user_info = array ();
			$temp_user_info['name'] = $row->name;
			$temp_user_info['who'] = $row->user_id == $user_id ? UserWho::SELF_USER : UserWho::OTHER_USER;
			$temp_user_info['icon'] = $row->icon_id;
			$temp_user_info['sex'] = $row->sex;
			$temp_user_info['hash'] = $row->user_hash;
			$data['user'] = $temp_user_info;
			$data['body'] = (string)$row->body;
			$data['type'] = $row->type;
			$data['send_time'] = $row->created_at;
		}

		$this->db->trans_complete();

		$this->output->set_json_output($data);
	}

	/**
	 * 指定メッセージより過去のメッセージを取得する。
	 * 取得数は設定値に依存する。
	 */
	public function select_messages_past($room_hash, $message_id){
		// ルームＩＤをデコードする
		$room_data = room_hash_decode($room_hash);
		$room_id = $room_data['room_id'];
		$user_id = $room_data['user_id'];

		if(!$this->user->exist_user($room_id, $user_id)){
			$this->output->set_json_error_output(array('room_hash' => $this->lang->line('exist_user'))); return;
		}

		$col = $this->stream_message->past_messages($room_id, $user_id, $message_id);

		// デバッグ用
		// $this->output->set_json_error_output(array($this->db->last_query())); return;

		$data = array ();
		$last_message_id = null;
		foreach ($col as $row) {
			$temp_row = array ();
			$temp_row['message_id'] = $row->message_id;
			$temp_user_info = array ();
			$temp_user_info['name'] = $row->name;
			$temp_user_info['who'] = $row->user_id == $user_id ? UserWho::SELF_USER : UserWho::OTHER_USER;
			$temp_user_info['icon'] = $row->icon_id;
			$temp_user_info['sex'] = $row->sex;
			$temp_user_info['hash'] = $row->user_hash;
			$temp_row['user'] = $temp_user_info;
			$temp_row['body'] = (string)$row->body;
			$temp_row['type'] = $row->type;
			$temp_row['send_time'] = $row->created_at;

			$data[] = $temp_row;
			$last_message_id = $row->message_id;
		}

		if (empty ($last_message_id)) {
			$this->output->set_json_output(array ()); return;
		}

		$this->output->set_json_output($data);
	}

	/**
	 * チャットに新しいメッセージを追加。
	 * POST
	 */
	public function create_message($room_hash) {
		if (!$this->form_validation->run('create_message')) {
			$this->output->set_json_error_output($this->form_validation->error_array()); return;
		}

		$body = $this->input->post('body');

		// ルームＩＤをデコードする
		$room_data = room_hash_decode($room_hash);
		$room_id = $room_data['room_id'];
		$user_id = $room_data['user_id'];

		if(!$this->user->exist_user($room_id, $user_id)){
			$this->output->set_json_error_output(array('room_hash' => $this->lang->line('exist_user'))); return;
		}

		$message_id = $this->stream_message->insert_user_message($room_id, $user_id, $body);

		$row = $this->stream_message->specific_message($room_id, $message_id);

		// デバッグ用
		//$this->output->set_json_error_output(array($this->db->last_query())); return;

		$data = array ();
		if (!empty($row)) {
			$data['message_id'] = $row->message_id;
			$temp_user_info = array ();
			$temp_user_info['name'] = $row->name;
			$temp_user_info['who'] = $row->user_id == $user_id ? UserWho::SELF_USER : UserWho::OTHER_USER;
			$temp_user_info['icon'] = $row->icon_id;
			$temp_user_info['sex'] = $row->sex;
			$temp_user_info['hash'] = $row->user_hash;
			$data['user'] = $temp_user_info;
			$data['body'] = (string)$row->body;
			$data['type'] = $row->type;
			$data['send_time'] = $row->created_at;
		}

		$this->output->set_json_output($data);
	}

	/**
	 * FIXME モデル系のリファクタリングを行う。
	 * チャットにユーザを追加。
	 * POST
	 */
	public function create_user($room_hash) {
		if (!$this->form_validation->run('create_user')) {
			$this->output->set_json_error_output($this->form_validation->error_array()); return;
		}

		// ルームＩＤをデコードする
		$room_data = room_hash_decode($room_hash);
		$room_id = $room_data['room_id'];

		if (empty($room_id)) {
			$this->output->set_json_error_output(array('room_hash' => $this->lang->line('exist_room'))); return;
		}

		$role = (string)$room_data['role'];

		if($role === UserRole::ADMIN) { // 管理人ハッシュで生成しようとした場合
			$this->output->set_json_error_output(array('room_hash' => $this->lang->line('is_admin'))); return;
		} else if(!in_array($role, array(UserRole::SPECIFIC_USER, UserRole::ANONYMOUS_USER)) || $room_data['user_id'] !== '0') { // 特定ユーザ、アノニマスユーザ以外が指定、ユーザＩＤが既に指定されている
			$this->output->set_json_error_output(array('room_hash' => $this->lang->line('wrong_hash'))); return;
		}

		if($this->user->duplicate_user($room_id, $this->input->post('fingerprint'))){
			$this->output->set_json_error_output(array('fingerprint' => $this->lang->line('exist_user_already'))); return;
		}

		if($role === '2'){ // 特定ユーザ
			$this->output->set_json_output($this->create_specific_user($room_id, $this->input->post('name'))); return;
		} else { // アノニマスユーザ
			$this->output->set_json_output($this->create_anonymous_user($room_id, $this->input->post('name'))); return;
		}
	}

	/**
	 * 性別のバリデーション
	 */
	public function _validate_sex($value){
		if($value === SEX::MAN || $value === SEX::WOMAN || $value === SEX::NONE){
			return TRUE;
		}
        $this->form_validation->set_message('_validate_sex', $this->lang->line('_validate_sex'));
		return FALSE;
	}

	/**
	 * チャットに特定ユーザを追加。
	 * POST
	 */
	private function create_specific_user($room_id, $name) {
		$icon_id = $this->input->post('icon');
		if(empty($icon_id)){
			// ユーザのアイコンＩＤを設定します。（アイコンＩＤを増やしたらコンフィグの値を変更する。）
			$icon_id = rand(1, $this->config->item('icon_num'));
		}

		$this->db->trans_start();

		// 特定ユーザを生成する。
		$user_id = $this->user->insert_user($name, $room_id, new UserRole(UserRole::SPECIFIC_USER), $this->input->post('fingerprint'), new SEX($this->input->post('sex')), $icon_id);

		$this->db->trans_complete();

		$data = array (
			'room_hash' => room_hash_encode($room_id, new UserRole(UserRole::SPECIFIC_USER), $user_id), // ユーザ専用のハッシュ値を生成する
			'user_hash' => $this->user->find($user_id)->user_hash
		);

		return $data;
	}

	/**
	 * チャットにユーザを追加。
	 * POST
	 */
	private function anonymous_user($room_id, $name) {
		// ユーザのアイコンＩＤを設定します。（アノニマスアイコン）
		$icon_id = 999;

		$this->db->trans_start();

		// アノニマスユーザを生成する。
		$user_id = $this->user->insert_user($name, $room_id, new UserRole(UserRole::ANONYMOUS_USER), $this->input->post('fingerprint'), new SEX($this->input->post('sex')),$icon_id);

		$this->db->trans_complete();

		$data = array (
			'room_hash' => room_hash_encode($room_id, new UserRole(UserRole::ANONYMOUS_USER), $user_id), // ユーザ専用のハッシュ値を生成する
			'user_hash' => $this->user->find($user_id)->user_hash
		);

		return $data;
	}
}