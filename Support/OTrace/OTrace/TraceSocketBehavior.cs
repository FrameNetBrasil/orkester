using WebSocketSharp;
using WebSocketSharp.Server;

namespace OTrace;

public class TraceSocketBehavior : WebSocketBehavior
{
    private static WebSocketSessionManager? _sessions;

    public static void Broadcast(string message)
    {
        _sessions?.Broadcast(message);
    }

    protected override void OnOpen()
    {
        Console.WriteLine($"Client opened: {Context.UserEndPoint}");
        _sessions = Sessions;
    }

    protected override void OnClose(CloseEventArgs e)
    {
        Console.WriteLine($"Client closed: {e.Reason}");
    }
}