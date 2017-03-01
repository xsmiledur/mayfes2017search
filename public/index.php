<?php

// コンポーネントをロードする
require_once 'Zend/Controller/Front.php';
require_once 'Zend/Controller/Router/Rewrite.php';
require_once 'Zend/Controller/Router/Route.php';
require_once 'Zend/Layout.php';
require_once 'Zend/Registry.php';
require_once 'Zend/Config/Ini.php';
require_once 'Zend/Session.php';


// セッションをスタートする
Zend_Session::start();

// 設定情報をロードする
$config = new Zend_Config_Ini('../application/modules/default/lib/config.ini', null);

// 設定情報をレジストリに登録する
Zend_Registry::set('config', $config);

// データベース関連の設定をレジストリに登録する
Zend_Registry::set('database', $config->datasource->database->toArray());

// デフォルトタイムゾーンの設定
date_default_timezone_set('Asia/Tokyo');

// フロントコントローラのインスタンスを取得する
$front = Zend_Controller_Front::getInstance();

// メインシステムのディレクトリを設定する
$front->setControllerDirectory(array(
    'default'	=> '../application/modules/default/controllers'
));

//$front->setParam('prefixDefaultModule',true);

// ルーターインスタンスの作成
//$router = $front->getRouter();



// ディスパッチする
//$front->dispatch();

$front->throwExceptions(true);
try {
    $front->dispatch();
} catch (Exception $e) {
    // ここで、自分自身で例外を処理します
    //echo $e->getMessage();
    exit();
    return false;
}



//Zend_Controller_Front::run('../application/modules/controllers');