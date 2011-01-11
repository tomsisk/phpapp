#!/bin/sh
cd ..
tar czvf modelquery/modelquery.tar.gz modelquery/modelquery/ modelquery/test/ modelquery/bin/ \
		modelquery/README modelquery/INSTALL modelquery/LICENSE modelquery/AUTHORS \
		modelquery/CHANGELOG \
		--exclude=**/.svn
