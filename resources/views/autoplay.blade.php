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

  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

    :root {
      color-scheme: dark;
      --color-bg: #0a0a0a;
      --color-text: #ffffff;
      --color-primary: #6366f1;
      --color-primary-hover: #4f46e5;
      --color-secondary: #a855f7;
      --color-accent: #ec4899;
      --color-success: #22c55e;
      --color-error: #ef4444;
      --color-gray-50: #f9fafb;
      --color-gray-100: #f3f4f6;
      --color-gray-700: #374151;
      --color-gray-800: #1f2937;
      --color-gray-900: #111827;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Inter', sans-serif;
      background: var(--color-bg);
      color: var(--color-text);
      min-height: 100vh;
    }

    .bg-gradient {
      background: linear-gradient(to bottom right, 
        var(--color-gray-900), 
        var(--color-secondary), 
        var(--color-gray-900)
      );
      padding: 2rem;
      min-height: 100vh;
    }

    .container {
      max-width: 64rem;
      margin: 0 auto;
    }

    .title {
      font-size: 2.5rem;
      font-weight: 700;
      text-align: center;
      margin-bottom: 2rem;
      background: linear-gradient(to right, var(--color-primary), var(--color-secondary));
      -webkit-background-clip: text;
      color: transparent;
    }

    .game-grid {
      display: grid;
      grid-template-columns: 2fr 1fr;
      gap: 2rem;
    }

    @media (max-width: 768px) {
      .game-grid {
        grid-template-columns: 1fr;
      }
    }

    .game-controls {
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(8px);
      border-radius: 1rem;
      padding: 1.5rem;
    }

    .move-selector {
      display: flex;
      flex-direction: column;
      gap: 1.5rem;
    }

    .move-buttons {
      display: flex;
      gap: 1rem;
      justify-content: center;
    }

    .move-btn {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      padding: 1rem;
      border: none;
      border-radius: 0.5rem;
      background: var(--color-gray-800);
      color: var(--color-text);
      font-weight: 500;
      cursor: pointer;
      transition: all 0.2s;
    }

    .move-btn:hover:not(:disabled) {
      background: var(--color-gray-700);
      transform: translateY(-2px);
    }

    .move-btn:disabled {
      opacity: 0.5;
      cursor: not-allowed;
      transform: none;
    }

    .selected-moves {
        background: var(--color-gray-800);
        border-radius: 0.5rem;
        padding: 1rem;
        height: 200px; /* Fixed height */
        overflow-y: auto; /* Enable scrolling */
    }

    .moves-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1rem;
    }

    .moves-list {
        display: flex;
        flex-direction: column; /* Stack moves vertically */
        gap: 0.5rem; /* Space between moves */
    }

    /* Move Chip Container */
    .move-chip {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem; /* Reduced padding */
    background: var(--color-gray-700); /* Background color */
    border-radius: 2rem; /* Rounded corners */
    width: fit-content; /* Auto-size based on content */
    max-width: 90%; /* Prevent being too wide */
    }

    /* Move Index Styling */
    .move-index {
    font-weight: bold;
    color: var(--color-primary); /* Highlight the index */
    }

    /* Remove Button */
    .move-chip button {
    border: none;
    background: none;
    color: var(--color-text);
    cursor: pointer;
    opacity: 0.8;
    transition: opacity 0.2s;
    padding: 0.25rem;
    }

    .move-chip button:hover {
    opacity: 1;
    }


    .empty-moves {
      color: var(--color-gray-400);
      font-size: 0.875rem;
    }

    .bet-selector {
      background: var(--color-gray-800);
      border-radius: 0.5rem;
      padding: 1rem;
    }

    .bet-header {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      margin-bottom: 1rem;
    }

    .bet-buttons {
      display: flex;
      gap: 0.5rem;
    }

    .bet-btn {
      flex: 1;
      padding: 0.5rem;
      border: none;
      border-radius: 0.5rem;
      background: var(--color-gray-700);
      color: var(--color-text);
      font-weight: 500;
      cursor: pointer;
      transition: all 0.2s;
    }

    .bet-btn.active {
      background: var(--color-primary);
    }

    .bet-btn:hover:not(.active) {
      background: var(--color-gray-600);
    }

    .submit-btn {
      width: 100%;
      padding: 1rem;
      border: none;
      border-radius: 0.5rem;
      background: linear-gradient(to right, var(--color-primary), var(--color-secondary));
      color: var(--color-text);
      font-weight: 600;
      cursor: pointer;
      transition: opacity 0.2s;
    }

    .submit-btn:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }

    .game-stats {
      display: flex;
      flex-direction: column;
      gap: 1.5rem;
    }

    .balance {
      display: flex;
      align-items: center;
      gap: 1rem;
      padding: 1rem;
      background: linear-gradient(to right, var(--color-success), var(--color-primary));
      border-radius: 0.5rem;
    }

    .balance-label {
      font-size: 0.875rem;
      opacity: 0.8;
    }

    .balance-amount {
      font-size: 1.25rem;
      font-weight: 600;
    }

    .history {
      background: var(--color-gray-800);
      border-radius: 0.5rem;
      padding: 1rem;
    }

    .history-header {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      margin-bottom: 1rem;
    }

    .history-list {
      display: flex;
      flex-direction: column;
      gap: 0.5rem;
      max-height: 300px;
      overflow-y: auto;
    }

    .history-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 0.75rem;
      background: var(--color-gray-700);
      border-radius: 0.5rem;
    }

    .history-result.win {
      color: var(--color-success);
    }

    .history-result.loss {
      color: var(--color-error);
    }

    .modal {
      position: fixed;
      inset: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 50;
    }

    .modal.hidden {
      display: none;
    }

    .modal-overlay {
      position: absolute;
      inset: 0;
      background: rgba(0, 0, 0, 0.5);
      backdrop-filter: blur(4px);
    }

    .modal-content {
      position: relative;
      width: 90%;
      max-width: 28rem;
      background: var(--color-gray-800);
      border-radius: 1rem;
      padding: 1.5rem;
    }

    .modal-header {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      margin-bottom: 1rem;
    }

    .modal-actions {
      display: flex;
      justify-content: flex-end;
      gap: 0.5rem;
      margin-top: 1.5rem;
    }

    .cancel-btn, .confirm-btn {
      padding: 0.5rem 1rem;
      border: none;
      border-radius: 0.5rem;
      font-weight: 500;
      cursor: pointer;
    }

    .cancel-btn {
      background: var(--color-gray-700);
      color: var(--color-text);
    }

    .confirm-btn {
      background: var(--color-primary);
      color: var(--color-text);
    }
  </style>
</head>
<body>
    <!-- Inject the User ID into a Hidden Input -->
  <input type="hidden" id="user-id" value="{{ auth()->user()->id }}">
  <div class="bg-gradient">
    <div class="container">
      <h1 class="title">Rock Paper Scissors</h1>

      <div class="game-grid">
        <div class="game-controls">
          <div class="move-selector">
            <div class="move-buttons">
              <button class="move-btn" data-move="rock">
                <i data-lucide="circle"></i>
                Rock
              </button>
              <button class="move-btn" data-move="paper">
                <i data-lucide="file"></i>
                Paper
              </button>
              <button class="move-btn" data-move="scissors">
                <i data-lucide="scissors"></i>
                Scissors
              </button>
            </div>

            <div class="selected-moves">
              <div class="moves-header">
                <h3>Selected Moves</h3>
                <span class="moves-count">0/100</span>
              </div>
              <div id="moves-list" class="moves-list">
                <p class="empty-moves">No moves selected</p>
              </div>
            </div>

            <div class="bet-selector">
              <div class="bet-header">
                <i data-lucide="coins"></i>
                <h3>Bet Amount</h3>
              </div>
              <div class="bet-buttons">
                <button class="bet-btn active" data-bet="0.01">0.01</button>
                <button class="bet-btn" data-bet="0.02">0.02</button>
                <button class="bet-btn" data-bet="0.05">0.05</button>
                <button class="bet-btn" data-bet="0.10">0.10</button>
              </div>
            </div>

            <button id="submit-btn" class="submit-btn" disabled>
              ENTER AUTO PLAY MODE
            </button>
          </div>
        </div>

        <div class="game-stats">
          <div class="balance">
            <i data-lucide="wallet"></i>
            <div>
              <p class="balance-label">Balance</p>
              <p class="balance-amount">1000 credits</p>
            </div>
          </div>

          <div class="balance"onclick="loginWithWallet()">
            <i data-lucide="wallet"></i>
            <div>
              <p class="balance-label" >Connect Wallet</p>
           
            </div>
          </div>

          <div class="history">
            <div class="history-header">
              <i data-lucide="history"></i>
              <h3>Game History</h3>
            </div>
            <div id="history-list" class="history-list"></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div id="confirmation-modal" class="modal hidden">
    <div class="modal-overlay"></div>
    <div class="modal-content">
      <div class="modal-header">
        <i data-lucide="alert-circle"></i>
        <h2>Confirm Budget</h2>
      </div>
      <p class="modal-message">
        Are you sure you want to proceed with a budget of <span id="budget-amount"></span> credits?
      </p>
      <div class="modal-actions">
        <button id="cancel-btn" class="cancel-btn">Cancel</button>
        <button id="confirm-btn" class="confirm-btn">Confirm</button>
      </div>
    </div>
  </div>

  <script>    

      // Game state
      const gameState = {
        selectedMoves: [],
        selectedBet: 1,
        balance: "--",
        history: []
      };


      // Pinata Configuration
      const PINATA_API_KEY = '467c54180f06b4dd708b';
      const PINATA_API_SECRET = '09784337d589c567219fa562a2c47443124047dd1259a75d86cdc5a807beabd8';
      const PINATA_API_URL = 'https://api.pinata.cloud/pinning/pinJSONToIPFS';
      const PINATA_PIN_LIST_URL = 'https://api.pinata.cloud/data/pinList?status=pinned';
      let cid = '';

      const userId = document.getElementById('user-id').value;

      // Utility functions
      function generateHash(moves) {
        return CryptoJS.SHA256(moves.join('')).toString();
      }

      function calculateBudget(betAmount) {
        const baseBudget = SECURITY_COEFFICIENT * betAmount;
        const fees = Math.ceil(baseBudget * 0.05);
        return baseBudget + fees;
      }

      function getMoveIcon(move) {
        switch (move) {
          case 'rock': return 'circle';
          case 'paper': return 'file';
          case 'scissors': return 'scissors';
          default: return '';
        }
      }

      // UI functions
      function renderMoves() {
        const movesList = document.getElementById('moves-list');
        const movesCount = document.querySelector('.moves-count');
        
        movesCount.textContent = `${gameState.selectedMoves.length}/100`;
        
        if (gameState.selectedMoves.length === 0) {
          movesList.innerHTML = '<p class="empty-moves">No moves selected</p>';
          return;
        }
        
        movesList.innerHTML = gameState.selectedMoves
          .map((move, index) => `
              <div class="move-chip">
                  <span class="move-index">${index + 1}.</span>
                  <i data-lucide="${getMoveIcon(move)}"></i>
                  <span>${move}</span>
                  <button onclick="handleMoveRemove(${index})">×</button>
              </div>
          `)
          .join('');
          
        lucide.createIcons();
      }

      function renderHistory() {
        const historyList = document.getElementById('history-list');
        
        historyList.innerHTML = gameState.history
          .map(game => `
            <div class="history-item">
              <div>
                <p class="history-time">${game.timestamp.toLocaleTimeString()}</p>
                <p>Bet: ${game.betAmount}× (${game.moves.length} moves)</p>
              </div>
              <p class="history-result ${game.result > 0 ? 'win' : game.result < 0 ? 'loss' : ''}">
                ${game.result > 0 ? '+' : ''}${game.result}
              </p>
            </div>
          `)
          .join('');
      }

      function renderBalance() {
        const balanceAmount = document.querySelector('.balance-amount');
        balanceAmount.textContent = `${gameState.balance} ETH`;
      }
      function updateBalance(pBalance){
        console.log("updating balance");
        gameState.balance = pBalance;
        renderBalance();
      }

      function updateSubmitButton() {
        const submitBtn = document.getElementById('submit-btn');
        const budget = calculateBudget(gameState.selectedBet);
        
        submitBtn.disabled = 
          gameState.selectedMoves.length === 0 || 
          budget > gameState.balance;
      }

      // Event handlers
      function handleMoveClick(e) {
        const move = e.target.closest('.move-btn').dataset.move;
        if (gameState.selectedMoves.length < 100) {
          gameState.selectedMoves.push(move);
          renderMoves();
          updateSubmitButton();
        }
      }

      function handleMoveRemove(index) {
        gameState.selectedMoves.splice(index, 1);
        renderMoves();
        updateSubmitButton();
      }
      window.handleMoveRemove = handleMoveRemove;

      function handleBetClick(e) {
        const bet = parseFloat(e.target.dataset.bet);
        gameState.selectedBet = bet;
        document.querySelectorAll('.bet-btn').forEach(btn => {
          btn.classList.toggle('active', parseFloat(btn.dataset.bet) === bet);
        });
        updateSubmitButton();
      }

      function handleSubmit() {
        const budget = calculateBudget(gameState.selectedBet);
        document.getElementById('budget-amount').textContent = budget;
        document.getElementById('confirmation-modal').classList.remove('hidden');
      }

      async function handleConfirm() {
        const budget = calculateBudget(gameState.selectedBet);
        const hash = generateHash(gameState.selectedMoves);
        //sendPayment(2);
        // Simulate game result
        const result = Math.floor(Math.random() * 3 - 1) * budget;
        
        gameState.balance += result;
        gameState.history.unshift({
          id: hash,
          timestamp: new Date(),
          moves: [...gameState.selectedMoves],
          betAmount: gameState.selectedBet,
          result
        });
        
        try {
            cid = await uploadMovesToPinata();
            
        } catch (error) {
            console.error('Error uploading moves to Pinata:', error);
            alert('Failed to upload moves. Please try again.');
            return; // Exit the function if the upload fails
        }
        await submitPreMoves();
        console.log('Type of ABI:', typeof gameState.selectedBet);
        console.log('gameState.selectedBet:', gameState.selectedBet);
        await addUserToPool(gameState.selectedBet);
        gameState.selectedMoves = [];
        document.getElementById('confirmation-modal').classList.add('hidden');
        
        renderMoves();
        renderHistory();
        renderBalance();
        updateSubmitButton();
      }
      async function submitPreMoves() {
          const moves = gameState.selectedMoves;

          try {
              const response = await fetch('/user/pre-moves', {
              method: 'POST',
              headers: {
                  'Content-Type': 'application/json',
                  'X-CSRF-TOKEN': "{{ csrf_token() }}",
              }, 
              body: JSON.stringify({
                  user_id: userId, // Send the User ID
                  pre_moves: moves,
                  bet_amount: 0.0000,
                  cid: cid // Send the CID
              }),
              });

              const data = await response.json();
              if (response.ok) {
              //alert(data.message);
              //gameState.selectedMoves = []; // Clear selected moves
              renderMoves(); // Update UI
              } else {
              //alert(data.message || 'An error occurred.');
              }
          } catch (error) {
              console.error('Error submitting moves:', error);
              //alert('Failed to submit moves. Please try again.');
               return;
          }
      }

      //WEB 3.0=============================================================================================================

      let ABI = [];
      let CONTRACT_ADDRESS="";
      let WALLET_ADDRESS="";
      let SECURITY_COEFFICIENT = 1000;

      let web3 = new Web3(window.ethereum);
      let contract = new web3.eth.Contract(ABI, CONTRACT_ADDRESS);

      async function getArtefacts() {
        try {
          // Fetch the contract information from the server
          const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
          const response = await fetch('/artefacts', {
        method: 'GET',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
        },
          });

          if (!response.ok) {
        throw new Error('Failed to retrieve contract information.');
          }

          const data = await response.json();
          const abi = data.abi;
          const address = data.address;
          SEUCRITY_COEFFICIENT = data.security_coefficient;
          // Assign ABI and CONTRACT_ADDRESS directly
  ABI =  [
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
      "name": "user/pre-movesHistoryCIDUpdated",
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
  ]; // ABI should be an array, not wrapped in an object
          CONTRACT_ADDRESS = address;

          console.log('Contract ABI:', ABI);
          console.log('Contract Address:', CONTRACT_ADDRESS);
          console.log('Security coefficient:', SECURITY_COEFFICIENT);

          console.log('Type of ABI:', typeof ABI);
          console.log('Is ABI an array?', Array.isArray(ABI));

          const web3 = new Web3(window.ethereum);
          contract = new web3.eth.Contract(ABI, CONTRACT_ADDRESS);

        } catch (error) {
          console.error('Error fetching contract info:', error.message);
        }
      }

      // Call the function when the page loads

      async function connectWallet() {
        
        const web3 = new Web3(window.ethereum);

        try {
          // Step 1: Connect Wallet
          await window.ethereum.request({ method: 'eth_requestAccounts' });
          const accounts = await web3.eth.getAccounts();
          WALLET_ADDRESS = accounts[0];
          console.log('Connected Wallet:', WALLET_ADDRESS);
        }catch (error) {
          console.error('Error during wallet authentication:', error);
          alert('Authentication failed. Please try again.');
        }

      }

      async function loginWithWallet() {
        if (!window.ethereum) {
          alert('Please install MetaMask!');
          return;
        }

        const web3 = new Web3(window.ethereum);

        try {
          // Step 1: Connect Wallet
          await window.ethereum.request({ method: 'eth_requestAccounts' });
          const accounts = await web3.eth.getAccounts();
          WALLET_ADDRESS = accounts[0];
          console.log('Connected Wallet:', WALLET_ADDRESS);

          // Step 2: Request signing message from the backend
          const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
          const messageResponse = await fetch('/wallet/generate-message', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json','X-CSRF-TOKEN': csrfToken, },
            body: JSON.stringify({ wallet_address: WALLET_ADDRESS }),
          });

          if (!messageResponse.ok) {
            const error = await messageResponse.json();
            alert(error.message);
            return;
          }

          const { message } = await messageResponse.json();

          // Step 3: Sign the message with the wallet
          const signature = await web3.eth.personal.sign(message, WALLET_ADDRESS);
          console.log("Signature:", signature);

          //new version=====================================

          const verifyResponse = await fetch('/wallet/verify-signature', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json','X-CSRF-TOKEN': csrfToken,},
            body: JSON.stringify({ wallet_address: WALLET_ADDRESS, signature }),
          });

          if (verifyResponse.ok) {
            const data = await verifyResponse.json();
            //alert(`Login successful! Welcome, ${data.user.name}`);
            console.log(data);
          } else {
            const error = await verifyResponse.json();
            alert(error.message);
          }
        } catch (error) {
          console.error('Error during wallet authentication:', error);
          alert('Authentication failed. Please try again.');
        }
      }

      async function addUserToPool(baseBet) {
        try {
          // Step 1: Get the user's pre-moves CID from Pinata
          //const cid = await uploadMovesToPinata();
          if (!cid) {
            throw new Error('Failed to upload moves to Pinata');
          }

          // Step 2: Make a deposit
          const depositAmount = baseBet * SECURITY_COEFFICIENT; // Deposit amount is the base bet multiplied by the security coefficient
          

          // Step 3: Send the CID and baseBet to the smart contract


          /*// Call the addSingleUserToPool function
          const tx = await contract.methods.addSingleUserToPool(baseBet, WALLET_ADDRESS).send({
            from: WALLET_ADDRESS,
            value: web3.utils.toWei(baseBet.toString(), 'ether')
          });

          console.log('Transaction hash:', tx.transactionHash);
          */
          // Step 4: Submit the pre-moves CID to the smart contract
             console.log('web3.utils.toWei(depositAmount.toString(), ether):', web3.utils.toWei(depositAmount.toString(), 'ether'));
             let baseB = web3.utils.toWei(baseBet.toString(), 'ether');

            const nonce = await web3.eth.getTransactionCount(WALLET_ADDRESS, 'latest');
            const submitTx = await contract.methods.submitPremoveCID(baseB, cid).send({
            from: WALLET_ADDRESS,
            value: web3.utils.toWei(depositAmount.toString(), 'ether'),
            nonce: nonce
            });

          
          console.log('Pre-moves CID submitted. Transaction hash:', submitTx.transactionHash);
          const balanceGPT = await getUserBalanceGPT(WALLET_ADDRESS);
          console.log('Balance from getUserBalanceGPT:', balanceGPT);
          return {
            success: true,
            submitCidTxHash: submitTx.transactionHash
          };
        } catch (error) {
          console.error('Error in addUserToPool:', error);
          throw error;
        }

      }

      async function sendPayment(amount) {
        connectWallet();
        try {
          // Ensure provider exists
          if (!window.ethereum) {
            throw new Error('Please install MetaMask or another web3 wallet');
          }
          //loginWithWallet();
          // Convert amount to Wei and then to hex
          const amountInWei = BigInt(Math.floor(amount * 1e18)).toString(16);

          // Get current gas price
          const gasPrice = await window.ethereum.request({
            method: 'eth_gasPrice'
          });

          const nonce = await window.ethereum.request({
            method: 'eth_getTransactionCount',
            params: [WALLET_ADDRESS, 'latest']
          });

          console.log("nonce ", nonce);

          // Create transaction parameters
          const transactionParameters = {
            to: CONTRACT_ADDRESS,
            from: WALLET_ADDRESS,
            value: '0x1000000000000000',
            nonce: nonce
          };

          console.log(transactionParameters.from);
          // Estimate gas for the transaction
          const gasEstimate = await window.ethereum.request({
            method: 'eth_estimateGas',
            params: [transactionParameters]
          });

          // Add gas parameters to transaction
          transactionParameters.gas = gasEstimate;
          transactionParameters.gasPrice = gasPrice;

          // Request account access if needed
          await window.ethereum.request({ method: 'eth_requestAccounts' });

          // Send the transaction
          const txHash = await window.ethereum.request({
            method: 'eth_sendTransaction',
            params: [transactionParameters],
          });

          console.log('Transaction hash:', txHash);

          // Wait for transaction confirmation
          const receipt = await waitForTransaction(txHash);

          if (receipt.status === '0x1') {
            console.log('Transaction confirmed:', receipt);
            return {
              success: true,
              hash: txHash,
              receipt: receipt
            };
          } else {
            throw new Error('Transaction failed');
          }
        } catch (err) {
          console.error('Error submitting payment:', err);
          throw err;
        }
      }

      // Helper function to wait for transaction confirmation
      async function waitForTransaction(txHash) {
        const maxAttempts = 50;
        let attempts = 0;

        while (attempts < maxAttempts) {
          const receipt = await window.ethereum.request({
            method: 'eth_getTransactionReceipt',
            params: [txHash],
          });

          if (receipt) {
            return receipt;
          }

          await new Promise(resolve => setTimeout(resolve, 3000)); // Wait 3 seconds
          attempts++;
        }

        throw new Error('Transaction confirmation timeout');
      }
      /*async function recoverPublicKey(message, signature) {
        const web3 = new Web3(window.ethereum);

        // Hash the message the same way Ethereum does before signing
        const messageHash = web3.utils.sha3(
          `\x19Ethereum Signed Message:\n${message.length}${message}`
        );

        // Recover the public key from the signature and hashed message
        const publicKey = web3.eth.accounts.recover(messageHash, signature, true);

        return publicKey;
      }*/


      function hexToBytes(hex) {
        const bytes = [];
        for (let i = 0; i < hex.length; i += 2) {
          bytes.push(parseInt(hex.substr(i, 2), 16));
        }
        return new Uint8Array(bytes);
      }

      // Helper function to recover public key


      async function updatedb(params) {
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        let counter = 88.88;
        try {
          const response = await fetch('http://127.0.0.1:8000/blockchain-update-database-counter', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({
              action: 'counter_updated',
              counter
            }),
          });

          if (response.ok) {
            console.log('Database updated successfully.');
          } else {
            console.error('Failed to update database:', response.statusText);
          }
        } catch (error) {
          console.error('Error updating database:', error.message);
        }
      }





      //==============================================PINATA================================================================    

        async function uploadMovesToPinata() {
          try {
            // Prepare the data for Pinata - only include moves for deterministic CID
            const movesData = {
              pinataContent: {
                moves: gameState.selectedMoves
              }
            };

            // Upload to Pinata
            const response = await fetch(PINATA_API_URL, {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'pinata_api_key': PINATA_API_KEY,
                'pinata_secret_api_key': PINATA_API_SECRET
              },
              body: JSON.stringify(movesData)
            });

            const data = await response.json();

            if (!response.ok) {
              throw new Error(data.error?.details || 'Failed to upload to Pinata');
            }

            // Display the CID and IPFS link
            const cid = data.IpfsHash;
            console.log('CID:', cid);
            return cid;
          } catch (error) {
            console.error('Error uploading to Pinata:', error);
            throw error;
          }
        }




      //=================================================PINATA===========================================================
      // Initialize

    // Function to get user balance from the smart contract
    async function getUserBalanceDeep(userAddress) {
      try {


        // Get the balance of the user's address
        const balanceInWei = await web3.eth.getBalance(userAddress);

        // Convert the balance from Wei to Ether
        const balanceInEther = web3.utils.fromWei(balanceInWei, 'ether');

        // Log the balance
        console.log(`✅ User balance for ${userAddress}: ${balanceInEther} ETH`);

        return balanceInEther; // Return the balance in Ether
      } catch (error) {
        console.error(`🚨 Error while getting user balance for ${userAddress}:`, error.message);
        throw error; // Re-throw the error to handle it in the calling function
      }
    }

    async function getUserBalanceGPT(userAddress) {
      try {
        // Ensure provider exists
        if (!window.ethereum) {
          throw new Error('Please install MetaMask or another web3 wallet');
        }

        // Retrieve the user's balance
        const balance = await contract.methods.getUserBalance(userAddress).call();
        const balanceInEther = web3.utils.fromWei(balance, 'ether');

        console.log(`✅ User balance for ${userAddress}: ${balanceInEther} ether`);
        return balanceInEther;
      } catch (error) {
        console.error(`🚨 Error while getting user balance for ${userAddress}:`, error.message);
        return null;
      }
    }

    async function depositEther(amountInEther) {
      const amountInWei = web3.utils.toWei(amountInEther, 'ether');
      const nonce = await web3.eth.getTransactionCount(WALLET_ADDRESS, 'latest');

      contract.methods.deposit().send({
        from: WALLET_ADDRESS,
        value: amountInWei,
        nonce: nonce
      });
    }

    // Example usage: deposit 1 Ether
    








        // Function to call updateUserBalance from the smart contract
        async function updateUserBalance(userAddress, newBalance) {
            try {
                // Ensure provider exists
                if (!window.ethereum) {
                    throw new Error('Please install MetaMask or another web3 wallet');
                }

                // Initialize Web3
                const web3 = new Web3(window.ethereum);

                // Get the contract instance
                const contract = new web3.eth.Contract(ABI, CONTRACT_ADDRESS);

                // Call the updateUserBalance function with nonce
                const nonce = await web3.eth.getTransactionCount(WALLET_ADDRESS, 'latest');
                const tx = await contract.methods.updateUserBalance(userAddress, newBalance).send({ 
                  from: WALLET_ADDRESS,
                  nonce: nonce
                });

                // Log the transaction
                console.log(`✅ User balance updated for ${userAddress}: ${newBalance} ETH`);
                console.log(`Transaction hash: ${tx.transactionHash}`);

                return tx;
            } catch (error) {
                console.error(`🚨 Error while updating user balance for ${userAddress}:`, error.message);
                return null;
            }
        }

        async function isUserInPool(poolId, userAddress) {
          try {
              // Ensure Web3 and contract are initialized
              if (!window.ethereum || !contract) {
                  throw new Error('Web3 or contract not initialized');
              }

              // Call the smart contract function
              const isInPool = await contract.methods.isUserInPool(poolId, userAddress).call();

              return isInPool; // Returns true or false
          } catch (error) {
              console.error('Error checking if user is in pool:', error);
              return false; // Default to false in case of an error
          }
        }

        async function getContractBalance() {
            try {
                // Ensure Web3 and contract are initialized
                if (!window.ethereum || !contract) {
                    throw new Error('Web3 or contract not initialized');
                }

                // Call the smart contract function
                const balanceInWei = await contract.methods.getContractBalance().call();
                const balanceInEther = web3.utils.fromWei(balanceInWei, 'ether'); // Convert Wei to Ether

                return balanceInEther; // Return the balance in Ether
            } catch (error) {
                console.error('Error fetching contract balance:', error);
                return null; // Return null in case of an error
            }
        }











        // Example usage







      document.querySelectorAll('.move-btn').forEach(btn => 
        btn.addEventListener('click', handleMoveClick)
      );

      document.querySelectorAll('.bet-btn').forEach(btn => 
        btn.addEventListener('click', handleBetClick)
      );

      document.getElementById('submit-btn').addEventListener('click', handleSubmit);
      document.getElementById('confirm-btn').addEventListener('click', handleConfirm);
      document.getElementById('cancel-btn').addEventListener('click', () => 
        document.getElementById('confirmation-modal').classList.add('hidden')
      );

      // Initialize UI
      lucide.createIcons();
      renderMoves();
      renderHistory();
      renderBalance();
      updateSubmitButton();
      console.log()







      window.addEventListener("DOMContentLoaded",function(){
      getArtefacts();
      const channelName = `App.Models.User.${userId}`;
      window.Echo.private(channelName)
      .listen("testevent",(event)=>{updateBalance(event.balance);})
      .listen("BalanceUpdated",(data)=>{console.log(data.balance);});
      });



      /*
      setTimeout(async () => {
        try {
          getArtefacts();
          const balanceDeep = await getUserBalanceDeep(WALLET_ADDRESS);
          console.log('Balance from getUserBalanceDeep:', balanceDeep);

          const balanceGPT = await getUserBalanceGPT(WALLET_ADDRESS);
          console.log('Balance from getUserBalanceGPT:', balanceGPT);

          console.log("============================================================================================");
          console.log("============================================================================================");
          console.log("============================================================================================");

          const userAddress = WALLET_ADDRESS; // Replace with the user's address you want to check
          const newBalance = '77000000000000000000'; // Replace with the new balance you want to set

          updateUserBalance(userAddress, newBalance).then(tx => {
          if (tx !== null) {
          // Update the UI with the transaction hash
          //document.getElementById('user-balance').textContent = `Balance updated. Transaction hash: ${tx.transactionHash}`;
          console.log(`🚀 🚀🚀🚀🚀🚀🚀🚀🚀🚀Balance updated. Transaction hash: ${tx.transactionHash}`);
          }
          }).catch(error => {
          console.error("🚨 Unexpected script error:", error.message);
          });
          
        } catch (error) {
          console.error('Error fetching balances:', error);
        }
      }, 8000);
 */

      
      setTimeout(checkBalancesAndPools, 30000);

      async function checkBalancesAndPools() {
        try {
          const MEDARD = await getUserBalanceGPT("0x70997970C51812dc3A010C7d01b50e0d17dc79C8");
          console.log('Balance MEDARD:', MEDARD);

          const poolId = 1; // Replace with the actual pool ID

          const isMedardInPool = await isUserInPool(poolId, "0x70997970C51812dc3A010C7d01b50e0d17dc79C8");
          console.log(`is MEDARD  in the pool: ${isMedardInPool}`);
        } catch (error) {
          console.error('Error fetching MEDARD balance or pool status:', error);
        }

        try {
          const DEV1 = await getUserBalanceGPT("0xf39Fd6e51aad88F6F4ce6aB8827279cffFb92266");
          console.log('Balance DEV1:', DEV1);

          const poolId = 1; // Replace with the actual pool ID

          const isDev1InPool = await isUserInPool(poolId, "0xf39Fd6e51aad88F6F4ce6aB8827279cffFb92266");
          console.log(`is DEV1  in the pool: ${isDev1InPool}`);
        } catch (error) {
          console.error('Error fetching DEV1 balance or pool status:', error);
        }


        // Example usage
        getContractBalance().then(balance => {
            console.log(`Contract balance: ${balance} ETH`);
        });


      }
     

  </script>
</body>
</html>