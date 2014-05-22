SET client_encoding = 'UTF8';

CREATE SCHEMA tvchat;
COMMENT ON SCHEMA tvchat IS 'SMS TV chat';


CREATE TABLE tvchat.ban
(
  id serial NOT NULL,
  msisdn character varying(16) NOT NULL DEFAULT ''::character varying,
  sn numeric(16,0) NOT NULL DEFAULT 0,
  since timestamp without time zone NOT NULL DEFAULT now(),
  till timestamp without time zone NOT NULL,
  note character varying(128) NOT NULL DEFAULT ''::character varying,
  CONSTRAINT ban_pkey PRIMARY KEY (id)
) WITH (OIDS=TRUE);

COMMENT ON TABLE tvchat.ban IS 'Ban list for SMS TV Chat';

-- ******************************************************************************

CREATE TABLE tvchat.chat
(
  id bigserial NOT NULL,
  msg_id bigint,
  msisdn character varying(16) NOT NULL,
  sn numeric(16,0) NOT NULL,
  body text NOT NULL DEFAULT ''::character varying,
  received timestamp without time zone NOT NULL DEFAULT now(),
  approved timestamp without time zone,
  status character varying(16) NOT NULL DEFAULT 'NEW'::character varying,
  editor_info character varying(512) NOT NULL DEFAULT ''::character varying,
  service_id integer NOT NULL DEFAULT 5,
  CONSTRAINT chat_pkey PRIMARY KEY (id)
) WITH (OIDS=TRUE);

COMMENT ON TABLE tvchat.chat IS 'Chat content from SMS traffic';

CREATE INDEX chat_received_idx ON tvchat.chat USING btree (received);
CREATE INDEX chat_sn ON tvchat.chat USING btree (sn);

-- ******************************************************************************

CREATE TABLE tvchat.service
(
  id serial NOT NULL,
  sn numeric(16,0) NOT NULL DEFAULT 0,
  "name" character varying(128) NOT NULL DEFAULT 0,
  active boolean NOT NULL DEFAULT true,
  "login" character varying(16) NOT NULL DEFAULT ''::character varying,
  passwd character varying(16) NOT NULL DEFAULT ''::character varying,
  reply_ok character varying(1024) NOT NULL DEFAULT ''::character varying,
  reply_help character varying(1024) NOT NULL DEFAULT ''::character varying,
  reply_closed character varying(1024) NOT NULL DEFAULT ''::character varying,
  pattern character varying(64) NOT NULL DEFAULT ''::character varying,
  CONSTRAINT service_pkey PRIMARY KEY (id)
) WITH (OIDS=TRUE);


COMMENT ON TABLE tvchat.service IS 'TV Chat services descriptors.';


-- ******************************************************************************

INSERT INTO core.apps (name,descr) VALUES ('app_tvchat','SMS TV Chat application');
INSERT INTO core.apps_conf (app_id,tag,value) VALUES (core.get_app_id('app_tvchat'),'msg_help','To apply your greetings just send any message and wait until it approved.');
INSERT INTO core.apps_conf (app_id,tag,value) VALUES (core.get_app_id('app_tvchat'),'msg_error','Sorry, we cannot find target chat group.');
INSERT INTO core.apps_conf (app_id,tag,value) VALUES (core.get_app_id('app_tvchat'),'msg_thanks','Thank you for feedback! Wait for approval.');
INSERT INTO core.apps_conf (app_id,tag,value) VALUES (core.get_app_id('app_tvchat'),'msg_closed','Sorry, this chat is closed now.');
INSERT INTO core.apps_conf (app_id,tag,value) VALUES (core.get_app_id('app_tvchat'),'msg_banned','Sorry, you are banned.');

-- TRANSLATIONS

INSERT INTO core.translations (lang, keyword, value, service) VALUES ('en', 'cms_head_ctl-tvchat', 'SMS-TV Chat', 'core');
INSERT INTO core.translations (lang, keyword, value, service) VALUES ('ru', 'cms_head_ctl-tvchat', 'SMS-ТВ чат', 'core');

