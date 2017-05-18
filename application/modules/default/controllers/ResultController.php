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
        $this->view->lang       = $this->_session->lang;

        //$this->_helper->layout->setLayout('index');

    }

    public function indexAction()
    {

        $clock1     = $this->_session->clock1;
        $clock2     = $this->_session->clock2;
        $date       = $this->_session->date;
        $start_pos  = $this->_session->start_pos;
        $pt_pid     = $this->_session->pt_pid;
        $time       = $this->_session->time;
        if (count($pt_pid) > 0) {

        } else {
            var_dump(count($pt_pid));
            exit();
        }

        $this->SendSession($clock1, $clock2, $date, $start_pos, $pt_pid, $time);

        /*エラーメッセージ*/
        if ($this->_session->errMsg) {
            $this->view->errMsg = $this->_session->errMsg;
            unset($this->_session->errMsg);
        }
        if (!$pt_pid) {
            return $this->_redirect('/');

        }

        /*建物情報*/
        $bd_pid = $this->setBuildingInfo($start_pos, $pt_pid);

        /*順路*/
        $order = $this->setOrderInfo($bd_pid);

        /*開始時刻を分単位に直す*/
        $clock1_ = $this->_main->convertTime($clock1);

        /*
         * 企画データの到着時刻、終了時刻、移動時間を格納
         * $data['project']に企画データ、
         * $data['end_']に終了時刻分単位表示が格納されている
         */
        $data = $this->fixProject($pt_pid, $order, $time, $clock1_);

        $this->view->project   = $data['project'];
        $this->view->clock1    = $clock1;
        $this->view->clock2    = $this->_main->fixTime($data['end_']); //時刻表示にする
        $this->view->sp_bd_pid = $start_pos;
        $this->view->start_pos = $this->_main->getBuildingData($start_pos);
        $this->view->order     = $order;

    }

    /**
     * @param $clock1
     * @param $clock2
     * @param $date
     * @param $start_pos
     * @param $pt_pid
     * @param $time
     */
    private function SendSession($clock1, $clock2, $date, $start_pos, $pt_pid, $time) {
        $this->_session->clock1     = $clock1;
        $this->_session->clock2     = $clock2;
        $this->_session->date       = $date;
        $this->_session->start_pos  = $start_pos;
        $search = $this->unsetPT_PID($pt_pid);
        $this->_session->search     = $search;
        $this->_session->time       = $time;

        $this->_session->research   = 1;
    }

    private function unsetPT_PID($pt_pid) {
        unset($pt_pid[0]);
        $result = array();
        foreach ($pt_pid as $i => $item) {
            $result[$i-1] = $item;
        }
        return $result;

    }

    /**
     * @param $start_pos
     * @param $pt_pid
     * @return array
     */
    private function setBuildingInfo($start_pos, $pt_pid) {
        $bd_pid = array();
        foreach ($pt_pid as $i => $item) { //$itemの中身はpt_pid
            if ($i == 0) $bd_pid[$i] = $start_pos;
            else {
                $info = $this->_main->getProjectInfo($item);
                $bd_pid[$i] = $info['pp_bd_pid'];
            }
        }
        return $bd_pid;

    }

    private function setOrderInfo($bd_pid) {
        $order = array();
        foreach ($bd_pid as $i => $item) { //bd_pidのキーは$i=1から
            if ($item != $bd_pid[$i + 1]) {
                $order[$i]['time'] = $this->_main->getTimeInfo($item, $bd_pid[$i + 1]); //ある企画の場所から次の企画の場所へ行くのに必要な時間
            } else {
                $order[$i]['time'] = false;
            }
            $order[$i]['way'] = $this->_main->getOrderWay($item, $bd_pid[$i + 1]);  //ある企画の場所から次の企画の場所への道順
        }
        return $order;
    }

    private function setProjectInfo($project, $i, $pt_pid, $order) {
        $project[$i]['info'] = $this->_main->getProjectInfo($pt_pid); //これでproject情報が手に入る
        //if (strlen($project[$key-1]['info']['pt_time']) == 0) $project[$key-1]['info']['pt_time'] = 30; //あり得ない場合です
        $project[$i]['time'] = $order[$i]['time']; //移動にかかる時間
        return $project;
    }

    private function setResearchTime($project, $time, $i, $pt_pid) {
        $project[$i]['time'] = $time[$pt_pid]; //再検索時に変更した滞在時間のデータがあれば格納
        return $project;
    }

    /**
     * @param $project //企画全データ
     * @param $start //対象の企画の開始時刻
     * @param $end　//対象の企画の終了時刻
     * @param $time //対象の企画の滞在時間目安
     * @param $re_t //対象の企画の変更後の滞在時間
     * @param $i //対象の企画のindex
     * @param $start_ //次の開始時刻
     * @return mixed
     */
    private function setStartEnd($project, $start, $end, $time, $re_t, $i, $start_, $order) {

        if ($start) { //もしこの企画に開始時刻が存在すれば
            $project[$i]['start'] = $this->_main->fixTime($start); //開始時刻はそのまま入れる
            //次回の移動開始時刻を考える
            $start_ = $this->setNextOrderTimeStart($start, $time, $re_t, $end);

        } else { //なければ、前の開始時刻に
            if ($order[$i]['time']) {
                $start_ += $order[$i]['time']; //前回算出した今回の移動開始時刻に、移動時間を足して、企画を見て回る開始時刻にする
            }
            $project[$i]['start'] = $this->_main->fixTime($start_); //時刻表示にする
            $start_ = $this->setNextOrderTimeStart($start_, $time, $re_t, $end);
        }
        //移動時間も書く
        $project[$i]['order_time'] = $order[$i]['time'];

        $result['start_'] = $start_;
        $result['project'] = $project;
        return $result;
    }


    private function fixProject($pt_pid, $order, $time, $start_) {
        $project = array();
        foreach ($pt_pid as $key => $item) { //$key=0は企画の個数Nのこと
            if ($key != 0) {
                $project = $this->setProjectInfo($project, $key-1, $item, $order);
                $project = $this->setResearchTime($project, $time, $key-1, $item);
                $result  = $this->setStartEnd($project, $project[$key-1]['info']['pt_start_'], $project[$key-1]['info']['pt_end_'], $project[$key-1]['info']['pt_time'], $time[$item], $key-1, $start_, $order);
                $project = $result['project'];
                $start_  = $result['start_'];
            }
        }
        $data['project'] = $project;
        $data['end_'] = $start_;
        return $data;
    }

    /**
     * 次の企画へ向かう移動開始時刻を算出
     * @param $start
     * @param $time
     * @param $re_t
     * @param $end
     * @return int
     */
    private function setNextOrderTimeStart($start, $time, $re_t, $end) {
        if (strlen($re_t) > 0)  $start += $re_t; //変更した滞在時間
        elseif (strlen($time) > 0) $start += $time; //標準の滞在時間があれば、これを足す
        elseif (strlen($end) > 0) $start = $end; //次の開始時刻の式に今回の終了時刻を分で代入
        else $start += 30;
        return $start;
    }



}
