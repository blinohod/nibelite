CREATE OR REPLACE FUNCTION core.delete_user_sessions(user_id integer) RETURNS boolean AS
$BODY$
delete from core.sessions where user_id = $1;
select true
$BODY$
LANGUAGE sql;

COMMENT ON FUNCTION core.delete_user_sessions(integer) IS 'Delete all user sessions';

