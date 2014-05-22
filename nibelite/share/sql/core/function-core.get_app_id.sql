CREATE OR REPLACE FUNCTION core.get_app_id(app_name character varying) RETURNS integer AS
 'select id from core.apps where name=$1'
LANGUAGE sql;

COMMENT ON FUNCTION core.get_app_id(character varying) IS 'Get application ID by name';

