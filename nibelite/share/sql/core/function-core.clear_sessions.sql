CREATE OR REPLACE FUNCTION core.clear_sessions() RETURNS boolean AS
$BODY$
delete from core.sessions where expire < now();
select true;
$BODY$
LANGUAGE sql;

COMMENT ON FUNCTION core.clear_sessions() IS 'Clear outdated sessions';
