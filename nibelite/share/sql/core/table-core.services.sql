CREATE TABLE core.services
(
  id serial NOT NULL, -- unique identifier
  service character varying(64) NOT NULL, -- mnemonic service name like "userdb"
  descr character varying(1024), -- description for casual users
  uri character varying(512) NOT NULL DEFAULT 'internal'::character varying,
	visible boolean NOT NULL DEFAULT TRUE,

  CONSTRAINT services_pkey PRIMARY KEY (id),
  CONSTRAINT services_service_key UNIQUE (service)
)
WITH (
  OIDS=FALSE
);

COMMENT ON TABLE core.services IS 'Services descriptors';
COMMENT ON COLUMN core.services.id IS 'unique identifier';
COMMENT ON COLUMN core.services.service IS 'mnemonic service name like "userdb"';
COMMENT ON COLUMN core.services.descr IS 'description for casual users';


