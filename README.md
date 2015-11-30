# Introduction
Xibo - Digital Signage - http://www.xibo.org.uk
Copyright (C) 2006-2015 Daniel Garner and Contributors.

This is the Xibo Message Relay (XMR) repository.

XMR is a php application built on ReactPHP which acts as a ZeroMQ message exchange between the Xibo CMS and connected
Xibo Players. It doesn't do anything beyond forward messages from the CMS to a pub/sub socket.

It is packaged into a PHAR file which is included in the [Xibo CMS](https://github.com/xibosignage/xibo-cms) release 
files.

## Installation
The install, use composer:

```
composer require xibosignage/xibo-xmr
```

## Licence
Xibo is free software: you can redistribute it and/or modify
it under the terms of the GNU Affero General Public License as published by
the Free Software Foundation, either version 3 of the License, or
any later version. 

Xibo is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU Affero General Public License for more details.

You should have received a copy of the GNU Affero General Public License
along with Xibo.  If not, see <http://www.gnu.org/licenses/>. 