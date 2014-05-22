CREATE OR REPLACE FUNCTION core.get_active_channels(chan_type character varying) RETURNS SETOF integer AS
$BODY$
	select app_id from core.apps_conf cf
	inner join core.apps a on (a.id = cf.app_id and a.active=true)
	where tag='chan_type' and value=$1
$BODY$
LANGUAGE sql;

COMMENT ON FUNCTION core.get_active_channels(character varying) IS 'Return list of app_id that are active channels of corresponding type';

