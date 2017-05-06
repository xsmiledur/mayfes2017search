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
        $pt_pid = $this->_session->pt_pid;  //企画pt_pidの回る順番を配列で。キー0には企画数N、キー1〜Nには回る順に企画のpd_pid
        $order = $this->_session->order;    //企画の回る順路を配列で。キーk(1≦i≦N-1)には企画i→企画i+1に回る経路の情報が与えられている。
        $research_t = $this->_session->research_t; //再検索の場合の、個別に設定した企画ごとの時間が格納されている配列


        //キーiに対してキー"time"には経路にかかる時間、
        //"way"にはキーj（j≧1）が与えられており、キーjにはj番目に回るノード番号が与えられている。
        $start = $this->_session->start;
        $start_pos = $this->_session->start_pos; //現在地の建物番号 bd_pid

        if ($this->_session->errMsg) {
            $this->view->errMsg = $this->_session->errMsg;
            unset($this->_session->errMsg);
        }
        /*
        echo "<pre>";
        var_dump($pt_pid);
        var_dump($order);
        var_dump($start);
        var_dump($start_pos);
        echo "</pre>";
        */
        //exit();

        //建物情報
        $bd_pid = array();
        foreach ($pt_pid as $i => $item) { //$itemの中身はpt_pid
            if ($i == 0) {
                $bd_pid[$i] = $start_pos;
            } else {
                $info = $this->_main->getProjectInfo($item);
                //var_dump($info['pp_bd_pid']);
                $bd_pid[$i] = $info['pp_bd_pid'];
            }
        }

        //順路
        $order = array();
        $num = $this->_session->num;
        $switch = $this->_session->switch;
        foreach ($bd_pid as $i => $item) { //bd_pidのキーは$i=1から
            if ($item != $bd_pid[$i + 1]) {
                $order[$i]['time'] = $this->_main->getTimeInfo($item, $bd_pid[$i + 1], $num, $switch); //ある企画の場所から次の企画の場所へ行くのに必要な時間
            } else {
                $order[$i]['time'] = false;
            }
            $order[$i]['way'] = $this->_main->getOrderWay($item, $bd_pid[$i + 1]);  //ある企画の場所から次の企画の場所への道順
            //$order[$i]['way'][count($order[$i]['way']) + 1] = $bd_copid[$i + 1];
        }


        $_start = intval(substr($start,0,2)) * 60 + intval(substr($start,3,2)); //分単位の開始時刻
        $project = array();
        foreach ($pt_pid as $key => $item) { //$key=0は企画の個数
            if ($key != 0) {
                $project[$key-1]['info'] = $this->_main->getProjectInfo($item); //これでproject情報が手に入る
                if (!$project[$key-1]['info']['pt_time']) $project[$key-1]['info']['pt_time'] = 30;
                $project[$key-1]['time'] = $order[$key-1]['time']; //移動にかかる時間
                //$project[$key-1]['pre']  = $_start;
                if ($project[$key-1]['info']['pt_start']) { //もしこの企画に開始時刻が存在すれば
                    $project[$key-1]['start'] = $project[$key-1]['info']['pt_start']; //開始時刻はそのまま
                    $_start = $project[$key-1]['info']['pt_start_'];
                    if ($project[$key-1]['info']['pt_time']) {
                        $_start += $project[$key-1]['info']['pt_time'];
                    } else {
                        $_start = $project[$key-1]['info']['pt_end_']; //次の開始時刻の式に今回の終了時刻を分で代入
                    }
                } else { //なければ、前の開始時刻に
                    $_start = $_start + $order[$key-1]['time']; //移動時間を足して
                    $h = floor($_start/60); //時間
                    if (strlen($h) < 2 ) $h = "0".$h;
                    $m = $_start%60; //分
                    if (strlen($m) < 2 ) $m = "0".$m;
                    $project[$key-1]['start'] = $h . ":" . $m; //時刻表示にする
                    $_start = $_start + $project[$key-1]['info']['pt_time']; //今回の企画の滞在時間を足しておく
                }
                if ($research_t[$item]) { //再検索した時の個別に設定した企画毎の時間データがあるなら
                    $project[$key-1]['research_t'] = $research_t[$item]; //再検索時に変更した時間のデータがあれば格納
                }
            }
        }

        $this->view->project = $project;
        $this->view->start   = $start;

        $h = floor($_start/60); //時間
        if (strlen($h) < 2 ) $h = "0".$h;
        $m = $_start%60; //分
        if (strlen($m) < 2 ) $m = "0".$m;
        $this->view->end     = $h . ":" . $m; //時刻表示にする

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
