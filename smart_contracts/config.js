export const privateKey = "29f13333db1a6b87a39c26d0986a74306aa970aff4a26657ba2a782525d65867";
export const alchemyUrl = "https://eth-sepolia.g.alchemy.com/v2/qGUwxK2NtwoK8xHN-qsQ7KJL5Bz9RBbo";
export const contractAddress = "0x5317e9C9409d40c2213aCfBdfD88214DebB988B6";
export const ContractName = "InteractingSC3";
export const contractName = "InteractingSC3";
export const abi = [
    {
        "inputs": [],
        "stateMutability": "nonpayable",
        "type": "constructor"
    },
    {
        "anonymous": false,
        "inputs": [
            {
                "indexed": false,
                "internalType": "uint256",
                "name": "newCounter",
                "type": "uint256"
            }
        ],
        "name": "CounterUpdated",
        "type": "event"
    },
    {
        "inputs": [],
        "name": "counter",
        "outputs": [
            {
                "internalType": "uint256",
                "name": "",
                "type": "uint256"
            }
        ],
        "stateMutability": "view",
        "type": "function"
    },
    {
        "inputs": [],
        "name": "minus1",
        "outputs": [],
        "stateMutability": "nonpayable",
        "type": "function"
    },
    {
        "inputs": [],
        "name": "plus1",
        "outputs": [],
        "stateMutability": "nonpayable",
        "type": "function"
    },
    {
        "inputs": [],
        "name": "reset",
        "outputs": [],
        "stateMutability": "nonpayable",
        "type": "function"
    }
];