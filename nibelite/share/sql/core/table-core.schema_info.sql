CREATE TABLE core.schema_info
(
  id serial NOT NULL,
  "name" character varying(64),
  "version" character varying(16),
  descr character varying(128) NOT NULL DEFAULT ''::character varying,
  installed timestamp(0) without time zone NOT NULL DEFAULT now(),
  CONSTRAINT schema_info_pkey PRIMARY KEY (id),
  CONSTRAINT schema_info_name_version_key UNIQUE (name, version)
);

COMMENT ON TABLE core.schema_info IS 'Informaion about installed SQL schemas';

COMMENT ON COLUMN core.schema_info."name" IS 'schema name';
COMMENT ON COLUMN core.schema_info."version" IS 'schema version (the same as application)';
COMMENT ON COLUMN core.schema_info.descr IS 'description';
