<?php
 /**
  * Search Controller
  */
require_once 'Zend/Controller/Action.php';
require_once 'Zend/Config/Ini.php';
require_once 'Zend/Session.php';
require_once '../application/modules/default/models/MainModel.php';

/**
 * PHP-resque
 */
require '../application/modules/default/functions/vendor/autoload.php';

Resque::setBackend('127.0.0.1:6379');
Resque::enqueue('search', 'SearchController');
class SearchController extends Zend_Controller_Action
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
        $this->view->lang       = $this->_session->langx;

        //$this->_helper->layout->setLayout('index');

    }

    public function indexAction()
    {
        //転送
        return $this->_redirect('/');
    }


    public function timePost1Action()
    {
        $request = $this->getRequest();
        $this->_session->date = $request->getPost('date');
    }

    public function refresh01Action()
    {
        $this->view->freewds = $this->_main->getFreeWords($this->_session->date);
        $this->view->arr = array(
            'blding' => array(
                'name' => 'bd_p_label2',
                'map'  => 'map'
            ),
            'bld-other' => array(
                'name' => 'bo_label',
                'sub'  => 'bd_p_label2',
            ),
            'data' => array(
                'name' => 'pd_label',
                'sub'  => 'bd_p_label2',
            )
        );

    }


    public function timePost2Action()
    {
        // viewレンダリング停止
        //$this->_helper->layout->disableLayout();
        //$this->_helper->viewRenderer->setNoRender();

        $request = $this->getRequest();
        $date  = $request->getPost('date');
        $clock1 = $request->getPost('clock1');
        $clock2 = $request->getPost('clock2');

        $this->_session->date = $date;
        $this->_session->start = intval(substr($clock1,0,2)) * 60 + intval(substr($clock1,3,2));
        $this->_session->end = intval(substr($clock2,0,2)) * 60 + intval(substr($clock2,3,2));

    }

    public function refresh02Action()
    {
        $this->view->data = $this->_main->getProjectInfoRefresh($this->_session->date ,$this->_session->start, $this->_session->end);

        $this->view->color = array('primary', 'warning', 'info', 'danger', 'success');

    }

    public function timePost3Action()
    {
        $request = $this->getRequest();
        $this->_session->pt_pid = $request->getPost('pt_pid');
    }

    public function refresh03Action()
    {
        $this->view->data = $this->_main->getProjectInfo($this->_session->pt_pid);
    }



    /**
     * アルゴリズム説明ページ
     */
    public function algorithmAction()
    {

    }

    /**
     * DBFix用アクション
     * 決してコメントアウトを外さないこと
     */
    public function testAction()
    {
        return $this->_redirect('/');
        /*
        //$data = $this->_main->getProjectData();
        //$this->_main->modifyProjectData();
        //$this->_main->modifyDataPlace();
        //$this->_main->modifyPlaceTime();
        //$this->_main->timeFix();
        //$this->_main->MakeNoActiveFlg();
        //$this->_main->timeFix2();
        //$this->_main->bddataFix();
        //$this->_main->bdDataUpdate();
        //$this->_main->insertFix();
        //$this->_main->insertStayTime();
        //$this->_main->fixTimeBug();
        */
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
            現在時刻分 終了時刻分
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

        $request    = $this->getRequest();
        $research = $request->getPost('research');

        if (!$research) {
            $search = $this->getDataParam('search', $research);
            $this->SendNoSearchError($search);
            $time = $this->getDataParam('time', $research);
            $data = $this->SetInfo($search);
            $time = $this->SetTime($search, $time, $research);
        } else {
            $search = $this->getDataParam('search', $research);
            $this->SendNoSearchError($search);
            $data = $this->SetInfo($search);
            $time = $this->SetTime($search, NULL, $research);
        }

        $start_pos  = $this->getDataParam('start_pos', $research);
        $date       = $this->getDataParam('date', $research);
        $clock1     = $this->getDataParam('clock1', $research);
        $clock2     = $this->getDataParam('clock2', $research);

        $N = $this->GetCount($search);

        $flg = 1; //0だと旧バーション　1だと新バージョンのコード

        $inputData = $this->setInputData1($N, $start_pos, $clock1, $clock2, $flg);

        $result = $this->setInputData2_BldingData($inputData, $start_pos, $data, $time, $flg);
        $inputData = $result['inputData'];
        $pp_search = $result['pp_search']; //中身はbd_pid

        //企画の建物間のかかる時間
        $inputData = $this->setInputData3($inputData, $pp_search, $N);
        var_dump($inputData);

        /*C++スクリプトとの結合*/
        $result = $this->procOpen(1); //1=サーバー 0=localhost
        $proc = $result['proc']; $pipes = $result['pipes'];

        //$echo = $this->returnResult($proc, $pipes, $flg, $inputData, $research, $N, $clock1, $clock2, $date, $start_pos, $time);
        $echo = $this->returnResult($proc, $pipes, $inputData, $research, $N, $clock1, $clock2, $date, $start_pos, $time);

        echo $echo;

        if ($research) {
            return $this->_redirect('/result');
            unset($research);
        }
        exit();

    }

    /**
     * @param $key
     * @param $researchFlg
     * @return mixed
     */
    private function getDataParam($key, $researchFlg) {
        $request    = $this->getRequest();
        $arr = array(
            'clock1'    => $this->_session->clock1,
            'clock2'    => $this->_session->clock2,
            'date'      => $this->_session->date,
            'start_pos' => $this->_session->start_pos,
            'search'    => $this->_session->search,
            'time'      => $this->_session->time,
        );
        if (!$researchFlg) {
            return $request->getPost($key);
        }
        else {
            return $arr[$key];
        }
    }


    private function SendSession($clock1, $clock2, $date, $start_pos, $pt_pid, $time) {
        $this->_session->clock1     = $clock1;
        $this->_session->clock2     = $clock2;
        $this->_session->date       = $date;
        $this->_session->start_pos  = $start_pos;
        $this->_session->pt_pid     = $pt_pid;
        $this->_session->time       = $time;
    }

    private function SendNoSearchError($search) {
        if (!$search) {
            $this->_session->errMsg = "エラーが発生しました。お手数ですが、再検索を行ってください。";
            //return $this->_redirect('/');
        }
        foreach ($search as $item) {
            if (intval($item) <= 0 ) {
                $this->_session->errMsg = "エラーが発生しました。お手数ですが、再検索を行ってください。";
                //return $this->_redirect('/');
            }
        }
    }

    private function GetCount($search) {
        $count = count($search);
        /*
        if ($count > 15) {
            $this->_session->errMsg = "エラーが発生しました。お手数ですが、再検索を行ってください。";
            //return $this->_redirect('/');
        }
        */
        return $count;
    }

    private function SetInfo($search) {
        $data = array();
        foreach ($search as $i => $item) { //$itemの中身
            /* 企画情報を格納 */
            $data[$i] = $this->_main->getProjectInfo($item);
            foreach ($search as $j =>  $val) { //同じpt_pidがあった時対策
                if ($val == $item && $i != $j) {
                    $this->_session->errMsg = "エラーが発生しました。お手数ですが、再検索を行ってください。";
                    //return $this->_redirect('/');
                }
            }
        }
        return $data;
    }

    private function SetTime($search, $timeData, $research) {
        $time   = array();
        $request = $this->getRequest();
        foreach ($search as $i => $item) { //$itemの中身
            if (!$research) { //１回目の検索なら
                /* 時間を格納 */
                $time[$item] = $timeData[$i];
            } else { //２回目以降の検索なら
                $time[$item] = $request->getPost('re-time'.$item);
            }
        }
        return $time;
    }

    private function setInputData1($N, $start_pos, $clock1, $clock2, $flg) {

        /* $clock1, $clock2から分単位の時間に変換 */
        $clock1_ = $this->_main->convertTime($clock1);
        $clock2_ = $this->_main->convertTime($clock2);

        $inputData = "";

        if ($flg == 1) {
            $inputData .= sprintf("%d \n", $N); //企画の個数
            $inputData .= sprintf("%d %d %d -1\n", $start_pos, $clock1_, $clock2_);
        } else {

            $inputData .= sprintf("%d %d\n", $N, $start_pos);
            $inputData .= sprintf("%d %d\n", $clock1_, $clock2_);
        }

        return $inputData;
    }

    private function setInputData2_BldingData($inputData, $start_pos, $data, $time, $flg) {
        //$pp_searchは建物のデータのこと
        $pp_search = array();
        $pp_search[0] = $start_pos;

        //$dataは、indexは0から増加する整数、中身はgetProjectInfoで獲得した、その企画に関する全てのデータ
        foreach ($data as $i => $item) {
            $pt_pid = $item['pt_pid']; //企画pt_pid
            $start = ($item['pt_start_']) ? $item['pt_start_'] : -1; //企画開始時刻
            $end   = ($item['pt_end_']  ) ? $item['pt_end_']   : -1; //企画終了時刻
            if ($flg == 1) {
                $inputData .= sprintf("%d %d %d %d\n", $pt_pid, $start, $end, $time[$pt_pid]);
            } else {
                $inputData .= sprintf("%d %d %d\n", $pt_pid, $start, $time);
            }
/*
            //企画startが09:00(start_ == 540)のものがあれば
            if ($_result[$i]['pt_start_'] == 540) $pos_bd_pid = $_result[$i]['pp_bd_pid'];
*/
            $pp_search[$i + 1] = $item['pp_bd_pid']; //建物のid
        }

        $result['pp_search'] = $pp_search;
        $result['inputData'] = $inputData;
        return $result;
    }

    private function setInputData3($inputData, $pp_search, $N) {
        var_dump($N);
        foreach ($pp_search as $i => $item) {
            var_dump($i);
            $t = array();
            foreach ($pp_search as $j => $item2) {
                if ($item == $item2) $t[$j] = 0;
                else $t[$j] = $this->_main->getTimeInfo($item, $item2);
            }
            foreach ($t as $k => $val) $inputData .= sprintf("%d ", $val);
            if ($i < $N) $inputData .= sprintf("\n");
        }
        return $inputData;
    }

    private function procOpen($flg) { //flg == 1ならサーバー, ==0ならlocalhost
        if ($flg ==  1) {
            $error = "/var/www/html/public/scripts/error-output.txt";
        } else {
            $error = "/var/www/scripts/error-output.txt";

        }
        $inout = array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => array("file", $error, "a")
        );

        if ($flg == 1) {
            $proc = proc_open('/var/www/html/public/scripts/search.out', $inout, $pipes, "/var/www/html/public/scripts/");
            //$proc = proc_open('/var/www/html/public/scripts/search.out', $inout, $pipes, "/var/www/html/public/scripts/");
        } else {
            $proc = proc_open('/var/www/scripts/search.out', $inout, $pipes, "/var/www/scripts/");
            //$proc = proc_open('/var/www/scripts/search.out', $inout, $pipes, "/var/www/scripts/");
        }
        $result['proc'] = $proc;
        $result['pipes'] = $pipes;

        return $result;
    }

    private function connectCproject($pipes, $inputData) {

        fwrite($pipes[0], $inputData);
        fclose($pipes[0]);

        $connect = stream_get_contents($pipes[1]);

        fclose($pipes[1]);

        return $connect;
    }

    private function returnResult($proc, $pipes, $inputData, $research, $N, $clock1, $clock2, $date, $start_pos, $time) {
        if(is_resource($proc)){
            $connect = $this->connectCproject($pipes, $inputData);
            var_dump($connect);
            if (substr($connect,0,2) == "-1" || substr($connect,0,1) == "0" ) {
                if ($research) $this->_session->errMsg = "設定した時間では最適な結果がありませんでした。";
                return 0;
            } else {
                $pt_pid = array_map('intval', explode("\n", $connect)); //explodeは文字列を文字列で分解する関数
                var_dump($pt_pid);

                if (count($pt_pid) <= 1) {
                    return -2;
                } else {
                    unset($pt_pid[$N + 1]);

                    $this->sendSession($clock1, $clock2, $date, $start_pos, $pt_pid, $time);

                    return 1;
                }
            }
        } else {
            return -1;
        }
    }

    private function refixPT_PID($pt_pid, $N) {
        unset($pt_pid[1]); //現在地
        unset($pt_pid[$N + 2]); //一番最後は消す
        $i = 0;
        $arr = array();
        foreach ($pt_pid as $item) {
            $arr[$i] = $item; ++$i;
        }
        return $arr;
    }

}
