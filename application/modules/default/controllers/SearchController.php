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

    public function indexAction(){
        $this->_request->getPost('');
        
        $inout = array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w')
        );

        $proc = proc_open('/var/www/scripts/search.out', $inout, $pipes);
        
        if(is_resource($proc)){
            fwrite($pipes[0],"just_echo");
            fclose($pipes[0]);

            echo stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            fclose($pipes[2]);

        }
        $this->view->result = "test";

    
    }
}