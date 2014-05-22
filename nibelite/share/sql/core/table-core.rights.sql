CREATE TABLE core.rights
(
  action_id integer NOT NULL,
  group_id integer NOT NULL,

  CONSTRAINT rights_pkey PRIMARY KEY (action_id, group_id),
  CONSTRAINT rights_action_id_fkey FOREIGN KEY (action_id)
      REFERENCES core.actions (id) MATCH SIMPLE
      ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT rights_group_id_fkey FOREIGN KEY (group_id)
      REFERENCES core.groups (id) MATCH SIMPLE
      ON UPDATE CASCADE ON DELETE CASCADE
)
WITH (OIDS=FALSE);

COMMENT ON TABLE core.rights IS 'Groups rights to actions';

