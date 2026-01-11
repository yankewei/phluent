#!/usr/bin/env node
'use strict';

const fs = require('fs');
const path = require('path');
const fsp = fs.promises;

const dataDir = path.resolve(process.cwd(), 'data');
const fileName = process.argv[2] || `random-${Date.now()}-${randomString(6)}.jsonl`;
const intervalArg = Number(process.argv[3]);
const intervalMs = Number.isFinite(intervalArg) && intervalArg > 0 ? intervalArg : 1000;
const filePath = path.join(dataDir, fileName);

fs.mkdirSync(dataDir, { recursive: true });

function randomInt(min, max) {
  return Math.floor(Math.random() * (max - min + 1)) + min;
}

function randomString(length) {
  const alphabet = 'abcdefghijklmnopqrstuvwxyz0123456789';
  let out = '';
  for (let i = 0; i < length; i += 1) {
    out += alphabet[randomInt(0, alphabet.length - 1)];
  }
  return out;
}

function makeRecord() {
  return {
    ts: new Date().toISOString(),
    id: randomInt(1000, 9999),
    level: ['info', 'warn', 'error'][randomInt(0, 2)],
    message: randomString(12),
    value: randomInt(1, 100000),
  };
}

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

async function main() {
  for (;;) {
    const line = JSON.stringify(makeRecord()) + '\n';
    const handle = await fsp.open(filePath, 'a');
    try {
      await handle.write(line);
      await handle.sync();
    } finally {
      await handle.close();
    }
    await sleep(intervalMs);
  }
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
