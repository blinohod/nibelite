CREATE OR REPLACE FUNCTION core.authorize(in_user_id integer, in_service character varying, in_action character varying) RETURNS boolean AS
$BODY$

DECLARE
 res integer;

BEGIN

-- Check if user is 'admin' and so allowed to do anything
select id into res from core.users u where u.id = in_user_id and u.login = 'admin';
if found then
 return 1;
end if;

-- Check other users
select 1 into res from core.rights r
 inner join core.actions a on (a.id = r.action_id)
 inner join core.services s on (s.id=a.service_id)
 inner join core.groups g on (g.id = r.group_id)
 inner join core.users_groups ug on (ug.group_id = g.id)
 inner join core.users u on (ug.user_id = u.id)
 where (u.id=in_user_id)
  and (a.action=in_action or a.action = '*')
  and (s.service=in_service) limit 1;

-- If found - return true
if found then
 return true;
else
 return false;
end if;

END
$BODY$
LANGUAGE plpgsql;

COMMENT ON FUNCTION core.authorize(integer, character varying, character varying) IS 'Authorize user for access to service/actions pair';

