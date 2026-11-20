// Settles public USDT <-> AVAXe swap orders after the user submits a tx hash.
// USDT deposits go to the USDT treasury; AVAXe deposits go to a dedicated swap
// hot wallet (HD index 1,000,003). No Wave, no KYC.
import fs from 'node:fs';
import mysql from 'mysql2/promise';
import { JsonRpcProvider, HDNodeWallet, Contract, Interface, parseUnits, formatUnits } from 'ethers';

const SEED_PATH = process.env.ONRAMP_DEPOSIT_SEED_FILE || '/run/secrets/onramp_deposit_seed';
const PINGALA_RPC = process.env.PINGALA_RPC_URL;
const AVAXE = '0x9d8AA35058493Aa826be143B568BCfA8E2F537F7';
const SWAP_HOT_INDEX = Number(process.env.SWAP_AVAXE_HOT_INDEX || 1000003);
const TREASURY_INDEX = Number(process.env.SWAP_USDT_TREASURY_INDEX || 1000000);
const USDT_TREASURY = (process.env.SWAP_USDT_TREASURY_ADDRESS || '').toLowerCase();
const SWAP_HOT = (process.env.SWAP_AVAXE_HOT_ADDRESS || '').toLowerCase();
const POLL_MS = Number(process.env.SWAP_POLL_MS || 7000);
const STALE_SECONDS = Number(process.env.SWAP_STALE_SECONDS || 300);
const TX_TIMEOUT_MS = Number(process.env.SWAP_TX_TIMEOUT_MS || 120000);
const LOCK = 'battlepool_treasury';
const TRANSFER = '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef';

function readSecret(path, env) { if (process.env[env]) return String(process.env[env]).trim(); try { return fs.readFileSync(path,'utf8').trim(); } catch { return ''; } }
const DB_PASSWORD = readSecret('/run/secrets/db_app_password','DB_PASSWORD');
const SEED = readSecret(SEED_PATH,'ONRAMP_DEPOSIT_SEED');
if (!DB_PASSWORD || !SEED || !PINGALA_RPC) throw new Error('missing DB/seed/RPC');
const pool = mysql.createPool({host:process.env.DB_HOST||'db',port:Number(process.env.DB_PORT||3306),user:process.env.DB_USERNAME||'rps_app',password:DB_PASSWORD,database:process.env.DB_DATABASE||'rock_paper_scissors',waitForConnections:true,connectionLimit:4,charset:'utf8mb4',supportBigNumbers:true,bigNumberStrings:true});
const pingala = new JsonRpcProvider(PINGALA_RPC);
const swapNode = HDNodeWallet.fromPhrase(SEED, undefined, `m/44'/60'/0'/0/${SWAP_HOT_INDEX}`).connect(pingala);
const treasuryNode = HDNodeWallet.fromPhrase(SEED, undefined, `m/44'/60'/0'/0/${TREASURY_INDEX}`);
const swapAddress = swapNode.address;
if (SWAP_HOT && swapAddress.toLowerCase() !== SWAP_HOT) throw new Error(`swap hot mismatch ${swapAddress}`);
const networks = {
  polygon: { rpc: process.env.ONRAMP_POLYGON_RPC_URL || 'https://polygon-bor-rpc.publicnode.com', token: process.env.ONRAMP_POLYGON_USDT_CONTRACT || '0xc2132D05D31c914a87C6611C10748AEb04B58e8F', conf: Number(process.env.SWAP_POLYGON_CONFIRMATIONS || 12) },
  bsc: { rpc: process.env.ONRAMP_BEP20_RPC_URL || 'https://bsc-rpc.publicnode.com', token: process.env.ONRAMP_BEP20_USDT_CONTRACT || '0x55d398326f99059fF775485246999027B3197955', conf: Number(process.env.SWAP_BSC_CONFIRMATIONS || 8) },
};
for (const [k,n] of Object.entries(networks)) { n.key=k; n.provider=new JsonRpcProvider(n.rpc); n.tokenRead=new Contract(n.token,['function decimals() view returns(uint8)','function balanceOf(address) view returns(uint256)'],n.provider); n.treasury=treasuryNode.connect(n.provider); }
const avaxeTransfer = new Contract(AVAXE,['function transfer(address,uint256) returns(bool)'],swapNode);
const avaxeToken = new Contract(AVAXE,['function balanceOf(address) view returns(uint256)'],pingala);
const iface = new Interface(['event Transfer(address indexed from,address indexed to,uint256 value)']);
const sleep = ms=>new Promise(r=>setTimeout(r,ms)); const log=(...a)=>console.log(new Date().toISOString(),...a);

function transferTo(receipt, token, to) {
  for (const l of receipt.logs || []) {
    if (String(l.address).toLowerCase() !== token.toLowerCase() || !l.topics || l.topics.length < 3) continue;
    if (String(l.topics[0]).toLowerCase() !== TRANSFER) continue;
    if ('0x' + l.topics[2].slice(26) !== to.toLowerCase()) continue;
    try { return BigInt(l.data); } catch {}
  }
  return null;
}
async function withTreasuryLock(network, fn) {
  const conn = await pool.getConnection(); const name = `${LOCK}_${network}`;
  try { const [[r]]=await conn.query('SELECT GET_LOCK(?,30) ok',[name]); if(!r||Number(r.ok)!==1) throw new Error('lock busy'); try{return await fn();}finally{await conn.query('SELECT RELEASE_LOCK(?)',[name]);} } finally { conn.release(); }
}
async function release(order, status, error) { await pool.query(`UPDATE swap_orders SET status=?,error=?,updated_at=NOW() WHERE id=? AND status='paying'`,[status,String(error||'').slice(0,1000),order.id]); log('swap release',order.reference,status,error); }
async function complete(order, txHash) { const [r]=await pool.query(`UPDATE swap_orders SET status='completed',payout_tx_hash=?,completed_at=NOW(),error=NULL,updated_at=NOW() WHERE id=? AND status='paying'`,[txHash.toLowerCase(),order.id]); if(r.affectedRows===1) log('swap completed',order.reference,txHash); }
async function processOrder(order) {
  const net = networks[order.network]; if(!net) return release(order,'manual_review','network missing');
  if (order.direction === 'usdt_to_avaxe') {
    const dep = await net.provider.getTransactionReceipt(order.deposit_tx_hash); if(!dep) return;
    const current = await net.provider.getBlockNumber(); const conf = current - dep.blockNumber + 1; if (conf < net.conf) return;
    if (Number(dep.status)!==1) return release(order,'manual_review','USDT deposit reverted');
    const amount = transferTo(dep, net.token, USDT_TREASURY); if(!amount) return release(order,'manual_review','USDT transfer to treasury not found');
    const decimals = Number(await net.tokenRead.decimals().catch(()=>6)); const expected = parseUnits(Number(order.amount_in).toFixed(decimals), decimals);
    if (amount < expected) return release(order,'manual_review','USDT amount too low');
    const out = parseUnits(Number(order.amount_out).toFixed(18),18);
    let tx; try { tx = await avaxeTransfer.transfer(order.payout_address,out); } catch(e){ return release(order,'manual_review',e.shortMessage||e.message); }
    try { await pool.query('UPDATE swap_orders SET payout_tx_hash=?,confirmations=?,verified_at=NOW(),updated_at=NOW() WHERE id=? AND status=\'paying\'',[tx.hash.toLowerCase(),conf,order.id]); const r=await tx.wait(1,TX_TIMEOUT_MS); if(r&&Number(r.status)===1) await complete(order,tx.hash); else await release(order,'manual_review','AVAXe payout failed'); } catch(e){ await release(order,'manual_review','AVAXe payout timeout '+ (e.shortMessage||e.message)); }
    return;
  }
  // avaxe_to_usdt: verify Pingala deposit to swap hot.
  const pdep = await pingala.getTransactionReceipt(order.deposit_tx_hash); if(!pdep) return;
  const pc = await pingala.getBlockNumber(); const pconf = pc - pdep.blockNumber + 1; if(pconf < Number(process.env.SWAP_PINGALA_CONFIRMATIONS || 2)) return;
  if(Number(pdep.status)!==1) return release(order,'manual_review','AVAXe deposit reverted');
  const amount = transferTo(pdep, AVAXE, swapAddress); if(!amount) return release(order,'manual_review','AVAXe transfer to swap address not found');
  const expected = parseUnits(Number(order.amount_in).toFixed(18),18); if(amount < expected) return release(order,'manual_review','AVAXe amount too low');
  const decimals = Number(await net.tokenRead.decimals().catch(()=>6)); const out = parseUnits(Number(order.amount_out).toFixed(decimals), decimals);
  let tx;
  try {
    tx = await withTreasuryLock(order.network, async()=>{
      const bal=await net.tokenRead.balanceOf(net.treasury.address); if(bal<out) throw new Error('USDT treasury insufficient');
      const t=await new Contract(net.token,['function transfer(address,uint256) returns(bool)'],net.treasury).transfer(order.payout_address,out);
      await pool.query('UPDATE swap_orders SET payout_tx_hash=?,confirmations=?,verified_at=NOW(),updated_at=NOW() WHERE id=? AND status=\'paying\'',[t.hash.toLowerCase(),pconf,order.id]); return t;
    });
  } catch(e){ return release(order,'manual_review',e.shortMessage||e.message); }
  try { const r=await tx.wait(1,TX_TIMEOUT_MS); if(r&&Number(r.status)===1) await complete(order,tx.hash); else await release(order,'manual_review','USDT payout failed'); } catch(e){ await release(order,'manual_review','USDT payout timeout '+ (e.shortMessage||e.message)); }
}
async function cycle() {
  const [rows]=await pool.query(`SELECT id,reference,direction,network,amount_in,amount_out,deposit_tx_hash,status,TIMESTAMPDIFF(SECOND,updated_at,NOW()) age FROM swap_orders WHERE status='pending_verification' ORDER BY id ASC LIMIT 10`);
  for(const o of rows){ const conn=await pool.getConnection(); try{ await conn.beginTransaction(); const [r]=await conn.query(`UPDATE swap_orders SET status='paying',updated_at=NOW() WHERE id=? AND status='pending_verification'`,[o.id]); await conn.commit(); if(r.affectedRows===1) await processOrder(o); } catch(e){await conn.rollback(); log('swap error',o.reference,e.message);} finally{conn.release();} }
  const [paying]=await pool.query(`SELECT id,reference,direction,network,amount_in,amount_out,deposit_tx_hash,payout_tx_hash,status,TIMESTAMPDIFF(SECOND,updated_at,NOW()) age FROM swap_orders WHERE status='paying' ORDER BY id ASC LIMIT 20`);
  for(const o of paying){ if(o.payout_tx_hash){ const net=networks[o.network]; try{const r=await net.provider.getTransactionReceipt(o.payout_tx_hash); if(r&&Number(r.status)===1) await complete(o,o.payout_tx_hash); else if(r) await release(o,'manual_review','payout reverted');}catch{} } else if(Number(o.age)>STALE_SECONDS) await release(o,'manual_review','stale paying order'); }
}
async function main(){ log('swap settlement worker started',{swapAddress,treasury:treasuryNode.address}); while(true){ try{await cycle();}catch(e){log('cycle error',e.message);} await sleep(POLL_MS); } }
process.on('SIGTERM',async()=>{try{await pool.end();}catch{}process.exit(0);}); process.on('SIGINT',async()=>{try{await pool.end();}catch{}process.exit(0);}); main().catch(e=>{log('fatal',e.message);process.exit(1);});
