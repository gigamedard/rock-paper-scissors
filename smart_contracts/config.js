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

export const privateKey2 = "0xac0974bec39a17e36ba4a6b4d238ff944bacb478cbed5efcae784d7bf4f2ff80";
export const localHardhatUrl = "http://127.0.0.1:8545";
export const contractAddress2 = "0x5fbdb2315678afecb367f032d93f642f64180aa3";

export const privateKey3 = "0x7c852118294e51e653712a81e05800f419141751be58f605c371e15141b007a6";
export const contractAddress3 = "0x5FbDB2315678afecb367f032d93F642f64180aa3";
export const abi3 = [
    {
      "anonymous": false,
      "inputs": [
        {
          "indexed": true,
          "internalType": "address",
          "name": "user",
          "type": "address"
        },
        {
          "indexed": false,
          "internalType": "uint256",
          "name": "amount",
          "type": "uint256"
        }
      ],
      "name": "DepositReceived",
      "type": "event"
    },
    {
      "inputs": [],
      "name": "deposit",
      "outputs": [],
      "stateMutability": "payable",
      "type": "function"
    },
    {
      "inputs": [
        {
          "internalType": "address",
          "name": "user",
          "type": "address"
        }
      ],
      "name": "getUserBalance",
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
      "inputs": [
        {
          "internalType": "address",
          "name": "",
          "type": "address"
        }
      ],
      "name": "userBalances",
      "outputs": [
        {
          "internalType": "uint256",
          "name": "",
          "type": "uint256"
        }
      ],
      "stateMutability": "view",
      "type": "function"
    }
  ];