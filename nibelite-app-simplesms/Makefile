HOMEDIR=/opt/nibelite

all: prepare install

prepare:
	mkdir -p $(HOMEDIR)/applications/simplesms
	mkdir -p $(HOMEDIR)/control
	mkdir -p $(HOMEDIR)/partners
	mkdir -p $(HOMEDIR)/config/reports
	mkdir -p $(HOMEDIR)/config/templates
	mkdir -p $(HOMEDIR)/sql/simplesms

install:
	install -m755 simplesms.app.pl $(HOMEDIR)/applications/simplesms/simplesms.pl
	install -m755 simplesms.conf $(HOMEDIR)/applications/simplesms/simplesms.conf
	install -m644 simplesms.sql $(HOMEDIR)/sql/simplesms
	install -m644 simplesms.php $(HOMEDIR)/control
	install -m644 design.simplesms.html $(HOMEDIR)/config/templates
	install -m644 design.simpleads.html $(HOMEDIR)/config/templates
	install -m644 design.simplecontent.html $(HOMEDIR)/config/templates
	install -m644 design.simpletopics.html $(HOMEDIR)/config/templates

