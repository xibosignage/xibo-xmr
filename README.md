# Introduction
Xibo - Digital Signage - http://www.xibo.org.uk
Copyright (C) 2006-2021 Xibo Signage Ltd and Contributors.

This is the Xibo Message Relay (XMR) repository.

XMR is a php application built on ReactPHP which acts as a ZeroMQ message exchange between the Xibo CMS and connected Xibo Players. It doesn't do anything beyond forward messages from the CMS to a pub/sub socket.

It is packaged into a PHAR file which is included in the [Xibo CMS](https://github.com/xibosignage/xibo-cms) release files.

**If you are here for anything other than software development purposes, it is unlikely you are in the right place. XMR is shipped with the Xibo CMS installation and you would usually install it from there.**



## Installation
XMR can be run using Docker and Compose, for example:

```yaml
version: "3"

services:
  xmr:
    image: xibosignage/xibo-xmr:latest
    ports:
     - "9505:9505"
     - "50001:50001"
```



You may also build this library from source code:

1. Clone this repository
2. Run `./build.sh`
3. Run `docker-compose up --build`



You may also reference this code in your own projects via Composer:

```bash
composer require xibosignage/xibo-xmr
```



### Ports

XMR requires a listen address and a publish address and therefore needs 2 ports. The listen address is used for communication with the CMS (incoming comms) and the publish address is used for outgoing messages.

When running in Docker, you will want to expose these ports to your machine OR connect your container to a Docker network which will facilitate communication with these ports.

An example ports directive would be:

``` yaml
ports:
     - "9505:9505" #Publish
     - "50001:50001" #Listen
```





## Licence

Xibo is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or any later version.

Xibo is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public License for more details.

You should have received a copy of the GNU Affero General Public License along with Xibo.  If not, see <http://www.gnu.org/licenses/>. 



#### 3rd Party

We use BOX to package the PHAR file - see https://github.com/box-project/box2