/*
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
namespace xibo_xmr;
public class ZmqSettings
{
    public string listenOn { get; set; }
    public List<string> pubOn { get; set; }
    public int? queuePoll { get; set; }
    public int? queueSize {get; set; }
    public bool ipv6RespSupport { get; set; }
    public bool ipv6PubSupport { get; set; }
    public int? pubSendTimeoutMs { get; set; }
    public string? relayOn {get; set; }
}
