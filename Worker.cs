using System.Text;
using System.Text.Json.Nodes;
using Microsoft.Extensions.Hosting;
using Microsoft.Extensions.Logging;
using Microsoft.Extensions.Options;
using NetMQ;
using NetMQ.Sockets;
using Newtonsoft.Json;

namespace xibo_xmr;

public class Worker : BackgroundService
{
    private readonly ILogger<Worker> _logger;
    private readonly ZmqSettings _settings;

    private readonly SynchronizedCollection<ZmqMessage> _queue;

    public Worker(ILogger<Worker> logger, IOptions<ZmqSettings> settings)
    {
        _logger = logger;
        _settings = settings.Value;
        _queue = new();
    }

    protected override async Task ExecuteAsync(CancellationToken stoppingToken)
    {
        _logger.LogInformation("Worker running at: {time}", DateTimeOffset.Now);

        if (string.IsNullOrEmpty(_settings.listenOn))
        {
            throw new Exception("Missing listenOn");
        }

        _logger.LogDebug("Worker will listen on {listenOn}", _settings.listenOn);

        if (_settings.pubOn.Count <= 0)
        {
            throw new Exception("Missing pubOn");
        }

        // 3 responsibilities
        // -------
        // 1. Set up a Responder (REP) socket listening on `listenOn` which takes 
        //    messages arriving from the CMS and adds them to the queue with the right QoS
        // 2. Set up a Publisher (PUB) socket bound to `pubOn` which processes the queue
        // 3. Set up a periodic timer which sends a heartbeat message (H) every 30 seconds
        // -------
        using var runtime = new NetMQRuntime();
        runtime.Run(stoppingToken, ResponderAsync(stoppingToken), PublisherAsync(stoppingToken));

        // Delay before we start again
        await Task.Delay(1000, stoppingToken);
    }

    async Task ResponderAsync(CancellationToken stoppingToken)
    {
        // Track service stats.
        Dictionary<string, int> stats = NewStats();

        using var responseSocket = new ResponseSocket(_settings.listenOn);

        if (!_settings.ipv6RespSupport)
        {
            responseSocket.Options.IPv4Only = true;
        }

        while (!stoppingToken.IsCancellationRequested)
        {
            var (message, _) = await responseSocket.ReceiveFrameStringAsync(stoppingToken);

            _logger.LogInformation("{message}", message);

            // Are we a request for stats?
            if (message.Equals("stats"))
            {
                string json = GetJsonStats(_queue.Count, stats);
                responseSocket.SendFrame(json);
                _logger.LogDebug("{json}", json);

                // Reset stats.
                stats = NewStats();
            }
            else
            {
                // Decode the message
                try
                {
                    ZmqMessage? zmqMessage = JsonConvert.DeserializeObject<ZmqMessage>(message) ?? throw new Exception("Empty");

                    // Validate
                    if (string.IsNullOrEmpty(zmqMessage.channel))
                    {
                        throw new Exception("Empty Channel");
                    }
                    if (string.IsNullOrEmpty(zmqMessage.key))
                    {
                        throw new Exception("Empty Key");
                    }
                    if (string.IsNullOrEmpty(zmqMessage.message))
                    {
                        throw new Exception("Empty Message");
                    }

                    // Set the QOS if none provided
                    zmqMessage.qos ??= 10;

                    // Stats
                    stats["total"]++;
                    stats["" + zmqMessage.qos]++;
                    stats["peak"] = Math.Max(stats["peak"], _queue.Count);

                    // Add to the queue
                    _logger.LogDebug("Queuing");
                    _queue.Add(zmqMessage);

                    // Reply
                    responseSocket.SendFrame("false");
                }
                catch (Exception e)
                {
                    _logger.LogError("Bad message: {e}", e.Message);
                }
            }
        }
    }

    async Task PublisherAsync(CancellationToken stoppingToken)
    {
        _logger.LogInformation("Queue polling every {poll} seconds.", _settings.queuePoll ?? 10);

        using var publisherSocket = new PublisherSocket();

        if (!_settings.ipv6PubSupport)
        {
            publisherSocket.Options.IPv4Only = true;
        }

        foreach (string pub in _settings.pubOn)
        {
            _logger.LogInformation("Bind to {pub} for publish", pub);
            publisherSocket.Bind(pub);
        }

        // Track the poll count
        int pollingTime = (_settings.queuePoll ?? 10) * 1000;
        int heartbeatDue = 20000;

        while (!stoppingToken.IsCancellationRequested)
        {
            if (heartbeatDue >= 30000)
            {
                heartbeatDue = 0;

                _logger.LogDebug("Heartbeat...");
                publisherSocket.SendMultipartMessage(ZmqMessage.Heartbeat());
            }

            if (_queue.Count > 0)
            {
                _logger.LogInformation("Queue Poll - work to be done");

                // TODO sort the queue

                // Send up to X messages
                int messagesToSend = _settings.queueSize ?? 10;

                while (messagesToSend > 0)
                {
                    if (_queue.Count <= 0)
                    {
                        _logger.LogDebug("Queue Poll - queue size reached");
                        break;
                    }

                    // Pop an element
                    ZmqMessage message = _queue[0];
                    _queue.Remove(message);

                    _logger.LogDebug("Sending message");

                    // TODO: increment sent stat

                    publisherSocket.SendMultipartMessage(message.AsNetMqMessage());

                    _logger.LogDebug("Popped 1 from the queue, new queue size {size}", _queue.Count);

                    messagesToSend--;
                }
            }

            heartbeatDue += pollingTime;

            await Task.Delay(pollingTime, stoppingToken);
        }
    }

    private static Dictionary<string, int> NewStats()
    {
        return new()
            {
                { "total", 0},
                { "sent", 0 },
                { "peak", 0 },
                { "1", 0 },
                { "2", 0 },
                { "3", 0 },
                { "4", 0 },
                { "5", 0 },
                { "6", 0 },
                { "7", 0 },
                { "8", 0 },
                { "9", 0 },
                { "10", 0 }
            };
    }
    
    private static string GetJsonStats(int queueSize, Dictionary<string, int> stats)
    {
        // Go through each and add a JSON string.
        StringBuilder sb = new();
        using StringWriter sw = new(sb);
        using JsonWriter writer = new JsonTextWriter(sw);
        writer.Formatting = Formatting.None;
        writer.WriteStartObject();
        writer.WritePropertyName("currentQueueSize");
        writer.WriteValue(queueSize);
        writer.WritePropertyName("peakQueueSize");
        writer.WriteValue(stats["peak"]);
        writer.WritePropertyName("messageCounters");
        
        writer.WriteStartObject();
        writer.WritePropertyName("total");
        writer.WriteValue(stats["total"]);
        writer.WritePropertyName("sent");
        writer.WriteValue(stats["sent"]);
        writer.WritePropertyName("qos1");
        writer.WriteValue(stats["1"]);
        writer.WritePropertyName("qos2");
        writer.WriteValue(stats["2"]);
        writer.WritePropertyName("qos3");
        writer.WriteValue(stats["3"]);
        writer.WritePropertyName("qos4");
        writer.WriteValue(stats["4"]);
        writer.WritePropertyName("qos5");
        writer.WriteValue(stats["5"]);
        writer.WritePropertyName("qos6");
        writer.WriteValue(stats["6"]);
        writer.WritePropertyName("qos7");
        writer.WriteValue(stats["7"]);
        writer.WritePropertyName("qos8");
        writer.WriteValue(stats["8"]);
        writer.WritePropertyName("qos9");
        writer.WriteValue(stats["9"]);
        writer.WritePropertyName("qos10");
        writer.WriteValue(stats["10"]);
        writer.WriteEndObject();
        
        writer.WriteEndObject();
        return sb.ToString();
    }
}
