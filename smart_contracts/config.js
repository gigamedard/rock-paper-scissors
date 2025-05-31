export const INNER_SCRIPT_TOKEN="0x7c852118294e51e653712a81e05800f419141751be58f605c371e18990756086"
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
export const backendUrl = "127.0.0.1:8000";

export const privateKey3 = "0xac0974bec39a17e36ba4a6b4d238ff944bacb478cbed5efcae784d7bf4f2ff80";
export const contractAddress3 = "0x5FbDB2315678afecb367f032d93F642f64180aa3";
export const abi3 = [
  {
    "inputs": [],
    "stateMutability": "nonpayable",
    "type": "constructor"
  },
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
    "anonymous": false,
    "inputs": [
      {
        "indexed": true,
        "internalType": "uint256",
        "name": "poolId",
        "type": "uint256"
      },
      {
        "indexed": false,
        "internalType": "string",
        "name": "cid",
        "type": "string"
      }
    ],
    "name": "MatchHistoryCIDUpdated",
    "type": "event"
  },
  {
    "anonymous": false,
    "inputs": [
      {
        "indexed": true,
        "internalType": "address",
        "name": "wallet",
        "type": "address"
      },
      {
        "indexed": false,
        "internalType": "uint256",
        "name": "amount",
        "type": "uint256"
      }
    ],
    "name": "PayoutProcessed",
    "type": "event"
  },
  {
    "anonymous": false,
    "inputs": [
      {
        "indexed": true,
        "internalType": "uint256",
        "name": "poolId",
        "type": "uint256"
      },
      {
        "indexed": false,
        "internalType": "uint256",
        "name": "baseBet",
        "type": "uint256"
      },
      {
        "indexed": false,
        "internalType": "uint256",
        "name": "maxSize",
        "type": "uint256"
      }
    ],
    "name": "PoolCreated",
    "type": "event"
  },
  {
    "anonymous": false,
    "inputs": [
      {
        "indexed": true,
        "internalType": "uint256",
        "name": "poolId",
        "type": "uint256"
      },
      {
        "indexed": false,
        "internalType": "uint256",
        "name": "baseBet",
        "type": "uint256"
      },
      {
        "indexed": false,
        "internalType": "address[]",
        "name": "users",
        "type": "address[]"
      },
      {
        "indexed": false,
        "internalType": "string[]",
        "name": "premoveCIDs",
        "type": "string[]"
      },
      {
        "indexed": false,
        "internalType": "string",
        "name": "poolSalt",
        "type": "string"
      }
    ],
    "name": "PoolEmitted",
    "type": "event"
  },
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
        "internalType": "string",
        "name": "cid",
        "type": "string"
      }
    ],
    "name": "PremoveCIDUpdated",
    "type": "event"
  },
  {
    "anonymous": false,
    "inputs": [
      {
        "indexed": false,
        "internalType": "uint256",
        "name": "newCoefficient",
        "type": "uint256"
      }
    ],
    "name": "SecurityCoefficientUpdated",
    "type": "event"
  },
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
        "internalType": "string",
        "name": "cid",
        "type": "string"
      }
    ],
    "name": "sessionHistoryCIDUpdated",
    "type": "event"
  },
  {
    "stateMutability": "payable",
    "type": "fallback"
  },
  {
    "inputs": [
      {
        "internalType": "uint256",
        "name": "baseBet",
        "type": "uint256"
      },
      {
        "internalType": "address",
        "name": "user",
        "type": "address"
      }
    ],
    "name": "addSingleUserToPool",
    "outputs": [],
    "stateMutability": "nonpayable",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "uint256",
        "name": "baseBet",
        "type": "uint256"
      },
      {
        "internalType": "address[]",
        "name": "users",
        "type": "address[]"
      }
    ],
    "name": "addUsersToPool",
    "outputs": [],
    "stateMutability": "nonpayable",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "address[]",
        "name": "wallets",
        "type": "address[]"
      },
      {
        "internalType": "uint256[]",
        "name": "amounts",
        "type": "uint256[]"
      }
    ],
    "name": "batchPayOut",
    "outputs": [],
    "stateMutability": "nonpayable",
    "type": "function"
  },
  {
    "inputs": [],
    "name": "deposit",
    "outputs": [],
    "stateMutability": "payable",
    "type": "function"
  },
  {
    "inputs": [],
    "name": "getContractAddress",
    "outputs": [
      {
        "internalType": "address",
        "name": "",
        "type": "address"
      }
    ],
    "stateMutability": "view",
    "type": "function"
  },
  {
    "inputs": [],
    "name": "getContractBalance",
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
        "internalType": "uint256",
        "name": "poolId",
        "type": "uint256"
      }
    ],
    "name": "getMatchHistoryCID",
    "outputs": [
      {
        "internalType": "string",
        "name": "",
        "type": "string"
      }
    ],
    "stateMutability": "view",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "uint256",
        "name": "baseBet",
        "type": "uint256"
      }
    ],
    "name": "getPoolUsers",
    "outputs": [
      {
        "internalType": "address[]",
        "name": "",
        "type": "address[]"
      }
    ],
    "stateMutability": "view",
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
    "name": "getPremoveCID",
    "outputs": [
      {
        "internalType": "string",
        "name": "",
        "type": "string"
      }
    ],
    "stateMutability": "view",
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
    "name": "getSessionHistoryCIDs",
    "outputs": [
      {
        "internalType": "string[]",
        "name": "",
        "type": "string[]"
      }
    ],
    "stateMutability": "view",
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
    "name": "isUserInAnyPool",
    "outputs": [
      {
        "internalType": "bool",
        "name": "",
        "type": "bool"
      }
    ],
    "stateMutability": "view",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "uint256",
        "name": "poolId",
        "type": "uint256"
      },
      {
        "internalType": "address",
        "name": "user",
        "type": "address"
      }
    ],
    "name": "isUserInPool",
    "outputs": [
      {
        "internalType": "bool",
        "name": "",
        "type": "bool"
      }
    ],
    "stateMutability": "view",
    "type": "function"
  },
  {
    "inputs": [],
    "name": "nextPoolId",
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
    "name": "owner",
    "outputs": [
      {
        "internalType": "address",
        "name": "",
        "type": "address"
      }
    ],
    "stateMutability": "view",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "address payable",
        "name": "user",
        "type": "address"
      },
      {
        "internalType": "uint256",
        "name": "amount",
        "type": "uint256"
      }
    ],
    "name": "payOut",
    "outputs": [],
    "stateMutability": "nonpayable",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "uint256",
        "name": "",
        "type": "uint256"
      }
    ],
    "name": "poolHistoryCIDs",
    "outputs": [
      {
        "internalType": "string",
        "name": "",
        "type": "string"
      }
    ],
    "stateMutability": "view",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "uint256",
        "name": "",
        "type": "uint256"
      }
    ],
    "name": "pools",
    "outputs": [
      {
        "internalType": "uint256",
        "name": "poolId",
        "type": "uint256"
      },
      {
        "internalType": "uint256",
        "name": "baseBet",
        "type": "uint256"
      },
      {
        "internalType": "uint256",
        "name": "maxSize",
        "type": "uint256"
      },
      {
        "internalType": "string",
        "name": "poolSalt",
        "type": "string"
      }
    ],
    "stateMutability": "view",
    "type": "function"
  },
  {
    "inputs": [],
    "name": "securityCoefficient",
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
      },
      {
        "internalType": "uint256",
        "name": "",
        "type": "uint256"
      }
    ],
    "name": "sessionHistoryCIDs",
    "outputs": [
      {
        "internalType": "string",
        "name": "",
        "type": "string"
      }
    ],
    "stateMutability": "view",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "uint256",
        "name": "baseBet",
        "type": "uint256"
      },
      {
        "internalType": "uint256",
        "name": "newMaxSize",
        "type": "uint256"
      }
    ],
    "name": "setPoolMaxSize",
    "outputs": [],
    "stateMutability": "nonpayable",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "uint256",
        "name": "newCoefficient",
        "type": "uint256"
      }
    ],
    "name": "setSecurityCoefficient",
    "outputs": [],
    "stateMutability": "nonpayable",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "uint256",
        "name": "poolId",
        "type": "uint256"
      },
      {
        "internalType": "string",
        "name": "cid",
        "type": "string"
      }
    ],
    "name": "storeMatchHistoryCID",
    "outputs": [],
    "stateMutability": "nonpayable",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "address",
        "name": "user",
        "type": "address"
      },
      {
        "internalType": "string",
        "name": "cid",
        "type": "string"
      }
    ],
    "name": "storeSessionCID",
    "outputs": [],
    "stateMutability": "nonpayable",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "uint256",
        "name": "baseBet",
        "type": "uint256"
      },
      {
        "internalType": "string",
        "name": "cid",
        "type": "string"
      }
    ],
    "name": "submitPremoveCID",
    "outputs": [],
    "stateMutability": "payable",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "uint256",
        "name": "poolId",
        "type": "uint256"
      },
      {
        "internalType": "uint256",
        "name": "baseBet",
        "type": "uint256"
      },
      {
        "internalType": "address[]",
        "name": "users",
        "type": "address[]"
      },
      {
        "internalType": "string[]",
        "name": "premoveCIDs",
        "type": "string[]"
      },
      {
        "internalType": "string",
        "name": "poolSalt",
        "type": "string"
      }
    ],
    "name": "triggerPoolEmittedEventForTesting",
    "outputs": [],
    "stateMutability": "nonpayable",
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
  },
  {
    "inputs": [
      {
        "internalType": "address",
        "name": "",
        "type": "address"
      }
    ],
    "name": "userPremoveCIDs",
    "outputs": [
      {
        "internalType": "string",
        "name": "",
        "type": "string"
      }
    ],
    "stateMutability": "view",
    "type": "function"
  },
  {
    "stateMutability": "payable",
    "type": "receive"
  }
];

export const predefinedCID = [
  {
    moves: [ 'rock', 'rock', 'scissors', 'paper', 'scissors' ],
    cid: 'QmRyJHzTYixQDrcioezZdhXvLujJjDpx1HeaUEKovrfKor'
  },
  {
    moves: [ 'scissors', 'rock', 'scissors', 'scissors', 'paper' ],
    cid: 'QmP8Pa9QXpjX758coUsbgLzciNmBRg5iXeiPGTRuzugkzZ'
  },
  {
    moves: [ 'paper', 'rock', 'rock', 'paper', 'scissors' ],
    cid: 'QmUjeqamrH92S1T5HG443yTAY2gQjcBgMXfcm9pZcESfAi'
  },
  {
    moves: [ 'rock', 'scissors', 'paper', 'rock', 'paper' ],
    cid: 'QmV5YYie6BpEdFhM6APqDXNbx8YeTShtuxgKcCDeFgPxky'
  },
  {
    moves: [ 'scissors', 'paper', 'scissors', 'rock', 'paper' ],
    cid: 'QmWJw3XsfVJG1LfpPqDrFk4TbTZMFo2B3mUmPaaRHY6qxm'
  },
  {
    moves: [ 'rock', 'rock', 'paper', 'rock', 'rock' ],
    cid: 'QmXdyidQ5Xp5DhgUoGhqJrfTr86pFhFhomdmAV2TqMf9Mq'
  },
  {
    moves: [ 'scissors', 'paper', 'scissors', 'paper', 'rock' ],
    cid: 'QmQ95JEwG6Fbvdb8Lku2GKxfrduV6EKGJaqd4u7YF39SMz'
  },
  {
    moves: [ 'rock', 'rock', 'rock', 'scissors', 'scissors' ],
    cid: 'QmQcFmExLNoXiv5cjiXDKXYd1fjzgnnHGHwQmTSFpyES7W'
  },
  {
    moves: [ 'rock', 'paper', 'paper', 'scissors', 'paper' ],
    cid: 'QmeLRMbX6GpBf8jQfQ3LFwmM1qQjkokbuz1SZPEnj3cMcR'
  },
  {
    moves: [ 'scissors', 'paper', 'scissors', 'scissors', 'paper' ],
    cid: 'Qmb6KjmPnVN8AdcesRGk3yPXtg4x757a5Z3ftYoxLxoY5M'
  },
  {
    moves: [ 'scissors', 'scissors', 'rock', 'rock', 'paper' ],
    cid: 'QmVy2332CcxUr3V37i7dpwfDTavoezkjYbC6y5Few9vY5Y'
  },
  {
    moves: [ 'paper', 'rock', 'paper', 'rock', 'rock' ],
    cid: 'QmVDHnNvw5aqK7FqMATMKSJjB2H7sDqzDA6f1AD38NYjYx'
  },
  {
    moves: [ 'paper', 'scissors', 'paper', 'scissors', 'paper' ],
    cid: 'QmQPwybFbn8xe44fB2o6azpXG6SnuovLTV9kxXXaTZsJuK'
  },
  {
    moves: [ 'paper', 'rock', 'scissors', 'paper', 'paper' ],
    cid: 'QmR3MhxMDuwXHcsoMQt4gX7na2SPng5faSvPvFZHcWiVEu'
  },
  {
    moves: [ 'rock', 'paper', 'scissors', 'paper', 'rock' ],
    cid: 'Qme98TcekcTETmyWJ7ErwxKSPDFCRaqvKFMnpBAgMHHQeD'
  },
  {
    moves: [ 'scissors', 'rock', 'scissors', 'scissors', 'scissors' ],
    cid: 'QmS7C9GgcevtGr3y6PGvrF653XqzG2A9JiWeQiNEwJfEyY'
  },
  {
    moves: [ 'paper', 'paper', 'rock', 'scissors', 'scissors' ],
    cid: 'QmNqdHNuW75FDYk3VE1krN9x5A75KGYNyaQQfK2GUwbPjv'
  },
  {
    moves: [ 'paper', 'paper', 'paper', 'paper', 'paper' ],
    cid: 'QmcTkTz6pJvmJoW9AwHJDZMfrZP3UMsPzU6urABACPytGn'
  },
  {
    moves: [ 'scissors', 'rock', 'scissors', 'paper', 'rock' ],
    cid: 'QmPR6FKY9DyRyV9U1VgvH2J8ZgpFwteWGAbuBdUZ6rDqxi'
  },
  {
    moves: [ 'paper', 'rock', 'scissors', 'scissors', 'paper' ],
    cid: 'QmchG5oVmMCvxr3ociZ7F7jPUwEAW2ngMTHNZwHjjk9NFt'
  },
  {
    moves: [ 'paper', 'rock', 'rock', 'rock', 'scissors' ],
    cid: 'QmbkCLWTpMD5daz2ZyBjSYooHADdqZ38So8RyJrevvS1y6'
  },
  {
    moves: [ 'rock', 'rock', 'rock', 'scissors', 'rock' ],
    cid: 'QmdebPqYrN77nPP4rGGtTFzF55JxthzpiY7SZrttWX2Eyu'
  },
  {
    moves: [ 'scissors', 'paper', 'scissors', 'scissors', 'scissors' ],
    cid: 'Qmabs87oddPDjvZZ3ww742ZaFzToCCbw2zLbhA9fvsGHHk'
  },
  {
    moves: [ 'rock', 'scissors', 'scissors', 'rock', 'paper' ],
    cid: 'QmdNqPjJHjjE7qQLZRhz9EiEkdr8tK9nVZ9njMtETqL2sm'
  },
  {
    moves: [ 'paper', 'scissors', 'rock', 'paper', 'paper' ],
    cid: 'QmXHBWgVPyR9K4BGdFZwbsHrdHRroH2swxGy3NuUAN3Q1r'
  },
  {
    moves: [ 'paper', 'rock', 'scissors', 'rock', 'rock' ],
    cid: 'Qmf2hnxwGyNZW2wvivb75biMDKx5DvmC4dPZZFKu1LYFNN'
  },
  {
    moves: [ 'rock', 'rock', 'scissors', 'scissors', 'paper' ],
    cid: 'QmNboL5W9rbeUDWB5qSgqK5uNcddf63GM7TcoyjGVdxrBg'
  },
  {
    moves: [ 'scissors', 'scissors', 'rock', 'paper', 'paper' ],
    cid: 'QmRiyduiMrvZh98R3ZtozLVTzphrN1f8WuDytg6TD7e8VJ'
  },
  {
    moves: [ 'rock', 'paper', 'scissors', 'paper', 'rock' ],
    cid: 'Qme98TcekcTETmyWJ7ErwxKSPDFCRaqvKFMnpBAgMHHQeD'
  },
  {
    moves: [ 'paper', 'scissors', 'rock', 'rock', 'paper' ],
    cid: 'QmPAoAypL9A4n8kX4RyshvU4GEXg4Jk2EE19YXa7nwap2D'
  },
  {
    moves: [ 'scissors', 'rock', 'paper', 'scissors', 'scissors' ],
    cid: 'QmcBcDqm6aCG6fuHpau5WREUWNXaUCSdEXKnrMxxVdJZia'
  },
  {
    moves: [ 'scissors', 'rock', 'rock', 'scissors', 'scissors' ],
    cid: 'QmdfybKvmoMEY95vs3LKTEqFofQYxMbwc8QFQGDda2J3qL'
  },
  {
    moves: [ 'paper', 'rock', 'scissors', 'scissors', 'paper' ],
    cid: 'QmchG5oVmMCvxr3ociZ7F7jPUwEAW2ngMTHNZwHjjk9NFt'
  },
  {
    moves: [ 'scissors', 'rock', 'paper', 'scissors', 'paper' ],
    cid: 'QmUe6haSZ1SAZ5Kj3xyUKvPiKouuJp6jVSp7SqUoCjUbCY'
  },
  {
    moves: [ 'scissors', 'rock', 'rock', 'scissors', 'rock' ],
    cid: 'QmfPQTg2MGEfke1RwzRzqwvw5LKJiJqxTvX158fNQ9oZcT'
  },
  {
    moves: [ 'rock', 'scissors', 'paper', 'paper', 'paper' ],
    cid: 'QmVovXTjkwHkFCp4DY5Ukqw4BgY3Fdbd75xqEhiGvp5Kc3'
  },
  {
    moves: [ 'paper', 'scissors', 'rock', 'rock', 'paper' ],
    cid: 'QmPAoAypL9A4n8kX4RyshvU4GEXg4Jk2EE19YXa7nwap2D'
  },
  {
    moves: [ 'rock', 'rock', 'rock', 'rock', 'rock' ],
    cid: 'QmcoqRBjxrE9h4sYY1pKDcX3Q2tgeuA3XwME1b3FUPFc9R'
  },
  {
    moves: [ 'rock', 'paper', 'rock', 'paper', 'paper' ],
    cid: 'QmexZDZD5Aey5MAUB966t2HjzNGqvhJy9KWQGfFL19x8hd'
  },
  {
    moves: [ 'rock', 'scissors', 'rock', 'paper', 'paper' ],
    cid: 'QmNyVkfeqyqj1xEHuUBPYzzAjRkiBJHZ1XgF6yoUiYKo81'
  },
  {
    moves: [ 'paper', 'paper', 'paper', 'paper', 'rock' ],
    cid: 'QmUM7bV4n2RedGzN1Atdo38bWzfSwuCNexd2wJ6KWfWHZt'
  },
  {
    moves: [ 'rock', 'scissors', 'paper', 'paper', 'paper' ],
    cid: 'QmVovXTjkwHkFCp4DY5Ukqw4BgY3Fdbd75xqEhiGvp5Kc3'
  },
  {
    moves: [ 'paper', 'scissors', 'scissors', 'rock', 'paper' ],
    cid: 'QmQfQKGkoqMjuyvgrqQhoPkQrcoKYLeYJx6i27F8a6k2ax'
  },
  {
    moves: [ 'rock', 'rock', 'paper', 'rock', 'rock' ],
    cid: 'QmXdyidQ5Xp5DhgUoGhqJrfTr86pFhFhomdmAV2TqMf9Mq'
  },
  {
    moves: [ 'paper', 'rock', 'scissors', 'paper', 'paper' ],
    cid: 'QmR3MhxMDuwXHcsoMQt4gX7na2SPng5faSvPvFZHcWiVEu'
  },
  {
    moves: [ 'paper', 'paper', 'paper', 'paper', 'rock' ],
    cid: 'QmUM7bV4n2RedGzN1Atdo38bWzfSwuCNexd2wJ6KWfWHZt'
  },
  {
    moves: [ 'rock', 'paper', 'scissors', 'rock', 'rock' ],
    cid: 'QmeDJnWnGBifuPU6W1o7WSi6D862zUE7bJdkEGYTHCggV5'
  },
  {
    moves: [ 'scissors', 'paper', 'paper', 'scissors', 'rock' ],
    cid: 'QmVU98j6zjiDhShyxP8cCYQxvSegJaqcFP4YrWYTBj9MHn'
  },
  {
    moves: [ 'paper', 'scissors', 'rock', 'scissors', 'paper' ],
    cid: 'QmeEhtFLkeo2Acz3zmsEkUFae1NtqdoB1gaKSdKzEhCmkm'
  },
  {
    moves: [ 'scissors', 'paper', 'rock', 'rock', 'paper' ],
    cid: 'QmcJU68rLgGn6dH68dWVRm8AZJ8pY6bwGC126TB86bzaW7'
  },
  {
    moves: [ 'scissors', 'rock', 'scissors', 'paper', 'rock' ],
    cid: 'QmPR6FKY9DyRyV9U1VgvH2J8ZgpFwteWGAbuBdUZ6rDqxi'
  },
  {
    moves: [ 'paper', 'rock', 'scissors', 'scissors', 'rock' ],
    cid: 'QmbexVcwe5iRwzwRdFE9xn4Kbi7qrUzNvV81Rugthi3zqd'
  },
  {
    moves: [ 'paper', 'scissors', 'paper', 'paper', 'scissors' ],
    cid: 'QmU3cqoq5vtaFstFj3Xg1832Hu5JkYAija4o2WJXdHVnqb'
  },
  {
    moves: [ 'paper', 'scissors', 'scissors', 'rock', 'scissors' ],
    cid: 'QmTTgEEf3dZsnDcjpJEdYu8Xp9ysvMqfuLVNJ84vWYgbZY'
  },
  {
    moves: [ 'scissors', 'rock', 'scissors', 'scissors', 'scissors' ],
    cid: 'QmS7C9GgcevtGr3y6PGvrF653XqzG2A9JiWeQiNEwJfEyY'
  },
  {
    moves: [ 'paper', 'paper', 'rock', 'paper', 'scissors' ],
    cid: 'QmTokkyJXrgmfQVDfKq38RvTauLqiK4uTYsM3GmVpm3tNK'
  },
  {
    moves: [ 'scissors', 'rock', 'paper', 'rock', 'scissors' ],
    cid: 'Qmc9zHVbZLYyNHqkDYe6VA49iinpC26zT1NnHs63RKjsaV'
  },
  {
    moves: [ 'paper', 'rock', 'paper', 'rock', 'scissors' ],
    cid: 'QmbuXBKTTHgA3AFTSvFpfxqzz8greJ8aMUkwk5UNFRLbYn'
  },
  {
    moves: [ 'rock', 'rock', 'paper', 'rock', 'rock' ],
    cid: 'QmXdyidQ5Xp5DhgUoGhqJrfTr86pFhFhomdmAV2TqMf9Mq'
  },
  {
    moves: [ 'paper', 'rock', 'paper', 'paper', 'paper' ],
    cid: 'QmR3MhxMDuwXHcsoMQt4gX7na2SPng5faSvPvFZHcWiVEu'
  },
  {
    moves: [ 'paper', 'paper', 'paper', 'paper', 'rock' ],
    cid: 'QmUM7bV4n2RedGzN1Atdo38bWzfSwuCNexd2wJ6KWfWHZt'
  },
  {
    moves: [ 'rock', 'scissors', 'paper', 'rock', 'rock' ],
    cid: 'QmeDJnWnGBifuPU6W1o7WSi6D862zUE7bJdkEGYTHCggV5'
  },
  {
    moves: [ 'paper', 'scissors', 'paper', 'rock', 'rock' ],
    cid: 'QmdQkSmotvKK2G2PnQfTmh1WG5PXN4W1rwF5k9Jqh5hHZv'
  },
  {
    moves: [ 'scissors', 'rock', 'scissors', 'paper', 'paper' ],
    cid: 'QmWSaXyMToHXxTjUp7WfHsBwUs3WhVCQFxiUpXKwywAG94'
  },
  {
    moves: [ 'rock', 'scissors', 'scissors', 'scissors', 'paper' ],
    cid: 'QmdSTqbxZv7KT7r5wy3LChXUPX2oWPgW4bZB5zUGv717gT'
  },
  {
    moves: [ 'paper', 'rock', 'scissors', 'rock', 'rock' ],
    cid: 'Qmf2hnxwGyNZW2wvivb75biMDKx5DvmC4dPZZFKu1LYFNN'
  },
  {
    moves: [ 'paper', 'paper', 'paper', 'scissors', 'rock' ],
    cid: 'QmR5BVSRRtcxvy2dypDi8GkTzm8hUrTBnA8Gw3PmgQFo1C'
  },
  {
    moves: [ 'rock', 'paper', 'rock', 'scissors', 'rock' ],
    cid: 'Qmb6RXfmu7kQUxrYZZrPpATbqe2TUqcnBcVLAiHTt5LUjH'
  },
  {
    moves: [ 'scissors', 'paper', 'paper', 'scissors', 'rock' ],
    cid: 'QmVU98j6zjiDhShyxP8cCYQxvSegJaqcFP4YrWYTBj9MHn'
  },
  {
    moves: [ 'paper', 'scissors', 'rock', 'scissors', 'paper' ],
    cid: 'QmeEhtFLkeo2Acz3zmsEkUFae1NtqdoB1gaKSdKzEhCmkm'
  },
  {
    moves: [ 'scissors', 'paper', 'rock', 'rock', 'paper' ],
    cid: 'QmcJU68rLgGn6dH68dWVRm8AZJ8pY6bwGC126TB86bzaW7'
  },
  {
    moves: [ 'scissors', 'rock', 'scissors', 'paper', 'rock' ],
    cid: 'QmPR6FKY9DyRyV9U1VgvH2J8ZgpFwteWGAbuBdUZ6rDqxi'
  },
  {
    moves: [ 'paper', 'rock', 'scissors', 'scissors', 'rock' ],
    cid: 'QmbexVcwe5iRwzwRdFE9xn4Kbi7qrUzNvV81Rugthi3zqd'
  },
  {
    moves: [ 'paper', 'scissors', 'paper', 'paper', 'scissors' ],
    cid: 'QmU3cqoq5vtaFstFj3Xg1832Hu5JkYAija4o2WJXdHVnqb'
  },
  {
    moves: [ 'paper', 'scissors', 'scissors', 'rock', 'scissors' ],
    cid: 'QmTTgEEf3dZsnDcjpJEdYu8Xp9ysvMqfuLVNJ84vWYgbZY'
  },
  {
    moves: [ 'scissors', 'rock', 'scissors', 'scissors', 'scissors' ],
    cid: 'QmS7C9GgcevtGr3y6PGvrF653XqzG2A9JiWeQiNEwJfEyY'
  },
  {
    moves: [ 'paper', 'paper', 'rock', 'paper', 'scissors' ],
    cid: 'QmTokkyJXrgmfQVDfKq38RvTauLqiK4uTYsM3GmVpm3tNK'
  },
  {
    moves: [ 'scissors', 'rock', 'paper', 'rock', 'scissors' ],
    cid: 'Qmc9zHVbZLYyNHqkDYe6VA49iinpC26zT1NnHs63RKjsaV'
  },
  {
    moves: [ 'paper', 'rock', 'paper', 'rock', 'scissors' ],
    cid: 'QmbuXBKTTHgA3AFTSvFpfxqzz8greJ8aMUkwk5UNFRLbYn'
  },
  {
    moves: [ 'rock', 'rock', 'paper', 'rock', 'rock' ],
    cid: 'QmXdyidQ5Xp5DhgUoGhqJrfTr86pFhFhomdmAV2TqMf9Mq'
  },
  {
    moves: [ 'paper', 'rock', 'paper', 'paper', 'paper' ],
    cid: 'QmR3MhxMDuwXHcsoMQt4gX7na2SPng5faSvPvFZHcWiVEu'
  },
  {
    moves: [ 'paper', 'paper', 'paper', 'paper', 'rock' ],
    cid: 'QmUM7bV4n2RedGzN1Atdo38bWzfSwuCNexd2wJ6KWfWHZt'
  },
  {
    moves: [ 'rock', 'scissors', 'paper', 'rock', 'rock' ],
    cid: 'QmeDJnWnGBifuPU6W1o7WSi6D862zUE7bJdkEGYTHCggV5'
  },
  {
    moves: [ 'paper', 'scissors', 'paper', 'rock', 'rock' ],
    cid: 'QmdQkSmotvKK2G2PnQfTmh1WG5PXN4W1rwF5k9Jqh5hHZv'
  },
  {
    moves: [ 'scissors', 'rock', 'scissors', 'paper', 'paper' ],
    cid: 'QmWSaXyMToHXxTjUp7WfHsBwUs3WhVCQFxiUpXKwywAG94'
  },
  {
    moves: [ 'rock', 'scissors', 'scissors', 'scissors', 'paper' ],
    cid: 'QmdSTqbxZv7KT7r5wy3LChXUPX2oWPgW4bZB5zUGv717gT'
  },
  {
    moves: [ 'paper', 'rock', 'scissors', 'rock', 'rock' ],
    cid: 'Qmf2hnxwGyNZW2wvivb75biMDKx5DvmC4dPZZFKu1LYFNN'
  },
  {
    moves: [ 'paper', 'paper', 'paper', 'scissors', 'rock' ],
    cid: 'QmR5BVSRRtcxvy2dypDi8GkTzm8hUrTBnA8Gw3PmgQFo1C'
  },
  {
    moves: [ 'rock', 'paper', 'rock', 'scissors', 'rock' ],
    cid: 'Qmb6RXfmu7kQUxrYZZrPpATbqe2TUqcnBcVLAiHTt5LUjH'
  },
  {
    moves: [ 'scissors', 'paper', 'paper', 'scissors', 'rock' ],
    cid: 'QmVU98j6zjiDhShyxP8cCYQxvSegJaqcFP4YrWYTBj9MHn'
  },
  {
    moves: [ 'paper', 'scissors', 'rock', 'scissors', 'paper' ],
    cid: 'QmeEhtFLkeo2Acz3zmsEkUFae1NtqdoB1gaKSdKzEhCmkm'
  }
];
