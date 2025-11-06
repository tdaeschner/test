const dgram = require('dgram');

class ArtNetController {
  constructor({ targetIp, port, universe = 0, channels = 512 } = {}) {
    if (!targetIp) {
      throw new Error('targetIp ist erforderlich, um Art-Net-Pakete zu versenden.');
    }

    this.targetIp = targetIp;
    this.port = port || 6454;
    this.universe = universe;
    this.channels = Math.min(Math.max(channels || 512, 1), 512);
    this.buffer = Buffer.alloc(512, 0);
    this.socket = dgram.createSocket('udp4');
    this.socket.bind(() => {
      this.socket.setBroadcast(true);
    });
  }

  getState() {
    return {
      targetIp: this.targetIp,
      port: this.port,
      universe: this.universe,
      channels: this.channels,
      values: Array.from(this.buffer.slice(0, this.channels)),
    };
  }

  async setChannel(channel, value) {
    const validatedChannel = this.#validateChannel(channel);
    const validatedValue = this.#validateValue(value);
    this.buffer[validatedChannel - 1] = validatedValue;
    await this.#send();
    return this.getState();
  }

  async setChannels(updates = []) {
    if (!Array.isArray(updates)) {
      throw new Error('updates muss ein Array sein.');
    }

    for (const update of updates) {
      if (typeof update !== 'object' || update === null) {
        throw new Error('Jedes Update muss ein Objekt mit channel und value sein.');
      }

      const { channel, value } = update;
      const validatedChannel = this.#validateChannel(channel);
      const validatedValue = this.#validateValue(value);
      this.buffer[validatedChannel - 1] = validatedValue;
    }

    await this.#send();
    return this.getState();
  }

  async blackout() {
    this.buffer.fill(0);
    await this.#send();
    return this.getState();
  }

  close() {
    this.socket.close();
  }

  #validateChannel(channel) {
    const parsed = parseInt(channel, 10);
    if (Number.isNaN(parsed) || parsed < 1 || parsed > this.channels) {
      throw new Error(`Ungültiger Kanal ${channel}. Erlaubt ist ein Bereich von 1 bis ${this.channels}.`);
    }
    return parsed;
  }

  #validateValue(value) {
    const parsed = parseInt(value, 10);
    if (Number.isNaN(parsed) || parsed < 0 || parsed > 255) {
      throw new Error(`Ungültiger DMX-Wert ${value}. Erlaubt ist ein Bereich von 0 bis 255.`);
    }
    return parsed;
  }

  async #send() {
    const packet = this.#buildPacket();
    await new Promise((resolve, reject) => {
      this.socket.send(packet, 0, packet.length, this.port, this.targetIp, (err) => {
        if (err) {
          reject(err);
        } else {
          resolve();
        }
      });
    });
  }

  #buildPacket() {
    const dataLength = this.channels;
    const packet = Buffer.alloc(18 + dataLength);

    // Header: "Art-Net" + 0x00
    packet.write('Art-Net', 0, 'ascii');
    packet[7] = 0x00;

    // OpCode ArtDMX (0x5000) Little Endian
    packet[8] = 0x00;
    packet[9] = 0x50;

    // Protokoll-Version 14
    packet[10] = 0x00;
    packet[11] = 0x0e;

    // Sequence & Physical (nicht genutzt)
    packet[12] = 0x00;
    packet[13] = 0x00;

    // Universe (Little Endian)
    packet[14] = this.universe & 0xff;
    packet[15] = (this.universe >> 8) & 0xff;

    // Länge (Big Endian)
    packet[16] = (dataLength >> 8) & 0xff;
    packet[17] = dataLength & 0xff;

    // DMX-Daten
    this.buffer.copy(packet, 18, 0, dataLength);

    return packet;
  }
}

module.exports = ArtNetController;
