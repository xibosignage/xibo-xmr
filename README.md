# Introduction

Xibo - Digital Signage - https://xibosignage.com
Copyright (C) 2006-2023 Xibo Signage Ltd and Contributors.

This is the Xibo Message Relay (XMR) repository.

XMR is an application which acts as a ZeroMQ message exchange between the Xibo CMS and connected Xibo Players. It forwards messages from the CMS to a pub/sub socket according to their QOS (quality of service) priority.

**If you are here for anything other than software development purposes, it is unlikely you are in the right place. XMR is shipped with the Xibo CMS installation and you would usually install it from there.**

## Installation

XMR can be run using Docker and Docker Compose, for example:

```yaml
version: "3"

services:
  xmr:
    image: xibosignage/xibo-xmr:latest
    ports:
     - "9505:9505"
     - "50001:50001"
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
