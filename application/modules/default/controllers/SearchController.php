<?php
 /**
  * Search Controller
  */


require_once 'Zend/Controller/Action.php';
require_once 'Zend/Config/Ini.php';
require_once 'Zend/Session.php';

require_once '../application/modules/default/models/MainModel.php';

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

        // テキストデータを取得

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

    public function refresh01Action()
    {
        $this->view->data = $this->_main->getProjectInfoRefresh($this->_session->date ,$this->_session->start, $this->_session->end);
        //unset ($this->view->data);
        //unset ($this->view->info);

        //$this->view->data_area  = $this->_main->getProjectDataArea($start, $end);
        //$this->view->data_genre = $this->_main->getProjectDataGenre($start, $end);
        //$this->view->data_rec   = $this->_main->getProjectDataRec($start, $end);

        $this->view->color = array('primary', 'warning', 'info', 'danger', 'success');
        $this->view->icon = array(
            'music' => 'music',
            'exhibition' => 'slideshare',
            'food' => 'cutlery',
            'performance' => 'magic',
            'join' => 'wechat',
            //'join' => 'handshake-o',
            'lecture' => 'mortar-board',
        );

        $this->_session->research = 1;
    }

    public function refresh02Action()
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
        $request = $this->getRequest();
        $this->_session->date = $request->getPost('date');
    }

    public function timePostAction()
    {
        // viewレンダリング停止
        //$this->_helper->layout->disableLayout();
        //$this->_helper->viewRenderer->setNoRender();

        $request = $this->getRequest();
        $radio  = $request->getPost('radio');
        $clock1 = $request->getPost('clock1');
        $clock2 = $request->getPost('clock2');
        $no_time = $request->getPost('no_time');

        /*ここちょっとやばいかも*/
        /*
        if (strlen($clock1) == 0) {
            $time = time() + 9*3600;  //GMTとの時差9時間を足す
            $clock1 = date("h:i", $time);
        }
        */
        if ($no_time) {
            $clock2 = "18:00";
        }

        $this->_session->date = $radio;
        $this->_session->no_time = $no_time;
        $this->_session->start = intval(substr($clock1,0,2)) * 60 + intval(substr($clock1,3,2));
        $this->_session->end = intval(substr($clock2,0,2)) * 60 + intval(substr($clock2,3,2));

exit();
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
        // viewレンダリング停止
        //$this->_helper->layout->disableLayout();
        //$this->_helper->viewRenderer->setNoRender();

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

        $inputData = "";
        $request    = $this->getRequest();
        $research = $request->getPost('research');
        if (!$research) {
            $search    = $request->getParam('search');
            $start_pos = $request->getParam('start_pos');
            $date      = $request->getParam('date');
            $clock1    = $request->getParam('clock1');
            $clock2    = $request->getParam('clock2');
        } else {
            $search    = $this->_session->re_search;
            $start_pos = $this->_session->re_start_pos;
            $date      = $this->_session->re_date;
            $clock1    = $this->_session->re_clock1;
            $clock2    = $this->_session->re_clock2;
            if (!$search) {
                $this->_session->errMsg = "エラーが発生しました。お手数ですが、再検索を行ってください。";
                //return $this->_redirect('/');
            }
        }

        $N = count($search);

        $_result = array();
        foreach ($search as $i => $item) { //$itemはpt_pid
            $_result[$i] = $this->_main->getProjectInfo($item);
            //！　もし企画startが09:00のものがあれば、現在時刻を変更
            //必要か？　実験
            if ($_result[$i]['pt_start_'] == 540) {
                $clock1 = "08:40"; //これでいいかなぁ〜
                $this->_session->errMsg = "最適化のため、開始時刻を変更しました。";
            }
        }

        if (!$clock1) {
            $clock1 = date("h:i");
            if (substr($clock1,0,2) == "06") { //9時台の時のみバグ起こりますので
                $time = time() + 9*3600;  //GMTとの時差9時間を足す
                $clock1 = date("h", $time);
            }
        }
        $clock1_ = intval(substr($clock1, 0, 2)) * 60 + intval(substr($clock1, 3, 2));
        $clock2_ = intval(substr($clock2, 0, 2)) * 60 + intval(substr($clock2, 3, 2));


        $inputData .= sprintf("%d %d\n", $N, $start_pos);
        $inputData .= sprintf("%d %d\n", $clock1_, $clock2_);


        /*
        var_dump($search);
        var_dump($N);
        var_dump($start_pos);
        var_dump($date);
        var_dump($clock1);
        var_dump($clock1_);
        var_dump($clock2);
        var_dump($clock2_);
        */

        //移動時間の修正
        $num = 2;
        $switch = 0; //0ならかける、1なら足す
        $this->_session->num = $num;
        $this->_session->switch = $switch;


        //$research = $this->_session->research;

        $this->_session->start = $clock1;
        $this->_session->start_pos = $start_pos;

        $result = null;
        $pos_bd_pid = false;
        $pp_search = array();
        $research_t = array(); //再検索後の時間
        $pp_search[0]['bd_pid'] = $start_pos;
        foreach ($search as $i => $item) { //$itemは$pt_pid
            //企画情報

            $pt_pid = $item; //企画summaryID

            if ($research) {
                $time = $request->getParam('re-time'.$_result[$i]['pt_pid']);
            } elseif (strlen($_result[$i]['pt_time']) > 0) {
                $time = $_result[$i]['pt_time']; //企画を回るのにかかるデフォの時間
            } else { //pt_timeは必ずあるはずなので、この条件文に入ることはないはずだが、一応
                $time = 30;
            }
            $research_t[$item] = $time;

            $start = ($_result[$i]['pt_start_']) ? $_result[$i]['pt_start_'] : -1;
            //$end   = ($_result[$i]['pt_end_'])   ? $_result[$i]['pt_end_']   : -1;
            $inputData .= sprintf("%d %d %d\n", $pt_pid, $start, $time);
            //$inputData .= sprintf("%d %d %d %d\n", $pt_pid, $start, $end, $time);

            //企画startが09:00(start_ == 540)のものがあれば
            if ($_result[$i]['pt_start_'] == 540) $pos_bd_pid = $_result[$i]['pp_bd_pid'];

            //for企画の建物間のかかる時間
            //$pp_search[$i]['ps_pid'] = $_result[$i]['ps_pid']; //企画のsummaryID 保険のため？
            $pp_search[$i + 1]['bd_pid'] = $_result[$i]['pp_bd_pid']; //建物のid
        }

        //企画の建物間のかかる時間
        foreach ($pp_search as $i => $item) {
            $time = array();
            foreach ($pp_search as $j => $item2) {
                if ($item['bd_pid'] == $item2['bd_pid']) {
                    $time[$j] = 0;
                } else {
                    $time[$j] = $this->_main->getTimeInfo($item['bd_pid'], $item2['bd_pid'], $num, $switch);
                }
            }
            foreach ($time as $key => $val) {
                $inputData .= sprintf("%d ", $val);
            }
            if ($i < $N) $inputData .= sprintf("\n");
        }

        $inout = array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            //2 => array('file', '/var/www/public/scripts/error-output.txt', 'a'),
            //2 => array("file", "/var/www/c_file/error-output", "a")
        );

        $cwd = "/var/www/scripts/";
        //ここまでは多分完成
        //search.outとの接続
        //var_dump($inputData);

        //var_dump(proc_open('/var/www/scripts/search_.out', $inout, $pipes, $cwd));

        $proc = proc_open('/var/www/html/public/scripts/search_.out', $inout, $pipes, $cwd);
        //$proc = proc_open('/var/www/scripts/search_.out', $inout, $pipes, $cwd);
        //var_dump("opencheck");
        //var_dump(is_resource($proc));
        if(is_resource($proc)){


            fwrite($pipes[0], $inputData);
            fclose($pipes[0]);

            //resultのpd_pidを返す

            //sleep(2);

            $result__ =  stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $return_value = proc_close($proc); //0以外ならエラー

            //var_dump($inputData);
            //var_dump($result__);
            //var_dump($return_value);

            if (substr($result__,0,2) == "-1") {
                echo 0;
                if ($research) {
                    $this->_session->errMsg = "設定した時間では最適な結果がありませんでした。";
                    return $this->_redirect('/result');
                }
            } else {

                //var_dump($result__);
                $pt_pid = array_map('intval', explode("\n", $result__)); //explodeは文字列を文字列で分解する関数
                unset($pt_pid[$N + 1]);
                $this->_session->pt_pid = $pt_pid;

                //var_dump($pt_pid);
                //var_dump($N);


                //$this->_session->ps_pid = $ps_pid;
                $this->_session->research_t = $research_t;


                //再検索のためのsession保存
                $this->_session->re_search    = $search;
                $this->_session->re_start_pos = $start_pos;
                $this->_session->re_date      = $date;
                $this->_session->re_clock1    = $clock1;
                $this->_session->re_clock2    = $clock2;
                echo 1;
            }

        } else {
            echo 0;
        }
        if ($research) {
            return $this->_redirect('/result');
        }
        exit();

    }




}
