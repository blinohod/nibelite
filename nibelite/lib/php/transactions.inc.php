<?php   //      PROJECT:        cms.inmetex
        //      MODULE:         Transaction Report Routines
        //      AUTHOR:         Anatoly Matyakh
        //      AUTHOR:         amatyakh@inmetex.com.ua

include_once 'core.inc.php'; 

function trans_open($app_id,$plan_id,$src_addr){
  $src_addr = db_escape($src_addr);
  if($oid = db_put("insert into transactions
    (request_id,src_app_id,src_plan_id,src_time,src_addr,status)
    values
    (0,$app_id,$plan_id,now(),'$src_addr',".TS_REQUEST.")"))
    if($data = db_get("select id from transactions where oid=$oid"))
      return $data[0]['id'];
  return false;
}

function trans_request($id,$request_id,$request=''){
  if($id==-1) return -1; // fake transaction
  if($id==0){
    $oid = db_put("insert into transactions default values");
    if($data = db_get("select id from transactions where oid=$oid"))
      $id = $data[0]['id'];
  }
  if($id)
    if(db("update transactions set request_id = $request_id, request='".db_escape($request)."' where id=$id"))
      return $id;
    else return false;
  else return false;
}

function trans_process($id,$app_id,$trigger,$content_id,$premium=0){
  if($id==-1) return -1; // fake transaction
  $trigger = db_escape($trigger);
  if($id==0){
    $oid = db_put("insert into transactions default values");
    if($data = db_get("select id from transactions where oid=$oid"))
      $id = $data[0]['id'];
  }
  if($id)
    if(db("update transactions set
             proc_app_id = $app_id,
             proc_time = now(),
             trigger = '$trigger',
             content_id = $content_id,
	     premium = $premium,
             status = ".TS_PROCESS."
           where id=$id"))
      return $id;
    else return false;
  else return false;
}

function trans_delivery($id,$app_id,$plan_id,$qty){
  if($id==-1) return -1; // fake transaction
  if($id==0){
    $oid = db_put("insert into transactions default values");
    if($data = db_get("select id from transactions where oid=$oid"))
      $id = $data[0]['id'];
  }
  if($id)
    if(db("update transactions set
             dst_app_id = $app_id,
             dst_plan_id = $plan_id,
             dst_time = now(),
             qty = $qty,
             status = ".TS_DELIVERY."
           where id=$id"))
      return $id;
    else return false;
  else return false;
}

function trans_close($id,$status){
  if($id==-1) return -1; // fake transaction
  if($id==0){
    $oid = db_put("insert into transactions default values");
    if($data = db_get("select id from transactions where oid=$oid"))
      $id = $data[0]['id'];
  }
  if($id)
    if(db("update transactions set
             status = $status,
			 proc_time = now()
           where id=$id"))
      return $id;
    else return false;
  else return false;
}


function make_transaction($fields){
  if(isset($fields['id'])) unset($fields['id']);
  $prepared = array();
  foreach($fields as $field=>$value)
    $prepared[$field] = "'".db_quote($value)."'";
  $oid = db_put("insert into transactions (".
    join(',',array_keys($prepared)).") values (".
    join(',',array_values($prepared)).")");
  if($data = db_get("select id from transactions where oid=$oid"))
    return $data[0]['id'];
  else return false;
}

// Function: save_request
// Save request for future WAP downloads
// Parameters:
//   transaction id (transactions.id),
//   MSISDN,
//   ordering code (mapping.code)
//
// This functions is called at least from logotones.php
function save_request($trans_id,$msisdn,$code){
	$trans = db_get("select src_time,src_plan_id,src_addr,content_id from transactions where id=$trans_id");
	$content_id = 0;
	if($trans){
		$src_time = "'".$trans[0]['src_time']."'";
		$plan_id = $trans[0]['src_plan_id'];
		$msisdn = $trans[0]['src_addr'];
		$content_id = $trans[0]['content_id']+0;
	}else{
		$src_time = 'now()';
		$plan_id = 0;
	}
	if($code!='whatever'){
		if($data = db_get("select content_id from mapping where code='$code' limit 1"))
		$content_id = $data[0]['content_id'];
	}
	if(db_get("select date from requests where content_id=$content_id and msisdn='$msisdn'")){
		db("update requests set date=now() where content_id=$content_id and msisdn='$msisdn'");
		return false;
	}
	db_put("insert into requests(id,msisdn,content_id,plan_id,date) values ($trans_id,'$msisdn',$content_id,$plan_id,$src_time)");
	return true;
}

function kill_request($trans_id){
  db("delete from requests where id = $trans_id");
}

?>
