CREATE OR REPLACE FUNCTION core.check_rights(in_action_id integer, in_group_id integer) RETURNS bigint AS
'select count(action_id) from core.rights where action_id=$1 and group_id=$2'
LANGUAGE sql;

COMMENT ON FUNCTION core.check_rights(integer, integer) IS 'Return 1 if group is allowed to invoke an action';
