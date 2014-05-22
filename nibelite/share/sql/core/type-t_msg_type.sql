CREATE TYPE core.t_msg_type AS ENUM
   ('UNKNOWN',
    'SMS_TEXT',
    'SMS_RAW',
    'SMS_WAPPUSH',
    'SMS_OTA',
    'SMS_MWI',
    'SMS_VCARD',
    'SMS_VCAL',
    'DLR',
    'MMS',
    'USSD',
    'EMAIL',
    'IM');
COMMENT ON TYPE core.t_msg_type IS 'Message types';

