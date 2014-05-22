<?php //REPORT 11 Detailed MO traffic by MSISDN

	// Retrieve number of successfully ordered content
	$tmp = db_get("select count(m.id) as cnt,
 			sa.name as src_app,
			m.src_addr as src_addr,
			m.dst_addr as dst_addr,
			da.name as dst_app,
			m.msg_status as msg_status
		from core.messages m
		join core.apps sa on (m.src_app_id = sa.id)
		left join core.apps da on (m.dst_app_id = da.id)
		where m.date_received between '$since' and '$till 23:59:59'
		and m.msg_type != 'DLR'
		and sa.name ilike 'chan_%'
		group by m.msg_status,sa.name,da.name,m.src_addr,m.dst_addr
		order by cnt desc");
	 
	start_table();
	th('Since :');th($since);row();
	th('Till :');th($till);row();
	th('#');th('Source application');th('Destination application');th('From');th('To');th('Quantity');row();

	$num = 0;
	foreach ($tmp as $rec) {

      tdn(++$num);
      
      td($rec['src_app']);
      td($rec['dst_app']);
      td($rec['src_addr']);
      td($rec['dst_addr']);
      tdn($rec['cnt']);
      
			row();
	};

	end_table();

?>
