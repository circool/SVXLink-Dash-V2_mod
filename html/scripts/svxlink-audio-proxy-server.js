// @filesource /scripts/svxlink_audio_proxy_server.js
const WebSocket = require('ws');
const { spawn } = require('child_process');

const wsPort = 8001; 

class AudioManager {
	constructor() {
		this.record = null;
		this.clientCount = 0;
		this.audioHandlers = new Map(); 
	}

	startRecording() {
		if (this.record) {
			console.log('Audio capture already running');
			return;
		}

		console.log('Starting audio capture...');
		this.record = spawn('arecord', [
			'-D', 'plughw:Loopback,1,0',
			'-f', 'S16_LE',
			'-r', '48000',
			'-c', '1'
		], {
			stdio: ['ignore', 'pipe', 'ignore']
		});

		this.record.on('error', (err) => {
			console.error('Failed to start audio capture:', err);
			this.record = null;
		});

		this.record.on('exit', (code, signal) => {
			console.warn(`arecord exited (code ${code}, signal ${signal})`);
			this.record = null;

			if (this.clientCount > 0) {
				console.log('Restarting audio capture for active clients...');
				setTimeout(() => this.startRecording(), 1000);
			}
		});

		this.record.stdout.on('data', (chunk) => {
			this.broadcastAudio(chunk);
		});

		return this.record;
	}

	stopRecording() {
		if (this.record) {
			console.log('Stopping audio capture...');
			this.record.kill('SIGTERM');
			this.record = null;
		}
	}

	addClient(ws) {
		const handler = (chunk) => {
			if (ws.readyState === WebSocket.OPEN) {
				ws.send(chunk);
			}
		};

		this.audioHandlers.set(ws, handler);
		this.clientCount++;

		if (this.clientCount === 1) {
			this.startRecording();
		}

		console.log(`Client connected. Total clients: ${this.clientCount}`);
	}

	removeClient(ws) {
		if (this.audioHandlers.has(ws)) {
			this.audioHandlers.delete(ws);
			this.clientCount = Math.max(0, this.clientCount - 1);

			console.log(`Client disconnected. Total clients: ${this.clientCount}`);

			if (this.clientCount === 0) {
				this.stopRecording();
			}
		}
	}

	broadcastAudio(chunk) {
		this.audioHandlers.forEach((handler, ws) => {
			try {
				handler(chunk);
			} catch (error) {
				console.error('Error sending audio to client:', error);
				this.removeClient(ws);
			}
		});
	}

	isRecording() {
		return this.record !== null;
	}
}

const audioManager = new AudioManager();
const wss = new WebSocket.Server({ port: wsPort });

wss.on('connection', (ws) => {
	console.log('New client connected');

	audioManager.addClient(ws);

	ws.on('close', () => {
		audioManager.removeClient(ws);
	});

	ws.on('error', (error) => {
		console.error('WebSocket error:', error);
		audioManager.removeClient(ws);
	});

	ws.isAlive = true;
	ws.on('pong', () => {
		ws.isAlive = true;
	});
});

const interval = setInterval(() => {
	wss.clients.forEach((ws) => {
		if (ws.isAlive === false) {
			console.log('Terminating dead connection');
			audioManager.removeClient(ws);
			return ws.terminate();
		}

		ws.isAlive = false;
		ws.ping(() => { });
	});
}, 30000);

wss.on('listening', () => {
	console.log(`WebSocket server listening on ws://0.0.0.0:${wsPort}/`);
	console.log('Audio capture will start when first client connects.');
});

wss.on('close', () => {
	clearInterval(interval);
	audioManager.stopRecording();
});

process.on('SIGINT', () => {
	console.log('Shutting down server...');
	audioManager.stopRecording();
	wss.clients.forEach((client) => {
		client.terminate();
	});
	wss.close(() => {
		console.log('Server shut down cleanly');
		process.exit(0);
	});
});

process.on('SIGTERM', () => {
	console.log('Received SIGTERM, shutting down...');
	audioManager.stopRecording();
	process.exit(0);
});

process.on('uncaughtException', (error) => {
	console.error('Uncaught Exception:', error);
	audioManager.stopRecording();
	process.exit(1);
});

process.on('unhandledRejection', (reason, promise) => {
	console.error('Unhandled Rejection at:', promise, 'reason:', reason);
});