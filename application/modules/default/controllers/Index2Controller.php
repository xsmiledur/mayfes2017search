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




    public function algorithmAction()
    {

    }

    public function testAction()
    {
        //$data = $this->_main->getProjectData();
        $this->_main->______timeFix();
    }

    public function resultAction()
    {
        $request = $this->getRequest();
        //$search = $this->_session->search;//来てる❤️
    }

    public function getDist($a, $b){
        //get the length of the shortest from $a to $b
        return $a+$b;//just a dummy
    }
    public function getStartTime($id)
    {
        //get the start time of the event $id
        return 0;
    }

    public function getEndTime($id)
    {
        //get the start time of the event $id
        return 0;
    }

    public function searchAction()
    {
        $req = $this->getRequest();
        $params = $req->getParms();

        $search = $this->getRequest()->getPost('search');
        $this->_session->search = $search;

        var_dump($search);

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
        $this->view->result = "test";



 
 
     }

}