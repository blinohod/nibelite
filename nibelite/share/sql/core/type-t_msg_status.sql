CREATE TYPE core.t_msg_status AS ENUM
   ('NEW',
    'ROUTED',
    'PROCESSED',
    'SENT',
    'DELIVERED',
    'UNDELIVERABLE',
    'EXPIRED',
    'REJECTED',
    'FAILED',
    'UNKNOWN');
COMMENT ON TYPE core.t_msg_status IS 'Message status';

