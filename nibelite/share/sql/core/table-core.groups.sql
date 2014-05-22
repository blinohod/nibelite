CREATE TABLE core.groups
(
  id serial NOT NULL, -- unique identifier
  group_name character varying(64) NOT NULL, -- group mnemonic name

  CONSTRAINT groups_pkey PRIMARY KEY (id),
  CONSTRAINT groups_group_key UNIQUE (group_name)
)
WITH (OIDS=FALSE);

COMMENT ON TABLE core.groups IS 'Groups';
COMMENT ON COLUMN core.groups.id IS 'unique identifier';
COMMENT ON COLUMN core.groups.group_name IS 'group mnemonic name';

