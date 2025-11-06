const elements = {
  targetIp: document.getElementById('status-target-ip'),
  universe: document.getElementById('status-universe'),
  channels: document.getElementById('status-channels'),
  blackout: document.getElementById('blackout-btn'),
  feedback: document.getElementById('feedback'),
  channelRangeForm: document.getElementById('channel-range-form'),
  startChannel: document.getElementById('start-channel'),
  channelCount: document.getElementById('channel-count'),
  channelsContainer: document.getElementById('channels-container'),
};

const channelRefs = new Map();

let currentState = null;
let socket = null;
let reconnectTimeout = null;
let displayedRange = {
  start: Number(elements.startChannel.value) || 1,
  count: Number(elements.channelCount.value) || 16,
};

const clamp = (value, min, max) => Math.min(Math.max(value, min), max);

const showFeedback = (message, type = 'info') => {
  if (!elements.feedback) return;
  elements.feedback.textContent = message || '';
  if (type === 'error') {
    elements.feedback.style.color = '#f87171';
  } else if (type === 'success') {
    elements.feedback.style.color = '#86efac';
  } else {
    elements.feedback.style.color = 'rgba(148, 163, 184, 0.85)';
  }
};

const updateStatusPanel = (state) => {
  elements.targetIp.textContent = state.targetIp;
  elements.universe.textContent = state.universe;
  elements.channels.textContent = state.channels;
};

const updateLocalState = (channel, value) => {
  if (!currentState) {
    return;
  }
  currentState.values[channel - 1] = value;
  const ref = channelRefs.get(channel);
  if (ref) {
    ref.slider.value = value;
    ref.number.value = value;
    ref.display.textContent = value;
  }
};

const applyState = (state) => {
  currentState = state;
  updateStatusPanel(state);
  const { start, count } = displayedRange;
  const lastChannel = Math.min(start + count - 1, state.channels);
  for (let channel = start; channel <= lastChannel; channel += 1) {
    const value = state.values[channel - 1] ?? 0;
    updateLocalState(channel, value);
  }
};

const sendChannelUpdate = async (channel, value) => {
  const numericValue = clamp(Number(value), 0, 255);
  updateLocalState(channel, numericValue);

  const payload = { type: 'setChannel', channel, value: numericValue };

  if (socket && socket.readyState === WebSocket.OPEN) {
    try {
      socket.send(JSON.stringify(payload));
      showFeedback(`Kanal ${channel} → ${numericValue}`, 'success');
      return;
    } catch (error) {
      console.error('WebSocket-Fehler:', error);
    }
  }

  try {
    const response = await fetch(`/api/channels/${channel}`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ value: numericValue }),
    });

    if (!response.ok) {
      const data = await response.json();
      throw new Error(data.error || 'Unbekannter Fehler');
    }

    showFeedback(`Kanal ${channel} → ${numericValue}`, 'success');
  } catch (error) {
    console.error(error);
    showFeedback(`Fehler beim Setzen von Kanal ${channel}: ${error.message}`, 'error');
  }
};

const sendBlackout = async () => {
  if (socket && socket.readyState === WebSocket.OPEN) {
    socket.send(JSON.stringify({ type: 'blackout' }));
    showFeedback('Blackout gesendet', 'success');
    return;
  }

  try {
    const response = await fetch('/api/blackout', {
      method: 'POST',
    });

    if (!response.ok) {
      const data = await response.json();
      throw new Error(data.error || 'Unbekannter Fehler');
    }

    showFeedback('Blackout gesendet', 'success');
  } catch (error) {
    console.error(error);
    showFeedback(`Blackout fehlgeschlagen: ${error.message}`, 'error');
  }
};

const createChannelCard = (channel) => {
  const value = currentState?.values[channel - 1] ?? 0;

  const card = document.createElement('div');
  card.className = 'channel-card';

  const header = document.createElement('div');
  header.className = 'channel-header';

  const title = document.createElement('h2');
  title.textContent = `Kanal ${channel}`;

  const valueLabel = document.createElement('span');
  valueLabel.className = 'channel-value';
  valueLabel.textContent = value;

  header.append(title, valueLabel);

  const slider = document.createElement('input');
  slider.type = 'range';
  slider.min = '0';
  slider.max = '255';
  slider.value = value;

  const numberInput = document.createElement('input');
  numberInput.type = 'number';
  numberInput.min = '0';
  numberInput.max = '255';
  numberInput.value = value;

  slider.addEventListener('input', () => {
    numberInput.value = slider.value;
    valueLabel.textContent = slider.value;
  });

  slider.addEventListener('change', () => {
    sendChannelUpdate(channel, slider.value);
  });

  numberInput.addEventListener('input', () => {
    numberInput.value = clamp(Number(numberInput.value) || 0, 0, 255);
  });

  numberInput.addEventListener('change', () => {
    const numericValue = clamp(Number(numberInput.value) || 0, 0, 255);
    slider.value = numericValue;
    valueLabel.textContent = numericValue;
    sendChannelUpdate(channel, numericValue);
  });

  card.append(header, slider, numberInput);
  elements.channelsContainer.appendChild(card);

  channelRefs.set(channel, {
    slider,
    number: numberInput,
    display: valueLabel,
  });
};

const renderChannels = () => {
  if (!currentState) return;

  elements.channelsContainer.innerHTML = '';
  channelRefs.clear();

  const { start, count } = displayedRange;
  const maxChannel = currentState.channels;
  const end = Math.min(start + count - 1, maxChannel);

  if (start > maxChannel) {
    showFeedback(`Startkanal liegt über dem verfügbaren Bereich (max. ${maxChannel}).`, 'error');
    return;
  }

  for (let channel = start; channel <= end; channel += 1) {
    createChannelCard(channel);
  }
};

const fetchState = async () => {
  try {
    const response = await fetch('/api/state');
    if (!response.ok) {
      throw new Error('Serverstatus konnte nicht geladen werden.');
    }
    const state = await response.json();
    currentState = state;
    updateStatusPanel(state);
    renderChannels();
  } catch (error) {
    console.error(error);
    showFeedback(error.message, 'error');
  }
};

const scheduleReconnect = () => {
  if (reconnectTimeout) return;
  reconnectTimeout = setTimeout(() => {
    reconnectTimeout = null;
    connectWebSocket();
  }, 2000);
};

const connectWebSocket = () => {
  const protocol = window.location.protocol === 'https:' ? 'wss' : 'ws';
  socket = new WebSocket(`${protocol}://${window.location.host}`);

  socket.addEventListener('open', () => {
    showFeedback('WebSocket verbunden', 'success');
  });

  socket.addEventListener('message', (event) => {
    try {
      const message = JSON.parse(event.data);
      if (message.type === 'state' && message.data) {
        applyState(message.data);
      } else if (message.type === 'error') {
        showFeedback(message.error || 'Unbekannter Fehler', 'error');
      }
    } catch (error) {
      console.error('Fehler beim Verarbeiten der WebSocket-Nachricht:', error);
    }
  });

  socket.addEventListener('close', () => {
    showFeedback('Verbindung getrennt. Erneuter Verbindungsversuch …', 'info');
    scheduleReconnect();
  });

  socket.addEventListener('error', (error) => {
    console.error('WebSocket-Fehler:', error);
    socket.close();
  });
};

const initEventListeners = () => {
  elements.blackout.addEventListener('click', (event) => {
    event.preventDefault();
    sendBlackout();
  });

  elements.channelRangeForm.addEventListener('submit', (event) => {
    event.preventDefault();
    const start = clamp(Number(elements.startChannel.value) || 1, 1, currentState?.channels || 512);
    const count = clamp(Number(elements.channelCount.value) || 1, 1, 64);
    displayedRange = { start, count };
    renderChannels();
  });

  window.addEventListener('beforeunload', () => {
    if (socket) {
      socket.close();
    }
    if (reconnectTimeout) {
      clearTimeout(reconnectTimeout);
    }
  });
};

const boot = async () => {
  initEventListeners();
  await fetchState();
  connectWebSocket();
};

boot();
