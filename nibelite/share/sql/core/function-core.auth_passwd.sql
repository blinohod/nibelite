CREATE OR REPLACE FUNCTION core.auth_passwd(in_login character varying, in_passwd character varying) RETURNS integer AS
$BODY$
select id from core.users u
	where (u.login = $1)
	and (u.password = md5($2))
	and (u.active = 'true')
	and (now() < u.expire)
$BODY$
LANGUAGE sql;

COMMENT ON FUNCTION core.auth_passwd(character varying, character varying) IS 'Return user_id if user successfully authentified';
