/*
 
 Function get_message_route() determines destination route for message

 Parameters:

  * src_app_id (integer)
  * msg_type (t_msg_type)
  * src_addr (varchar)
  * dst_addr (varchar)
  * msg_body (varchar)

 Return:

  dst_app_id (integer)

 Example:

  SELECT core.get_message_route(18, 'SMS_TEXT', '380501111111', '2222', 'Test message') as dst_app_id

 */
CREATE OR REPLACE FUNCTION core.get_message_route(integer, core.t_msg_type, varchar, varchar, varchar) RETURNS smallint AS
$BODY$
SELECT r.dst_app_id 
	FROM
		core.routing r
	WHERE
    (r.src_app_id = 0 or r.src_app_id = $1)
    AND
    (r.msg_type is NULL or r.msg_type = $2)
    AND
    (r.src_addr_regexp = '' or $3 ~* r.src_addr_regexp)
    AND
    (r.dst_addr_regexp = '' or $4 ~* r.dst_addr_regexp)
    AND
    (r.body_regexp = '' or $5 ~* r.body_regexp)
  ORDER BY
    r.priority ASC
  LIMIT 1;
$BODY$
LANGUAGE sql;
