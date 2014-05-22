CREATE TABLE core.users
(
  id serial NOT NULL,
  "login" character varying(64) NOT NULL, -- user login
  "password" character varying(128) NOT NULL, -- password MD5-encrypted
  active boolean NOT NULL DEFAULT true, -- true if user allowed to work
  created timestamp(0) without time zone NOT NULL DEFAULT now(), -- user creation time
  expire timestamp(0) without time zone NOT NULL DEFAULT (now() + '1 year'::interval), -- expiration time
  "name" character varying(256) NOT NULL DEFAULT '',
  email character varying(256) NOT NULL DEFAULT '',
  descr character varying(1024) NOT NULL DEFAULT '',

  CONSTRAINT users_pkey PRIMARY KEY (id),
  CONSTRAINT users_login_key UNIQUE (login),
  CONSTRAINT users_check CHECK (expire > created),
  CONSTRAINT users_login_check CHECK (login ~ '^[a-z][a-z0-9]{1,15}$'),
  CONSTRAINT users_password_check CHECK (password <> '')
)
WITH (OIDS=FALSE);

COMMENT ON TABLE core.users IS 'Users records';
COMMENT ON COLUMN core.users."login" IS 'user login (2 to 16 characters)';
COMMENT ON COLUMN core.users."password" IS 'password MD5-encrypted';
COMMENT ON COLUMN core.users.active IS 'true if user allowed to work';
COMMENT ON COLUMN core.users.created IS 'user creation time';
COMMENT ON COLUMN core.users.expire IS 'expiration time';
