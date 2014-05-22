<?php //REPORT 111 [маркетинг] Трафик по номерам и пакетам новостей
/*
 *
 */


$tmp = db_get("select 
	t.topic as topic,
	ms.msg_status as msg_status,
	ms.dst_addr as msisdn,
	count(m.id) as cnt
 from smsnews.topics t
 join smsnews.msg_meta m on (m.topic_id = t.id)
 join core.messages ms on (ms.id = m.id)
 where ms.date_received between '$since' and '$till 23:59:59'
 group by t.topic,ms.msg_status,ms.dst_addr");

// Table Header
start_table();
th('Начало периода (включая):');th($since);row();
th('Конец периода (включая):');th($till);row();

th("Пакет новостей");
th("Номер телефона"); 
th("Статус доставки"); 
th("Отправлено новостей"); 
row();	

$i = 0; 
// Table Body
if ($tmp) {
	foreach($tmp as $rec) {
		td($rec['topic']);
		td($rec['msisdn']);
		td($rec['msg_status']);
		td($rec['cnt']);
		row();
		$i++;
	};
} else {
	td('Нет данных для отчета за данный период.');
};

end_table();

?>
