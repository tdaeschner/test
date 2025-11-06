const path = require('path');
const http = require('http');
const express = require('express');
const cors = require('cors');
const WebSocket = require('ws');

const config = require('./config');
const ArtNetController = require('./artnet');

const app = express();
const server = http.createServer(app);
const wss = new WebSocket.Server({ server });

const controller = new ArtNetController(config.artnet);

app.use(cors());
app.use(express.json());
app.use(express.static(path.join(__dirname, '..', 'public')));

const asyncHandler = (handler) => (req, res, next) => {
  Promise.resolve(handler(req, res, next)).catch(next);
};

const broadcastState = (state = controller.getState()) => {
  const message = JSON.stringify({ type: 'state', data: state });
  wss.clients.forEach((client) => {
    if (client.readyState === WebSocket.OPEN) {
      client.send(message);
    }
  });
};

wss.on('connection', (socket) => {
  socket.send(JSON.stringify({ type: 'state', data: controller.getState() }));

  socket.on('message', async (rawMessage) => {
    try {
      const message = JSON.parse(rawMessage.toString());
      const { type } = message;
      let state;

      switch (type) {
        case 'setChannel':
          state = await controller.setChannel(message.channel, message.value);
          broadcastState(state);
          break;
        case 'setChannels':
          state = await controller.setChannels(message.updates);
          broadcastState(state);
          break;
        case 'blackout':
          state = await controller.blackout();
          broadcastState(state);
          break;
        case 'ping':
          socket.send(JSON.stringify({ type: 'pong', data: Date.now() }));
          break;
        default:
          socket.send(
            JSON.stringify({ type: 'error', error: `Unbekannter Nachrichtentyp: ${type}` }),
          );
      }
    } catch (error) {
      socket.send(JSON.stringify({ type: 'error', error: error.message }));
    }
  });
});

app.get(
  '/api/state',
  asyncHandler(async (req, res) => {
    res.json(controller.getState());
  }),
);

app.put(
  '/api/channels/:channel',
  asyncHandler(async (req, res) => {
    const { channel } = req.params;
    const { value } = req.body;
    const state = await controller.setChannel(channel, value);
    broadcastState(state);
    res.json(state);
  }),
);

app.post(
  '/api/channels/bulk',
  asyncHandler(async (req, res) => {
    const { updates } = req.body;
    const state = await controller.setChannels(updates);
    broadcastState(state);
    res.json(state);
  }),
);

app.post(
  '/api/blackout',
  asyncHandler(async (req, res) => {
    const state = await controller.blackout();
    broadcastState(state);
    res.json(state);
  }),
);

app.use((req, res) => {
  res.status(404).json({ error: 'Ressource nicht gefunden' });
});

app.use((err, req, res, next) => {
  // eslint-disable-line no-unused-vars
  console.error(err);
  const status = err.statusCode || 400;
  res.status(status).json({ error: err.message || 'Unbekannter Fehler' });
});

const port = config.server.port;

server.listen(port, () => {
  console.log(`Art-Net DMX Controller läuft auf Port ${port}`);
});

const shutdown = () => {
  controller.close();
  server.close(() => {
    process.exit(0);
  });
};

process.on('SIGINT', shutdown);
process.on('SIGTERM', shutdown);
