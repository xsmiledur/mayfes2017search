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
        $this->_read->beginTransaction();
        $this->_read->query('begin');
        try {

            $data = array();

            $sql = "";
            $sql .= "SELECT * FROM 90_project_time ";
            $sql .= "INNER JOIN 90_project_data ON pt_pd_pid = pd_pid ";
            $sql .= "INNER JOIN 90_project_place ON pt_pp_pid = pp_pid ";
            $sql .= "WHERE pd_active_flg = '1' ";

            $sql .= $this->ConnectSql($date, $start, $end);

            $sql .= ";";

            $data['data'] = $this->_read->fetchAll($sql);

            // 成功した場合はコミットする
            $this->_read->commit();
            $this->_read->query('commit');

        } catch (Exception $e) {
            // 失敗した場合はロールバックしてエラーメッセージを返す
            $this->_read->rollBack();
            $this->_read->query('rollback');
            var_dump($e->getMessage());exit();
            return false;
        }


        /**エリア別**/


        $area = array(
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

        foreach ($area as $key => $item) {

            $select = $this->_read->select();
            $select->from('building_data', array('bd_pid', 'bd_p_name1', 'bd_p_label1', 'bd_p_name2', 'bd_p_label2' ));
            $select->where('bd_active_flg = ?', 1)
                ->where('bd_p_name1 = ?', $item['name'])
                ->order('bd_order');
            $stmt = $select->query();
            $data['area'][$item['name']] = $stmt->fetchAll();


            foreach ($data['area'][$item['name']] as $key2 => $item2) {

                $this->_read->beginTransaction();
                $this->_read->query('begin');
                try {

                    $sql = "";
                    $sql .= "SELECT * FROM 90_project_time ";
                    $sql .= "INNER JOIN 90_project_data ON pt_pd_pid = pd_pid ";
                    $sql .= "INNER JOIN 90_project_place ON pt_pp_pid = pp_pid ";
                    $sql .= "INNER JOIN building_data ON pp_bd_pid = bd_pid ";
                    $sql .= "WHERE pd_active_flg = '1' ";

                    $sql .= $this->ConnectSql($date, $start, $end);

                    $sql .= "AND pp_name1 = '{$item2['bd_p_name1']}' ";
                    $sql .= "AND pp_name2 = '{$item2['bd_p_name2']}' ";
                    $sql .= ";";

                    $data['area'][$item['name']][$key2]['data'] = $this->_read->fetchAll($sql);

                    // 成功した場合はコミットする
                    $this->_read->commit();
                    $this->_read->query('commit');

                } catch (Exception $e) {
                    // 失敗した場合はロールバックしてエラーメッセージを返す
                    $this->_read->rollBack();
                    $this->_read->query('rollback');
                    var_dump($e->getMessage());exit();
                    return false;
                }

            }
        }


        /**ジャンル別**/

        $genre = array(
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

        foreach ($genre as $key => $item) {


            $select = $this->_read->select();
            $select->from('genre_data', array('gd_pid', 'gd_index', 'gd_index_label', 'gd_detail', 'gd_detail_label'));
            $select->where('gd_active_flg = ?', 1)
                ->where('gd_index = ?', $item['name']);
            $stmt = $select->query();
            $data['genre'][$item['name']] = $stmt->fetchAll();


            foreach ($data['genre'][$item['name']] as $key2 => $item2) {

                $this->_read->beginTransaction();
                $this->_read->query('begin');
                try {

                    $sql = "";
                    $sql .= "SELECT * FROM 90_project_time ";
                    $sql .= "INNER JOIN 90_project_data ON pt_pd_pid = pd_pid ";
                    $sql .= "INNER JOIN 90_project_place ON pt_pp_pid = pp_pid ";
                    $sql .= "WHERE pd_active_flg = '1' ";

                    $sql .= $this->ConnectSql($date, $start, $end);

                    $sql .= "AND pd_genre1 = '{$item2['gd_index']}' ";
                    $sql .= "AND pd_genre2 = '{$item2['gd_detail']}' ";
                    $sql .= ";";

                    $data['genre'][$item['name']][$key2]['data'] = $this->_read->fetchAll($sql);

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


            }

        }


        /**おすすめ企画**/

        $this->_read->beginTransaction();
        $this->_read->query('begin');
        try {

            $sql = "";
            $sql .= "SELECT * FROM 90_project_time ";
            $sql .= "INNER JOIN 90_project_data ON pt_pd_pid = pd_pid ";
            $sql .= "INNER JOIN 90_project_place ON pt_pp_pid = pp_pid ";
            $sql .= "WHERE pd_active_flg = '1' ";

            $sql .= $this->ConnectSql($date, $start, $end);
            $sql .= "AND pd_rec_flg = '1' ";
            $sql .= ";";

            $data['rec'] = $this->_read->fetchAll($sql);

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

        /**ピックアップ企画**/

        $this->_read->beginTransaction();
        $this->_read->query('begin');
        try {

            $sql = "";
            $sql .= "SELECT * FROM 90_project_time ";
            $sql .= "INNER JOIN 90_project_data ON pt_pd_pid = pd_pid ";
            $sql .= "INNER JOIN 90_project_place ON pt_pp_pid = pp_pid ";
            $sql .= "WHERE pd_active_flg = '1' ";

            $sql .= $this->ConnectSql($date, $start, $end);
            $sql .= "AND pd_pickup_flg = '1' ";
            $sql .= ";";

            $data['pickup'] = $this->_read->fetchAll($sql);

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

        /**学術企画**/

        $this->_read->beginTransaction();
        $this->_read->query('begin');
        try {

            $sql = "";
            $sql .= "SELECT * FROM 90_project_time ";
            $sql .= "INNER JOIN 90_project_data ON pt_pd_pid = pd_pid ";
            $sql .= "INNER JOIN 90_project_place ON pt_pp_pid = pp_pid ";
            $sql .= "WHERE pd_active_flg = '1' ";

            $sql .= $this->ConnectSql($date, $start, $end);
            $sql .= "AND pd_academic_flg = '1' ";
            $sql .= ";";

            $data['academic'] = $this->_read->fetchAll($sql);

            // 成功した場合はコミットする
            $this->_read->commit();
            $this->_read->query('commit');

        } catch (Exception $e) {
            // 失敗した場合はロールバックしてエラーメッセージを返す
            $this->_read->rollBack();
            $this->_read->query('rollback');
            var_dump($e->getMessage());exit();
            return false;
        }

        /**委員会**/

        $this->_read->beginTransaction();
        $this->_read->query('begin');
        try {

            $sql = "";
            $sql .= "SELECT * FROM 90_project_time ";
            $sql .= "INNER JOIN 90_project_data ON pt_pd_pid = pd_pid ";
            $sql .= "INNER JOIN 90_project_place ON pt_pp_pid = pp_pid ";
            $sql .= "WHERE pd_active_flg = '1' ";

            $sql .= $this->ConnectSql($date, $start, $end);
            $sql .= "AND pd_com_flg = '1' ";
            $sql .= ";";

            $data['com'] = $this->_read->fetchAll($sql);

            // 成功した場合はコミットする
            $this->_read->commit();
            $this->_read->query('commit');

        } catch (Exception $e) {
            // 失敗した場合はロールバックしてエラーメッセージを返す
            $this->_read->rollBack();
            $this->_read->query('rollback');
            var_dump($e->getMessage());exit();
            return false;
        }

        return $data;
        unset ($data);
    }

    private function ConnectSql($date, $start, $end) {
        /*
         * 日付
         */
        $sql = "AND pp_day = '{$date}' ";
        $sql .= "AND (pt_full = 1 ";

        /*
         * fullでない企画について
         */
        $sql .= "OR pt_full = 0 AND (";

        /*
         * 企画開始時間がある場合
         * ①企画開始時刻が滞在開始時刻よりも遅い
         * ②企画終了時刻が滞在終了時刻よりも早い
         * ③企画開始時刻＋企画標準滞在時刻が滞在終了時刻よりも早い
        */
        $sql .= "pt_start_ IS NOT NULL AND ";
        $sql .= "pt_start_ >= '{$start}' AND ";
        $sql .= "pt_end_ <= '{$end}' AND ";
        $sql .= "pt_start_ + pt_time <= '{$end}' ";

        $sql .= "OR ";

        /*
         * 企画開始時刻は無いが企画開場時刻と企画終了時刻がある場合
         * ①企画開場時刻が滞在終了時刻より早い
         * ②滞在開始時刻＋企画標準滞在時刻が企画終了時刻より早い
         * ＝企画終了時刻ー企画標準滞在時刻が滞在開始時刻より遅い
         */
        $sql .= "pt_start_ IS NULL AND pt_open_ IS NOT NULL AND pt_end_ IS NOT NULL AND ";
        $sql .= "pt_open_ <= '{$end}' AND ";
        $sql .= "pt_end_ - pt_time > '{$start}' ";

        $sql .= "OR ";

        /*
         * 企画開始時刻と企画終了時刻が無いがき各区会場時刻はある場合
         * ①企画開場時刻が滞在終了時刻より早い
         * ②滞在開始時刻＋企画標準滞在時刻が企画終了時刻より早い
         * ＝企画終了時刻ー企画標準滞在時刻が滞在開始時刻より遅い
         */
        $sql .= "pt_start_ IS NULL AND pt_open_ IS NOT NULL AND pt_end_ IS NULL AND ";
        $sql .= "pt_open_ <= '{$end}' AND ";
        $sql .= "'1080' - '{$start}' > pt_time ";

        $sql .= "OR ";

        /*
         * 企画開始時刻も企画開場時刻も無いが企画終了時刻がある場合
         * ①滞在開始時刻＋企画標準滞在時刻が企画終了時刻より早い
         * ＝企画終了時刻ー企画標準滞在時刻が滞在開始時刻より遅い
         */
        $sql .= "pt_start_ IS NULL AND pt_open_ IS NULL AND pt_end_ IS NOT NULL AND ";
        $sql .= "pt_end_ - pt_time > '{$start}' ";

        $sql .= "))";

        return $sql;
    }


    /**
     * 企画を取ってくる
     * @param $pt_pid
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

        // トランザクション開始
        $this->_read->beginTransaction();
        $this->_read->query('begin');
        try {

            $select = $this->_read->select();
            $select->from('building_data');
            $select->where('bd_active_flg = ?', 1)
                ->where('bd_pid = ?', $bd_pid);
            $stmt = $select->query();
            return $stmt->fetch();

        } catch (Exception $e) {
            // 失敗した場合はロールバックしてエラーメッセージを返す
            $this->_read->rollBack();
            $this->_read->query('rollback');
//            var_dump($e->getMessage());exit();
            return false;
        }
    }

    //建物間の
    /**
     * @param $bd_pid1
     * @param $bd_pid2
     * @param $time //定数
     * @param $switch //足すかかけるか 1なら足す 0ならかける
     * @return bool
     */
    public function getTimeInfo($bd_pid1, $bd_pid2)
    {

        $time = 1;
        $switch = 0;
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

            if ($switch == 1) {
                $result = $data['cd_time'] + $time;
            } else {
                $result = $data['cd_time'] * $time;
            }
            return $result;
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
     * @return bool
     */
    public function getFreeWords($date)
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
            $data['blding'] = $stmt->fetchAll();

            //その他の建物名から検索
            $select = $this->_read->select();
            $select->from('building_other', array('bo_label', 'bo_bd_pid'))
                ->join('building_data', 'bo_bd_pid = bd_pid', array('bd_pid', 'bd_p_label2'));
            $select->where('bo_active_flg = ?', 1);
            $stmt = $select->query();
            $data['bld-other'] = $stmt->fetchAll();

            //企画名から検索

            $sql = "";
            $sql .= "SELECT * FROM 90_project_time ";
            $sql .= "INNER JOIN 90_project_data ON pt_pd_pid = pd_pid ";
            $sql .= "INNER JOIN 90_project_place ON pt_pp_pid = pp_pid ";
            $sql .= "INNER JOIN building_data ON pp_bd_pid = bd_pid ";
            $sql .= "WHERE pd_active_flg = '1' ";
            $sql .= "AND bd_pos_flg = '1' ";
            $sql .= "AND pp_day = '{$date}';";
            $data['data'] = $this->_read->fetchAll($sql);

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
             $select->from('90_project_data_nochange_')
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
                     if (substr($item['pd_'.$val.$i],0,9) == 'undefined' || strlen($item['pd_'.$val.$i]) == 0) {
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

                 $this->_write->insert('90_project_data_', $insert);

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
             $select->from('90_project_data_')
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
                         $N = 1; $M = 4;
                     } else {
                         $N = 4; $M = 7;
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

                     /*
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
                     */
                     echo "<pre>";
                     var_dump($insert);
                     echo "</pre>";



                     $this->_write->beginTransaction();
                     $this->_write->query('begin');

                     try {

                         $this->_write->insert('90_project_place_', $insert);

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
             $select->from('90_project_place_')
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

                         $this->_write->insert('90_project_time_', $insert);

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

     public function MakeNoActiveFlg()
     {

         $this->_read->beginTransaction();
         $this->_read->query('begin');
         try {
             $select = $this->_read->select();
             $select->from('90_project_data');
             $stmt = $select->query();
             $data = $stmt->fetchAll();

         } catch (Exception $e) {
             // 失敗した場合はロールバックしてエラーメッセージを返す
             $this->_read->rollBack();
             $this->_read->query('rollback');
             var_dump($e->getMessage());exit();
             //return false;
         }

         foreach ($data as $item) {
             if ($item['pd_genre2_'] == '屋外模擬店（飲食物）') {
                 $update['pd_active_flg'] = 0;
                 $where = '';
                 $where[] = "pd_pid = '{$item['pd_pid']}'";

                 var_dump($item['pd_pid']);

                 $this->_write->beginTransaction();
                 $this->_write->query('begin');

                 try {

                     $this->_write->update('90_project_data', $update, $where);

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
     }

     public function timeFix2()
     {
         $this->_read->beginTransaction();
         $this->_read->query('begin');
         try {
             $select = $this->_read->select();
             $select->from('90_project_data');
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
             if (substr($item['pd_note9'],0,9) == "undefined") {
                 $update['pd_note9'] = NULL;
                 $where = '';
                 $where[] = "pd_pid = '{$item['pd_pid']}'";


                 $this->_write->beginTransaction();
                 $this->_write->query('begin');

                 try {

                     $this->_write->update('90_project_data', $update, $where);

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

        $arr = array("20","21");
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

    public function bddataFix()
    {
        $this->_read->beginTransaction();
        $this->_read->query('begin');
        try {
            $select = $this->_read->select();
            $select->from('building_import');
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

        $update = array();
        foreach ($data as $item) {
            if ($item['name'] == 'bd_pid') {
                $update['bd_pid'] = intval($item['field']);
            } elseif ($item['name'] == 'bd_top2') {
                if (strlen($update['bd_pid']) > 0) $update['bd_top2'] = intval($item['field']);
            } elseif ($item['name'] == 'bd_left2') {
                if (strlen($update['bd_pid']) > 0 && strlen($update['bd_top2']) > 0) $update['bd_left2'] = intval($item['field']);
            }

            if (strlen($update['bd_pid']) > 0 && strlen($update['bd_top2']) > 0 && strlen($update['bd_left2']) > 0) {

                echo "<pre>";
                var_dump($update);
                echo "</pre>";
                $this->_write->beginTransaction();
                $this->_write->query('begin');

                try {

                    $where = '';
                    $where[] = "bd_pid = '{$update['bd_pid']}'";
                    unset($update['bd_pid']);

                    $this->_write->update('building_data', $update, $where);

                    // 成功した場合はコミットする

                    $update = array();

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


    public function bdDataUpdate()
    {
        $this->_read->beginTransaction();
        $this->_read->query('begin');
        try {
            $select = $this->_read->select();
            $select->from('90_project_place_')
                ->join('building_data', 'pp_name1 = bd_p_name1 AND pp_name2 = bd_p_name2', array('bd_pid'))
                ->order('pp_pid');
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

        foreach ($data as $item) {
            $update['pp_bd_pid'] = $item['bd_pid'];
            $where = '';
            $where[] = "pp_pid = '{$item['pp_pid']}'";

            echo "<pre>";
            var_dump($item);
            var_dump($update);
            echo "</pre>";


            $this->_write->beginTransaction();
            $this->_write->query('begin');

            try {

                $this->_write->update('90_project_place_', $update, $where);

                // 成功した場合はコミットする

                $update = array();

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

    public function insertFix()
    {
        $arr = array(
            'pd' => 'data',
            //'pp' => 'place',
            //'pt' => 'time',
        );

        foreach ($arr as $key => $item) {
            $this->_read->beginTransaction();
            $this->_read->query('begin');
            try {
                $select = $this->_read->select();
                $select->from('90_project_'.$item.'_')
                    ->order($key.'_pid');
                $stmt = $select->query();
                $data = $stmt->fetchAll();

                $this->_read->commit();
                $this->_read->query('commit');
            } catch (Exception $e) {
                // 失敗した場合はロールバックしてエラーメッセージを返す
                $this->_read->rollBack();
                $this->_read->query('rollback');
                var_dump($e->getMessage());
                exit();
                return false;
            }
            foreach ($data as $item2) {
                if ($item != 'data') {
                    unset($item2[$key . '_pid']);
                }
                echo "<pre>";
                var_dump($item2);
                echo "</pre>";



                $this->_write->beginTransaction();
                $this->_write->query('begin');
                try {

                    $this->_write->insert('90_project_'.$item, $item2);

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

    public function insertStayTime()
    {
        $this->_read->beginTransaction();
        $this->_read->query('begin');
        try {
            $select = $this->_read->select();
            $select->from('90_project_time')
                ->join('90_staytime', 'pt_pd_pid = id')
                ->where('pt_time IS NULL')
                ->order('pt_pid');
            $stmt = $select->query();
            $data = $stmt->fetchAll();

            $this->_read->commit();
            $this->_read->query('commit');
        } catch (Exception $e) {
            // 失敗した場合はロールバックしてエラーメッセージを返す
            $this->_read->rollBack();
            $this->_read->query('rollback');
            var_dump($e->getMessage());
            exit();
            return false;
        }
        foreach ($data as $item) {
            $update['pt_time'] = intval($item['滞在時間目安']);
            $where = '';
            $where[] = "pt_pid = '{$item['pt_pid']}'";

            echo "<pre>";
            var_dump($item);
            var_dump($update);
            echo "</pre>";




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

    public function fixTimeBug()
    {
        $this->_read->beginTransaction();
        $this->_read->query('begin');
        try {
            $select = $this->_read->select();
            $select->from('90_project_time')
                ->order('pt_pid');
            $stmt = $select->query();
            $data = $stmt->fetchAll();

            $this->_read->commit();
            $this->_read->query('commit');
        } catch (Exception $e) {
            // 失敗した場合はロールバックしてエラーメッセージを返す
            $this->_read->rollBack();
            $this->_read->query('rollback');
            var_dump($e->getMessage());
            //exit();
            return false;
        }

        foreach ($data as $item) {
            $select = $this->_read->select();
            $select->from('90_project_place')
                ->where('pp_pid = ?', $item['pt_pp_pid'])
                ->where('pp_pd_pid = ?', $item['pt_pd_pid'])
                ->order('pp_pd_pid');
            $stmt = $select->query();
            $place = $stmt->fetch();
            if (!$place) {
                $select = $this->_read->select();
                $select->from('_90_project_place_')
                    ->where('pp_pid = ?', $item['pt_pp_pid'])
                    ->where('pp_pd_pid = ?', $item['pt_pd_pid'])
                    ->order('pp_pd_pid');
                $stmt = $select->query();
                $place_ = $stmt->fetchAll();

                foreach ($place_ as $item2) { //間違ったpp_pidが$place_に含まれている
                    $select = $this->_read->select();
                    $select->from('90_project_place')
                        ->where('pp_pd_pid = ?', $item2['pp_pd_pid'])
                        ->where('pp_place = ?', $item2['pp_place'])
                        ->where('pp_day = ?', $item2['pp_day'])
                        ->where('pp_full = ?', $item2['pp_full'])
                        ->order('pp_pd_pid');
                    $stmt = $select->query();
                    $truep = $stmt->fetch(); //正しいpp_pidが含まれている

                    $update['pt_pp_pid'] = $truep['pp_pid'];
                    $where = '';
                    $where[] = "pt_pid = '{$item['pt_pid']}'";

                    var_dump(count($place_));
                    echo "<pre>";
                    var_dump($truep);
                    var_dump($update);
                    var_dump($where);
                    echo "</pre>";



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
        }
    }

    public function fixKana() {
        $this->_read->beginTransaction();
        $this->_read->query('begin');
        try {
            $select = $this->_read->select();
            $select->from('90_project_data')
                ->order('pd_pid');
            $stmt = $select->query();
            $data = $stmt->fetchAll();

            $this->_read->commit();
            $this->_read->query('commit');
        } catch (Exception $e) {
            // 失敗した場合はロールバックしてエラーメッセージを返す
            $this->_read->rollBack();
            $this->_read->query('rollback');
            var_dump($e->getMessage());
            //exit();
            return false;
        }

        foreach ($data as $item) {
            $update = array();
            $update['pd_label_kana'] = mb_convert_kana($item['pd_label'],'ca');
            $update['pd_body_kana']  = mb_convert_kana($item['pd_body'],'ca');
            echo "<pre>";
            var_dump($update);
            echo "</pre>";

            $where = '';
            $where[] = "pd_pid = '{$item['pd_pid']}'";

            $this->_write->beginTransaction();
            $this->_write->query('begin');
            try {

                //$this->_write->update('90_project_data', $update, $where);

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

        $data = array();

        $this->_read->beginTransaction();
        $this->_read->query('begin');
        try {
            $select = $this->_read->select();
            $select->from('90_project_place')
                ->order('pp_pid');
            $stmt = $select->query();
            $data = $stmt->fetchAll();

            $this->_read->commit();
            $this->_read->query('commit');
        } catch (Exception $e) {
            // 失敗した場合はロールバックしてエラーメッセージを返す
            $this->_read->rollBack();
            $this->_read->query('rollback');
            var_dump($e->getMessage());
            //exit();
            return false;
        }

        foreach ($data as $item) {
            $update = array();
            $update['pp_place_num'] = mb_convert_kana($item['pp_place'],'ca');

            echo "<pre>";
            var_dump($update);
            echo "</pre>";


            $where = '';
            $where[] = "pp_pid = '{$item['pp_pid']}'";

            $this->_write->beginTransaction();
            $this->_write->query('begin');
            try {

                //$this->_write->update('90_project_place', $update, $where);

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


    /**
     * 99:99の時間表示を分単位に直す
     * @param $time
     * @return mixed
     */
    public function convertTime($time) {
        return intval(substr($time,0,2)) * 60 + intval(substr($time,3,2)); //分単位の時刻
    }

    /**
     * 時間表示を99:99に直す
     * @param $_time
     * @return string
     */
    public function fixTime($_time)
    {
        $h = floor($_time/60); //時間
        if (strlen($h) < 2 ) $h = "0".$h;
        $m = $_time%60; //分
        if (strlen($m) < 2 ) $m = "0".$m;
        return $h.":".$m;
    }



}
