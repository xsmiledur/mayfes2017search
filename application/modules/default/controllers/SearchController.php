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

    public function getDist($a, $b){
        //get the length of the shortest from $a to $b
        return $a+$b;//just a dummy
    }

    public function getStartTime($id){
        //get the start time of the event $id
        return 0;
    }

    
    public function getEndTime($id){
        //get the start time of the event $id
        return 0;
    }
    
    public function indexAction(){
        $req = $this->getRequest();
        $params = $req->getParms();
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