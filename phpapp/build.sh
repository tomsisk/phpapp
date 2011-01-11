#!/bin/sh
cd ..
tar czvf phpapp/phpapp.tar.gz phpapp/src/phpapp-* phpapp/test/ \
		phpapp/README phpapp/INSTALL phpapp/LICENSE phpapp/AUTHORS \
		phpapp/CHANGELOG \
		--exclude=**/.svn
