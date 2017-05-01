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

        $errMsg = $this->_session->errMsg;
        if (strlen($errMsg) > 0) {
            echo $errMsg;
            //exit();
        }
        $ps_pid = $this->_session->ps_pid;  //企画ps_pidの回る順番を配列で。キー0には企画数N、キー1〜Nには回る順に企画のpd_pid
        $order = $this->_session->order;    //企画の回る順路を配列で。キーk(1≦i≦N-1)には企画i→企画i+1に回る経路の情報が与えられている。
        $research_t = $this->_session->research_t; //再検索の場合の、個別に設定した企画ごとの時間が格納されている配列
        //キーiに対してキー"time"には経路にかかる時間、
        //"way"にはキーj（j≧1）が与えられており、キーjにはj番目に回るノード番号が与えられている。
        $start = $this->_session->start;
        $start_pos = $this->_session->start_pos; //現在地の建物番号 bd_pid
        /*
        echo "<pre>";
        var_dump($ps_pid);
        var_dump($order);
        var_dump($start);
        var_dump($start_pos);
        echo "</pre>";
        */
        //exit();

        $_start = strtotime($start);
        //var_dump($start);
        //var_dump($_start);
        $project = array();
        foreach ($ps_pid as $key => $item) { //$key=0は企画の個数
            if ($key != 0) {
                $project[$key-1]['info'] = $this->_main->getProjectInfo($item); //これでproject情報が手に入る
                if (!$project[$key-1]['info']['pt_time']) $project[$key-1]['info']['pt_time'] = 30;
                $project[$key-1]['time'] = $order[$key-1]['time'];
                $project[$key-1]['pre']  = $_start;
                $_start = $_start + $order[$key-1]['time'] * 60;
                if ($project[$key-2]['info']['pt_time']) {
                    $_start += $project[$key-2]['info']['pt_time'] * 60;
                }
                if ($research_t[$item]) { //再検索した時の個別に設定した企画毎の時間データがあるなら
                    $project[$key-1]['research_t'] = $research_t[$item];
                }
                $project[$key-1]['after'] = $_start;
                $project[$key-1]['start'] = date("h:i", $_start);
            }
        }

        echo "<pre>";
        foreach ($project as $item) {
            var_dump($item['info']['pt_time']);
            var_dump($item['info']['pt_start']);
        }
        echo "</pre>";

        $end = $_start + $project[$key-1]['info']['pt_time'] * 60;
        $this->view->project = $project;
        $this->view->start   = $start;
        $this->view->end     = date("h:i", $end);
        $this->view->start_pos_bd_pid = $start_pos;
        $this->view->start_pos = $this->_main->getBuildingData($start_pos);
        $this->view->order = $order;

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