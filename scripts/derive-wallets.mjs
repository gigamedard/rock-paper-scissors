import { HDNodeWallet, wordlists, Mnemonic } from 'ethers';
import { wordlist as frenchWords } from '@scure/bip39/wordlists/french';

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
        join(words) { return words.join(' '); }
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
        json: args.includes('--json'),
    };
}

async function main() {
    const { index, lang, json } = parseArgs();
    if (!Number.isInteger(index) || index < 0) {
        throw new Error('INDEX invalide (entier >= 0 attendu).');
    }

    let phrase = (process.env.SEED_PHRASE || '').trim();
    if (!phrase && !process.stdin.isTTY) {
        phrase = await readStdin();
    }
    if (!phrase) {
        console.error('Usage : SEED_PHRASE="mot1 mot2 ..." node scripts/derive-wallets.mjs [--index 0] [--json]');
        console.error('Ou : node scripts/derive-wallets.mjs < seed.txt');
        console.error('Ne jamais coller une seed réelle dans un shell partagé/historisé.');
        process.exit(1);
    }

    phrase = phrase.replace(/\s+/g, ' ').trim();
    const passphrase = process.env.BIP39_PASSPHRASE || '';

    const evmPath = `m/44'/60'/0'/0/${index}`;
    const wordlist = lang === 'fr' ? makeFrenchWordlist(frenchWords) : wordlists.en;
    if (!wordlist) {
        console.error('Langue BIP39 non supportée (utilise --lang fr ou --lang en).');
        process.exit(1);
    }

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
