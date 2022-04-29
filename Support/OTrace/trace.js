function connect(
    wsHost,
    wsPort,
    css = {},
    blacklist = [],
    whitelist = undefined) {
    try {
        const socket = new WebSocket(`ws://${wsHost}:${wsPort}`);
        socket.onclose = _ => {
            console.debug("Trace connection closed");
        };
        socket.onopen = _ => {
            console.debug("Trace connection opened");
        };
        socket.onmessage = event => {
            const data = JSON.parse(event.data);
            if (blacklist.includes(data.tag)) {
                return;
            }
            if (whitelist && !whitelist.includes(data.tag)) {
                return;
            }
            const message = data.tag === "CONSOLE" ?
                JSON.parse(data.message) :
                data.message;
            let fn;
            switch (data.tag) {
                case "FATAL":
                case "ERROR":
                    fn = console.error;
                    break;
                case "WARN":
                    fn = console.warn;
                    break;
                default:
                    fn = console.log;
            }
            fn(`%c${data.tag}`, css[data.tag] || "", message);
        };
        return socket;
    } catch (e) {
        console.error("Trace connection failed", e);
        return undefined;
    }
}

async function disconnect(socket) {
    socket?.close();
}

window.connect = connect
window.disconnect = disconnect
