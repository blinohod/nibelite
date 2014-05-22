CREATE TABLE core.messages
(
  id bigserial NOT NULL,
  refer_id bigint, -- optional message reference (identify replies and dlrs)
  uuid uuid DEFAULT uuid_generate_v1(), -- externally used id
  src_app_id integer NOT NULL DEFAULT 0, -- application generated this message
  dst_app_id integer, -- application to receive this message
  src_addr character varying(64) NOT NULL, -- originator address
  dst_addr character varying(64) NOT NULL, -- recipient address
  date_received timestamp(0) without time zone NOT NULL DEFAULT now(), -- when message injected to platform
  date_processed timestamp(0) without time zone, -- when message was processed
  msg_status core.t_msg_status NOT NULL DEFAULT 'NEW'::core.t_msg_status, -- message processing status
  external_id character varying(256), -- external message ID (from SMSC)
  charging character varying(32), -- SMSC specific charging information
  msg_type core.t_msg_type NOT NULL DEFAULT 'SMS_TEXT'::core.t_msg_type, -- message type (sms, mms, etc)
  msg_body text, -- message body encoded in accordance with msg_type
  prio smallint NOT NULL DEFAULT 0, -- priority (0 - bulk, 1 - interactive)
  retries smallint DEFAULT 0, -- number of delivery retries
  extra bytea, -- additional message parameters as JSON encoded record
	qty smallint NOT NULL DEFAULT 1, -- number of PDU

  CONSTRAINT messages_pkey PRIMARY KEY (id),
  CONSTRAINT messages_dst_app_id_fkey FOREIGN KEY (dst_app_id)
      REFERENCES core.apps (id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION,
  CONSTRAINT messages_src_app_id_fkey FOREIGN KEY (src_app_id)
      REFERENCES core.apps (id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION,
  CONSTRAINT messages_prio_check CHECK (prio IN (0, 1))
)
WITH (OIDS=TRUE);

COMMENT ON TABLE core.messages IS 'all messages - virtual table gathering spool and archive';
COMMENT ON COLUMN core.messages.refer_id IS 'optional message reference (identify replies and dlrs)';
COMMENT ON COLUMN core.messages.uuid IS 'externally used id';
COMMENT ON COLUMN core.messages.src_app_id IS 'application generated this message';
COMMENT ON COLUMN core.messages.dst_app_id IS 'application to receive this message';
COMMENT ON COLUMN core.messages.src_addr IS 'originator address';
COMMENT ON COLUMN core.messages.dst_addr IS 'recipient address';
COMMENT ON COLUMN core.messages.date_received IS 'when message injected to platform';
COMMENT ON COLUMN core.messages.date_processed IS 'when message was processed';
COMMENT ON COLUMN core.messages.msg_status IS 'message processing status';
COMMENT ON COLUMN core.messages.external_id IS 'external message ID (from SMSC)';
COMMENT ON COLUMN core.messages.charging IS 'SMSC specific charging information';
COMMENT ON COLUMN core.messages.msg_type IS 'message type (sms, mms, etc)';
COMMENT ON COLUMN core.messages.msg_body IS 'message body encoded in accordance with msg_type';
COMMENT ON COLUMN core.messages.prio IS 'priority (0 - bulk, 1 - interactive)';
COMMENT ON COLUMN core.messages.retries IS 'number of delivery retries';
COMMENT ON COLUMN core.messages.extra IS 'additional message parameters as JSON encoded record';
COMMENT ON COLUMN core.messages.qty IS 'number of PDU';


CREATE INDEX messages_id_idx ON core.messages (id);
