CREATE TABLE core._messages_queue
(
  CONSTRAINT _messages_queue_msg_status_check CHECK (msg_status IN ('NEW', 'ROUTED', 'SENT', 'UNKNOWN','FAILED')),
  CONSTRAINT _messages_queue_prio_check CHECK (prio IN (0, 1))
)
INHERITS (core.messages)
WITH (OIDS=TRUE);

COMMENT ON TABLE core._messages_queue IS 'Active messages queue';

CREATE INDEX _archive_queue_idx ON core._messages_queue (msg_type);
CREATE INDEX _messages_queue_id_idx ON core._messages_queue (id);
CREATE INDEX _messages_queue_status_idx ON core._messages_queue (msg_status);
CREATE INDEX _messages_recv_idx ON core._messages_queue (date_received);
CREATE INDEX _queue_typ_rcv_idx ON core._messages_queue (msg_type, date_received);

CREATE TRIGGER update_dst_route BEFORE INSERT ON core._messages_queue FOR EACH ROW EXECUTE PROCEDURE core.route_message();
