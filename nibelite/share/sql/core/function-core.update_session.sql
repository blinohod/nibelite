CREATE OR REPLACE FUNCTION core.update_session(in_session_id character varying, in_ttl_seconds integer) RETURNS boolean AS
$BODY$
	update core.sessions
	set expire = now() + $2::text::interval
	where session_id = $1;
select true
$BODY$
LANGUAGE sql;

COMMENT ON FUNCTION core.update_session(character varying, integer) IS 'Update existing session TTL for given number of seconds';

