PREFIX=
ALTERNC_BASE_PATH ?= /usr/share/alternc/panel/
CSS_IDENTIFIER = "/*** alternc-oneclickinstaller ***/"

install:
	cp -r src/ $(PREFIX)/$(ALTERNC_BASE_PATH)
	C := $(grep -q "$(CSS_IDENTIFIER)" ; echo $$?)
	ifneq ($(C), 0)
		cat css/style-custom.css >> $(PREFIX)/$(ALTERNC_BASE_PATH)/admin/styles/style-custom.css
	endif
