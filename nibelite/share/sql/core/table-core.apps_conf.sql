CREATE TABLE core.apps_conf
(
  id serial NOT NULL,
  app_id integer NOT NULL, -- application
  tag character varying(64) NOT NULL, -- config parameter name
  value character varying(1024), -- parameter value

  CONSTRAINT apps_conf_pkey PRIMARY KEY (id),
  CONSTRAINT apps_conf_app_id_fkey FOREIGN KEY (app_id)
      REFERENCES core.apps (id) MATCH SIMPLE
      ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT apps_conf_app_id_key UNIQUE (app_id, tag)
)
WITH (OIDS=TRUE);

COMMENT ON TABLE core.apps_conf IS 'Applications runtime configuration';
COMMENT ON COLUMN core.apps_conf.app_id IS 'application';
COMMENT ON COLUMN core.apps_conf.tag IS 'config parameter name';
COMMENT ON COLUMN core.apps_conf."value" IS 'parameter value';

