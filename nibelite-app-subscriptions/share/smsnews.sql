SET client_encoding = 'UTF8';
SET default_with_oids = true;

CREATE SCHEMA smsnews;
COMMENT ON SCHEMA smsnews IS 'SMS news subscriptions';



-- ***************************** TOPICS *****************************

CREATE TABLE smsnews.topic_groups
(
  id serial NOT NULL,
  groupname character varying(128) NOT NULL,

  CONSTRAINT groups_pkey PRIMARY KEY (id),
  CONSTRAINT groups_group_key UNIQUE (groupname)
);
COMMENT ON TABLE smsnews.topic_groups IS 'topic groups for visual separation';
COMMENT ON COLUMN smsnews.topic_groups.groupname IS 'mnemonic group name';


CREATE TABLE smsnews.topics
(
  id serial NOT NULL,
  topic character varying(256) NOT NULL,
  code character varying(8) NOT NULL,
  group_id integer NOT NULL,
  priority smallint NOT NULL DEFAULT 0,

  CONSTRAINT topics_pkey PRIMARY KEY (id),
  CONSTRAINT topics_group_id_fkey FOREIGN KEY (group_id)
      REFERENCES smsnews.topic_groups (id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION,
  CONSTRAINT topics_code_key UNIQUE (code)
);
COMMENT ON TABLE smsnews.topics IS 'content topics';
COMMENT ON COLUMN smsnews.topics.topic IS 'topic description.';
COMMENT ON COLUMN smsnews.topics.code IS 'mnemonic code for SMS based subscription';
COMMENT ON COLUMN smsnews.topics.group_id IS 'group reference';
COMMENT ON COLUMN smsnews.topics.priority IS 'visual sorting priority';


-- ***************************** CATEGORIES *****************************

CREATE TABLE smsnews.categories
(
  id serial NOT NULL, -- unique identifier
  category character varying(64) NOT NULL, -- subscriber category name
  descr character varying(512), -- more detailed description

  CONSTRAINT categories_pkey PRIMARY KEY (id),
  CONSTRAINT categories_category_key UNIQUE (category)
);
COMMENT ON TABLE smsnews.categories IS 'subscribers categories';
COMMENT ON COLUMN smsnews.categories.id IS 'unique identifier';
COMMENT ON COLUMN smsnews.categories.category IS 'subscriber category name';
COMMENT ON COLUMN smsnews.categories.descr IS 'more detailed description';


-- ***************************** SUBSCRIBERS *****************************

CREATE TABLE smsnews.subscribers
(
  id serial NOT NULL, -- unique identifier
  msisdn numeric(16,0) NOT NULL, -- phone number without +
  category_id integer,
  created timestamp(0) without time zone NOT NULL DEFAULT now(),
  expire timestamp(0) without time zone NOT NULL DEFAULT (now() + '1 mon'::interval), -- global subscriber expiration
  usage_days integer NOT NULL DEFAULT 0, -- number of days subscriber is in production stage
  test_until timestamp(0) without time zone,
  status character varying(16) NOT NULL DEFAULT 'INACTIVE'::character varying,
  comments text,
  "name" character varying(256),

  CONSTRAINT subscribers_pkey PRIMARY KEY (id),
  CONSTRAINT subscribers_category_id_fkey FOREIGN KEY (category_id)
      REFERENCES smsnews.categories (id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION,
  CONSTRAINT subscribers_msisdn_key UNIQUE (msisdn)
);
COMMENT ON TABLE smsnews.subscribers IS 'Subscribers';
COMMENT ON COLUMN smsnews.subscribers.id IS 'unique identifier';
COMMENT ON COLUMN smsnews.subscribers.msisdn IS 'phone number without +';
COMMENT ON COLUMN smsnews.subscribers.expire IS 'global subscriber expiration';
COMMENT ON COLUMN smsnews.subscribers.usage_days IS 'number of days subscriber is in production stage';

CREATE INDEX subscribers_category_id_idx ON smsnews.subscribers USING btree (category_id);
CREATE INDEX subscribers_expire_idx ON smsnews.subscribers USING btree (expire);
CREATE INDEX subscribers_msisdn_idx ON smsnews.subscribers USING btree (msisdn);
CREATE INDEX subscribers_status_idx ON smsnews.subscribers USING btree (status);

-- ***************************** SUBSCRIPTIONS *****************************

CREATE TABLE smsnews.subscriptions
(
  id bigserial NOT NULL,
  subscriber_id integer NOT NULL,
  topic_id integer NOT NULL,
  created timestamp(0) without time zone NOT NULL DEFAULT now(),
  started timestamp(0) without time zone, -- subscription activity period
  stopped timestamp(0) without time zone, -- subscription activity period
  payment character varying(256), -- payment information
  status character varying(16) NOT NULL DEFAULT 'ACTIVE'::character varying,
	sn numeric(8,0) NOT NULL,

  CONSTRAINT subscriptions_pkey PRIMARY KEY (id),
  CONSTRAINT subscriptions_subscriber_id_fkey FOREIGN KEY (subscriber_id)
      REFERENCES smsnews.subscribers (id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION,
  CONSTRAINT subscriptions_topic_id_fkey FOREIGN KEY (topic_id)
      REFERENCES smsnews.topics (id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION,
  CONSTRAINT subscriptions_subscriber_id_key UNIQUE (subscriber_id, topic_id)
);
COMMENT ON TABLE smsnews.subscriptions IS 'Subscriptions: topics linked to subscribers';
COMMENT ON COLUMN smsnews.subscriptions.started IS 'subscription activity period';
COMMENT ON COLUMN smsnews.subscriptions.stopped IS 'subscription activity period';
COMMENT ON COLUMN smsnews.subscriptions.payment IS 'payment information';
COMMENT ON COLUMN smsnews.subscriptions.sn IS 'short code to use as broadcasting src_addr';

CREATE INDEX subscriptions_started_stopped_idx ON smsnews.subscriptions USING btree (started, stopped);
CREATE INDEX subscriptions_status_idx ON smsnews.subscriptions USING btree (status);
CREATE INDEX subscriptions_subscriber_id_idx ON smsnews.subscriptions USING btree (subscriber_id);
CREATE INDEX subscriptions_topic_id_idx ON smsnews.subscriptions USING btree (topic_id);

-- ***************************** CONTENT *****************************

CREATE TABLE smsnews.queue
(
  id bigserial NOT NULL, -- unique identifier
  msg_id uuid NOT NULL DEFAULT uuid_generate_v1(), -- message UUID for external usage
  created timestamp(0) without time zone NOT NULL DEFAULT now(), -- message creation time
  creator character varying(64) NOT NULL, -- login of manager created message
  send_time timestamp(0) without time zone NOT NULL, -- when message should be sent
  topics integer[] NOT NULL, -- array of destination topics
  category_id integer NOT NULL, -- destination subscribers category
  priority smallint NOT NULL DEFAULT 0, -- 1 - high priority
  msg_type character varying(16) NOT NULL DEFAULT 'SMS_TEXT'::character varying, -- type of message body
  msg_body text, -- message body (plain text for SMS_TEXT)
  extra text, -- additional information as JSON structure
  status character varying(16) NOT NULL DEFAULT 'NEW'::character varying, -- event status (NEW, SENT)
  num_subs integer NOT NULL DEFAULT 0, -- number of subscribers
  num_sms integer NOT NULL DEFAULT 0, -- number of SMS expected
  num_test integer NOT NULL DEFAULT 0, -- number of test subscribers
  coding smallint NOT NULL DEFAULT 0, -- SMS coding (0 - lat, 2 - cyr)

  CONSTRAINT queue_pkey PRIMARY KEY (id),
  CONSTRAINT queue_category_id_fkey FOREIGN KEY (category_id)
      REFERENCES smsnews.categories (id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION,
  CONSTRAINT queue_msg_id_key UNIQUE (msg_id)
);
COMMENT ON TABLE smsnews.queue IS 'news messages queue';
COMMENT ON COLUMN smsnews.queue.id IS 'unique identifier';
COMMENT ON COLUMN smsnews.queue.msg_id IS 'message UUID for external usage';
COMMENT ON COLUMN smsnews.queue.created IS 'message creation time';
COMMENT ON COLUMN smsnews.queue.creator IS 'login of manager created message';
COMMENT ON COLUMN smsnews.queue.send_time IS 'when message should be sent';
COMMENT ON COLUMN smsnews.queue.topics IS 'array of destination topics';
COMMENT ON COLUMN smsnews.queue.category_id IS 'destination subscribers category';
COMMENT ON COLUMN smsnews.queue.priority IS '1 - high priority';
COMMENT ON COLUMN smsnews.queue.msg_type IS 'type of message body';
COMMENT ON COLUMN smsnews.queue.msg_body IS 'message body (plain text for SMS_TEXT)';
COMMENT ON COLUMN smsnews.queue.extra IS 'additional information as JSON structure';
COMMENT ON COLUMN smsnews.queue.status IS 'event status (NEW, SENT)';
COMMENT ON COLUMN smsnews.queue.num_subs IS 'number of subscribers';
COMMENT ON COLUMN smsnews.queue.num_sms IS 'number of SMS expected';
COMMENT ON COLUMN smsnews.queue.num_test IS 'number of test subscribers';
COMMENT ON COLUMN smsnews.queue.coding IS 'SMS coding (0 - lat, 2 - cyr)';

CREATE INDEX queue_category_id_idx ON smsnews.queue USING btree (category_id);
CREATE INDEX queue_created_idx ON smsnews.queue USING btree (created);
CREATE INDEX queue_priority_idx ON smsnews.queue USING btree (priority);
CREATE INDEX queue_send_time_idx ON smsnews.queue USING btree (send_time);
CREATE INDEX queue_status_idx ON smsnews.queue USING btree (status);
CREATE INDEX queue_topics_idx ON smsnews.queue USING btree (topics);

-- ***************************** LOGS *****************************

CREATE TABLE smsnews.msg_meta
(
  id bigint NOT NULL,
  topic_id integer,
  queue_id bigint,
  sub_id integer,
  num_sms smallint DEFAULT 1,
  msisdn numeric(16,0),
	sn numeric(8,0) NOT NULL,
  is_test boolean DEFAULT false,

  CONSTRAINT msg_meta_pkey PRIMARY KEY (id)
);
COMMENT ON TABLE smsnews.msg_meta IS 'meta data of broadcasted news messages';

CREATE INDEX msg_meta_msisdn_idx ON smsnews.msg_meta USING btree (msisdn);
CREATE INDEX msg_meta_queue_id_idx ON smsnews.msg_meta USING btree (queue_id);
CREATE INDEX msg_meta_sub_id_idx ON smsnews.msg_meta USING btree (sub_id);
CREATE INDEX msg_meta_topic_id_idx ON smsnews.msg_meta USING btree (topic_id);


CREATE TABLE smsnews."log"
(
  id bigserial NOT NULL, -- unique identifier
  created timestamp(0) without time zone NOT NULL DEFAULT now(), -- when record created
  subscriber_id integer, -- reference to subscriber
  message character varying(1024), -- text of the message
  "level" character varying(32) NOT NULL DEFAULT 'info'::character varying, -- Event level - syslog like
  event character varying(64) NOT NULL DEFAULT 'generic'::character varying, -- type of business logic event

  CONSTRAINT log_pkey PRIMARY KEY (id)
);
COMMENT ON TABLE smsnews."log" IS 'Business events for subscriptions.';
COMMENT ON COLUMN smsnews."log".id IS 'unique identifier';
COMMENT ON COLUMN smsnews."log".created IS 'when record created';
COMMENT ON COLUMN smsnews."log".subscriber_id IS 'reference to subscriber';
COMMENT ON COLUMN smsnews."log".message IS 'text of the message';
COMMENT ON COLUMN smsnews."log"."level" IS 'Event level - syslog like';
COMMENT ON COLUMN smsnews."log".event IS 'type of business logic event';

CREATE INDEX log_created_idx ON smsnews."log" USING btree (created);

-- ***************************** VIEWS, FUNCTIONS *****************************

CREATE OR REPLACE FUNCTION smsnews.subscriber_exists(msisdn numeric) RETURNS boolean AS
	'select exists (select id from smsnews.subscribers where msisdn=$1)'
LANGUAGE sql;

CREATE OR REPLACE VIEW smsnews.active_subscribers AS 
 SELECT * FROM smsnews.subscribers WHERE status = 'ACTIVE';
COMMENT ON VIEW smsnews.active_subscribers IS 'Subscribers that are active and not expired';

CREATE OR REPLACE VIEW smsnews.active_subscriptions AS 
 SELECT * FROM smsnews.subscriptions WHERE status = 'ACTIVE' AND now() <= stopped;
COMMENT ON VIEW smsnews.active_subscriptions IS 'Active subscriptions';



-- ***************************** INITIAL CONTENT *****************************

INSERT INTO smsnews.categories (category,descr) VALUES ('DEFAULT','Generic SMS subscribers (DO NOT REMOVE!)');
INSERT INTO smsnews.topic_groups (groupname) VALUES ('SMS News');
INSERT INTO smsnews.topics (topic, code, group_id) SELECT 'Default Topic', 'DEFAULT', id FROM smsnews.topic_groups LIMIT 1;

-- ***************************** ADD APPLICATION *****************************

INSERT INTO core.apps (name,descr) VALUES ('app_smsnews','SMS Subscription application');
INSERT INTO core.apps_conf (app_id,tag,value) VALUES (core.get_app_id('app_smsnews'),'smsnews_prolongate_days','30');
INSERT INTO core.apps_conf (app_id,tag,value) VALUES (core.get_app_id('app_smsnews'),'msg_help','To subscribe send SUB TOPIC message.');
INSERT INTO core.apps_conf (app_id,tag,value) VALUES (core.get_app_id('app_smsnews'),'msg_unknown_topic','Sorry. We cannot find such topic for you.');
INSERT INTO core.apps_conf (app_id,tag,value) VALUES (core.get_app_id('app_smsnews'),'msg_prolongated','Congratulations! Your subscription was prolongated.');
INSERT INTO core.apps_conf (app_id,tag,value) VALUES (core.get_app_id('app_smsnews'),'msg_sub_ok','Congratulations! You have subscribed successfully.');
INSERT INTO core.apps_conf (app_id,tag,value) VALUES (core.get_app_id('app_smsnews'),'msg_unsub_ok','You have unsubscribed successfully.');
INSERT INTO core.apps_conf (app_id,tag,value) VALUES (core.get_app_id('app_smsnews'),'msg_unsub_none','You are not subscribed to any topic.');


INSERT INTO core.translations (lang, keyword, value, service) VALUES ('en', 'cms_head_ctl-smsnews', 'SMS Subscriptions', 'core');
INSERT INTO core.translations (lang, keyword, value, service) VALUES ('ru', 'cms_head_ctl-smsnews', 'SMS Подписка', 'core');

