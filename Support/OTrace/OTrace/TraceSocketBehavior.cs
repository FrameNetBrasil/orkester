using WebSocketSharp;
using WebSocketSharp.Server;

namespace OTrace;

public class TraceSocketBehavior : WebSocketBehavior
{
    private static WebSocketSessionManager? _sessions;
    private static readonly HashSet<WebSocketSessionManager> sessionsManagers = new();

    public static void Broadcast(string message)
    {
        foreach (var session in sessionsManagers)
        {
            session.Broadcast(message);
        }
    }

    protected override void OnOpen()
    {
        Console.WriteLine($"Client opened: {Context.UserEndPoint}");
        sessionsManagers.Add(Sessions);
    }

    protected override void OnClose(CloseEventArgs e)
    {
        Console.WriteLine($"Client closed: {e.Code} | {e.Reason} | {e.WasClean}");
        sessionsManagers.Remove(Sessions);
    }
}