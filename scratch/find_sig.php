<?php
require 'vendor/autoload.php';
use kornrunner\Keccak;
use Elliptic\EC;

$pk = 'ac0974bec39a17e36ba4a6b4d238ff944bacb478cbed5efcae784d7bf4f2ff80';
$target = '0x7a21b016c26bd1bba97d4748bc0270849e4140b2e248bc2feb7c4077481ba2df7edcc94145cac71cf6de0a27e2113cc47163809b1d0b3eb42a534f7018edc3951c';

function encode($items) {
    $r='';
    foreach($items as $i) {
        if($i[0]=='address'){
            $clean=str_starts_with($i[1],'0x')?substr($i[1],2):$i[1];
            $r.=str_pad(strtolower($clean),40,'0',STR_PAD_LEFT);
        } else {
            $r.=str_pad(gmp_strval(gmp_init($i[1]),16),64,'0',STR_PAD_LEFT);
        }
    }
    return $r;
}

$found = false;
// Check different nonces
for($n=0; $n<=10; $n++){
    $data = encode([
        ['address','0xfe0f143fcad5b561b1ed2ac960278a2f23559ef9'],
        ['uint256','50000000000000000000'],
        ['uint256',$n],
        ['address','0x5FbDB2315678afecb367f032d93F642f64180aa3']
    ]);
    
    $hash=Keccak::hash("\x19Ethereum Signed Message:\n32".hex2bin(Keccak::hash(hex2bin($data),256)),256);
    $ec=new EC('secp256k1');
    $sig=$ec->keyFromPrivate($pk)->sign($hash, ['canonical'=>true]);
    $v='0x'.str_pad($sig->r->toString(16),64,'0',STR_PAD_LEFT).str_pad($sig->s->toString(16),64,'0',STR_PAD_LEFT).dechex($sig->recoveryParam+27);
    
    if($v === $target) {
        echo 'Found nonce: '.$n.PHP_EOL;
        $found = true;
    }
}

// Check with amount 50.1000 ETH
$data = encode([
    ['address','0xfe0f143fcad5b561b1ed2ac960278a2f23559ef9'],
    ['uint256','50100000000000000000'],
    ['uint256',0],
    ['address','0x5FbDB2315678afecb367f032d93F642f64180aa3']
]);
$hash=Keccak::hash("\x19Ethereum Signed Message:\n32".hex2bin(Keccak::hash(hex2bin($data),256)),256);
$ec=new EC('secp256k1');
$sig=$ec->keyFromPrivate($pk)->sign($hash, ['canonical'=>true]);
$v='0x'.str_pad($sig->r->toString(16),64,'0',STR_PAD_LEFT).str_pad($sig->s->toString(16),64,'0',STR_PAD_LEFT).dechex($sig->recoveryParam+27);
if($v === $target) {
    echo 'Found with amount 50.1!'.PHP_EOL;
    $found = true;
}

if (!$found) echo 'Not found in checks.';
