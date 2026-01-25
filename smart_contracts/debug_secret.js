
import dotenv from 'dotenv';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

dotenv.config({ path: join(__dirname, '.env') });

const secret = process.env.INTERNAL_API_SECRET;
console.log(`Secret: '${secret}'`);
console.log(`Length: ${secret ? secret.length : 'undefined'}`);
console.log('Hex:', Buffer.from(secret || '').toString('hex'));
