using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.Hosting;
using xibo_xmr;

IHost host = Host.CreateDefaultBuilder(args)
    .ConfigureAppConfiguration((hostingContext, config) =>
    {
        config.AddCommandLine(args);
        config.AddJsonFile($"appsettings.json", optional: false, reloadOnChange: true);
        config.AddEnvironmentVariables();
    })
    .ConfigureServices((hostContext, services) =>
    {
        services.Configure<ZmqSettings>(hostContext.Configuration.GetSection("Zmq"));
        services.AddHostedService<Worker>();
    })
    .Build();

host.Run();
