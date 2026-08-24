// secrets-manager.mjs — Chiffrement/déchiffrement des secrets au repos
// Utilise AES-256-GCM avec clé dérivée de passphrase via scrypt.
// La passphrase est stockée dans Windows Credential Manager (DPAPI), pas sur disque.
// Les fichiers .secrets/*.enc sont chiffrés ; les fichiers en clair sont supprimés.

import { createCipheriv, createDecipheriv, randomBytes, scryptSync } from 'crypto';
import { readFileSync, writeFileSync, existsSync, unlinkSync, readdirSync, mkdirSync } from 'fs';
import { join, dirname } from 'path';
import { fileURLToPath } from 'url';
import { execSync } from 'child_process';
import { tmpdir } from 'os';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);
const SECRETS_DIR = join(__dirname, '.secrets');
const CRED_TARGET = 'rock-paper-scissors:secrets-passphrase';

// ─── Credential Storage (Windows DPAPI) ──────────────────────
// The passphrase is stored encrypted via DPAPI (tied to Windows user account).
// It is written to a small file .secrets/.passphrase.enc which is encrypted by
// Windows itself — only the current user can decrypt it. An attacker who copies
// the file to another machine or another user account cannot decrypt it.
const PASSPHRASE_ENC_PATH = join(SECRETS_DIR, '.passphrase.enc');

function storePassphrase(passphrase) {
  try {
    // Use DPAPI to encrypt: only current Windows user can decrypt
    // Write a PowerShell script to a temp file to avoid quoting issues
    const psScript = `
      $plain = ConvertTo-SecureString '${passphrase}' -AsPlainText -Force
      $encrypted = ConvertFrom-SecureString $plain
      Set-Content -Path '${PASSPHRASE_ENC_PATH}' -Value $encrypted -NoNewline
    `;
    const tmpFile = join(tmpdir(), 'rps_store_pass.ps1');
    writeFileSync(tmpFile, psScript);
    execSync(`powershell -NoProfile -ExecutionPolicy Bypass -File "${tmpFile}"`, { stdio: 'pipe' });
    try { unlinkSync(tmpFile); } catch (e) { /* ignore */ }
    return true;
  } catch (e) {
    console.error('❌ Failed to store passphrase:', e.message);
    return false;
  }
}

function loadPassphrase() {
  try {
    if (!existsSync(PASSPHRASE_ENC_PATH)) return null;
    // Use DPAPI to decrypt: only current Windows user can decrypt
    const psScript = `
      $encrypted = Get-Content -Path '${PASSPHRASE_ENC_PATH}' -Raw
      $secure = ConvertTo-SecureString $encrypted
      $bstr = [System.Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
      $plain = [System.Runtime.InteropServices.Marshal]::PtrToStringAuto($bstr)
      Write-Output $plain
    `;
    const tmpFile = join(tmpdir(), 'rps_load_pass.ps1');
    writeFileSync(tmpFile, psScript);
    const result = execSync(`powershell -NoProfile -ExecutionPolicy Bypass -File "${tmpFile}"`, { encoding: 'utf8', stdio: 'pipe' });
    try { unlinkSync(tmpFile); } catch (e) { /* ignore */ }
    return result.trim();
  } catch (e) {
    return null;
  }
}

function deletePassphrase() {
  try {
    if (existsSync(PASSPHRASE_ENC_PATH)) unlinkSync(PASSPHRASE_ENC_PATH);
    return true;
  } catch (e) {
    return false;
  }
}

// ─── AES-256-GCM encrypt/decrypt ──────────────────────────────
const KEY_LEN = 32; // 256 bits
const SALT_LEN = 16;
const IV_LEN = 12;
const TAG_LEN = 16;
const SCRYPT_N = 16384;
const SCRYPT_R = 8;
const SCRYPT_P = 1;

function deriveKey(passphrase, salt) {
  return scryptSync(passphrase, salt, KEY_LEN, { N: SCRYPT_N, r: SCRYPT_R, p: SCRYPT_P });
}

function encrypt(plaintext, passphrase) {
  const salt = randomBytes(SALT_LEN);
  const iv = randomBytes(IV_LEN);
  const key = deriveKey(passphrase, salt);
  const cipher = createCipheriv('aes-256-gcm', key, iv);
  const encrypted = Buffer.concat([cipher.update(plaintext, 'utf8'), cipher.final()]);
  const tag = cipher.getAuthTag();
  // Format: salt(16) + iv(12) + tag(16) + ciphertext
  return Buffer.concat([salt, iv, tag, encrypted]);
}

function decrypt(encryptedBuf, passphrase) {
  const salt = encryptedBuf.subarray(0, SALT_LEN);
  const iv = encryptedBuf.subarray(SALT_LEN, SALT_LEN + IV_LEN);
  const tag = encryptedBuf.subarray(SALT_LEN + IV_LEN, SALT_LEN + IV_LEN + TAG_LEN);
  const ciphertext = encryptedBuf.subarray(SALT_LEN + IV_LEN + TAG_LEN);
  const key = deriveKey(passphrase, salt);
  const decipher = createDecipheriv('aes-256-gcm', key, iv);
  decipher.setAuthTag(tag);
  const decrypted = Buffer.concat([decipher.update(ciphertext), decipher.final()]);
  return decrypted.toString('utf8');
}

// ─── Commands ─────────────────────────────────────────────────

function cmdInit() {
  const passphrase = randomBytes(32).toString('base64url');
  console.log('🔐 Generating new passphrase for secret encryption...');
  if (storePassphrase(passphrase)) {
    console.log('✅ Passphrase stored in Windows Credential Manager (DPAPI).');
    console.log('   It is tied to your Windows user account and never written to disk.');
  } else {
    console.error('❌ Failed to store passphrase. Aborting.');
    process.exit(1);
  }
}

function cmdEncrypt() {
  const passphrase = loadPassphrase();
  if (!passphrase) {
    console.error('❌ No passphrase found in Credential Manager. Run "node secrets-manager.mjs init" first.');
    process.exit(1);
  }

  if (!existsSync(SECRETS_DIR)) {
    console.error('❌ .secrets/ directory not found.');
    process.exit(1);
  }

  const files = readdirSync(SECRETS_DIR).filter(f => !f.endsWith('.enc') && !f.endsWith('.gitignore'));
  if (files.length === 0) {
    console.log('No plaintext secret files found to encrypt.');
    return;
  }

  let count = 0;
  for (const file of files) {
    const plaintextPath = join(SECRETS_DIR, file);
    const encPath = join(SECRETS_DIR, file + '.enc');
    const plaintext = readFileSync(plaintextPath, 'utf8').trim();
    const encrypted = encrypt(plaintext, passphrase);
    writeFileSync(encPath, encrypted);
    unlinkSync(plaintextPath); // Remove plaintext file
    console.log(`✅ Encrypted: ${file} → ${file}.enc (plaintext deleted)`);
    count++;
  }
  console.log(`\n🔒 ${count} secret(s) encrypted. Plaintext files deleted.`);
  console.log('   Encrypted files: .secrets/*.enc');
}

function cmdDecrypt(name) {
  const passphrase = loadPassphrase();
  if (!passphrase) {
    console.error('❌ No passphrase found in Credential Manager. Run "node secrets-manager.mjs init" first.');
    process.exit(1);
  }

  const encPath = join(SECRETS_DIR, name + '.enc');
  if (!existsSync(encPath)) {
    console.error(`❌ Encrypted secret not found: .secrets/${name}.enc`);
    process.exit(1);
  }

  const encrypted = readFileSync(encPath);
  const plaintext = decrypt(encrypted, passphrase);
  return plaintext;
}

function cmdDecryptAll() {
  const passphrase = loadPassphrase();
  if (!passphrase) {
    console.error('❌ No passphrase found in Credential Manager.');
    process.exit(1);
  }

  if (!existsSync(SECRETS_DIR)) {
    console.error('❌ .secrets/ directory not found.');
    process.exit(1);
  }

  const encFiles = readdirSync(SECRETS_DIR).filter(f => f.endsWith('.enc'));
  const results = {};
  for (const file of encFiles) {
    const name = file.replace('.enc', '');
    const encPath = join(SECRETS_DIR, file);
    const encrypted = readFileSync(encPath);
    results[name] = decrypt(encrypted, passphrase);
  }
  return results;
}

function cmdStatus() {
  const passphrase = loadPassphrase();
  console.log('🔐 Secrets Manager Status');
  console.log('   Passphrase in Credential Manager:', passphrase ? '✅ Yes' : '❌ No');

  if (existsSync(SECRETS_DIR)) {
    const allFiles = readdirSync(SECRETS_DIR).filter(f => !f.endsWith('.gitignore'));
    const encFiles = allFiles.filter(f => f.endsWith('.enc'));
    const plainFiles = allFiles.filter(f => !f.endsWith('.enc'));
    console.log('   Encrypted files:', encFiles.length);
    console.log('   Plaintext files:', plainFiles.length, plainFiles.length > 0 ? '(⚠️ should be encrypted)' : '(✅ all encrypted)');
    if (encFiles.length > 0) {
      console.log('   Encrypted secrets:', encFiles.map(f => f.replace('.enc', '')).join(', '));
    }
  } else {
    console.log('   .secrets/ directory: not found');
  }
}

function cmdProvision() {
  // Decrypt all secrets to plaintext (for docker-compose up)
  const passphrase = loadPassphrase();
  if (!passphrase) {
    console.error('❌ No passphrase found in Credential Manager. Run "node secrets-manager.mjs init" first.');
    process.exit(1);
  }

  if (!existsSync(SECRETS_DIR)) {
    mkdirSync(SECRETS_DIR, { recursive: true });
  }

  const encFiles = readdirSync(SECRETS_DIR).filter(f => f.endsWith('.enc') && f !== '.passphrase.enc');
  if (encFiles.length === 0) {
    console.log('No encrypted secrets to provision.');
    return;
  }

  let count = 0;
  for (const file of encFiles) {
    const name = file.replace('.enc', '');
    const encPath = join(SECRETS_DIR, file);
    const plainPath = join(SECRETS_DIR, name);
    const encrypted = readFileSync(encPath);
    const plaintext = decrypt(encrypted, passphrase);
    writeFileSync(plainPath, plaintext);
    console.log(`✅ Provisioned: ${name}`);
    count++;
  }
  console.log(`\n🔓 ${count} secret(s) provisioned to .secrets/ (plaintext).`);
  console.log('   Run "docker compose up -d" now.');
  console.log('   Run "node secrets-manager.mjs deprovision" after to delete plaintext.');
}

function cmdDeprovision() {
  // Delete plaintext files (keep only .enc)
  if (!existsSync(SECRETS_DIR)) return;

  const plainFiles = readdirSync(SECRETS_DIR).filter(f => !f.endsWith('.enc') && !f.endsWith('.gitignore'));
  let count = 0;
  for (const file of plainFiles) {
    const plainPath = join(SECRETS_DIR, file);
    unlinkSync(plainPath);
    console.log(`🗑️  Deleted plaintext: ${file}`);
    count++;
  }
  console.log(`\n🔒 ${count} plaintext file(s) deleted. Only encrypted (.enc) files remain.`);
}

// ─── CLI ──────────────────────────────────────────────────────
const command = process.argv[2];

switch (command) {
  case 'init':
    cmdInit();
    break;
  case 'encrypt':
    cmdEncrypt();
    break;
  case 'status':
    cmdStatus();
    break;
  case 'provision':
    cmdProvision();
    break;
  case 'deprovision':
    cmdDeprovision();
    break;
  case 'decrypt': {
    const name = process.argv[3];
    if (!name) {
      console.error('Usage: node secrets-manager.mjs decrypt <name>');
      process.exit(1);
    }
    const val = cmdDecrypt(name);
    console.log(val);
    break;
  }
  case 'decrypt-all': {
    const all = cmdDecryptAll();
    console.log(JSON.stringify(all, null, 2));
    break;
  }
  default:
    console.log(`Usage: node secrets-manager.mjs <command>
Commands:
  init          Generate and store a new passphrase in Windows Credential Manager
  encrypt       Encrypt all .secrets/* (plaintext) → .secrets/*.enc (delete plaintext)
  provision     Decrypt .secrets/*.enc → .secrets/* (for docker compose up)
  deprovision   Delete .secrets/* plaintext (keep only .enc)
  status        Show encryption status
  decrypt <name>   Decrypt one secret (print to stdout)
  decrypt-all      Decrypt all secrets (JSON to stdout)
`);
}