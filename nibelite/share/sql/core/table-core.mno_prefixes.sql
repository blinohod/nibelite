CREATE TABLE core.mno_prefixes
(
  id serial NOT NULL,
  mno_id integer NOT NULL,
  prefix character varying(16) NOT NULL,
  CONSTRAINT mno_prefixes_pkey PRIMARY KEY (id),
  CONSTRAINT mno_prefixes_mno_id_fkey FOREIGN KEY (mno_id)
      REFERENCES core.mno (id) MATCH SIMPLE
      ON UPDATE CASCADE ON DELETE CASCADE
);

COMMENT ON TABLE core.mno_prefixes IS 'MSISDN prefixes for MNOs';
