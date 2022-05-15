let truncated = ''
let truncatedCount = 0;

function getLogger(level) {
    switch (level) {
        case "CRITICAL":
        case "ERROR":
            return console.error;
        case "WARNING":
            return console.warn;
        default:
            return console.log;
    }
}

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
            const data = JSON.parse(event.data)
            if (blacklist.includes(data.level_name) || blacklist.includes(data.context.tag)) {
                return;
            }
            if (whitelist && !(whitelist.includes(data.level_name) || whitelist.includes(data.context.tag))) {
                return;
            }
            const log = getLogger(data.level_name)
            if (data.context.tag === 'GRAPHQL') {
                const message = JSON.parse(data.message)
                log("GRAPHQL", message.type, message.alias || message.name, message)
            } else {
                const message = data.context.tag === "CONSOLE" ?
                    JSON.parse(data.message) :
                    data.message;
                log(`%c${data.context.tag || data.level_name}`, css[data.tag] || "", message);
            }
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
