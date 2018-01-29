ALTERNC_BASE_PATH ?= /usr/share/alternc/panel/
LOCALDIR=$(DESTDIR)$(ALTERNC_BASE_PATH)
CSS_IDENTIFIER = "/*** alternc-oneclickinstaller ***/"
C:=$(grep -s "$(CSS_IDENTIFIER) $(LOCALDIR)admin/styles/style-custom.css" 2>&1 > /dev/null; echo $$?)

build:

all:

binary-arch:

install:
	install -d $(LOCALDIR)
	install -d $(LOCALDIR)admin/styles
	cp -r src/* $(LOCALDIR)
	cat css/style-custom.css >> $(LOCALDIR)admin/styles/style-custom.css
