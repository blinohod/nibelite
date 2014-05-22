<?php //REPORT 16 [Kannel] MO SMS traffic on platform by SMSC and short codes

	$cv = ''; // FIXME: this is SQL query part for WHERE clause

	// Retrieve number of successfully ordered content
$tmp = db_get("select
		smsc,
		dst,
		count(id) as cnt
	from stat.kannel_sms
	where received between '$since' and '$till 24:00:00'
	and direction = 'MO'
	group by smsc, dst
	order by smsc, dst");
	 
	start_table();
	th('Report:');th('MO SMS traffic on platform by SMSC and short codes');row();
	th('Since:');th($since);row();
	th('Till:');th($till);row();
	th('#');th('SMSC');th('Service number (short code)');th('Number of MO SM');row();

	$num = 0;
	foreach ($tmp as $rec) {

      tdn(++$num);
      
      td($rec['smsc']);
      td($rec['dst']);
      tdn($rec['cnt']);
      
			row();
	};

	end_table();

?>
