CREATE OR REPLACE FUNCTION core.get_mno_name(mno character varying) RETURNS character varying AS
$$
select m.name from core.mno m
 join core.mno_prefixes p on (p.mno_id = m.id)
 where $1 LIKE p.prefix||'%'
 limit 1
$$
STABLE LANGUAGE sql;

COMMENT ON FUNCTION core.get_mno_name(character varying) IS 'Determine MNO name by MSISDN';

