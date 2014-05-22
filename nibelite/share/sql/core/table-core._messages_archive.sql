CREATE TABLE core._messages_archive
(
  CONSTRAINT _messages_archive_msg_status_check CHECK (msg_status NOT IN ('NEW', 'ROUTED', 'SENT')),
  CONSTRAINT _messages_archive_prio_check CHECK (prio IN (0, 1))
)
INHERITS (core.messages)
WITH (OIDS=TRUE);

COMMENT ON TABLE core._messages_archive IS 'Archived messages';

CREATE INDEX _archive_typ_rcv_idx ON core._messages_archive (msg_type, date_received);
CREATE INDEX _archive_type_idx ON core._messages_archive (msg_type);
CREATE INDEX _messages_archive_id_idx ON core._messages_archive (id);

