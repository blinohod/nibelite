CREATE TABLE core.actions
(
  id serial NOT NULL, -- unique identifier
  service_id integer NOT NULL, -- reference to service
  "action" character varying(64) NOT NULL, -- action mnemonic name
	visible boolean NOT NULL DEFAULT FALSE,
  descr character varying(1024), -- human readable description

  CONSTRAINT actions_pkey PRIMARY KEY (id),
  CONSTRAINT actions_service_id_fkey FOREIGN KEY (service_id)
      REFERENCES core.services (id) MATCH SIMPLE
      ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT actions_service_id_key UNIQUE (service_id, action)
)
WITH (OIDS=FALSE);

COMMENT ON TABLE core.actions IS 'Service actions';
COMMENT ON COLUMN core.actions.id IS 'unique identifier';
COMMENT ON COLUMN core.actions.service_id IS 'reference to service';
COMMENT ON COLUMN core.actions."action" IS 'action mnemonic name';
COMMENT ON COLUMN core.actions.descr IS 'human readable description';


