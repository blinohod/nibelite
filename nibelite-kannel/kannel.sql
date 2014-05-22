BEGIN TRANSACTION;

CREATE TABLE dlr
(
  smsc character varying(40), -- SMSC identifier
  ts character varying(256), -- transaction ID
  destination character varying(40), -- destination address
  source character varying(40), -- source address
  service character varying(40), -- service name
  url character varying(1024), -- DLR URL
  mask integer, -- DLR binary mask
  status integer, -- SM status
  boxc character varying(48), -- smsbox ID
  ts_time timestamp without time zone DEFAULT now() -- transaction timestamp
) WITH (OIDS=TRUE);

COMMENT ON TABLE dlr IS 'Kannel DLR storage';
COMMENT ON COLUMN dlr.smsc IS 'SMSC identifier';
COMMENT ON COLUMN dlr.ts IS 'transaction ID';
COMMENT ON COLUMN dlr.destination IS 'destination address';
COMMENT ON COLUMN dlr.source IS 'source address';
COMMENT ON COLUMN dlr.service IS 'service name';
COMMENT ON COLUMN dlr.url IS 'DLR URL';
COMMENT ON COLUMN dlr.mask IS 'DLR binary mask';
COMMENT ON COLUMN dlr.status IS 'SM status';
COMMENT ON COLUMN dlr.boxc IS 'smsbox ID';
COMMENT ON COLUMN dlr.ts_time IS 'transaction timestamp';


CREATE INDEX dlr_smsc_idx ON dlr (smsc);
CREATE INDEX dlr_ts_idx ON dlr (ts);

COMMIT;
