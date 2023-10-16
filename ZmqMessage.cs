﻿/*
 * Copyright (C) 2023 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */
using NetMQ;

namespace xibo_xmr;
public class ZmqMessage
{
    public string? channel { get; set; }
    public string? key {get; set; }
    public string? message {get; set; }
    public int? qos {get; set; }

    public NetMQMessage AsNetMqMessage()
    {
        NetMQMessage netMQFrames = new(3);
        netMQFrames.Append(channel);
        netMQFrames.Append(key);
        netMQFrames.Append(message);
        return netMQFrames;
    }

    public static NetMQMessage Heartbeat()
    {
        NetMQMessage netMQFrames = new(3);
        netMQFrames.Append("H");
        netMQFrames.Append("");
        netMQFrames.Append("");
        return netMQFrames;
    }
}