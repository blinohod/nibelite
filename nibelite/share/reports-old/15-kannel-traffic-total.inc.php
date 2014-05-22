<?php //REPORT 15 [Kannel] SMS traffic on platform by SMSC

	$cv = ''; // FIXME: this is SQL query part for WHERE clause

	// Retrieve number of successfully ordered content
$tmp = db_get("select
		s.smsc as smsc,
		count(mo.id) as mo,
		count(mt.id) as mt,
		count(dlr.id) as dlr
	from stat.kannel_sms s
	left outer join stat.kannel_sms mo on (s.id = mo.id and mo.direction='MO')
	left outer join stat.kannel_sms mt on (s.id = mt.id and mt.direction='MT')
	left outer join stat.kannel_sms dlr on (s.id = dlr.id and dlr.direction='DLR')
	where s.received between '$since' and '$till 24:00:00' 
	group by s.smsc
	order by s.smsc");
	 
	start_table();
	th('Report:');th('SMS traffic on platform by SMSC');row();
	th('Since:');th($since);row();
	th('Till:');th($till);row();
	th('#');th('SMSC');th('MO SM (from mobile)');th('MT SM (to mobile)');th('DLR (reports)');row();

	$num = 0;
	foreach ($tmp as $rec) {

      tdn(++$num);
      
      td($rec['smsc']);
      tdn($rec['mo']);
      tdn($rec['mt']);
      tdn($rec['dlr']);
      
			row();
	};

	end_table();

?>
