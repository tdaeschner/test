const DEFAULT_CHANNELS = 512;

module.exports = {
  server: {
    port: parseInt(process.env.PORT, 10) || 3000,
  },
  artnet: {
    targetIp: process.env.ARTNET_TARGET_IP || '255.255.255.255',
    port: parseInt(process.env.ARTNET_PORT, 10) || 6454,
    universe: parseInt(process.env.ARTNET_UNIVERSE, 10) || 0,
    channels: parseInt(process.env.ARTNET_CHANNELS, 10) || DEFAULT_CHANNELS,
  },
};
