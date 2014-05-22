CREATE OR REPLACE FUNCTION core.delete_session(in_session character varying) RETURNS boolean AS
$BODY$
delete from core.sessions where session_id = $1;
select true
$BODY$
LANGUAGE sql;

COMMENT ON FUNCTION core.delete_session(character varying) IS 'Delete session by key';

