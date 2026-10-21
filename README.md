# Introduction
Xibo - Digital Signage - https://xibosignage.com
Copyright (C) 2006-2026 Xibo Signage Ltd and Contributors.

This is the Xibo Message Relay (XMR) repository.

XMR is a php application built on ReactPHP/Ratchet which acts as a push message exchange between the Xibo CMS and 
connected Xibo Displays . It doesn't do anything beyond forward messages from the CMS to either a WebSocket or 
a ZeroMQ pub/sub socket.

It is packaged into a PHAR file which is included in the [Xibo CMS](https://github.com/xibosignage/xibo-cms) 
release files.

**If you are here for anything other than software development purposes, it is unlikely you are in the right place.
XMR is shipped with the Xibo CMS installation and you would usually install it from there.**


## Installation
XMR can be run using Docker and Compose, for example:

```yaml
version: "3"

services:
  xmr:
    image: xibosignage/xibo-xmr:latest
    ports:
      - "8080:8080" # WebSocket
      - "9505:9505" # ZMQ Pub Socket
```

You may also build this library from source code:

1. Clone this repository
2. Run `./build.sh`
3. Run `docker-compose up --build`


## Tests
Run `tests/run.sh` to build the image, start XMR and run the test suite against it. The tests run inside the XMR
image, so they use the same PHP and extensions that ship. Arguments are passed to PHPUnit, for example
`tests/run.sh --testsuite Unit`.



You may also reference this code in your own projects via Composer:

```bash
composer require xibosignage/xibo-xmr
```


## Licence
Xibo is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public
License as published by the Free Software Foundation, either version 3 of the License, or any later version.

Xibo is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty
of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public License for more details.

You should have received a copy of the GNU Affero General Public License along with Xibo.  If not,
see <http://www.gnu.org/licenses/>. 



#### 3rd Party
We use BOX to package the PHAR file - see https://github.com/box-project/box2
