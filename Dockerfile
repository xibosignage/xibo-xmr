FROM mcr.microsoft.com/dotnet/sdk:7.0 AS build-env
WORKDIR /App

# Copy everything
COPY . ./

# Restore as distinct layers
RUN dotnet restore

# Build and publish a release
RUN dotnet publish -c Release -o out

# Build runtime image
FROM mcr.microsoft.com/dotnet/aspnet:7.0
WORKDIR /App
COPY --from=build-env /App/out .

# Define some environment variables
ENV XMR_DEBUG false
ENV XMR_QUEUE_POLL 5
ENV XMR_QUEUE_SIZE 10
ENV XMR_IPV6RESPSUPPORT false
ENV XMR_IPV6PUBSUPPORT false

# Expose the ports
EXPOSE 9505 50001

ENTRYPOINT ["dotnet", "xibo-xmr.dll"]