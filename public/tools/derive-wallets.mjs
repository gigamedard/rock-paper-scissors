import { HDNodeWallet, wordlists, Mnemonic } from 'ethers';
import { wordlist as frenchWords } from '@scure/bip39/wordlists/french';
import { randomBytes } from 'node:crypto';

function makeFrenchWordlist(words) {
    const map = new Map();
    words.forEach((w, i) => map.set(w.normalize('NFKD'), i));
    return {
        locale: 'fr',
        getWordIndex(word) {
            const key = String(word).toLowerCase().normalize('NFKD');
            return map.has(key) ? map.get(key) : -1;
        },
        getWord(index) { return words[index]; },
        split(phrase) { return String(phrase).toLowerCase().split(/\s+/g); },
        join(words) { return words.map((w) => String(w).normalize('NFC')).join(' '); }
    };
}

async function readStdin() {
    let data = '';
    process.stdin.setEncoding('utf8');
    for await (const chunk of process.stdin) data += chunk;
    return data.trim();
}

function parseArgs() {
    const args = process.argv.slice(2);
    const get = (name, fallback) => {
        const i = args.indexOf(name);
        return i >= 0 && args[i + 1] !== undefined ? args[i + 1] : fallback;
    };
    return {
        index: Number(get('--index', process.env.INDEX || '0')),
        lang: String(get('--lang', process.env.BIP39_LANGUAGE || 'en')).toLowerCase(),
        words: Number(get('--words', process.env.BIP39_WORDS || '12')),
        generate: args.includes('--generate'),
        json: args.includes('--json'),
    };
}

async function main() {
    const { index, lang, words, generate, json } = parseArgs();
    if (!Number.isInteger(index) || index < 0) {
        throw new Error('INDEX invalide (entier >= 0 attendu).');
    }

    const wordlist = lang === 'fr' ? makeFrenchWordlist(frenchWords) : wordlists.en;
    if (!wordlist) {
        console.error('Langue BIP39 non supportée (utilise --lang fr ou --lang en).');
        process.exit(1);
    }

    if (generate) {
        const count = words === 24 ? 24 : 12;
        const mnemonic = Mnemonic.fromEntropy(randomBytes(count === 24 ? 32 : 16), '', wordlist);
        const generated = { language: lang, words: count, mnemonic: mnemonic.phrase.normalize('NFC') };
        if (json) {
            console.error('⚠️  SECRET : ne jamais partager ni committer cette seed.');
            console.log(JSON.stringify(generated, null, 2));
        } else {
            console.log('⚠️  SECRET : sauvegarde cette seed hors ligne, ne la partage jamais.');
            console.log(`Langue : ${lang}`);
            console.log(`Mots : ${count}`);
            console.log('');
            console.log(generated.mnemonic);
        }
        return;
    }

    let phrase = (process.env.SEED_PHRASE || '').trim();
    if (!phrase && !process.stdin.isTTY) {
        phrase = await readStdin();
    }
    if (!phrase) {
        console.error('Usage : SEED_PHRASE="mot1 mot2 ..." node scripts/derive-wallets.mjs [--lang fr|en] [--index 0] [--json]');
        console.error('Ou : node scripts/derive-wallets.mjs --generate --lang fr --words 12|24');
        console.error('Ou : node scripts/derive-wallets.mjs < seed.txt');
        console.error('Ne jamais coller une seed réelle dans un shell partagé/historisé.');
        process.exit(1);
    }

    phrase = phrase.replace(/\s+/g, ' ').trim();
    const passphrase = process.env.BIP39_PASSPHRASE || '';

    const evmPath = `m/44'/60'/0'/0/${index}`;

    let evm;
    try {
        if (!Mnemonic.isValidMnemonic(phrase, wordlist)) {
            throw new Error('checksum');
        }
        evm = HDNodeWallet.fromPhrase(phrase, passphrase, evmPath, wordlist);
    } catch (e) {
        console.error(`Seed phrase BIP39 ${lang === 'fr' ? 'française' : 'anglaise'} invalide (mots/checksum/ordre).`);
        process.exit(1);
    }

    const result = {
        language: lang,
        index,
        evm: {
            path: evmPath,
            address: evm.address,
            publicKey: evm.signingKey.publicKey,
            privateKey: evm.privateKey,
        },
    };

    if (json) {
        console.log(JSON.stringify(result, null, 2));
        return;
    }

    console.log('⚠️  SECRETS : ne jamais partager ni committer ces clés.');
    console.log(`Langue : ${lang}`);
    console.log(`Index : ${index}`);
    console.log('');
    console.log('EVM (BNB / Polygon / Pingala / Avalanche C-Chain / MetaMask)');
    console.log(`  path       : ${result.evm.path}`);
    console.log(`  address    : ${result.evm.address}`);
    console.log(`  publicKey  : ${result.evm.publicKey}`);
    console.log(`  privateKey : ${result.evm.privateKey}`);

}

main().catch((e) => {
    console.error('Erreur :', e.message);
    process.exit(1);
});
