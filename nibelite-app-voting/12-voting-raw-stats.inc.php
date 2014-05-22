<?php //REPORT 12 [Voting]: SMS Voting - raw statistics
//FORM
	function report_form () {
		$CHANNEL = array('n/a' => '- ALL -');
		if ($tmp = db_get("select id, name from core.apps where name like 'chan_%' order by name")) {
			foreach($tmp as $rec) {
				$CHANNEL[$rec['id']] = $rec['name'];
			};
		};
		return
			'<tr><td>Канал доставки<br><small>(короткие номера)</small></td>'.
			'<td>'.selector('channel_id',$CHANNEL,'n/a').'</td></tr>';
	}
//ENDFORM


	$channel_id=$_REQUEST['channel_id']+0;
	
	// Retrieve number of successfully ordered content
	$tmp = db_get("select 
			m.date_received as dtime,
			m.src_addr as msisdn,
			m.dst_addr as sn,
			m.msg_body as body
		from core.messages m
		join core.apps a on (m.dst_app_id = a.id)
		where date_received between '$since' and '$till 23:59:59'
		and m.src_app_id = '$channel_id'
		and m.msg_type = 'SMS_TEXT'
		and a.name = 'app_voting'
		order by m.id");
	 
	start_table();
	th('Report');th('SMS Voting - raw incoming (MO) messages statistics');row();
	th('Since');th($since);row();
	th('Till');th($till);row();

	th('#');th('Date/Time');th('Short Code');th('MSISDN');th('Body');row();

	$num = 0;
	foreach ($tmp as $rec) {

      tdn(++$num);
      
			//echo '<pre>';print_r($rec);echo '</pre>';

      $rec['body'] = preg_replace('/\s+/','',$rec['body']);

      td($rec['dtime']);
      td($rec['sn']);
      td($rec['msisdn']);
      td($rec['body']);
      
			row();
	};

	end_table();

?>
