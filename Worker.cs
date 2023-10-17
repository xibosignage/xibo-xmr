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
using ConcurrentPriorityQueue.Core;
using Microsoft.Extensions.Options;
using NetMQ;
using NetMQ.Sockets;
using Newtonsoft.Json;

namespace xibo_xmr;

public class Worker : BackgroundService
{
    private readonly ILogger<Worker> _logger;
    private readonly ZmqSettings _settings;

    private readonly ConcurrentPriorityQueue<ZmqMessage, int> _queue;

    private readonly BlockingCollection<string> _relayQueue;

    private int _sentCount = 0;

    public Worker(ILogger<Worker> logger, IOptions<ZmqSettings> settings)
    {
        _logger = logger;
        _settings = settings.Value;
        _queue = new();
        _relayQueue = new();
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
        // 4. Handle relay if set
        // -------
        List<Task> tasks = new()
        {
            Task.Factory.StartNew(() => { new NetMQRuntime().Run(stoppingToken, ResponderAsync(stoppingToken)); }, stoppingToken, TaskCreationOptions.LongRunning, TaskScheduler.Default),
            Task.Factory.StartNew(() => { new NetMQRuntime().Run(stoppingToken, PublisherAsync(stoppingToken)); }, stoppingToken, TaskCreationOptions.LongRunning, TaskScheduler.Default),
            Task.Factory.StartNew(() => { new NetMQRuntime().Run(stoppingToken, HeartbeatAsync(stoppingToken)); }, stoppingToken, TaskCreationOptions.LongRunning, TaskScheduler.Default)
        };

        // Do we relay?
        if (!string.IsNullOrEmpty(_settings.relayOn))
        {
            tasks.Add(Task.Factory.StartNew(() => { new NetMQRuntime().Run(stoppingToken, Relay(stoppingToken)); }, stoppingToken, TaskCreationOptions.LongRunning, TaskScheduler.Default));
        }

        // Await all
        await Task.WhenAll(tasks);
 
        // Must call clean up at the end
        NetMQConfig.Cleanup();
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
                string json = GetJsonStats(_queue.Count, _sentCount, stats);
                responseSocket.SendFrame(json);
                _logger.LogDebug("{json}", json);

                // Reset stats.
                stats = NewStats();
                Interlocked.Exchange(ref _sentCount, 0);
            }
            else
            {
                // Relay
                if (!string.IsNullOrEmpty(_settings.relayOn))
                {
                    bool relayResult = _relayQueue.TryAdd(message);
                    if (!relayResult)
                    {
                        _logger.LogError("Failed to add message to the relay queue");
                    }
                }

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
                    stats["peak"] = Math.Max(stats["peak"], _queue.Count + 1);

                    // Add to the queue
                    _logger.LogDebug("Queuing");
                    bool result = _queue.TryAdd(zmqMessage);
                    if (!result)
                    {
                        _logger.LogError("Failed to add message to the queue");
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
        _logger.LogInformation("Creating a publisher socket.");

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

        while (!stoppingToken.IsCancellationRequested)
        {
            // Process in batches
            int batchCount = 0;
            _logger.LogDebug("Batch start");

            while (batchCount < (_settings.queueSize ?? 10))
            {
                bool result = _queue.TryTake(out ZmqMessage message);
                if (result && message != null)
                {
                    if (message.isHeartbeat)
                    {
                        _logger.LogDebug("Heartbeat...");
                    }
                    else
                    {
                        _logger.LogDebug("Sending message, qos {qos}, queue size {size}", message.qos, _queue.Count);
                    }

                    // Send with a timeout.
                    bool isSent = publisherSocket.TrySendMultipartMessage(
                        TimeSpan.FromMilliseconds(_settings.pubSendTimeoutMs ?? 500),
                        message.AsNetMqMessage()
                    );

                    if (!isSent)
                    {
                        _logger.LogError("Timeout sending message for channel: {channel} after {pubSendTimeoutMs}ms", message.channel, _settings.pubSendTimeoutMs ?? 500);
                    }

                    // increment sent stat
                    if (!message.isHeartbeat)
                    {
                        Interlocked.Increment(ref _sentCount);
                    }

                    batchCount++;
                }
                else
                {
                    _logger.LogDebug("Queue empty");
                    break;
                }
            }

            _logger.LogDebug("Batch complete");

            await Task.Delay(TimeSpan.FromSeconds(_settings.queuePoll ?? 5), stoppingToken);
        }
    }

    async Task HeartbeatAsync(CancellationToken stoppingToken)
    {
        while (!stoppingToken.IsCancellationRequested)
        {
            _queue.TryAdd(new ZmqMessage { isHeartbeat = true, qos = 5});
            await Task.Delay(30000, stoppingToken);
        }
    }

    Task Relay(CancellationToken stoppingToken)
    {
        _logger.LogInformation("Creating a relay socket");

        using var relaySocket = new RequestSocket(_settings.relayOn);

        while (!stoppingToken.IsCancellationRequested)
        {
            bool result = _relayQueue.TryTake(out string message, -1, stoppingToken);
            if (result && !string.IsNullOrEmpty(message))
            {
                bool sendResult = relaySocket.TrySendFrame(message);
                if (!sendResult)
                {
                    _logger.LogError("Unable to relay message");
                }

                bool receiveResult = relaySocket.TryReceiveFrameString(TimeSpan.FromMilliseconds(500), out string sendReturn);
                if (!receiveResult)
                {
                    _logger.LogError("Unable to relay message, no response");
                }

                _logger.LogDebug("Relay message: {return}", sendReturn);
            }
        }

        return Task.CompletedTask;
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
