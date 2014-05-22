<?php //REPORT 112 [маркетинг] Средний трафик по пакетам новостей
/*
 *
 */


$tmp = db_get("select t.topic, count(distinct sub_id) as num_subs,
 		count(mm.id) as num_msg, sum(mm.num_sms) as num_sms
	from smsnews.msg_meta mm
  join smsnews.topics t on (t.id = mm.topic_id)
	join core.messages ms on (ms.id = mm.id)
	where ms.date_received between '$since' and '$till 23:59:59'
	group by t.topic,mm.topic_id");

// Table Header
start_table();
th('Начало периода (включая):');th($since);row();
th('Конец периода (включая):');th($till);row();

th("Пакет новостей");
th("Кол-во подписчиков"); 
th("Всего сообщений"); 
th("Всего SMS"); 
th("Сообщений на подписчика"); 
th("SMS на подписчика"); 
row();	

$i = 0; 
// Table Body
if ($tmp) {
	foreach($tmp as $rec) {
		td($rec['topic']);
		td($rec['num_subs']);
		td($rec['num_msg']);
		td($rec['num_sms']);
		tdn( $rec['num_msg'] / $rec['num_subs']);
		tdn( $rec['num_sms'] / $rec['num_subs']);
		row();
		$i++;
	};
} else {
	td('Нет данных для отчета за данный период.');
};

end_table();

?>
