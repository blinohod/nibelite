CREATE TABLE core.mno
(
  id serial NOT NULL,
  "name" character varying(128) NOT NULL,
  country_code character (2) NOT NULL DEFAULT '',
  title text DEFAULT '',
  CONSTRAINT mno_pkey PRIMARY KEY (id),
  CONSTRAINT mno_name_key UNIQUE (name)
);

COMMENT ON TABLE core.mno IS 'List of mobile carriers.';
