using System.Collections.Immutable;
using System.Net;
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

const int BufferSize = 8192;

void ReadTraceSocket(Socket socket)
{
    var builder = new StringBuilder();

    void Flush()
    {
        if (builder.Length > 14)
        {
            TraceSocketBehavior.Broadcast(builder.Remove(0, 14).ToString());
        }

        builder.Clear();
    }

    Span<byte> buffer = stackalloc byte[BufferSize];
    try
    {
        while (true)
        {
            var read = socket.Receive(buffer);
            if (read == 0) break;

            using var reader = new StringReader(Encoding.UTF8.GetString(buffer.Slice(0, read)));
            string? line;
            while ((line = reader.ReadLine()) != null)
            {
                var match = Regex.Match(line, @"^<record_start>");
                if (match.Success)
                {
                    Flush();
                }

                builder.Append(line);
            }
        }
    }
    catch (SocketException)
    {
    }
    finally
    {
        Flush();
    }
}

void StartTraceListener(IPAddress ip, int port)
{
    var listener = new TcpListener(ip, port);
    listener.Start();
    Console.WriteLine($"Trace listener started at {ip}:{port}");
    while (listener.Server.IsBound)
    {
        var socket = listener.AcceptSocket();
        var task = new Task(() => ReadTraceSocket(socket));
        task.Start();
    }
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

    new Task(() => StartTraceListener(listenIp, listenPort)).Start();
    StartWebSocketServer(wsIp, wsPort);
    var stop = new ManualResetEvent(false);
    stop.WaitOne();
}
catch (Exception e)
{
    Console.WriteLine(e.Message);
    Environment.Exit(1);
}