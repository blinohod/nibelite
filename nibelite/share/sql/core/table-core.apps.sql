CREATE TABLE core.apps
(
  id serial NOT NULL,
  name character varying(64) NOT NULL, -- application mnemonic name to use in external scripts
  active boolean NOT NULL DEFAULT true, -- is application available for usage
  descr character varying(512),

  CONSTRAINT apps_pkey PRIMARY KEY (id),
  CONSTRAINT apps_name_key UNIQUE (name)
)
WITH (OIDS=TRUE);

COMMENT ON TABLE core.apps IS 'Abstract applications/channels';
COMMENT ON COLUMN core.apps."name" IS 'application mnemonic name to use in external scripts';
COMMENT ON COLUMN core.apps.active IS 'is application available for usage';
COMMENT ON COLUMN core.apps.descr IS 'human readable description';

