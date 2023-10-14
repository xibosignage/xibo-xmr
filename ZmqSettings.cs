using System.Collections;

namespace xibo_xmr;
public class ZmqSettings
{
    public string listenOn { get; set; }
    public List<string> pubOn { get; set; }
    public int? queuePoll { get; set; }
    public int? queueSize {get; set; }
    public bool ipv6RespSupport { get; set; }
    public bool ipv6PubSupport { get; set; }

    public bool XMR_DEBUG {get; set; }
}
