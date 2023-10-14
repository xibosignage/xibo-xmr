using System.Diagnostics;
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
