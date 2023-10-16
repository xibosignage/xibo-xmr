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
using System.Collections.Concurrent;
using System.Text;
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

    private readonly ConcurrentQueue<ZmqMessage> _queueQos1;
    private readonly ConcurrentQueue<ZmqMessage> _queueQos2;
    private readonly ConcurrentQueue<ZmqMessage> _queueQos3;
    private readonly ConcurrentQueue<ZmqMessage> _queueQos4;
    private readonly ConcurrentQueue<ZmqMessage> _queueQos5;
    private readonly ConcurrentQueue<ZmqMessage> _queueQos6;
    private readonly ConcurrentQueue<ZmqMessage> _queueQos7;
    private readonly ConcurrentQueue<ZmqMessage> _queueQos8;
    private readonly ConcurrentQueue<ZmqMessage> _queueQos9;
    private readonly ConcurrentQueue<ZmqMessage> _queueQos10;

    private int _sentCount = 0;

    public Worker(ILogger<Worker> logger, IOptions<ZmqSettings> settings)
    {
        _logger = logger;
        _settings = settings.Value;
        _queueQos1 = new();
        _queueQos2 = new();
        _queueQos3 = new();
        _queueQos4 = new();
        _queueQos5 = new();
        _queueQos6 = new();
        _queueQos7 = new();
        _queueQos8 = new();
        _queueQos9 = new();
        _queueQos10 = new();
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
        await Task.WhenAll(
            Task.Factory.StartNew(() => { new NetMQRuntime().Run(stoppingToken, ResponderAsync(stoppingToken)); }, stoppingToken),
            Task.Factory.StartNew(() => { new NetMQRuntime().Run(stoppingToken, PublisherAsync(stoppingToken)); }, stoppingToken)
        );
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
                string json = GetJsonStats(GetCurrentQueueSize(), _sentCount, stats);
                responseSocket.SendFrame(json);
                _logger.LogDebug("{json}", json);

                // Reset stats.
                stats = NewStats();
                Interlocked.Exchange(ref _sentCount, 0);
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
                    stats["peak"] = Math.Max(stats["peak"], GetCurrentQueueSize() + 1);

                    // Add to the queue
                    _logger.LogDebug("Queuing");

                    if (zmqMessage.qos == 1) {
                        _queueQos1.Enqueue(zmqMessage);
                    } else if (zmqMessage.qos == 2) {
                        _queueQos2.Enqueue(zmqMessage);
                    } else if (zmqMessage.qos == 3) {
                        _queueQos3.Enqueue(zmqMessage);
                    } else if (zmqMessage.qos == 4) {
                        _queueQos4.Enqueue(zmqMessage);
                    } else if (zmqMessage.qos == 5) {
                        _queueQos5.Enqueue(zmqMessage);
                    } else if (zmqMessage.qos == 6) {
                        _queueQos6.Enqueue(zmqMessage);
                    } else if (zmqMessage.qos == 7) {
                        _queueQos7.Enqueue(zmqMessage);
                    } else if (zmqMessage.qos == 8) {
                        _queueQos8.Enqueue(zmqMessage);
                    } else if (zmqMessage.qos == 9) {
                        _queueQos9.Enqueue(zmqMessage);
                    } else {
                        _queueQos10.Enqueue(zmqMessage);
                    }

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

            int currentQueueSize = GetCurrentQueueSize();
            if (currentQueueSize > 0)
            {
                _logger.LogInformation("Queue Poll - work to be done, queue size: {size}", currentQueueSize);

                // Send up to X messages
                int messagesToSend = _settings.queueSize ?? 10;

                ProcessQueue(publisherSocket, _queueQos10, ref messagesToSend);
                ProcessQueue(publisherSocket, _queueQos9, ref messagesToSend);
                ProcessQueue(publisherSocket, _queueQos8, ref messagesToSend);
                ProcessQueue(publisherSocket, _queueQos7, ref messagesToSend);
                ProcessQueue(publisherSocket, _queueQos6, ref messagesToSend);
                ProcessQueue(publisherSocket, _queueQos5, ref messagesToSend);
                ProcessQueue(publisherSocket, _queueQos4, ref messagesToSend);
                ProcessQueue(publisherSocket, _queueQos3, ref messagesToSend);
                ProcessQueue(publisherSocket, _queueQos2, ref messagesToSend);
                ProcessQueue(publisherSocket, _queueQos1, ref messagesToSend);
            }

            heartbeatDue += pollingTime;

            await Task.Delay(pollingTime, stoppingToken);
        }
    }

    private void ProcessQueue(PublisherSocket publisherSocket, ConcurrentQueue<ZmqMessage> queue, ref int messagesToSend)
    {
        try {
            while (messagesToSend > 0)
            {
                bool result = queue.TryDequeue(out ZmqMessage message);
                if (result && message != null)
                {
                    _logger.LogDebug("Sending message, qos {qos}", message.qos);
                    publisherSocket.SendMultipartMessage(message.AsNetMqMessage());

                    messagesToSend--;

                    // increment sent stat
                    Interlocked.Increment(ref _sentCount);
                } else {
                    break;
                }
            }
        } 
        catch (Exception e)
        {
            _logger.LogError("Process Queue: failed {e}", e.Message);
        }
    }

    private static Dictionary<string, int> NewStats()
    {
        return new()
            {
                { "total", 0},
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

    private int GetCurrentQueueSize()
    {
        return _queueQos1.Count
            + _queueQos2.Count
            + _queueQos3.Count
            + _queueQos4.Count
            + _queueQos5.Count
            + _queueQos6.Count
            + _queueQos7.Count
            + _queueQos8.Count
            + _queueQos9.Count
            + _queueQos10.Count;
    }
    
    private static string GetJsonStats(int queueSize, int sentCount, Dictionary<string, int> stats)
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
        writer.WriteValue(sentCount);
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
