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
        $this->_write->query("set names 'utf8'");

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
        $select->from('project_summary_89')
            ->join('project_data_89', 'ps_pd_pid = pd_pid')
            ->join('project_place_89', 'ps_pp_pid = pp_pid')
            ->joinLeft('project_time_89', 'ps_pt_pid = pt_pid');
        $select->where('pd_active_flg = ?', 1);
        $stmt = $select->query();
        return $stmt->fetchAll();
    }


    /**
     * 企画データを取得
     *
     * @return array
     */

    public function getProjectData($start, $end)
    {
        $this->_read->beginTransaction();
        $this->_read->query('begin');
        try {
            $select = $this->_read->select();
            $select->from('project_summary_89', 'ps_pid')
                ->join('project_data_89', 'ps_pd_pid = pd_pid')
                ->join('project_place_89', 'ps_pp_pid = pp_pid')
                ->joinLeft('project_time_89', 'ps_pt_pid = pt_pid')
                ->where('pd_active_flg = ?', 1)
                ->order('pd_pid');
            /*$select->from('project_data_89')
                ->join('project_place_89', 'pd_pid = pp_pd_pid')
                ->joinLeft('project_time_89', 'pp_pid = pt_pp_pid')
                ->where('pd_active_flg = ?', 1);*/
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

    public function getAreaInfo()
    {
        $arr = array('no_dept', 'ko_dept', 'yasuko','akamon');

        $data = array();
        foreach ($arr as $name) {
            $select = $this->_read->select();
            $select->from('building_data');
            $select->where('bd_active_flg = ?', 1)
                ->where('bd_kind = ?', $name);
            $stmt = $select->query();
            $data[$name] = $stmt->fetchAll();
        }

        return $data;
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
                ->distinct();
            $stmt = $select->query();
            $_blding =  $stmt->fetchAll();
            $data = array();
            foreach ($_blding as $key => $name) {
                if ($name['bd_kind'] == 'ko_dept' || $name['bd_kind'] == 'no_dept' || $name['bd_kind'] == 'yasuko' || $name['bd_kind'] == 'akamon' ) {
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
     */
    public function getProjectInfo($ps_pid)
    {

        $select = $this->_read->select();
        $select->from('project_summary_89');
        $select->join('project_data_89', 'ps_pd_pid = pd_pid')
            ->join('project_place_89','ps_pp_pid = pp_pid')
            ->joinLeft('project_time_89', 'ps_pt_pid = pt_pid');
        $select->where('ps_pd_active_flg = ?', 1)
            ->where('ps_pid = ?', $ps_pid);
        $stmt = $select->query();
        return $stmt->fetch();
    }

    //建物間の
    public function getTimeInfo($bd_pid1, $bd_pid2)
    {
        $select = $this->_read->select();
        $select->from('checkpos_data_89');
        $select->where('cd_active_flg = ?', 1)
            ->where('cd_bd_pid1 = ?', $bd_pid1)
            ->where('cd_bd_pid2 = ?', $bd_pid2);
        $stmt = $select->query();
        $data = $stmt->fetch();
        return $data['cd_time'];
    }

    public function getOrderWay($bd_pid1,$bd_pid2)
    {
        $select = $this->_read->select();
        $select->from('checkpos_data_89');
        $select->where('cd_active_flg = ?', 1)
            ->where('cd_bd_pid1 = ?', $bd_pid1)
            ->where('cd_bd_pid2 = ?', $bd_pid2);
        $stmt = $select->query();
        $res = $stmt->fetch();

        $data = array();
        if ($res['cd_pid']) {
            $select = $this->_read->select();
            $select->from('checkpos_order_89');
            $select->where('co_active_flg = ?', 1)
                ->where('co_cd_pid = ?', $res['cd_pid'])
                ->order('co_order');
            $stmt = $select->query();
            $_data = $stmt->fetchAll();
            $node_num = count($_data) + 1;

            foreach ($_data as $key => $item) {
                $data[$_data['co_order']] = $_data['co_node1'];
                if ($key == $node_num - 1) {
                    $data[$node_num] = $_data['co_node2'];
                }
            }
        }
        return $data;

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

    /**
     * フリーワード検索
     *
     * @return array
     */
    public function searchFree()
    {
        //$contents = $this->getContentsData('ja',null);

        /*
        $select = $this->_read->select();
        $select->from('free_words_data')
            ->where('fw_active_flg = ? ', 1);
        //$data = $this->_read->quoteInto('fw_name LIKE ?', '%'.$search.'%');
        /*$select->where('fw_name LIKE ?', '%'.$search.'%')
            ->orwhere('fw_area LIKE ?', '%'.$search.'%')
            ->orwhere('fw_place_index LIKE ?', '%'.$search.'%');*/

        //var_dump($data);exit();
        /*
                $stmt = $select->query();
                $data = $stmt->fetchAll();
                */

        return $data;
    }

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


   /*  public function timeFix()
     {
         $this->_read->beginTransaction();
         $this->_read->query('begin');
         try {
             $select = $this->_read->select();
             $select->from('project_data_89');
             $stmt = $select->query();
             $data = $stmt->fetchAll();
         } catch (Exception $e) {
             // 失敗した場合はロールバックしてエラーメッセージを返す
             $this->_read->rollBack();
             $this->_read->query('rollback');
             var_dump($e->getMessage());exit();
             return false;
         }
         foreach ($data as $item) {

             $update = array();
             if ($item['pd_genre_'] == 'パフォーマンス') {
                 $update['pd_genre'] = 'performance';
                 if ($item['pd_genre_detail_'] == 'お笑い') {
                     $update['pd_genre_detail'] = 'comedy';
                 } elseif ($item['pd_genre_detail_'] == 'ショー') {
                     $update['pd_genre_detail'] = 'show';
                 } elseif ($item['pd_genre_detail_'] == 'スポーツ・武道') {
                     $update['pd_genre_detail'] = 'sports';
                 } elseif ($item['pd_genre_detail_'] == 'ダンス') {
                     $update['pd_genre_detail'] = 'dance';
                 } elseif ($item['pd_genre_detail_'] == '演劇') {
                     $update['pd_genre_detail'] = 'theater';
                 }
             } elseif ($item['pd_genre_'] == '参加型') {
                 $update['pd_genre'] = 'join';
                 if ($item['pd_genre_detail_'] == '体験') {
                     $update['pd_genre_detail'] = 'experience';
                 } elseif ($item['pd_genre_detail_'] == 'ワークショップ') {
                     $update['pd_genre_detail'] = 'workshop';
                 } elseif ($item['pd_genre_detail_'] == 'ゲーム') {
                     $update['pd_genre_detail'] = 'game';
                 } elseif ($item['pd_genre_detail_'] == 'その他') {
                     $update['pd_genre_detail'] = 'others';
                 }
             } elseif ($item['pd_genre_'] == '講演会・討論会') {
                 $update['pd_genre'] = 'lecture';
                 if ($item['pd_genre_detail_'] == 'エンタメ') {
                     $update['pd_genre_detail'] = 'entertain';
                 } elseif ($item['pd_genre_detail_'] == '社会系') {
                     $update['pd_genre_detail'] = 'society';
                 }
             } elseif ($item['pd_genre_'] == '展示・実演') {
                 $update['pd_genre'] = 'exhibition';
                 if ($item['pd_genre_detail_'] == '社会系') {
                     $update['pd_genre_detail'] = 'society';
                 } elseif ($item['pd_genre_detail_'] == '理科系') {
                     $update['pd_genre_detail'] = 'science';
                 } elseif ($item['pd_genre_detail_'] == 'カルチャー') {
                     $update['pd_genre_detail'] = 'culture';
                 } elseif ($item['pd_genre_detail_'] == 'その他') {
                     $update['pd_genre_detail'] = 'others';
                 }
             } elseif ($item['pd_genre_'] == '音楽') {
                 $update['pd_genre'] = 'music';
                 if ($item['pd_genre_detail_'] == '歌唱') {
                     $update['pd_genre_detail'] = 'sing';
                 } elseif ($item['pd_genre_detail_'] == '和楽') {
                     $update['pd_genre_detail'] = 'ja-music';
                 } elseif ($item['pd_genre_detail_'] == 'バンド') {
                     $update['pd_genre_detail'] = 'band';
                 } elseif ($item['pd_genre_detail_'] == 'オーケストラ・吹奏楽') {
                     $update['pd_genre_detail'] = 'orchestra';
                 } elseif ($item['pd_genre_detail_'] == 'その他') {
                     $update['pd_genre_detail'] = 'others';
                 }
             } elseif ($item['pd_genre_'] == '飲食・販売') {
                 $update['pd_genre'] = 'food';
                 if ($item['pd_genre_detail_'] == '物販') {
                     $update['pd_genre_detail'] = 'shop';
                 } elseif ($item['pd_genre_detail_'] == '模擬店（飲食物）') {
                     $update['pd_genre_detail'] = 'mogi-ten';
                 } elseif ($item['pd_genre_detail_'] == '喫茶・バー') {
                     $update['pd_genre_detail'] = 'cafe';
                 }
             }

             $where = '';
             $where[] = "pd_pid = '{$item['pd_pid']}'";

             echo "<pre>";
             var_dump($item['pd_pid']);
             var_dump($update);
             echo "</pre>";



             $this->_write->beginTransaction();
             $this->_write->query('begin');

             try {

                 $this->_write->update('project_data_89', $update, $where);

                 // 成功した場合はコミットする

                 $this->_write->commit();
                 $this->_write->query('commit');
             } catch (Exception $e) {
                 // 失敗した場合はロールバックしてエラーメッセージを返す
                 $this->_write->rollBack();
                 $this->_write->query('rollback');
                 //var_dump($e->getMessage());exit();
                 return false;
             }



         }

         exit();

    }
   */

}