<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <title>Rock Paper Scissors Game</title>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/ethereumjs-util/7.1.5/index.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.2.0/crypto-js.min.js"></script>
  <script src="https://unpkg.com/lucide@0.344.0"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/web3/1.8.0/web3.min.js"></script>
  <script src="https://bundle.run/secp256k1@4.0.3"></script>

  @vite(['resources/css/app.css', 'resources/js/app.js','resources/js/echo.js'])


</head>
<body>
<body>
    <h1>Interact with Ethereum Smart Contract</h1>
    <button id="connectButton">Connect to MetaMask</button>


    <h2>Smart Contract Interaction</h2>
    <button id="getNextPoolIdButton">Get Next Pool ID</button>
    <h2>update user Balance</h2>
    <button id="updateUserBalanceButton">... </button>

    <script>
        let web3;
        let contract;
        let userAccount;
        const contractABI = [
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
                        "internalType": "uint256",
                        "name": "baseBet",
                        "type": "uint256"
                    },
                    {
                        "internalType": "uint256",
                        "name": "maxSize",
                        "type": "uint256"
                    }
                ],
                "name": "createPool",
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
                        "name": "user",
                        "type": "address"
                    },
                    {
                        "internalType": "uint256",
                        "name": "newBalance",
                        "type": "uint256"
                    }
                ],
                "name": "updateUserBalance",
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
        const contractAddress = '0x2279b7a0a67db372996a5fab50d91eaa73d2ebe6';  // Replace with your contract address

        // Check if MetaMask is installed
        if (typeof window.ethereum !== 'undefined') {
            web3 = new Web3(window.ethereum);
            console.log('MetaMask is installed!');
        } else {
            alert('MetaMask is not installed. Please install it and try again.');
        }

        // Request connection to MetaMask
        document.getElementById('connectButton').onclick = async () => {
            try {
                // Request account access
                const accounts = await window.ethereum.request({ method: 'eth_requestAccounts' });
                userAccount = accounts[0];
                console.log('Connected account:', userAccount);
            } catch (error) {
                console.error('User denied account access:', error);
            }
        };

        // Create contract instance
        const createContractInstance = () => {
          console.log('intentiation of a contract');
            contract = new web3.eth.Contract(contractABI, contractAddress);
            
        };

        // Interact with the smart contract (Example: Get Next Pool ID)
        document.getElementById('getNextPoolIdButton').onclick = async () => {
          createContractInstance();
            if (contract) {
                try {
                    const nextPoolId = await contract.methods.getUserBalance(userAccount).call();
                    alert('Next Pool ID: ' + nextPoolId);
                } catch (error) {
                    console.error('Error fetching data from the contract:', error);
                }
            } else {
                alert('Contract instance not created. Please connect to MetaMask first.');
            }
        };


        document.getElementById('updateUserBalanceButton').onclick = async () => {
        createContractInstance();
        if (contract && userAccount) {
            try {
                const newBalance = prompt("Enter the new balance (in Wei):");

                if (!newBalance || isNaN(newBalance)) {
                    alert("Invalid balance amount entered!");
                    return;
                }
                console.log("Sending transaction with params:");
                console.log("Account:", userAccount);
                console.log("New Balance (Wei):", newBalance);

                const tx = await contract.methods.updateUserBalance(userAccount, newBalance).send({
                    from: userAccount,
                    gas: 2000000,
                });

                console.log('Transaction successful:', tx);
                alert(`User balance updated! Transaction hash: ${tx.transactionHash}`);
            } catch (error) {
                console.error('Error sending transaction:', error);
                alert('Failed to update user balance. Check the console for details.');
            }
        } else {
            alert('Please connect to MetaMask and try again.');
        }
    };











    </script>
</body>
</body>
</html>