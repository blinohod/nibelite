HOMEDIR=$(DESTDIR)/opt/nibelite

all: install

install:
	mkdir -p $(DESTDIR)/etc/rc.d/init.d
	#install -m755 scripts/smsnews.init $(DESTDIR)/etc/rc.d/init.d/nibelite.smsnews
	mkdir -p $(HOMEDIR)/apps
	mkdir -p $(HOMEDIR)/etc/apps
	mkdir -p $(HOMEDIR)/share/setup
	mkdir -p $(HOMEDIR)/share/sql/smsnews
	mkdir -p $(HOMEDIR)/share/templates-old
	mkdir -p $(HOMEDIR)/share/reports-old
	mkdir -p $(HOMEDIR)/control
	mkdir -p $(HOMEDIR)/lib/php/webgui
	mkdir -p $(DESTDIR)/etc/monit.d
	install -m750 scripts/smsnews-broadcast.init $(DESTDIR)/etc/rc.d/init.d/nibelite.smsnews-broadcast
	install -m750 scripts/smsnews-subscribe.init $(DESTDIR)/etc/rc.d/init.d/nibelite.smsnews-subscribe
	install -m644 scripts/nibelite.smsnews-subscribe.monit $(DESTDIR)/etc/monit.d/nibelite.smsnews-subscribe
	install -m644 scripts/nibelite.smsnews-broadcast.monit $(DESTDIR)/etc/monit.d/nibelite.smsnews-broadcast
	install -m644 etc/apps/smsnews.conf $(HOMEDIR)/etc/apps
	install -m755 apps/smsnews-* $(HOMEDIR)/apps/
	install -m644 control/* $(HOMEDIR)/control/
	install -m644 templates/* $(HOMEDIR)/share/templates-old/
	install -m644 reports/* $(HOMEDIR)/share/reports-old/
	install -m644 share/smsnews.sql $(HOMEDIR)/share/sql/smsnews/
	install -m644 lib-webgui/* $(HOMEDIR)/lib/php/webgui/
	install -m755 scripts/bootstrap-smsnews.sh $(HOMEDIR)/share/setup
