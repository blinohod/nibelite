CREATE TABLE core.users_groups
(
  user_id integer NOT NULL,
  group_id integer NOT NULL,

  CONSTRAINT users_groups_pkey PRIMARY KEY (user_id, group_id),
  CONSTRAINT users_groups_group_id_fkey FOREIGN KEY (group_id)
      REFERENCES core.groups (id) MATCH SIMPLE
      ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT users_groups_user_id_fkey FOREIGN KEY (user_id)
      REFERENCES core.users (id) MATCH SIMPLE
      ON UPDATE CASCADE ON DELETE CASCADE
)
WITH (OIDS=FALSE);

COMMENT ON TABLE core.users_groups IS 'Linking users to groups many to many';

