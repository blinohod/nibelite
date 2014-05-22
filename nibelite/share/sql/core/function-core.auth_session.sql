CREATE OR REPLACE FUNCTION core.auth_session(in_session_id character varying) RETURNS integer AS
$BODY$

select user_id from core.sessions s, core.users u
 where (s.session_id = $1)
 and (now() < s.expire)
 and (u.active = 'true')
 and (now() < u.expire)

$BODY$
LANGUAGE sql;

COMMENT ON FUNCTION core.auth_session(character varying) IS 'Return user_id if user successfully authentified';

