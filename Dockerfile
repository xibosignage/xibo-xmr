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

LABEL org.opencontainers.image.source=https://github.com/xibosignage/xibo-xmr
LABEL org.opencontainers.image.description="Xibo Message Relay - XMR"
LABEL org.opencontainers.image.licenses=AGPL-3.0-or-later
LABEL org.opencontainers.image.authors="support@xibosignage.com"

WORKDIR /App
COPY --from=build-env /App/out .

# Expose the ports
EXPOSE 9505 50001

ENTRYPOINT ["dotnet", "xibo-xmr.dll"]