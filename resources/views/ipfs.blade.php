<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>RPS Pre-move Composer</title>
  <style>
    /* All previous styles remain unchanged */
    /* Add a new style for the retrieve button */
    .retrieve-btn {
      width: 100%;
      background: linear-gradient(to right, #60a5fa, #a78bfa);
      border: none;
      color: #fff;
      padding: 0.875rem;
      border-radius: 0.5rem;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.2s;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      margin-top: 1rem;
    }

    .retrieve-btn:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }
  </style>
</head>
<body>
  <!-- Your existing HTML content -->

  <!-- Add an element to display the user balance -->
  <div id="user-balance">Balance: Loading...</div>

  <!-- Include the provided CDNs -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/ethereumjs-util/7.1.5/index.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.2.0/crypto-js.min.js"></script>
  <script src="https://unpkg.com/lucide@0.344.0"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/web3/1.8.0/web3.min.js"></script>
  <script src="https://bundle.run/secp256k1@4.0.3"></script>

  <script>
    // Initialize web3
    const web3 = new Web3(Web3.givenProvider || 'YOUR_RPC_PROVIDER_URL');

    // Contract details
    const contractAddress = 'YOUR_CONTRACT_ADDRESS';
    const abi = [ /* Your contract ABI */ ];
    const contract = new web3.eth.Contract(abi, contractAddress);

    // Function to get user balance from the smart contract
    async function getUserBalance(userAddress) {
      try {
        const balance = await contract.methods.getUserBalance(userAddress).call();
        console.log(`✅ User balance for ${userAddress}: ${balance} wei`);
        return balance;
      } catch (error) {
        console.error(`🚨 Error while getting user balance for ${userAddress}:`, error.message);
        return null;
      }
    }

    // Example usage
    const userAddress = 'USER_WALLET_ADDRESS'; // Replace with the user's address you want to check
    getUserBalance(userAddress).then(balance => {
      if (balance !== null) {
        // Update the UI with the balance
        document.getElementById('user-balance').textContent = `Balance: ${balance} wei`;
      }
    }).catch(error => {
      console.error("🚨 Unexpected script error:", error.message);
    });










//WEB 3.0=============================================================================================================

      let ABI = [];
      let CONTRACT_ADDRESS="";
      let WALLET_ADDRESS="";

      async function getArtefacts() {
        try
        {
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

          const { abi, address } = await response.json();

          // Update ABI and Contract Address
          ABI = abi;
          CONTRACT_ADDRESS = address;

          console.log('Contract ABI:', ABI);
          console.log('Contract Address:', CONTRACT_ADDRESS);

        } catch (error) 
        {
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

      // Define the deposit function signature
        const DEPOSIT_FUNCTION_SIGNATURE = '0xd0e30db0'; // This is the keccak256 hash of "deposit()"

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
      value: '0x' + amountInWei,
      nonce: nonce,
      data: DEPOSIT_FUNCTION_SIGNATURE  // Using the predefined function signature
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




















  </script>
</body>
</html>