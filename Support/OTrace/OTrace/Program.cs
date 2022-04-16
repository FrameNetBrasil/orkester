﻿using System.Net;
using System.Net.Sockets;
using System.Text;
using System.Text.Encodings.Web;
using System.Text.Json;
using System.Text.RegularExpressions;
using OTrace;
using WebSocketSharp.Server;

void StartWebSocketServer(IPAddress ip, int port)
{
    var ws = new WebSocketServer(ip, port);
    ws.AddWebSocketService<TraceSocketBehavior>("/");
    ws.Start();
    Console.WriteLine($"WebSocket Server started at {ws.Address}:{ws.Port}");
}

void ProcessTraceMessage(string tag, string message)
{
    TraceSocketBehavior.Broadcast(JsonSerializer.Serialize(new
    {
        tag,
        message
    }, new JsonSerializerOptions
    {
        Encoder = JavaScriptEncoder.UnsafeRelaxedJsonEscaping
    }));
    // Console.WriteLine(message);
    // Console.WriteLine($"##########  {tag} END OF MESSAGE ##############");
}

void ReadTraceSocket(Socket socket)
{
    Span<byte> buffer = stackalloc byte[8192];
    var builder = new StringBuilder();

    void CompleteMessage(string tag)
    {
        if (builder.Length > 0)
        {
            ProcessTraceMessage(tag, builder.ToString());
            builder.Clear();
        }
    }

    string tag = "";
    while (true)
    {
        try
        {
            var read = socket.Receive(buffer);
            if (read == 0) break;
            using var reader = new StringReader(Encoding.UTF8.GetString(buffer.Slice(0, read)));
            string? line;
            while ((line = reader.ReadLine()) != null)
            {
                var match = Regex.Match(line, @"^\[([\w_:]+)\]");
                if (match.Success)
                {
                    CompleteMessage(tag);
                    tag = match.Groups[1].Value;
                }
                builder.Append(line);
            }
        }
        catch (SocketException)
        {
            break;
        }
    }

    CompleteMessage(tag);
}

void StartTraceListener(IPAddress ip, int port)
{
    var listener = new TcpListener(ip, port);
    listener.Start();

    async Task AcceptSocket()
    {
        //This only accepts ONE connection from orkester at a time
        var socket = await listener.AcceptSocketAsync();
        ReadTraceSocket(socket);
        AcceptSocket();
    }

    AcceptSocket();
    Console.WriteLine($"Trace listener started at {ip}:{port}");
}

try
{
    if (args.Length < 4)
    {
        throw new ArgumentException("Usage: OTrace ListenIP ListenPort WebSocketIP WebSocketPort");
    }

    if (!IPAddress.TryParse(args[0], out var listenIp))
    {
        throw new ArgumentException($"Invalid Listen IP: {args[0]}");
    }

    if (!int.TryParse(args[1], out var listenPort))
    {
        throw new ArgumentException($"Invalid listen port: {args[1]}");
    }

    if (!IPAddress.TryParse(args[2], out var wsIp))
    {
        throw new ArgumentException($"Invalid WebSocket IP: {args[2]}");
    }

    if (!int.TryParse(args[3], out var wsPort))
    {
        throw new ArgumentException($"Invalid WebSocket port: {args[3]}");
    }

    StartTraceListener(listenIp, listenPort);
    StartWebSocketServer(wsIp, wsPort);

    var stop = new ManualResetEvent(false);
    stop.WaitOne();
}
catch (Exception e)
{
    Console.WriteLine(e.Message);
    Environment.Exit(1);
}