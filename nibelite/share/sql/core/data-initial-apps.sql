INSERT INTO core.apps (id,name,descr) VALUES (0,'ANY','Pseudo application for routing purposes');

INSERT INTO core.apps (name,descr) VALUES ('app_echotest','Sample echotest application');
INSERT INTO core.apps_conf (app_id,tag,value) VALUES (core.get_app_id('app_echotest'),'bandwidth','5');

