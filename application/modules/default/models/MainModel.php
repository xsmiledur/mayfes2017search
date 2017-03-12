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
            'host'              => $db_read['host'],
            'username'          => $db_read['username'],
            'password'          => $db_read['password'],
            'dbname'            => $db_read['name'],
            'charset'           => $db_read['charset'],
            'driver_options'    => $pdoParams
        );

        // データベースアダプタを作成する
        $this->_read = Zend_Db::factory($db_read['type'], $read_params);
        // 文字コードをUTF-8に設定する
        $this->_read->query("set names 'utf8'");

        // データ取得形式を設定する
        $this->_read->setFetchMode(Zend_Db::FETCH_ASSOC);


        // データベースの接続パラメータを定義する
        $write_params = array(
            'host'              => $db_write['host'],
            'username'          => $db_write['username'],
            'password'          => $db_write['password'],
            'dbname'            => $db_write['name'],
            'charset'           => $db_write['charset'],
            'driver_options'    => $pdoParams
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
    public function getContentsData($lang,$path)
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
     * 企画データを取得
     *
     * @return array
     */

    public function getProjectData()
    {
        // トランザクション開始
        /*
        $this->_read->beginTransaction();
        $this->_read->query('begin');
        try {
        */
            $select = $this->_read->select();
            $select->from('project_data');
            $select->where('active_flg = ?', 1);
                //->order('sc_order ASC')

            $stmt = $select->query();
            $data = $stmt->fetchAll();

            return $data;

            //var_dump($data);exit();

            // 成功した場合はコミットする
            /*
            $this->_read->commit();
            $this->_read->query('commit');
            */
            //return $data;
            /*
        } catch (Exception $e) {
            // 失敗した場合はロールバックしてエラーメッセージを返す
            $this->_read->rollBack();
            $this->_read->query('rollback');
//            var_dump($e->getMessage());exit();
            return false;
        }
            */

    }

    /**
     * フリーワード検索
     *
     * @param $search
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

}