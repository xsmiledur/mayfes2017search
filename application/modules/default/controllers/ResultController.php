<?php
/**
 * ResultController
 */
require_once 'Zend/Controller/Action.php';
require_once 'Zend/Config/Ini.php';
require_once 'Zend/Session.php';

require_once '../application/modules/default/models/MainModel.php';

class ResultController extends Zend_Controller_Action
{
    private $_config;                         // 設定情報
    private $_session;                        // セッションのインスタンス
    private $_lang;                           // 言語設定
    private $_contents;                       // 言語データ
    private $_main;                           // モデルのインスタンス

    public function init()
    {
        /**
         * ドメイン設定とスペースの確認
         */
        // 基本セッションを構築

        $this->_session = new Zend_Session_Namespace('data');

        // 設定情報をロードする
        $this->_config = new Zend_Config_Ini('../application/modules/default/lib/config.ini', null);

        /**
         * DBの接続
         */

        $db_rand = rand(1,2);
        if ($db_rand == 2) {
            $db_read = $this->_config->datasourceread2->database->toArray();
        } else {
            $db_read = $this->_config->datasourceread1->database->toArray();
        }

        $db_write = $this->_config->datasource->database->toArray();

        // モデルのインスタンスを生成する
        $this->_main = new MainModel($db_read,$db_write);

        /**
         * 言語データを取得する
         */
        // 言語指定を確認

        if ($this->getRequest()->getParam('la')) {
            $this->_session->lang = $this->getRequest()->getParam('la');
        } elseif(!$this->_session->lang) {
            $this->_session->lang = 'ja';
        }
        $this->_lang = $this->_session->lang;

        // テキストデータを取得

        $this->_contents = $this->_main->getContentsData($this->_session->lang,$this->getRequest()->getPathInfo());

        /**
         * Viewに必要データを渡す
         */

        // メッセージがあればviewに渡す
        $this->view->successMsg = $this->_session->successMsg;
        $this->view->errMsg = $this->_session->errMsg;
        unset($this->_session->successMsg);
        unset($this->_session->errMsg);

        // pathとユーザー情報をviewに渡す
        $this->view->path       = $this->getRequest()->getPathInfo();
        $this->view->contents   = $this->_contents;
        $this->view->lang       = $this->_session->lang;

        //$this->_helper->layout->setLayout('index');

    }

    public function indexAction()
    {

        // ディレクトリのパスを記述
        $dir = "/var/www/public/img/maps/" ;
        /*
        // ディレクトリの存在を確認し、ハンドルを取得
        if( is_dir( $dir ) && $handle = opendir( $dir ) ) {
            // [ul]タグ
            echo "<ul>" ;
            // ループ処理
            $i = 0;
            $file_data = array();
            while( ($file = readdir($handle)) !== false ) {
                // ファイルのみ取得
                if( filetype( $path = $dir . $file ) == "file" ) {
                    /********************
                    各ファイルへの処理
                    $file ファイル名
                    $path ファイルのパス
                     ********************/
        //$file_data[$i] = $file;
        //++$i;
        /*
        // [li]タグ
        echo "<li>" ;
        // ファイル名を出力する
        echo $file ;
        // ファイルのパスを出力する
        echo " (" . $path . ")" ;
        // [li]タグ
        echo "</li>" ;
        */
        /*
        if ($i == 50) break;
    }
}
$this->view->file_data = $file_data;
// [ul]タグ
//echo "</ul>" ;
}
        */
        $errMsg = $this->_session->errMsg;
        if (strlen($errMsg) > 0) {
            echo $errMsg;
            exit();
        }
        $pd_pid = $this->_session->pd_pid;  //企画の回る順番を配列で。キー0には企画数N、キー1〜Nには回る順に企画のpd_pid
        $order = $this->_session->order;    //企画の回る順路を配列で。キーk(1≦i≦N-1)には企画i→企画i+1に回る経路の情報が与えられている。
        //キーiに対してキー"time"には経路にかかる時間、
        //"way"にはキーj（j≧1）が与えられており、キーjにはj番目に回るノード番号が与えられている。
        $start = $this->_session->start;
        $start_pos = $this->_session->start_pos; //現在地の建物番号 bd_pid
        echo "<pre>";
        var_dump($pd_pid);
        var_dump($order);
        var_dump($start);
        var_dump($start_pos);
        echo "</pre>";
        //exit();
        //例
        /*
        $start = "10:00";
        $_start_pos = 43;
        $start_pos = $this->_main->getBuildingData($_start_pos);
        $pd_pid = array(3,4,1,336);
        $order  = array(
            array(
                //orderのキー0には何も入ってない
            ),
            array(
                'time' => 10,
                'way'  => 9
            ),
            array(
                'time' => 10,
                'way'  => 12
            ),
            array(
                'time' => 10,
                'way'  => 5
            ),
        );
        */
        $_start = strtotime($start);
        $project = array();
        foreach ($pd_pid as $key => $item) { //$key=0は企画の個数
            if ($key != 0) {
                $project[$key-1]['info'] = $this->_main->getProjectInfo($item); //これでproject情報が手に入る
                $project[$key-1]['time'] = $order[$key]['time'];
                $project[$key-1]['pre']  = $_start;
                $_start = $_start + $order[$key]['time'] * 60;
                if ($project[$key-2]['info']['pt_time']) {
                    $_start += $project[$key-2]['info']['pt_time'] * 60;
                }
                $project[$key-1]['after'] = $_start;
                $project[$key-1]['start'] = date("h:i", $_start);
            }
        }
        $end = $_start + $project[$key-1]['info']['pt_time'] * 60;
        $this->view->project = $project;
        $this->view->start   = $start;
        $this->view->end     = date("h:i", $end);
        $this->view->start_pos = $this->_main->getBuildingData($start_pos);
        $this->view->order = $order;
        //$this->view->color = array('navy', 'yellow', 'red', 'blue');
        /*$this->view->icon  = array(
            'akamon' => '';
            'yasuko' => '';
            'ko_dept' => '';
            'no_dept' => '';
        );*/


    }

    /*
    public function result2Action()
    {

        $request = $this->getRequest();
        $this->view->data1 = $request->getPost('data1');
        $this->view->data2 = $request->getPost('data2');
        $this->view->data3 = $request->getPost('data3');
    }

    public function exampleAction() {

    }
    public function resultAction()
    {
        $request = $this->getRequest();
        $clock1 = $request->getPost('clock1');
        $clock2 = $request->getPost('clock2');

        var_dump($clock1);
        var_dump($clock2);exit();
        exit();


    }

    public function prac1Action()
    {
        $this->view->data = $this->_main->getProjectData();

        $request = $this->getRequest();
        $search = $request->getParam('search');

        $result = $this->_main->searchFree();

        $this->view->result = $result;

    }
    */

}