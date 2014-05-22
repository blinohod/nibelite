CREATE TABLE core.sessions
(
  user_id integer NOT NULL,
  created timestamp(0) without time zone NOT NULL DEFAULT now(), -- session creation timestamp
  expire timestamp(0) without time zone DEFAULT (now() + '08:00:00'::interval),
  session_id character varying(128) NOT NULL DEFAULT core.make_session_id(), -- random generated session Id

  CONSTRAINT session_pkey PRIMARY KEY (session_id),
  CONSTRAINT session_user_id_fkey FOREIGN KEY (user_id)
      REFERENCES core.users (id) MATCH SIMPLE
      ON UPDATE CASCADE ON DELETE CASCADE
)
WITH (OIDS=FALSE);

COMMENT ON TABLE core.sessions IS 'Authentication sessions';
COMMENT ON COLUMN core.sessions.created IS 'session creation timestamp';
COMMENT ON COLUMN core.sessions.session_id IS 'random generated session Id';


