HOMEDIR=/opt/nibelite

all: prepare install

prepare:
	mkdir -p $(HOMEDIR)/applications/voting
	mkdir -p $(HOMEDIR)/control
	mkdir -p $(HOMEDIR)/partners
	mkdir -p $(HOMEDIR)/config/reports
	mkdir -p $(HOMEDIR)/config/templates
	mkdir -p $(HOMEDIR)/sql/voting

install:
	install -m755 voting.app.pl $(HOMEDIR)/applications/voting/voting.pl
	install -m755 voting.conf $(HOMEDIR)/applications/voting/voting.conf
	install -m644 12-voting-raw-stats.inc.php $(HOMEDIR)/config/reports/
	install -m644 voting.control.php $(HOMEDIR)/control/votingsms.php
	install -m644 voting.partners.php $(HOMEDIR)/partners/votingsms.php
	install -m644 design.votingsms.html $(HOMEDIR)/config/templates/
	install -m644 partners.votingsms.html $(HOMEDIR)/config/templates/
	install -m644 voting.sql $(HOMEDIR)/sql/voting

