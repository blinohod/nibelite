HOMEDIR=$(DESTDIR)/opt/nibelite

all: install

install:
	mkdir -p $(DESTDIR)/etc/rc.d/init.d
	mkdir -p $(DESTDIR)/etc/monit.d
	install -m755 scripts/tvchat.init $(DESTDIR)/etc/rc.d/init.d/nibelite.tvchat
	install -m644 scripts/nibelite.tvchat.monit $(DESTDIR)/etc/monit.d/nibelite.tvchat
	mkdir -p $(HOMEDIR)/{apps,bin,etc/apps,share/setup,share/sql/tvchat,control,partners/tvchat,share/templates-old,custom}
	install -m644 etc/apps/tvchat.conf $(HOMEDIR)/etc/apps
	install -m755 apps/tvchat $(HOMEDIR)/apps
	install -m644 control/* $(HOMEDIR)/control
	install -m644 partners/* $(HOMEDIR)/partners/tvchat
	install -m644 templates/* $(HOMEDIR)/share/templates-old
	install -m644 share/tvchat.sql $(HOMEDIR)/share/sql/tvchat
	install -m755 scripts/bootstrap-tvchat.sh $(HOMEDIR)/share/setup
