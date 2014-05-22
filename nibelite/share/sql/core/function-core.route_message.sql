CREATE OR REPLACE FUNCTION core.route_message() RETURNS trigger AS
$BODY$

DECLARE
  target_id integer;

BEGIN

  -- Not a new message, seems already routed
  IF NEW.msg_status <> 'NEW'::core.t_msg_status THEN
    RETURN NEW;
  END IF;

  -- Look for proper destination route
  SELECT INTO target_id core.get_message_route(NEW.src_app_id, NEW.msg_type, NEW.src_addr, NEW.dst_addr, NEW.msg_body);

	-- Set messages status and destination application
  IF target_id IS NULL THEN
    NEW.msg_status := 'FAILED'::core.t_msg_status; -- No target
		RAISE NOTICE 'Impossible to find destination route: from=% to=%', NEW.src_addr, NEW.dst_addr;
  ELSE
    NEW.dst_app_id := target_id;
    NEW.msg_status := 'ROUTED'::core.t_msg_status; -- Routed to application
  END IF;

	-- Check for possible routing loop
	IF (NEW.dst_app_id = NEW.src_app_id) THEN
		NEW.msg_status := 'FAILED'::core.t_msg_status; -- Loops are not allowed!
		RAISE NOTICE 'Routing loop: from=% to=%', NEW.src_addr, NEW.dst_addr;
	END IF;

	-- Return updated message record
  RETURN NEW;
  
END;
$BODY$
LANGUAGE plpgsql;

COMMENT ON FUNCTION core.route_message() IS 'Provides routing functionality - set dst_app_id';

