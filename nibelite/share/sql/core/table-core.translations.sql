CREATE TABLE core.translations
(
  id serial NOT NULL,
  lang character(2) NOT NULL DEFAULT '%%'::bpchar,
  service character varying(32) NOT NULL DEFAULT ''::character varying,
  keyword character varying(64) NOT NULL DEFAULT ''::character varying,
  "value" text NOT NULL DEFAULT ''::text,

  CONSTRAINT translations_pkey PRIMARY KEY (id),
  CONSTRAINT translations_lang_serv_key UNIQUE (lang, service, keyword)
)
WITH (OIDS=TRUE);

COMMENT ON TABLE core.translations IS 'Translations for text messages';

CREATE OR REPLACE FUNCTION core.drop_old_translation() RETURNS trigger AS
$BODY$
declare
begin
 delete from core.translations
  where service=NEW.service and lang=NEW.lang and keyword=NEW.keyword;
 return NEW;
end;
$BODY$
  LANGUAGE plpgsql;
COMMENT ON FUNCTION core.drop_old_translation() IS 'Drop old translation before insertion of new one';


CREATE TRIGGER drop_old_translation BEFORE INSERT ON core.translations
  FOR EACH ROW EXECUTE PROCEDURE core.drop_old_translation();
