<?php //REPORT 13 Daily MO traffic by short code
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
	$tmp = db_get("select count(m.id) as cnt,
			m.dst_addr as dst_addr,
			m.date_received::date as dt
		from core.messages m
		join core.apps sa on (m.src_app_id = sa.id)
		where m.date_received between '$since' and '$till 23:59:59'
		and m.src_app_id = '$channel_id'
		and m.msg_type != 'DLR'
		and sa.name ilike 'chan_%'
		group by dt,m.dst_addr
		order by dt");
	 
	start_table();
	th('Since :');th($since);row();
	th('Till :');th($till);row();
	th('#');th('Date');th('Short Code');th('Quantity');row();

	$num = 0;
	foreach ($tmp as $rec) {

      tdn(++$num);
      
      td($rec['dt']);
      td($rec['dst_addr']);
      tdn($rec['cnt']);
      
			row();
	};

	end_table();

?>
