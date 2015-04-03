XG ?= php ../../_transifex/xg.php
locales = $(patsubst /%,%,$(subst locales,,$(shell find locales -maxdepth 1 -type d)))

all: update-global clean

update-pot:
	$(XG) CWD extract

update-po:
	$(XG) CWD merge $(locales)

update-global: update-pot update-po
	$(XG) CWD convert $(locales)

clean:
	rm -f $(wildcard locales/*/LC_MESSAGES/*~)
