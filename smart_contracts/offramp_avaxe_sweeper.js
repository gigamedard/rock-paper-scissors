// Sweeps AVAXe received on unique off-ramp addresses to the AVAXe hot wallet.
// TST gas is topped up from a dedicated HD gas wallet (index 1,000,002),
// separate from the on-ramp credit worker.
import fs from 'node:fs';
import mysql from 'mysql2/promise';
import { JsonRpcProvider, HDNodeWallet, Contract, parseEther, formatEther, formatUnits } from 'ethers';

const SEED_PATH = process.env.ONRAMP_DEPOSIT_SEED_FILE || '/run/secrets/onramp_deposit_seed';
const RPC_URL = process.env.PINGALA_RPC_URL || process.env.FUJI_RPC_URL;
const TOKEN = process.env.AVAXE_ADDRESS || '0x9d8AA35058493Aa826be143B568BCfA8E2F537F7';
const DESTINATION = process.env.OFFRAMP_AVAXE_DESTINATION || '0x8B21bef13d147D36b5F9F2682175BB6529A8D375';
const GAS_INDEX = Number(process.env.OFFRAMP_TST_GAS_INDEX || 1000002);
const GAS_MIN = process.env.OFFRAMP_TST_GAS_MIN || '0.002';
const GAS_TOPUP = process.env.OFFRAMP_TST_GAS_TOPUP || '0.005';
const GAS_RESERVE = process.env.OFFRAMP_TST_GAS_RESERVE || '0.05';
const POLL_MS = Number(process.env.OFFRAMP_SWEEP_POLL_MS || 15000);
const TX_TIMEOUT_MS = Number(process.env.OFFRAMP_SWEEP_TX_TIMEOUT_MS || 120000);
const STALE_SECONDS = Number(process.env.OFFRAMP_SWEEP_STALE_SECONDS || 300);
const TELEGRAM_TOKEN = process.env.TELEGRAM_BOT_TOKEN || '';
const TELEGRAM_ADMIN_IDS = String(process.env.TELEGRAM_ADMIN_IDS || '').split(',').map(s=>s.trim()).filter(Boolean);

function readSecret(path, envName) {
  if (process.env[envName]) return String(process.env[envName]).trim();
  try { return fs.readFileSync(path, 'utf8').trim(); } catch { return ''; }
}
const DB_PASSWORD = readSecret('/run/secrets/db_app_password', 'DB_PASSWORD');
const SEED = readSecret(SEED_PATH, 'ONRAMP_DEPOSIT_SEED');
if (!DB_PASSWORD) throw new Error('DB password missing');
if (!SEED) throw new Error('seed missing');
const pool = mysql.createPool({host:process.env.DB_HOST||'db',port:Number(process.env.DB_PORT||3306),user:process.env.DB_USERNAME||'rps_app',password:DB_PASSWORD,database:process.env.DB_DATABASE||'rock_paper_scissors',waitForConnections:true,connectionLimit:4,charset:'utf8mb4',supportBigNumbers:true,bigNumberStrings:true});
const provider = new JsonRpcProvider(RPC_URL);
const gasWallet = HDNodeWallet.fromPhrase(SEED, undefined, `m/44'/60'/0'/0/${GAS_INDEX}`).connect(provider);
const tokenRead = new Contract(TOKEN, ['function decimals() view returns (uint8)','function balanceOf(address) view returns(uint256)'], provider);
const sleep = ms => new Promise(r=>setTimeout(r,ms));
const log = (...a) => console.log(new Date().toISOString(), ...a);
let decimals = 18;
let lastGasNotice = 0;

async function notify(chatId,text) { if(!TELEGRAM_TOKEN||!chatId)return; try{const r=await fetch(`https://api.telegram.org/bot${TELEGRAM_TOKEN}/sendMessage`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({chat_id:String(chatId),text,disable_web_page_preview:true})});if(!r.ok)log('telegram notify failed',chatId,r.status);}catch(e){log('telegram notify error',chatId,e.message);} }
async function notifyAdmins(text){for(const id of TELEGRAM_ADMIN_IDS)await notify(id,text);}
async function event(orderId,type,payload){await pool.query(`INSERT INTO offramp_events (offramp_order_id,type,actor_type,actor_id,payload,created_at,updated_at) VALUES (?,?, 'system','offramp-sweeper',?,NOW(),NOW())`,[orderId,type,payload?JSON.stringify(payload):null]);}
async function release(order,status,reason){await pool.query(`UPDATE offramp_orders SET sweep_status=?,sweep_last_error=?,sweep_started_at=NULL,updated_at=NOW() WHERE id=? AND sweep_status='sweeping'`,[status,String(reason).slice(0,1000),order.id]);await event(order.id,'sweep_deferred',{status,reason});log('sweep deferred',order.reference,status,reason);}
async function manual(order,reason,txHash=null){const m=String(reason).slice(0,1000);await pool.query(`UPDATE offramp_orders SET sweep_status='manual_review',sweep_last_error=?,sweep_tx_hash=COALESCE(?,sweep_tx_hash),sweep_started_at=NULL,updated_at=NOW() WHERE id=? AND sweep_status='sweeping'`,[m,txHash?txHash.toLowerCase():null,order.id]);await event(order.id,'sweep_manual_review',{error:m,tx_hash:txHash});await notifyAdmins(`⚠️ Sweep AVAXe en revue\nCommande: ${order.reference}\nRaison: ${m}${txHash?`\nTx: ${txHash}`:''}\nGas wallet: ${gasWallet.address}`);log('sweep manual',order.reference,m);}
async function finalize(order,txHash,amount){const conn=await pool.getConnection();try{await conn.beginTransaction();const [res]=await conn.query(`UPDATE offramp_orders SET sweep_status='swept',sweep_tx_hash=?,sweep_amount=?,swept_at=NOW(),sweep_last_error=NULL,sweep_started_at=NULL,updated_at=NOW() WHERE id=? AND sweep_status='sweeping'`,[txHash.toLowerCase(),amount,order.id]);if(res.affectedRows===1)await conn.query(`INSERT INTO offramp_events (offramp_order_id,type,actor_type,actor_id,payload,created_at,updated_at) VALUES (?, 'avaxe_swept','system','offramp-sweeper',?,NOW(),NOW())`,[order.id,JSON.stringify({tx_hash:txHash.toLowerCase(),amount,destination:DESTINATION})]);await conn.commit();return res.affectedRows===1;}catch(e){await conn.rollback();throw e;}finally{conn.release();}}
async function loadSweeps(){const [rows]=await pool.query(`SELECT id,reference,user_id,deposit_index,deposit_address,deposit_amount,status,sweep_status,sweep_tx_hash,TIMESTAMPDIFF(SECOND,sweep_started_at,NOW()) AS age FROM offramp_orders WHERE sweep_tx_hash IS NULL AND deposit_index IS NOT NULL AND deposit_amount>0 AND status IN ('approved','paying','paid') AND (sweep_status IS NULL OR sweep_status IN ('pending','gas_deferred')) ORDER BY id ASC LIMIT 10`);return rows;}
async function claim(order){const [r]=await pool.query(`UPDATE offramp_orders SET sweep_status='sweeping',sweep_attempts=sweep_attempts+1,sweep_started_at=NOW(),sweep_last_error=NULL,updated_at=NOW() WHERE id=? AND (sweep_status IS NULL OR sweep_status IN ('pending','gas_deferred'))`,[order.id]);return r.affectedRows===1;}
async function recover(){const [rows]=await pool.query(`SELECT id,reference,deposit_index,deposit_address,deposit_amount,sweep_status,sweep_tx_hash,TIMESTAMPDIFF(SECOND,sweep_started_at,NOW()) AS age FROM offramp_orders WHERE sweep_status='sweeping' ORDER BY id ASC LIMIT 20`);for(const order of rows){const age=Number(order.age||0);if(!order.sweep_tx_hash){if(age>=STALE_SECONDS)await manual(order,'Sweep interrompu avant envoi AVAXe.');continue;}try{const r=await provider.getTransactionReceipt(order.sweep_tx_hash);if(r){if(Number(r.status)===1){if(await finalize(order,order.sweep_tx_hash,order.deposit_amount))await notifyAdmins(`✅ AVAXe sweep récupéré\nCommande: ${order.reference}\nTx: ${order.sweep_tx_hash}`);}else await manual(order,'Sweep AVAXe reverted.',order.sweep_tx_hash);}else if(age>Math.max(STALE_SECONDS,900))await manual(order,'Sweep sans receipt — vérifier.',order.sweep_tx_hash);}catch(e){log('recover sweep error',order.reference,e.message);}}}
async function processOrder(order){let wallet;try{wallet=HDNodeWallet.fromPhrase(SEED,undefined,`m/44'/60'/0'/0/${order.deposit_index}`).connect(provider);}catch(e){await manual(order,`Dérivation impossible: ${e.message}`);return;}if(wallet.address.toLowerCase()!==String(order.deposit_address).toLowerCase()){await manual(order,'Adresse dérivée ≠ commande.');return;}
 const balance=await tokenRead.balanceOf(order.deposit_address);if(balance<=0n){await manual(order,'Aucun AVAXe sur l’adresse.');return;}
 const gas=await provider.getBalance(wallet.address);if(gas<parseEther(GAS_MIN)){const gb=await provider.getBalance(gasWallet.address);const topup=parseEther(GAS_TOPUP);const reserve=parseEther(GAS_RESERVE);if(gb<topup+reserve){const now=Date.now();if(now-lastGasNotice>300000){lastGasNotice=now;await notifyAdmins(`⛔ TST gas bas pour sweep AVAXe\nGas wallet: ${gasWallet.address}\nSolde: ${formatEther(gb)} TST\nRequis: ${GAS_TOPUP} + ${GAS_RESERVE} TST\nCommande: ${order.reference}`);}await release(order,'gas_deferred',`TST gas insuffisant (${formatEther(gb)}).`);return;}try{const t=await gasWallet.sendTransaction({to:wallet.address,value:topup});await t.wait(1,TX_TIMEOUT_MS);await event(order.id,'sweep_tst_topup',{amount:GAS_TOPUP,tx_hash:t.hash});log('tst topup',order.reference,GAS_TOPUP,t.hash);}catch(e){await release(order,'gas_deferred',`Topup TST échoué: ${e.shortMessage||e.message}`);return;}}
 const c=new Contract(TOKEN,['function transfer(address to,uint256 amount) returns(bool)'],wallet);let tx;try{tx=await c.transfer(DESTINATION,balance);}catch(e){await release(order,'pending',`Envoi AVAXe échoué: ${e.shortMessage||e.message}`);return;}try{await pool.query(`UPDATE offramp_orders SET sweep_tx_hash=?,sweep_amount=?,updated_at=NOW() WHERE id=? AND sweep_status='sweeping'`,[tx.hash.toLowerCase(),formatUnits(balance,decimals),order.id]);}catch(e){log('store sweep hash failed',order.reference,tx.hash,e.message);}log('sweep broadcast',order.reference,tx.hash);try{const r=await tx.wait(1,TX_TIMEOUT_MS);if(r&&Number(r.status)===1){if(await finalize(order,tx.hash,formatUnits(balance,decimals))){log('swept',order.reference,tx.hash);await notifyAdmins(`✅ AVAXe rapatrié\nCommande: ${order.reference}\nMontant: ${formatUnits(balance,decimals)} AVAXe\nDestination: ${DESTINATION}\nTx: ${tx.hash}`);}}else await manual(order,'AVAXe transfer sans succès.',tx.hash);}catch(e){await manual(order,`Attente receipt sweep: ${e.shortMessage||e.message}`,tx.hash);}}
async function main(){decimals=Number(await tokenRead.decimals().catch(()=>18));log('offramp AVAXe sweeper started',{gasWallet:gasWallet.address,gas:formatEther(await provider.getBalance(gasWallet.address).catch(()=>0n)),destination:DESTINATION,token:TOKEN});while(true){try{await recover();const orders=await loadSweeps();for(const o of orders)if(await claim(o))await processOrder(o);}catch(e){log('sweep cycle error',e.message);}await sleep(POLL_MS);}}
process.on('SIGTERM',async()=>{try{await pool.end();}catch{}process.exit(0);});process.on('SIGINT',async()=>{try{await pool.end();}catch{}process.exit(0);});main().catch(e=>{log('fatal',e.message);process.exit(1);});
