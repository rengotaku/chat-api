<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| 管理者トークン
| -------------------------------------------------------------------------
| 管理者のみが操作できるAPIに必要なトークン
*/
$config['chat_token'] = '1234qwer';

/*
| -------------------------------------------------------------------------
| 再入場時に取得可能な最大件数
| -------------------------------------------------------------------------
| ユーザが再入場をした際に、メッセージを再取得する際のその上限値。
| あまり大きすぎない方がよい
*/
$config['reentry_max_count'] = 100;

/*
|--------------------------------------------------------------------------
| ルームのハッシュ値を生成する際のキー値
|--------------------------------------------------------------------------
| ルームのハッシュ値を生成する際に用いる。32文字を設定する。
|
| $config['encryption_key'] = '318595d2b7102232fb3d2dc7aa94889b';
| 上記を使用する場合は注意すること。取得しようとした際にさらに暗号化されている模様。
*/
$config['room_encryption_key'] = '905e3ada3e70936a5cf80c0ae3b7067e';

/*
| -------------------------------------------------------------------------
| アイコンの数
| -------------------------------------------------------------------------
| ユーザにランダムで割り振るアイコンの最大数を設定する。
| 最初は「1」から始まることを念頭に置く。
| ※「0」は管理者のアイコンにする。アイコンが4つ(管理者含む)ある場合は「3」を設定する。
*/
$config['icon_num'] = 3;

/*
| -------------------------------------------------------------------------
| 管理者名
| -------------------------------------------------------------------------
| ユーザにランダムで割り振るアイコンの最大数を設定する。
| 最初は「1」から始まることを念頭に置く。
| ※「0」は管理者のアイコンにする。アイコンが4つ(管理者含む)ある場合は「3」を設定する。
*/
$config['admin_name'] = '管理者';
