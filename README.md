FileWatcher PHP class
=====================

* Copyright (c) 2011, [http://www.phpgangsta.de](http://www.phpgangsta.de)
* Author: Michael Kliewe, [@PHPGangsta](http://twitter.com/PHPGangsta)
* Licensed under the BSD License.

Usage:
------
1 edit FileWatcher.config.php and set email address, password and all paths and filenames
2 create your main script, see example.php. Should be more or less 4 lines like this:

    <?php

    require_once 'FileWatcher.php';
    $fileWatcher = new FileWatcher();

    $fileWatcher->readConfig('FileWatcher.config.php')
                ->checkNow();

3 upload everything important to your website (you don't need README and ToDo).
4 run this script on the server regularly (password and overallHash are optional):

    http://www.domain.de/example.php

or from CLI:

    php example.php

- make sure 2 files have been created on the first run: FileWatcher.log and FileWatcher.MasterHashes.txt

Security considerations:
------------------------
- If you want to make it a little bit more secure you can set a password and provide it via GET, POST or CLI parameter. POST is preferrred over GET, for example via curl or wget, then the password is not logged to Apache access.log
- You can also provide the script with an overall hash that you provide via GET or POST like this:
  http://www.domain.de/example.php?password=YourPassword&overallhash=243a7ab8c30ebdf31728fd947a1bab0ad7ddfc65
- If possible you should put all data outside your document root, otherwise the hacker could also change these files and disable your FileWatcher or manipulate the master hashes file. Obviously then you cannot use the obove http calls, you then have to work with local cronjobs on the machine.

ToDo:
-----
- add example calls with curl or wget when sending POST data to the script
- provide more information about new/deleted/changed files: modified time, creation time, on a new file perhaps the first 10 lines?
- add additional parameters to mail() call, for example sendmail sometimes needs -f
- change alert methods to a plugin structure, so new alert methods can be added easily
- make alert email a bit nicer, perhaps a nice html mail with a table and color codes, like a colored diff?
- add logFilename and masterHashesFilename to exclude dir if includePaths contains .
- also support checking filesize and last-modified-timestamp, that should be faster on large or many files
- maybe skipping of excluded files (excludeFolderList, excludeExtensionList) is easier with FilterIterator

Notes:
------
If you like this script or have some features to add, contact me, fork this project, send pull requests, you know how it works.