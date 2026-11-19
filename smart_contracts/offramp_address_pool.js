// Generates unique AVAXe deposit addresses for off-ramp orders.
// Dedicated range starts at 2,000,000, separate from on-ramp (0..1999) and
// treasury index 1,000,000. The seed is never logged.
import fs from 'node:fs';
import mysql from 'mysql2/promise';
import { HDNodeWallet } from 'ethers';

const SEED_PATH = process.env.ONRAMP_DEPOSIT_SEED_FILE || '/run/secrets/onramp_deposit_seed';
const START_INDEX = Number(process.env.OFFRAMP_ADDRESS_START_INDEX || 2000000);
const COUNT = Math.max(1, Number(process.argv[2] || process.env.OFFRAMP_ADDRESS_BATCH || 2000));
const BATCH = 200;
function readSecret(path, envName) {
  if (process.env[envName]) return String(process.env[envName]).trim();
  return fs.readFileSync(path, 'utf8').trim();
}
const seed = readSecret(SEED_PATH, 'ONRAMP_DEPOSIT_SEED');
const dbPassword = readSecret('/run/secrets/db_app_password', 'DB_PASSWORD');
if (!seed) throw new Error('seed missing');
if (!dbPassword) throw new Error('DB password missing');
const pool = mysql.createPool({host:process.env.DB_HOST||'db',port:Number(process.env.DB_PORT||3306),user:process.env.DB_USERNAME||'rps_app',password:dbPassword,database:process.env.DB_DATABASE||'rock_paper_scissors',connectionLimit:3,charset:'utf8mb4'});
const [[row]] = await pool.query('SELECT COALESCE(MAX(derivation_index), ?) AS max_index FROM offramp_deposit_addresses', [START_INDEX - 1]);
const start = Math.max(START_INDEX, Number(row.max_index) + 1);
let inserted = 0;
for (let offset = 0; offset < COUNT; offset += BATCH) {
  const values = [];
  const end = Math.min(COUNT, offset + BATCH);
  for (let i = offset; i < end; i++) {
    const index = start + i;
    const wallet = HDNodeWallet.fromPhrase(seed, undefined, `m/44'/60'/0'/0/${index}`);
    values.push([index, wallet.address, new Date(), new Date()]);
  }
  const [res] = await pool.query('INSERT IGNORE INTO offramp_deposit_addresses (derivation_index, address, created_at, updated_at) VALUES ?', [values]);
  inserted += res.affectedRows;
}
const [[stats]] = await pool.query('SELECT COUNT(*) AS total, SUM(order_id IS NULL) AS free FROM offramp_deposit_addresses');
console.log(JSON.stringify({generated:inserted,start,count:COUNT,total:Number(stats.total),free:Number(stats.free)}));
await pool.end();
