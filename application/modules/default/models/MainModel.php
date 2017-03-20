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
                ->order('sc_order ASC')
                ->where($this->_read->quoteInto('sc_lang = ?', $lang))
                ->where($this->_read->quoteInto('sc_path = ?', 'form') . ' OR ' .
                    $this->_read->quoteInto('sc_path = ?', 'list') . ' OR ' .
                    $this->_read->quoteInto('sc_path = ?', 'page'));

            $stmt = $select->query();
            $data = $stmt->fetchAll();

            if ($data) {

                foreach ($data as $val) {

                    if ($val['sc_path'] != 'form') {
                        $item = $val['sc_text'];
                    } else {
                        $item = array(
                            'lang' => $val['sc_lang'],
                            'kind' => $val['sc_kind'],
                            'key' => $val['sc_key'],
                            'name' => $val['sc_text'],
                            'type' => $val['sc_type'],
                        );
                    }

                    if ($val['sc_path'] == 'form') {
                        $result[$val['sc_path']][$val['sc_kind']][] = $item;
                    } elseif ($val['sc_path'] == 'list') {
                        $result[$val['sc_path']][$val['sc_kind']][$val['sc_key']] = $item;
                    } else {
                        $result[$val['sc_path']][$val['sc_key']] = $item;
                    }
                }

                if ($path) {

                    $select = $this->_read->select();
                    $select->from('site_contents');
                    $select->where('sc_active_flg = ?', 1)
                        ->order('sc_order ASC')
                        ->where($this->_read->quoteInto('sc_lang = ?', $lang))
                        ->where($this->_read->quoteInto('sc_path = ?', $path));
                    $stmt = $select->query();
                    $data_path = $stmt->fetchAll();

                    if ($data_path) {
                        foreach ($data_path as $val) {
                            $result[$val['sc_path']][$val['sc_key']] = $val['sc_text'];
                        }
                    }
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

    public function getProjectData()
    {
        $select = $this->_read->select();
        $select->from('project_data_89');
        $select->where('pd_active_flg = ?', 1);
        $stmt = $select->query();
        $data = $stmt->fetchAll();

        foreach ($data as $key => $item) {
            $select = $this->_read->select();
            $select->from('project_place_89');
            $select->where('pp_active_flg = ?', 1)
                ->where('pp_pd_pid = ?', $item['pd_pid']);
            //->order('sc_order ASC')

            $stmt = $select->query();
            $data[$key]['place_info'] = $stmt->fetchAll();


            foreach ($data[$key]['place_info'] as $key2 => $item2) {

                if ($item2['pp_fulltime'] == 0 ) {

                    $select = $this->_read->select();
                    $select->from('project_time_89');
                    $select->where('pt_active_flg = ?', 1)
                        ->where('pt_pp_pid = ?', $item2['pp_pid']);
                    //->order('sc_order ASC')

                    $stmt = $select->query();
                    $data[$key]['place_info'][$key2]['time_info'] = $stmt->fetchAll();
                }
            }
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
     */
    public function getProjectDataArea()
    {
        $arr = array('no_dept', 'ko_dept', 'yasuko','akamon');

        $data = array();
        foreach ($arr as $name) {
            $select = $this->_read->select();
            $select->from('building_data');
            $select->where('bd_active_flg = ?', 1)
                ->where('bd_kind = ?', $name);
            $stmt = $select->query();
            $bld = $stmt->fetchAll();
            foreach ($bld as $item) {

                $select = $this->_read->select();
                $select->from('project_place_89')
                    ->joinLeft('project_data_89', 'pp_pd_pid = pd_pid');
                $select->where('pd_active_flg = ?', 1)
                    ->where('pp_active_flg = ?', 1)
                    ->where('pp_area = ?', $name)
                    ->where('pp_place_index = ?', $item['bd_name']);
                $stmt = $select->query();
                $data[$name][$item['bd_name']] = $stmt->fetchAll();

                foreach ($data[$name][$item['bd_name']] as $key => $item2) {

                    $select = $this->_read->select();
                    $select->from('project_summary_89', 'ps_pid');
                    $select->where('ps_active_flg = ?', 1)
                        ->where('ps_pd_pid = ?', $item2['pd_pid'])
                        ->where('ps_pp_pid = ?', $item2['pp_pid']);
                    $stmt = $select->query();
                    $ps_pid = $stmt->fetch();
                    $data[$name][$item['bd_name']][$key] = array_merge($data[$name][$item['bd_name']][$key], $ps_pid);
                }

            }
        }

        return $data;

    }


    /**
     * ジャンル検索のための
     */
    public function getProjectDataGenre()
    {
        $arr = array('join', 'exhibition', 'music'); //他にlec_or_c

        $data = array();
        foreach ($arr as $name) {
            $select = $this->_read->select();
            $select->from('building_data');
            $select->where('bd_active_flg = ?', 1)
                ->where('bd_kind = ?', $name);
            $stmt = $select->query();
            $bld = $stmt->fetchAll();
            foreach ($bld as $item) {

                $select = $this->_read->select();
                $select->from('project_place_89')
                    ->joinLeft('project_data_89', 'pp_pd_pid = pd_pid');
                $select->where('pd_active_flg = ?', 1)
                    ->where('pp_active_flg = ?', 1)
                    ->where('pp_area = ?', $name)
                    ->where('pp_place_index = ?', $item['bd_name']);
                $stmt = $select->query();
                $data[$name][$item['bd_name']] = $stmt->fetchAll();
            }
        }

        return $data;

        echo "<pre>";
        var_dump($_data);
        echo "</pre>";
        exit();

        foreach ($_data as $name => $item) {


            foreach ($item as $key => $val) {
                $data[$name][$val['pp_place_index']] = $val;
            }
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


     public function timeFix()
     {
         /*$select = $this->_read->select();
         $select->from('project_place_89')
             ->join('project_data_89', 'pp_pd_pid = pd_pid');
         $select->where('fulltime = ?', 0);
         $stmt = $select->query();
         $data = $stmt->fetchAll();

         $_data = null;
         $i = 1;
         foreach ($data as $key => $item) {
             if ($_data['pd_pid'] == $item['pd_pid']) {
                 $i++;
             } else {
                 $i = 1;
             }
             $_data = $item;
                 echo "<br>";
             for ($j = 1; $j < 5; $j++) {
                 if ($item['start' . $i . '-' . $j]) {
                     $insert = array();
                     $insert['pt_pd_pid'] = $item['pd_pid'];
                     $insert['pt_pp_pid'] = $item['pp_pid'];
                     $insert['pt_start'] = $item['start' . $i . '-' . $j];
                     $insert['pt_end'] = $item['end' . $i . '-' . $j];

                     echo "<pre>";
                     var_dump($insert);
                     echo "</pre>";

                     $this->_write->beginTransaction();
                     $this->_write->query('begin');
                     try {

                         $this->_write->insert('project_time_89', $insert);

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

         exit();*/



            $i = 0;
            $select = $this->_read->select();
            $select->from('project_place_89')
                ->where('pp_fulltime = ?', 0);
            $stmt = $select->query();
            $_data = $stmt->fetchAll();

            foreach ($_data as $item) {

                $select = $this->_read->select();
                $select->from('project_time_89')
                    ->where('pt_pp_pid = ?', $item['pp_pid']);
                $stmt = $select->query();
                $data = $stmt->fetchAll();
                echo "<pre>";
                var_dump($data);
                echo "</pre>";

                if ($data) {
                    $num = count($data);

                    $update = array();
                    $update['pp_time_count'] = $num;

                    echo "<pre>";
                    var_dump($update);
                    echo "</pre>";
                    $i++;

                    $this->_write->beginTransaction();
                    $this->_write->query('begin');
                    try {


                        $where = '';
                        $where[] = "pp_pid = '{$item['pp_pid']}'";

                        $this->_write->update('project_place_89', $update, $where);

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
            echo $i;
            exit();
    /*
            $_pd_pid = null;
            foreach ($data as $key => $item) {
                if ($_pd_pid != $item['pid']) {
                    $_pd_pid = $item['pid'];

                    $insert = array();
                    $insert['_inc_pid'] = $item['inc_pid'];
                    $insert['pd_pid'] = $item['pid'];
                    $insert['pd_name'] = $item['name'];
                    $insert['pd_body'] = $item['body'];
                    $insert['pd_web'] = $item['web'];
                    $insert['pd_genre'] = $item['genre'];
                    $insert['pd_genre_detail'] = $item['genre_detail'];
                    $insert['pd_fulltime'] = $item['fulltime'];
                    $insert['pd_active_flg'] = $item['active_flg'];

                    echo "<pre>";
                    var_dump($insert);
                    echo "</pre>";


                    $this->_write->beginTransaction();
                    $this->_write->query('begin');
                    try {

                        $this->_write->insert('project_data_89_new', $insert);

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

            exit();

            return true;*/

    }

}