CREATE OR REPLACE FUNCTION core.make_session_id() RETURNS character varying AS
'select md5(random()::text)'
LANGUAGE sql;

COMMENT ON FUNCTION core.make_session_id() IS 'Create random session key';

