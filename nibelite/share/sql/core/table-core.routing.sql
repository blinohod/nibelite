CREATE TABLE core.routing
(
  id serial NOT NULL,
  msg_type core.t_msg_type, -- message type match
  src_app_id smallint, -- source application match
  src_addr_regexp character varying(512), -- originator address match
  dst_addr_regexp character varying(512), -- recipient address match
  body_regexp character varying(512), -- body regexp
  priority smallint NOT NULL DEFAULT 1000, -- rule priority - less is preferrable
  dst_app_id smallint NOT NULL, -- destination route to set
  description character varying(1024), -- human readable description

  CONSTRAINT routing_pkey PRIMARY KEY (id),
  CONSTRAINT routing_src_app_id_fkey FOREIGN KEY (src_app_id)
      REFERENCES core.apps (id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION
)
WITH (OIDS=TRUE);

COMMENT ON TABLE core.routing IS 'Routing rules for messaging';
COMMENT ON COLUMN core.routing.msg_type IS 'message type match';
COMMENT ON COLUMN core.routing.src_app_id IS 'source application match';
COMMENT ON COLUMN core.routing.src_addr_regexp IS 'originator address match';
COMMENT ON COLUMN core.routing.dst_addr_regexp IS 'recipient address match';
COMMENT ON COLUMN core.routing.body_regexp IS 'body regexp';
COMMENT ON COLUMN core.routing.priority IS 'rule priority - less is preferrable';
COMMENT ON COLUMN core.routing.dst_app_id IS 'destination route to set';
COMMENT ON COLUMN core.routing.description IS 'human readable description';

