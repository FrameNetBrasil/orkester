FROM mcr.microsoft.com/dotnet/sdk:6.0-alpine3.15 as build-env
# see https://docs.microsoft.com/en-us/dotnet/core/docker/build-container?tabs=linux

WORKDIR /app
COPY . ./
RUN dotnet restore
RUN dotnet publish -c Release -o out

# Build runtime image
FROM mcr.microsoft.com/dotnet/runtime:6.0-alpine3.15
WORKDIR /app
COPY --from=build-env /app/out .
ENTRYPOINT ["dotnet", "OTrace.dll"]

# docker build -t orkester_trace .
# docker-compose up -d
