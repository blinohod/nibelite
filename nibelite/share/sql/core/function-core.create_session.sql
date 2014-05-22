CREATE OR REPLACE FUNCTION core.create_session(in_user_id integer, in_ttl_seconds integer) RETURNS character varying AS
$BODY$
declare
 sess_id varchar;
begin
 insert into core.sessions (user_id, expire)
  values (in_user_id, now() + in_ttl_seconds::text::interval)
  returning session_id into sess_id;
 return sess_id;
end
$BODY$
LANGUAGE plpgsql;

COMMENT ON FUNCTION core.create_session(integer, integer) IS 'Create new session for user and return session id';

