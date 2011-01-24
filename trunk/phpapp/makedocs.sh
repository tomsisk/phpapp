#!/bin/bash
phpdoc -ti PHPApp -f "src/phpapp-core/*.class.php,src/phpapp-modules/*.class.php" -i "*/phpthumb.class.php" -o HTML:frames:default -t docs/phpdoc #-po phpapp-core,phpapp-modules
