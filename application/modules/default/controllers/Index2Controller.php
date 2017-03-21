<?php
/**
 * Index2Controller
 */
require_once 'Zend/Controller/Action.php';
require_once 'Zend/Config/Ini.php';
require_once 'Zend/Session.php';

require_once '../application/modules/default/models/MainModel.php';

class Index2Controller extends Zend_Controller_Action
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
        $this->view->data = $this->_main->getProjectData();
        $this->view->data_area = $this->_main->getProjectDataArea();

        $this->view->area = $this->_main->getAreaInfo();
        $this->view->arr_area = array(
            1 => array(
                'label' => '農学部エリア',
                'name' => 'no_dept',
            ),
            2 => array(
                'label' => '工学部エリア',
                'name' => 'ko_dept',
            ),
            3 => array(
                'label' => '安田講堂エリア',
                'name' => 'yasuko',
            ),
            4 => array(
                'label' => '赤門エリア',
                'name' => 'akamon',
            ),
        );
        $this->view->data_all = $this->_main->getProjectDataAll();
        //$this->view->data_area = $this->_main->getProjectDataGenre();

        $request = $this->getRequest();
        $search = $request->getParam('search');

        //$result = $this->_main->searchFree();

        //$this->view->result = $result;

    }


    /**
     * アルゴリズム説明ページ
     */
    public function algorithmAction()
    {

    }

    public function testAction()
    {
        //$data = $this->_main->getProjectData();
        $this->_main->__________timeFix();
    }

    /**
     * index.phtmlで入力されたデータを整形してC++programに渡し、
     * 受け取った結果をresultActionに受け渡す
     */
    public function searchAction()
    {
        /*
            最短オイラー路問題

            入力形式

            N v_0
            v_1 s_1 t_1
            v_2 s_2 t_2
            :
            v_N s_N t_N
            d_00 d_01 .. d_0N
            d_10 d_11 .. d_1N
            :
            d_N0 d_N1 .. d_NN

            1行目に巡る企画数Nと始点v_0が与えられる。

            続くN行のうちのi行目にはi番目の巡りたい企画のID v_i とそれに到着したい時刻 s_i と費やす時間t_iが空白区切りで入力される。

            続くN+1行のうちi+1行目にはN+1個の整数d_i1, d_i2, .. , d_iNが
            空白区切りで与えられる。(0≦i≦N)
            d_ijはi番目の企画（の建物）からj番目の企画（の建物）に行くのに
            かかる時間である。

            時間、時刻の単位は分である。時刻は日付が変わってから何分経ったかで持つ。
            時間や時刻に指定がない場合はs_i = -1やt_i = -1。
            入力はすべて整数
        */

        $inputData = "";

        $request    = $this->getRequest();
        $search     = $request->getPost('search');
        $N          = count($search);
        $start_pos   = $request->getPost('start-pos');
        $inputData .= sprintf("%d %d\n", $N, $start_pos);
        $clock1     = $request->getPost('clock1');
        $clock2     = $request->getPost('clock2');
        $inputData .= sprintf("%d %d\n", $clock1, $clock2);

        $result = null;
        $pp_search = array();
        foreach ($search as $i => $item) { //$itemは$ps_pid
            $_result = $this->_main->getProjectInfo($item);

            //企画情報

            $ps_pid = $_result['ps_pid']; //企画summaryID
            $time = $_result['pt_time']; //企画を回るのにかかるデフォの時間
            $__start = $_result['pt_start']; //企画start
            if (!$__start) {
                $start = -1;
            } else {
                $_start = strtotime($__start); //タイムスタンプに直す
                $start = $_start/60; //分単位に直す
            }
            $inputData .= sprintf("%d %d %d\n", $ps_pid, $start, $time);

            //for企画の建物間のかかる時間
            //$pp_search[$i]['ps_pid'] = $_result['ps_pid']; //企画のsummaryID 保険のため？
            $pp_search[$i]['bd_pid'] = $_result['pp_bd_pid']; //建物のid
        }

        //企画の建物間のかかる時間
        foreach ($pp_search as $i => $item) {
            foreach ($pp_search as $j => $item2) {
                if ($item['bd_pid'] == $item2['bd_pid']) {
                    $time[$j] = 0;
                } else {
                    $time[$j] = $this->_main->getTimeInfo($item['bd_pid'], $item2['bd_pid']);
                }
            }
            foreach ($time as $key => $val) {
                $inputData .= sprintf("%d ", $item);
            }
            $inputData .= sprintf("\n");
        }

        $inout = array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w')
        );

        $proc = proc_open('/var/www/scripts/search.out', $inout, $pipes);

        if(is_resource($proc)){
            fwrite($pipes[0], $inputData);
            fclose($pipes[0]);
            //resultのpd_pidを返す
            $pd_pid = array_map('intval', explode("\n", $pipes[1])); //explodeは文字列を文字列で分解する関数

            $bd_pid = array();
            foreach ($pd_pid as $i => $item) {
                if ($i != 0) {
                    $info = $this->_main->getProjectInfo($item);
                    $bd_pid[$i] = $info['pd_bd_pid'];
                }
            }
            $order = array();
            foreach ($bd_pid as $i => $item) {
                if ($i < $N) {
                    $order[$i]['time'] = $this->_main->getTimeInfo($item, $bd_pid[$i + 1]); //ある企画の場所から次の企画の場所へ行くのに必要な時間
                    $order[$i]['way']  = $this->_main->getOrderWay($item, $bd_pid[$i + 1]);  //ある企画の場所から次の企画の場所への道順
                }
            }

            $this->_session->pd_pid = $pd_pid;
            $this->_session->order = $order;

            fclose($pipes[1]);
            fclose($pipes[2]);
        }
        $this->view->result = "test";


        //この後はいらない。

/*
        //続くN行のうちのi行目にはi番目の巡りたい企画のID v_i と
        //それに到着したい時刻 s_i と
        //費やす時間t_iが空白区切りで入力される。
        $this->_session->result = $result;
        $N = $i; //巡る企画数
        $this->_session->num = $N;

        
        //続くN+1行のうちi+1行目にはN+1個の整数d_i1, d_i2, .. , d_iNが
        //空白区切りで与えられる。(0≦i≦N)
        //d_ijはi番目の企画（の建物）からj番目の企画（の建物）に行くのに
        //かかる時間である。
        
        //ダイクストラで事前に算出した時間を取り出す


        for($i = 0; $i < $n; $i++){
            $eventID = $checkpoints[$i];
            $startTime = getStartTime($eventID);
            $endTime = getEndTime($eventID);
            $inputData .= sprintf("%d %d %d\n", $eventID, $startTime, $endTime);
        }


        $req = $this->getRequest();
        $params = $req->getParms();
        $beginTime = $params["clock1"];
        $endTime = $params["clock2"];
        $param_keys = array_keys($parms);
        $checkpoints = array();
        foreach ($keys as $key){
            if(substr($key, 0, 5) != "input")continue;
            if($parms[$key] == "")continue;
            $checkpoints += array(intval(substr($key, 5, -1)));
        }

        $n = count($checkpoints);
        $startPos = $parms["startPos"];

        $inputData = "";
        $inputData .= sprintf("%d %d\n", $n, $startPos);
        for($i = 0; $i < $n; $i++){
            $eventID = $checkpoints[$i];
            $startTime = getStartTime($eventID);
            $endTime = getEndTime($eventID);
            $inputData .= sprintf("%d %d %d\n", $eventID, $startTime, $endTime);
        }
        for($i = 0;$i < count($checkpoitns); $i++){
            for($j = -1;$j < $n; $j++){
                $from = $i;
                $to = $j;
                if($to == -1)$to = $startPos;
                $inputData .= sprintf("%d ", getDist($from, $to));
            }
        }





        /*$startPos = $request->getPost('start_pos'); //始点




        $beginTime = $request->getPost('clock1');
        $endTime = $request->getPost('clock2');




        /*
         * 全点対
         */

        /*
            N M
            v_1 u_1 t_1
            v_2 u_2 t_2
            :
            v_M u_M t_M
            1行目に全頂点数Nと辺の数Mが与えられる。

            続くM行のうちのi行目にはi番目の辺の情報が与えられる。
            i番目の辺はv_iからu_iまでt_i分で結ぶ有向辺である。
         */



/*
        $ver = array(); //辺の情報
        $j = 0;
        foreach ($data as $key => $item) {
            foreach ($data as $key2 => $item2) { //$item,$item2は共にbd_pid
                if ($key != $key2) { //$key,$key2は共にpd_pid 企画pid
                    $var[$j]['v'] = $item; //ベクトルの始点
                    $var[$j]['u'] = $item2; //ベクトルの終点
                    $var[$j]['t'] = $this->_main->getTimeInfo($item, $item2);
                    $j++;
                }
            }
        }


        //$beginTime = $params["clock1"];
        //$endTime = $params["clock2"];
        //$param_keys = array_keys($parms);
        $checkpoints = array();
        foreach ($keys as $key){
            if(substr($key, 0, 5) != "input")continue;
            if($parms[$key] == "")continue;
            $checkpoints += array(intval(substr($key, 5, -1)));
        }

        $n = count($checkpoints);
        $startPos = $parms["startPos"];

        $inputData = "";
        $inputData .= sprintf("%d %d\n", $n, $startPos);
        for($i = 0; $i < $n; $i++){
            $eventID = $checkpoints[$i];
            $startTime = getStartTime($eventID);
            $endTime = getEndTime($eventID);
            $inputData .= sprintf("%d %d %d\n", $eventID, $startTime, $endTime);
        }
        for($i = 0;$i < count($checkpoitns); $i++){
            for($j = -1;$j < $n; $j++){
                $from = $i;
                $to = $j;
                if($to == -1)$to = $startPos;
                $inputData .= sprintf("%d ", getDist($from, $to));
            }
        }


        $inout = array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w')
        );

        $proc = proc_open('/var/www/scripts/search.out', $inout, $pipes);

        if(is_resource($proc)){
            fwrite($pipes[0], $inputData);
            fclose($pipes[0]);
            $answer = array_map(intval, explode($pipes[1], "\n"));

            //setparams ... not yet

            fclose($pipes[1]);
            fclose($pipes[2]);
        }
        $this->_session->data = $result;*/


 
 
    }


    /**
     * 結果表示画面
     */
    public function resultAction()
    {
        $pd_pid = $this->_session->pd_pid;  //企画の回る順番を配列で。キー0には企画数N、キー1〜Nには回る順に企画のpd_pid
        $order = $this->_session->order;    //企画の回る順路を配列で。キーk(1≦i≦N-1)には企画i→企画i+1に回る経路の情報が与えられている。
                                            //キーiに対してキー"time"には経路にかかる時間、
                                            //"way"にはキーj（j≧1）が与えられており、キーjにはj番目に回るノード番号が与えられている。

        foreach ($pd_pid as $key => $item) {
            $project[$key]['info'] = $this->_main->getProjectInfo($item); //これでproject情報が手に入る
            if ($key != $pd_pid[0]) { //$pd_pid[0]には企画数
                $project[$key]['time'] = $order[$key]['time'];
            }
        }
        $this->view->project = $project;
        $this->view->order = $order;


    }


}