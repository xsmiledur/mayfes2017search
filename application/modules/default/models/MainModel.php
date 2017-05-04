<?php

// コンポーネントをロードする
require_once 'Zend/Db.php';
require_once 'Zend/Registry.php';
require_once 'Zend/Date.php';
require_once 'Zend/Feed.php';
require_once 'Zend/Debug.php';
require_once "Zend/File/Transfer/Adapter/Http.php";
require_once 'Zend/Service/Amazon/S3.php';

class MainModel
{
    private $_read;  // データベースアダプタのハンドル
    private $_write;  // データベースアダプタのハンドル

    /**
     * コンストラクタ
     *
     */
    public function __construct($db_read, $db_write)
    {
        // 接続情報を取得する
        if (!isset($db_read) || count($db_read) < 1 || !isset($db_write) || count($db_write) < 1) {
            throw new Zend_Exception(__FILE__ . '(' . __LINE__ . '): ' . 'データベース接続情報が取得できませんでした。');
        }

        $pdoParams = array(
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
        );

        // データベースの接続パラメータを定義する
        $read_params = array(
            'host' => $db_read['host'],
            'username' => $db_read['username'],
            'password' => $db_read['password'],
            'dbname' => $db_read['name'],
            'charset' => $db_read['charset'],
            'driver_options' => $pdoParams
        );

        // データベースアダプタを作成する
        $this->_read = Zend_Db::factory($db_read['type'], $read_params);
        // 文字コードをUTF-8に設定する
        $this->_read->query("set names 'utf8'");

        // データ取得形式を設定する
        $this->_read->setFetchMode(Zend_Db::FETCH_ASSOC);


        // データベースの接続パラメータを定義する
        $write_params = array(
            'host' => $db_write['host'],
            'username' => $db_write['username'],
            'password' => $db_write['password'],
            'dbname' => $db_write['name'],
            'charset' => $db_write['charset'],
            'driver_options' => $pdoParams
        );
        // データベースアダプタを作成する
        $this->_write = Zend_Db::factory($db_read['type'], $write_params);
        // 文字コードをUTF-8に設定する
        $this->_write->query('set names "utf8"');

        // データ取得形式を設定する
        $this->_write->setFetchMode(Zend_Db::FETCH_ASSOC);

    }

    /**
     * コンテンツテキストデータを取得
     *
     * @param $lang
     * @param $path
     * @return bool|mixed
     */
    public function getContentsData($lang, $path)
    {
        // トランザクション開始
        $this->_read->beginTransaction();
        $this->_read->query('begin');
        try {
            $result = array();

            $select = $this->_read->select();
            $select->from('site_contents');
            $select->where('sc_active_flg = ?', 1)
                ->order('sc_order ASC');
            $stmt = $select->query();
            $data = $stmt->fetchAll();

            if ($data) {

                foreach ($data as $val) {
                    $result[$val['sc_name']] = $val['label'];
                }

            }

            // 成功した場合はコミットする
            $this->_read->commit();
            $this->_read->query('commit');
            return $result;
        } catch (Exception $e) {
            // 失敗した場合はロールバックしてエラーメッセージを返す
            $this->_read->rollBack();
            $this->_read->query('rollback');
//            var_dump($e->getMessage());exit();
            return false;
        }
    }


    /**
     * 普通に企画データを取得
     *
     * @return array
     */
    public function getProjectDataAll()
    {
        $select = $this->_read->select();
        $select->from('90_project_time')
            ->join('90_project_data', 'pt_pd_pid = pd_pid')
            ->join('90_project_place', 'pt_pp_pid = pp_pid');
        $select->where('pd_active_flg = ?', 1);
        $stmt = $select->query();
        return $stmt->fetchAll();
    }


    /**
     * 企画データを取得
     *
     * @return array
     */

    public function getProjectData()
    {
        $this->_read->beginTransaction();
        $this->_read->query('begin');
        try {
            $select = $this->_read->select();
            $select->from('90_project_time')
                ->join('90_project_data', 'pt_pd_pid = pd_pid')
                ->join('90_project_place', 'pt_pp_pid = pp_pid')
                ->where('pd_active_flg = ?', 1)
                ->order('pd_pid');
            /*$select->from('project_data_89')
                ->join('project_place_89', 'pd_pid = pp_pd_pid')
                ->joinLeft('project_time_89', 'pp_pid = pt_pp_pid')
                ->where('pd_active_flg = ?', 1);*/
            $stmt = $select->query();
            $data = $stmt->fetchAll();

            // 成功した場合はコミットする
            $this->_read->commit();
            $this->_read->query('commit');

        } catch (Exception $e) {
            // 失敗した場合はロールバックしてエラーメッセージを返す
            $this->_read->rollBack();
            $this->_read->query('rollback');
            //var_dump($e->getMessage());exit();
            return false;
        }

        return $data;

    }

    public function getProjectInfoRefresh($date, $start, $end)
    {
        $data = array();

        $select = $this->_read->select();
        $select->from('90_project_time', array('pt_pid', 'pt_start','pt_start_','pt_end','pt_end_','pt_open','pt_open_', 'pt_note'))
            ->join('90_project_data', 'pt_pd_pid = pd_pid', array('pd_pid', 'pd_label', 'pd_body', 'pd_web_simple', 'pd_web_long', 'pd_web_body', 'pd_genre1', 'pd_genre2', 'pd_rec_flg', 'pd_pickup_flg', 'pd_academic_flg'))
            ->join('90_project_place', 'pt_pp_pid = pp_pid', array('pp_place', 'pp_name1', 'pp_name2', 'pp_full', 'pp_day'))
            ->where('pd_active_flg = ?', 1)
            ->where('pp_day = ?', $date)
            ->order('pd_pid');
        $stmt = $select->query();
        $_data = $stmt->fetchAll();

        if (!$start && !$end) {
            $data['data'] = $_data;
        } else {
            $i = 0;
            foreach ($_data as $item) {
                if ($item['pp_full']) {
                    $data['data'][$i] = $item;
                    $i++;
                } else { //$item['pt_start']は必ずある
                    if ($item['pt_start_'] >= $start || ($item['pt_open_'] && $item['pt_open_'] >= $start)) {
                        if ($item['pt_start_'] + $item['pt_time'] <= $end) {
                            $data['data'][$i] = $item;
                            $i++;
                        }
                    }
                }
            }
        }

        $data['area'] = array(
            0 => array(
                'name' => 'no_dept',
                'label' => '農学部エリア',
            ),
            1 => array(
                'name' => 'ko_dept',
                'label' => '工学部エリア',
            ),
            2 => array(
                'name' => 'yasuko',
                'label' => '安田講堂エリア',
            ),
            3 => array(
                'name' => 'akamon',
                'label' => '赤門エリア',
            ),
        );

        foreach ($data['area'] as $key => $item) {
            $select = $this->_read->select();
            $select->from('building_data', array('bd_pid', 'bd_p_name1', 'bd_p_label1', 'bd_p_name2', 'bd_p_label2' ));
            $select->where('bd_active_flg = ?', 1)
                ->where('bd_p_name1 = ?', $item['name'])
                ->order('bd_order');
            $stmt = $select->query();
            $data['area'][$key]['info'] = $stmt->fetchAll();
        }


        $data['genre'] = array(
            0 => array(
                'name' => 'exhibition',
                'label' => '展示・実演',
            ),
            1 => array(
                'name' => 'music',
                'label' => '音楽',
            ),
            2 => array(
                'name' => 'shop',
                'label' => '飲食・販売',
            ),
            3 => array(
                'name' => 'performance',
                'label' => 'パフォーマンス',
            ),
            4 => array(
                'name' => 'join',
                'label' => '参加型',
            ),
            5 => array(
                'name' => 'lecture',
                'label' => '講演会・討論会',
            )
        );

        foreach ($data['genre'] as $key => $item) {
            $select = $this->_read->select();
            $select->from('genre_data', array('gd_pid', 'gd_detail', 'gd_detail_label'));
            $select->where('gd_active_flg = ?', 1)
                ->where('gd_index = ?', $item['name']);
            $stmt = $select->query();
            $data['genre'][$key]['info'] = $stmt->fetchAll();
        }

        return $data;
        unset ($data);
    }


    /**
     * エリア検索のための
     * @param $start
     * @param $end
     * @return array
     */
    public function getProjectDataArea($start, $end)
    {
        $this->_read->beginTransaction();
        $this->_read->query('begin');
        try {

            $select = $this->_read->select();
            $select->from('building_data', array('bd_kind', 'bd_kind_label'))
                ->where('bd_kind = "ko_dept" || bd_kind = "no_dept" || bd_kind = "yasuko" || bd_kind = "akamon"')
                ->distinct();
            $stmt = $select->query();
            $_blding =  $stmt->fetchAll();
            $data = array();
            foreach ($_blding as $key => $name) {
                $select = $this->_read->select();
                $select->from('building_data')
                    ->where('bd_kind = ?', $name['bd_kind']);
                $stmt = $select->query();
                $data[$key]['name'] = $name['bd_kind_label'];
                $data[$key]['kind'] = $name['bd_kind'];
                $data[$key]['info'] = $stmt->fetchAll();

                foreach ($data[$key]['info'] as $name => $item) {
                    //fulltimeのものを先に
                    $select = $this->_read->select();
                    $select->from('project_summary_89', 'ps_pid')
                        ->join('project_data_89', 'ps_pd_pid = pd_pid')
                        ->join('project_place_89', 'ps_pp_pid = pp_pid')
                        ->joinLeft('project_time_89', 'ps_pt_pid = pt_pid')
                        ->where('pd_active_flg = ?', 1)
                        ->where('pp_area = ?', $item['bd_kind'])
                        ->where('pp_place_index = ?', $item['bd_name']);
                    $stmt = $select->query();
                    $_data = $stmt->fetchAll();

                    if (!$start && !$end) {
                        $data = $_data;
                    } else {
                        $i = 0;
                        foreach ($_data as $item2) {
                            if (!$item2['pt_pid']) {
                                $data[$key]['info'][$name]['data'][$i] = $item2;
                                $i++;
                            } else {
                                if (strtotime($item2['pt_start']) > $start && strtotime($item2['pt_end']) < $end) {

                                    $data[$key]['info'][$name]['data'][$i] = $item2;
                                    $i++;
                                }
                            }
                        }
                    }
                }
            }

            // 成功した場合はコミットする
            $this->_read->commit();
            $this->_read->query('commit');

        } catch (Exception $e) {
            // 失敗した場合はロールバックしてエラーメッセージを返す
            $this->_read->rollBack();
            $this->_read->query('rollback');
            //var_dump($e->getMessage());exit();
            return false;
        }
        return $data;

    }


    /**
     * ジャンル検索のための
     * @param $start
     * @param $end
     * @return array
     */
    public function getProjectDataGenre($start, $end)
    {

        $this->_read->beginTransaction();
        $this->_read->query('begin');
        try {

            $select = $this->_read->select();
            $select->from('genre_data', array('gd_index', 'gd_index_label'))
                ->distinct();
            $stmt = $select->query();
            $_genre =  $stmt->fetchAll();
            $data = array();
            foreach ($_genre as $key => $name) {
                $select = $this->_read->select();
                $select->from('genre_data')
                    ->where('gd_index = ?', $name['gd_index']);
                $stmt = $select->query();
                $data[$key]['name'] = $name['gd_index_label'];
                $data[$key]['kind'] = $name['gd_index'];
                $data[$key]['info'] = $stmt->fetchAll();

                foreach ($data[$key]['info'] as $name => $item) {
                    $select = $this->_read->select();
                    $select->from('project_summary_89', 'ps_pid')
                        ->join('project_data_89', 'ps_pd_pid = pd_pid')
                        ->joinLeft('project_time_89', 'ps_pt_pid = pt_pid')
                        ->where('pd_active_flg = ?', 1)
                        ->where('pd_genre = ?', $item['gd_index'])
                        ->where('pd_genre_detail = ?', $item['gd_detail']);
                    $stmt = $select->query();
                    $_data = $stmt->fetchAll();

                    if (!$start && !$end) {
                        $data = $_data;
                    } else {
                        $i = 0;
                        foreach ($_data as $item2) {
                            if (!$item2['pt_pid']) {
                                $data[$key]['info'][$name]['data'][$i] = $item2;
                                $i++;
                            } else {
                                if (strtotime($item2['pt_start']) > $start && strtotime($item2['pt_end']) < $end) {
                                    $data[$key]['info'][$name]['data'][$i] = $item2;
                                    $i++;
                                }
                            }
                        }
                    }
                }
            }

            // 成功した場合はコミットする
            $this->_read->commit();
            $this->_read->query('commit');

        } catch (Exception $e) {
            // 失敗した場合はロールバックしてエラーメッセージを返す
            $this->_read->rollBack();
            $this->_read->query('rollback');
            //var_dump($e->getMessage());exit();
            return false;
        }

        return $data;
    }


    public function getProjectDataRec($start, $end)
    {

        $this->_read->beginTransaction();
        $this->_read->query('begin');
        try {
            $select = $this->_read->select();
            $select->from('project_summary_89', 'ps_pid')
                ->join('project_data_89', 'ps_pd_pid = pd_pid')
                ->joinLeft('project_time_89', 'ps_pt_pid = pt_pid')
                ->where('pd_active_flg = ?', 1)
                ->where('pd_rec_flg = ?', 1);
            $stmt = $select->query();
            $_data = $stmt->fetchAll();

            if (!$start && !$end) {
                $data = $_data;
            } else {
                $i = 0;
                foreach ($_data as $item) {
                    if (!$item['pt_pid']) {
                        $data[$i] = $item;
                        $i++;
                    } else {
                        if (strtotime($item['pt_start']) > $start && strtotime($item['pt_end']) < $end) {
                            $data[$i] = $item;
                            $i++;
                        }
                    }
                }
            }

            // 成功した場合はコミットする
            $this->_read->commit();
            $this->_read->query('commit');

        } catch (Exception $e) {
            // 失敗した場合はロールバックしてエラーメッセージを返す
            $this->_read->rollBack();
            $this->_read->query('rollback');
            //var_dump($e->getMessage());exit();
            return false;
        }

        return $data;
    }


    /**
     * 企画を取ってくる
     * @param $ps_pid
     * @return array
     */
    public function getProjectInfo($pt_pid)
    {

        // トランザクション開始
        $this->_read->beginTransaction();
        $this->_read->query('begin');
        try {

            $select = $this->_read->select();
            $select->from('90_project_time');
            $select->join('90_project_data', 'pt_pd_pid = pd_pid')
                ->join('90_project_place','pt_pp_pid = pp_pid')
                ->join('building_data', 'pp_bd_pid = bd_pid');
            $select->where('pd_active_flg = ?', 1)
                ->where('pt_pid = ?', $pt_pid);
            $stmt = $select->query();
            $result = $stmt->fetch();

            // 成功した場合はコミットする
            $this->_read->commit();
            $this->_read->query('commit');
            return $result;
        } catch (Exception $e) {
            // 失敗した場合はロールバックしてエラーメッセージを返す
            $this->_read->rollBack();
            $this->_read->query('rollback');
//            var_dump($e->getMessage());exit();
            return false;
        }
    }

    /**
     * 現在地のbd_pidから建物情報を返す
     * @param $bd_pid
     * @return array
     */
    public function getBuildingData($bd_pid)
    {
        $select = $this->_read->select();
        $select->from('building_data');
        $select->where('bd_active_flg = ?', 1)
            ->where('bd_pid = ?', $bd_pid);
        $stmt = $select->query();
        return $stmt->fetch();
    }

    //建物間の
    public function getTimeInfo($bd_pid1, $bd_pid2)
    {

        // トランザクション開始
        $this->_read->beginTransaction();
        $this->_read->query('begin');
        try {

            $select = $this->_read->select();
            $select->from('90_checkpos_data');
            $select->where('cd_active_flg = ?', 1)
                ->where('cd_bd_pid1 = ?', $bd_pid1)
                ->where('cd_bd_pid2 = ?', $bd_pid2);
            $stmt = $select->query();
            $data = $stmt->fetch();

            // 成功した場合はコミットする
            $this->_read->commit();
            $this->_read->query('commit');
            return $data['cd_time'];
        } catch (Exception $e) {
            // 失敗した場合はロールバックしてエラーメッセージを返す
            $this->_read->rollBack();
            $this->_read->query('rollback');
//            var_dump($e->getMessage());exit();
            return false;
        }
    }

    public function getOrderWay($bd_pid1,$bd_pid2)
    {


        // トランザクション開始
        $this->_read->beginTransaction();
        $this->_read->query('begin');
        try {

            $select = $this->_read->select();
            $select->from('90_checkpos_data');
            $select->where('cd_active_flg = ?', 1)
                ->where('cd_bd_pid1 = ?', $bd_pid1)
                ->where('cd_bd_pid2 = ?', $bd_pid2);
            $stmt = $select->query();
            $res = $stmt->fetch();

            if ($res['cd_pid'] && $res['cd_bd_pid1'] != $res['cd_bd_pid2']) {
                $select = $this->_read->select();
                $select->from('90_checkpos_order');
                $select->where('co_active_flg = ?', 1)
                    ->where('co_cd_pid = ?', $res['cd_pid'])
                    ->order('co_order');
                $stmt = $select->query();
                $_data = $stmt->fetchAll();

                $node_num = count($_data) + 1;

                foreach ($_data as $key => $item) {
                    $data[$item['co_order']] = $item['co_node1'];
                    if ($key == $node_num - 2) {
                        $data[$node_num] = $item['co_node2'];
                    }
                }
            } else {
                $data = false;
            }

            // 成功した場合はコミットする
            $this->_read->commit();
            $this->_read->query('commit');
            return $data;
        } catch (Exception $e) {
            // 失敗した場合はロールバックしてエラーメッセージを返す
            $this->_read->rollBack();
            $this->_read->query('rollback');
//            var_dump($e->getMessage());exit();
            return false;
        }

    }


    /**
     * step2用　フリーワード
     * @param $date
     * @param $start
     * @return bool
     */
    public function getFreeWords($date, $start)
    {
        // トランザクション開始
        $this->_read->beginTransaction();
        $this->_read->query('begin');
        try {

            //建物名から検索
            $select = $this->_read->select();
            $select->from('building_data', array('bd_p_label2', 'bd_pid'));
            $select->where('bd_pos_flg = ?', 1);
            $stmt = $select->query();
            $_data = $stmt->fetchAll();

            foreach ($_data as $key => $item) {
                $data[$key]['bd_pid'] = $item['bd_pid'];
                $data[$key]['name'] = $item['bd_p_label2'];
                $data[$key]['bd_flg'] = 1;
            }

            //その他の建物名から検索
            $select = $this->_read->select();
            $select->from('building_other', array('bo_label', 'bo_bd_pid'))
                ->join('building_data', 'bo_bd_pid = bd_pid', 'bd_p_label2');
            $select->where('bo_active_flg = ?', 1);
            $stmt = $select->query();
            $_data = $stmt->fetchAll();

            foreach ($_data as $key => $item) {
                $arr = array();
                $arr['bd_pid'] = $item['bo_bd_pid'];
                $arr['name'] = $item['bd_p_label2'];
                $arr['data'] = $item['bo_label'];
                array_push($data, $arr);
            }


            //企画名から検索
            $select = $this->_read->select();
            $select->from('90_project_time',  array('pt_pid', 'pt_start_', 'pt_end_', 'pt_open_'))
                ->join('90_project_data', 'pt_pd_pid = pd_pid', array('pd_pid', 'pd_body', 'pd_label'))
                ->join('90_project_place', 'pt_pp_pid = pp_pid', array('pp_place', 'pp_bd_pid'))
                ->join('building_data', 'pp_bd_pid = bd_pid', array('bd_pos_flg'))
                ->where('pd_active_flg = ?', 1)
                ->where('bd_pos_flg = ?', 1)
                ->where('pp_day = ?', $date)
                ->order('pd_pid');
            $stmt = $select->query();
            $_data = $stmt->fetchAll();

            foreach ($_data as $item) {
                if ($item['pp_full']) {
                    $arr['bd_pid'] = $item['pp_bd_pid'];
                    $arr['name'] = $item['pd_label'];
                    $arr['data'] = $item['pd_body'];
                    array_push($data, $arr);
                } else {
                    if ($item['pt_start_']) {
                        if ($item['pt_end_']) {
                            if ($start < $item['pt_end_'] + 60 || $start > $item['pt_start_'] - 60 ) {
                                $arr['bd_pid'] = $item['pp_bd_pid'];
                                $arr['name'] = $item['pd_label'];
                                $arr['body'] = $item['pd_body'];
                                array_push($data, $arr);
                            }
                        } else {
                            if ($start > $item['pt_start'] - 60) {
                                $arr['bd_pid'] = $item['pp_bd_pid'];
                                $arr['name'] = $item['pd_label'];
                                $arr['body'] = $item['pd_body'];
                                array_push($data, $arr);
                            }
                        }
                    }
                }
            }


            // 成功した場合はコミットする
            $this->_read->commit();
            $this->_read->query('commit');
            return $data;
        } catch (Exception $e) {
            // 失敗した場合はロールバックしてエラーメッセージを返す
            $this->_read->rollBack();
            $this->_read->query('rollback');
            var_dump($e->getMessage());exit();
            return false;
        }
    }



    /* public function getProjectDataForArea()
     {
         $select = $this->_read->select();
         $select->from('project_data_89');
         $select->where('active_flg = ?', 1);
         $stmt = $select->query();
         $_data = $stmt->fetchAll();

         $i = 0;
         foreach ($_data as $item) {
             $data1 = null;
             $data2 = null;
             $data3 = null;
             $data4 = null;

             $data[$i]['name'] = $item['name'];
             $data[$i]['body'] = $item['body'];
             $data[$i]['web'] = $item['web'];
             $data[$i]['genre'] = $item['genre'];
             $data[$i]['genre_detail'] = $item['genre_detail'];
             $data[$i]['area'] = $item['area1'];
             $data[$i]['place'] = $item['place1'];
             $data[$i]['place_index'] = $item['place_index1'];
             $data[$i]['place_detail'] = $item['place_detail1'];
             $data[$i]['pid'] = $item['pid'];
             $data[$i]['start1'] = $item['start1-1'];
             $data[$i]['end1'] = $item['end1-1'];
             $data[$i]['start2'] = $item['start1-2'];
             $data[$i]['end2'] = $item['end1-2'];
             $data[$i]['start3'] = $item['start1-3'];
             $data[$i]['end3'] = $item['end1-3'];
             $data[$i]['start4'] = $item['start1-4'];
             $data[$i]['end4'] = $item['end1-4'];
             $i++;

             if ($item['place_index2'] || $item['place2'] || $item['place_index2']) {

                 if ($item['place_index1'] != $item['place_index2']) {
                     $data[$i]['name'] = $item['name'];
                     $data[$i]['body'] = $item['body'];
                     $data[$i]['web'] = $item['web'];
                     $data[$i]['genre'] = $item['genre'];
                     $data[$i]['genre_detail'] = $item['genre_detail'];
                     $data[$i]['area'] = $item['area2'];
                     $data[$i]['place'] = $item['place2'];
                     $data[$i]['place_index'] = $item['place_index2'];
                     $data[$i]['place_detail'] = $item['place_detail2'];
                     $data[$i]['pid'] = $item['pid'];
                     $data[$i]['start1'] = $item['start2-1'];
                     $data[$i]['end1'] = $item['end2-1'];
                     $data[$i]['start2'] = $item['start2-2'];
                     $data[$i]['end2'] = $item['end2-2'];
                     $data[$i]['start3'] = $item['start2-3'];
                     $data[$i]['end3'] = $item['end2-3'];
                     $data[$i]['start4'] = $item['start2-4'];
                     $data[$i]['end4'] = $item['end2-4'];
                     $i++;
                 }

                 if ($item['place_index3'] || $item['place3'] || $item['place_index3']) {
                     if ($item['place_index1'] != $item['place_index3'] && $item['place_index3'] != $item['place_index2']) {
                         $data[$i]['name'] = $item['name'];
                         $data[$i]['body'] = $item['body'];
                         $data[$i]['web'] = $item['web'];
                         $data[$i]['genre'] = $item['genre'];
                         $data[$i]['genre_detail'] = $item['genre_detail'];
                         $data[$i]['area'] = $item['area3'];
                         $data[$i]['place'] = $item['place3'];
                         $data[$i]['place_index'] = $item['place_index3'];
                         $data[$i]['place_detail'] = $item['place_detail3'];
                         $data[$i]['pid'] = $item['pid'];
                         $i++;
                     }

                     if ($item['place_index4'] || $item['place4'] || $item['place_index4']) {
                         if ($item['place_index1'] != $item['place_index4'] && $item['place_index2'] != $item['place_index4'] && $item['place_index3'] != $item['place_index4']) {

                             $data[$i]['name'] = $item['name'];
                             $data[$i]['body'] = $item['body'];
                             $data[$i]['web'] = $item['web'];
                             $data[$i]['genre'] = $item['genre'];
                             $data[$i]['genre_detail'] = $item['genre_detail'];
                             $data[$i]['area'] = $item['area2'];
                             $data[$i]['place'] = $item['place2'];
                             $data[$i]['place_index'] = $item['place_index2'];
                             $data[$i]['place_detail'] = $item['place_detail2'];
                             $data[$i]['pid'] = $item['pid'];
                             $i++;
                         }

                     }
                 }
             }

         }

         echo "<pre>";
         var_dump($data);
         echo "</pre>";

         return $data;
     }*/

    /*
    /**
     * タイムデータ保存
     *
     * @param $pid1
     * @param $pid2
     * @param $time
     * @return bool
     */
    /*public function insertTimeInfo($pid1,$pid2,$time)
    {
        $this->_write->beginTransaction();
        $this->_write->query('begin');
        try {
            $insert = array();
            $insert['pid1'] = $pid1;
            $insert['pid2'] = $pid2;
            $insert['time'] = $time;

            $this->_write->insert('place_time', $insert);

            // 成功した場合はコミットする
            $this->_write->commit();
            $this->_write->query('commit');

            return true;
        } catch (Exception $e) {
            // 失敗した場合はロールバックしてエラーメッセージを返す
            $this->_write->rollBack();
            $this->_write->query('rollback');
            //var_dump($e->getMessage());exit();
            return false;
        }
    }*/

    /**
     * nochangeデータからproject_dataを作成
     * @return bool
     */
     public function modifyProjectData()
     {
         $this->_read->beginTransaction();
         $this->_read->query('begin');
         try {
             $select = $this->_read->select();
             $select->from('90_project_data_nochange')
                 ->order('pd_pid');
             $stmt = $select->query();
             $data = $stmt->fetchAll();

         } catch (Exception $e) {
             // 失敗した場合はロールバックしてエラーメッセージを返す
             $this->_read->rollBack();
             $this->_read->query('rollback');
             var_dump($e->getMessage());exit();
             return false;
         }

         $arr = array(
             'pd_full_20',
             'pd_full_21',
             'pd_day_20',
             'pd_day_21',
         );

         $arr2 = array('open', 'start', 'end', 'note');

         $arr3 = array('pd_rec_flg','pd_pickup_flg','pd_academic_flg');

         foreach ($data as $key => $item) {
             $select = $this->_read->select();
             $select->from('genre_data')
                 ->where('gd_index_label = ?', $item['pd_genre1_'])
                 ->where('gd_detail_label = ?', $item['pd_genre2_']);
             $stmt = $select->query();
             $genre = $stmt->fetch();

             $insert = array();
             $insert = $item;
             $insert['pd_genre1'] = $genre['gd_index'];
             $insert['pd_genre2'] = $genre['gd_detail'];

             if ($genre['gd_genre2_'] == '屋外模擬店（飲食物）') {
                 $insert['pd_active_flg'] = 0;
             } else {
                 $insert['pd_active_flg'] = 1;
             }

             foreach ($arr as $val) {
                 if ($item[$val] == 'true') $insert[$val] = 1;
                 else $insert[$val] = 0;
             }

             for ($i = 1; $i < 10; $i++) {
                 foreach ($arr2 as $val) {
                     if ($item['pd_'.$val.$i] == 'undefined' || strlen($item['pd_'.$val.$i]) == 0) {
                         $insert['pd_'.$val.$i] = NULL;
                     } elseif (strlen($item['pd_'.$val.$i]) == 4) {
                         $insert['pd_'.$val.$i] = "0".$insert['pd_'.$val.$i];
                     }
                 }
             }

             $select = $this->_read->select();
             $select->from('90_recommend', $arr3)
                 ->where('pd_pid = ?', $item['pd_pid']);
             $stmt = $select->query();
             $rec = $stmt->fetch();

             if ($rec) {
                 foreach ($arr3 as $val) {
                     if ($rec[$val]) $insert[$val] = 1;
                 }
             } else {
                 var_dump("ERROR おすすめフラグ等がない");
             }

             echo "<pre>";
             var_dump($insert);
             echo "</pre>";

             $this->_write->beginTransaction();
             $this->_write->query('begin');

             try {

                 $this->_write->insert('90_project_data', $insert);

                 // 成功した場合はコミットする

                 $this->_write->commit();
                 $this->_write->query('commit');
             } catch (Exception $e) {
                 // 失敗した場合はロールバックしてエラーメッセージを返す
                 $this->_write->rollBack();
                 $this->_write->query('rollback');
                 var_dump($e->getMessage());
                 exit();
                 return false;
             }



         }

         exit();
     }

     public function modifyDataPlace()
     {
         $this->_read->beginTransaction();
         $this->_read->query('begin');
         try {
             $select = $this->_read->select();
             $select->from('90_project_data')
                 ->where('pd_active_flg = ?', 1)
                 ->order('pd_pid');
             $stmt = $select->query();
             $data = $stmt->fetchAll();

         } catch (Exception $e) {
             // 失敗した場合はロールバックしてエラーメッセージを返す
             $this->_read->rollBack();
             $this->_read->query('rollback');
             var_dump($e->getMessage());exit();
             return false;
         }

         $arr = array('20', '21');
         $arr2 = array('open', 'start', 'end', 'note');
         foreach ($data as $item) {
             foreach ($arr as $day) {
                 if ($item['pd_day_'.$day]) {
                     $insert = array();
                     $insert['pp_pd_pid'] = $item['pd_pid'];
                     $insert['pp_place']  = $item['pd_place'];
                     $insert['pp_day']    = $day;
                     $insert['pp_full']   = $item['pd_full_'.$day];
                     $insert['pp_pd_active_flg'] = $item['pd_active_flg'];
                     if ($day == "20") {
                         $N = 1; $M = 5;
                     } else {
                         $N = 5; $M = 10;
                     }
                     $j = 1;
                     for ($i = $N; $i < $M; $i++) {
                         foreach ($arr2 as $val) {
                             if ($item['pd_'.$val.$i]) {
                                 $insert['pp_'.$val.$j] = $item['pd_'.$val.$i];
                             }
                         }
                         ++$j;
                     }

                     $select = $this->_read->select();
                     $select->from('__90_project_place', array('pp_bd_pid', 'pp_name1', 'pp_name2'))
                         ->where('pp_pd_pid = ?', $item['pd_pid']);
                     $stmt = $select->query();
                     $bld = $stmt->fetch();

                     if ($bld) {
                         $insert = array_merge($insert, $bld);
                     } else {
                         var_dump("建物データがありません");
                     }
                     echo "<pre>";
                     var_dump($insert);
                     echo "</pre>";



                     $this->_write->beginTransaction();
                     $this->_write->query('begin');

                     try {

                         $this->_write->insert('90_project_place', $insert);

                         // 成功した場合はコミットする

                         $this->_write->commit();
                         $this->_write->query('commit');
                     } catch (Exception $e) {
                         // 失敗した場合はロールバックしてエラーメッセージを返す
                         $this->_write->rollBack();
                         $this->_write->query('rollback');
                         var_dump($e->getMessage());
                         exit();
                         return false;
                     }


                 }

             }
         }
         exit();
     }

     public function modifyPlaceTime()
     {
         $this->_read->beginTransaction();
         $this->_read->query('begin');
         try {
             $select = $this->_read->select();
             $select->from('90_project_place')
                 ->order('pp_pid');
             $stmt = $select->query();
             $data = $stmt->fetchAll();

         } catch (Exception $e) {
             // 失敗した場合はロールバックしてエラーメッセージを返す
             $this->_read->rollBack();
             $this->_read->query('rollback');
             var_dump($e->getMessage());
             exit();
             return false;
         }
         $arr = array('open', 'start', 'end', 'note');

         foreach ($data as $item) {
             $insert = array();
             $insert['pt_pd_pid'] = $item['pp_pd_pid'];
             $insert['pt_pp_pid'] = $item['pp_pid'];
             $insert['pt_pd_active_flg'] = $item['pp_pd_active_flg'];
             $insert['pt_full'] = $item['pp_full'];

             $select = $this->_read->select();
             $select->from('90_staytime')
                 ->where('id = ?', $item['pp_pd_pid']);
             $stmt = $select->query();
             $time = $stmt->fetch();
             if ($time) $insert['pt_time'] = $time['滞在時間目安'];
             else var_dump("ERROR 滞在時間目安がありません");

             if ($item['pp_full']) {
                 echo "<pre>";
                 var_dump($insert);
                 echo "</pre>";

                 $this->_write->beginTransaction();
                 $this->_write->query('begin');
                 try {

                     $this->_write->insert('90_project_time', $insert);

                     // 成功した場合はコミットする

                     $this->_write->commit();
                     $this->_write->query('commit');
                 } catch (Exception $e) {
                     // 失敗した場合はロールバックしてエラーメッセージを返す
                     $this->_write->rollBack();
                     $this->_write->query('rollback');
                     var_dump($e->getMessage());
                     exit();
                     return false;
                 }
             }

             $flg = array();
             for ($i=1; $i<6; $i++) {
                 foreach ($arr as $val) {
                     if ($item['pp_'.$val.$i]) {
                         $flg[$i] = 1; break;
                     }
                 }
             }

             for ($i=1; $i<6; $i++) {
                 if ($flg[$i]) {
                     foreach ($arr as $val) {
                         $insert['pt_' . $val] = $item['pp_' . $val . $i];
                         if ($item['pt_'.$val]) {
                             $update['pt_' . $val . '_'] = intval(substr($item['pt_' . $val], 0, 2)) * 60 + intval(substr($item['pt_' . $val],3,2));
                         }
                     }
                     echo "<pre>";
                     var_dump($insert);
                     echo "</pre>";
                     $this->_write->beginTransaction();
                     $this->_write->query('begin');
                     try {

                         $this->_write->insert('90_project_time', $insert);

                         // 成功した場合はコミットする

                         $this->_write->commit();
                         $this->_write->query('commit');
                     } catch (Exception $e) {
                         // 失敗した場合はロールバックしてエラーメッセージを返す
                         $this->_write->rollBack();
                         $this->_write->query('rollback');
                         var_dump($e->getMessage());
                         exit();
                         return false;
                     }
                 }
             }

         }
         exit();
     }

     public function timeFix()
     {

         $this->_read->beginTransaction();
         $this->_read->query('begin');
         try {
             $select = $this->_read->select();
             $select->from('90_project_time');
             $stmt = $select->query();
             $data = $stmt->fetchAll();

         } catch (Exception $e) {
             // 失敗した場合はロールバックしてエラーメッセージを返す
             $this->_read->rollBack();
             $this->_read->query('rollback');
             var_dump($e->getMessage());exit();
             return false;
         }

         $arr = array('20', '21');
         $arr2 = array('open', 'start', 'end');

         foreach ($data as $key => $item) {

             $update = array();
             foreach ($arr2 as $val) {
                 if ($item['pt_'.$val]) {
                     $update['pt_' . $val . '_'] = intval(substr($item['pt_' . $val], 0, 2)) * 60 + intval(substr($item['pt_' . $val],3,2));
                 }
             }
             $where = '';
             $where[] = "pt_pid = '{$item['pt_pid']}'";

             echo "<pre>";
             var_dump($update);
             echo "</pre>";
             if (count($update) > 0) {




                 $this->_write->beginTransaction();
                 $this->_write->query('begin');

                 try {

                     $this->_write->update('90_project_time', $update, $where);

                     // 成功した場合はコミットする

                     $this->_write->commit();
                     $this->_write->query('commit');
                 } catch (Exception $e) {
                     // 失敗した場合はロールバックしてエラーメッセージを返す
                     $this->_write->rollBack();
                     $this->_write->query('rollback');
                     var_dump($e->getMessage());
                     exit();
                     return false;
                 }


             }

         }


             /*

             foreach ($data as $key => $item) {

                 $select = $this->_read->select();
                 $select->from('_90_project_place')
                     ->where('pp_pd_pid = ?', $item['pp_pd_pid'])
                     ->where('pp_day = ?', $item['pp_day']);
                 $stmt = $select->query();
                 $place = $stmt->fetch();


                 $update = array();
                 $update['pp_bd_pid'] = $place['pp_bd_pid'];
                 $update['pp_name1'] = $place['pp_name1'];
                 $update['pp_name2'] = $place['pp_name2'];
                 $update['pp_place_'] = $place['pp_place'];


                 echo "<pre>";
                 var_dump($place);
                 var_dump($update);
                 echo "</pre>";


                 $where = '';
                 $where[] = "pp_pid = '{$item['pp_pid']}";

                 $this->_write->beginTransaction();
                 $this->_write->query('begin');

                 try {

                     $this->_write->update('90_project_place', $update, $where);

                     // 成功した場合はコミットする

                     $this->_write->commit();
                     $this->_write->query('commit');
                 } catch (Exception $e) {
                     // 失敗した場合はロールバックしてエラーメッセージを返す
                     $this->_write->rollBack();
                     $this->_write->query('rollback');
                     var_dump($e->getMessage());
                     exit();
                     return false;
                 }



             }
             */

         exit();


    }

    public function Fix2()
    {
        $this->_read->beginTransaction();
        $this->_read->query('begin');
        try {
            $select = $this->_read->select();
            $select->from('90_project_place')
                ->where('pp_full = ? ', 0)
                ->where('pp_start1 IS NULL');
            $stmt = $select->query();
            $data = $stmt->fetchAll();


            $this->_read->commit();
            $this->_read->query('commit');
        } catch (Exception $e) {
            // 失敗した場合はロールバックしてエラーメッセージを返す
            $this->_read->rollBack();
            $this->_read->query('rollback');
            var_dump($e->getMessage());exit();
            return false;
        }

        $arr = array("20", "21");
        $arr2 = array('open', 'start', 'end', 'note');

        foreach ($data as $item) {


            $insert = array();
            $insert['pt_pd_pid'] = $item['pp_pd_pid'];
            $insert['pt_pp_pid'] = $item['pp_pid'];
            $insert['pt_pd_active_flg'] = $item['pp_pd_active_flg'];

            $this->_write->beginTransaction();
            $this->_write->query('begin');

            try {

                $this->_write->insert('90_project_time', $insert);

                // 成功した場合はコミットする

                $this->_write->commit();
                $this->_write->query('commit');
            } catch (Exception $e) {
                // 失敗した場合はロールバックしてエラーメッセージを返す
                $this->_write->rollBack();
                $this->_write->query('rollback');
                var_dump($e->getMessage());
                exit();
                return false;
            }
        }
    }


}