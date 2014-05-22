<?php //REPORT 14 Top 100 SMS senders by short code
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

	$chan_name = "ALL"; // Default

	if ($channel_id) {
		$chan_sql = "and m.src_app_id = '$channel_id'";
		$chns = db_get("select * from core.apps where id = $channel_id");
		$chan_name = $chns[0]['name'];
	};

	// Retrieve number of top 100 senders
	$tmp = db_get("select count(m.id) as cnt,
			m.src_addr as src_addr
		from core.messages m
		join core.apps sa on (m.src_app_id = sa.id)
		where m.date_received between '$since' and '$till 23:59:59'
		$chan_sql
		and m.msg_type != 'DLR'
		and sa.name ilike 'chan_%'
		group by m.src_addr
		order by cnt desc
		limit 100");
	 
	start_table();
	th('Since :');th($since);row();
	th('Till :');th($till);row();
	th('Channel :');th($chan_name);row();
	th('#');th('MSISDN');th('Quantity');row();

	$num = 0;
	foreach ($tmp as $rec) {

      tdn(++$num);
      td($rec['src_addr']);
      tdn($rec['cnt']);
      
			row();
	};

	end_table();

?>
