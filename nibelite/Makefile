HOMEDIR=$(DESTDIR)/opt/nibelite

all: install

install:
	mkdir -p $(HOMEDIR)
	mkdir -p $(DESTDIR)/etc/rc.d/init.d
	cp share/scripts/* $(DESTDIR)/etc/rc.d/init.d
	chmod 755 $(DESTDIR)/etc/rc.d/init.d/*
	cp -rf admin partners apps channels etc lib control share bin gui $(HOMEDIR)/
	chmod 750 $(HOMEDIR)/bin/*

update:
	cp share/scripts/* $(DESTDIR)/etc/rc.d/init.d
	chmod 755 $(DESTDIR)/etc/rc.d/init.d/*
	cp -rf admin partners apps channels lib control share bin gui $(HOMEDIR)/
	chmod 750 $(HOMEDIR)/bin/*

ui:
	cp -rf gui $(HOMEDIR)/
	cp -rf lib/perl $(HOMEDIR)/lib/

uninstall:
	rm -rf $(HOMEDIR)
