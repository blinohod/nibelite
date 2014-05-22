<?php //REPORT 110 [маркетинг] Трафик по рубрикам новостей
/*
 *
 */


$tmp = db_get("select t.id,
	t.topic as topic,
	count(q.id) as news_cnt,
	sum(q.num_sms) as sms_cnt,
	sum(q.num_subs) as msg_cnt
 from smsnews.topics t
 join smsnews.queue q on (t.id = ANY( q.topics))
 where q.send_time between '$since' and '$till 23:59:59'
 group by t.id,t.topic");

// Table Header
start_table();
th('Начало периода (включая):');th($since);row();
th('Конец периода (включая):');th($till);row();

th("Рубрика новостей");
th("Отправлено новостей"); 
th("Кол-во сообщений"); 
th("Кол-во отдельных SMS"); 
row();	

$i = 0; 
// Table Body
if ($tmp) {
	foreach($tmp as $rec) {
		td($rec['topic']);
		td($rec['news_cnt']);
		td($rec['msg_cnt']);
		td($rec['sms_cnt']);
		row();
		$i++;
	};
} else {
	td('Нет данных для отчета за данный период.');
};

end_table();

?>
