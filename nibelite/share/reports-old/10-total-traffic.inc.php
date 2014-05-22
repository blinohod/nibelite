<?php //REPORT 10 Total traffic on platform

	$cv = ''; // FIXME: this is SQL query part for WHERE clause

	// Retrieve number of successfully ordered content
	$tmp = db_get("select count(m.id) as cnt,
 			sa.name as src_app,
			da.name as dst_app,
			m.msg_status as msg_status
		from core.messages m
		join core.apps sa on (m.src_app_id = sa.id)
		left join core.apps da on (m.dst_app_id = da.id)
		where m.date_received between '$since' and '$till 23:59:59'
		and msg_type != 'DLR'
		group by m.msg_status,sa.name,da.name
		order by sa.name,da.name,m.msg_status");
	 
	start_table();
	th('Since:');th($since);row();
	th('Till:');th($till);row();
	th('#');th('Source application');th('Destination application');th('Status');th('Quantity');row();

	$num = 0;
	foreach ($tmp as $rec) {

      tdn(++$num);
      
      td($rec['src_app']);
      td($rec['dst_app']);
      td($rec['msg_status']);
      tdn($rec['cnt']);
      
			row();
	};

	end_table();

?>
