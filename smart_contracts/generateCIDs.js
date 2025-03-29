import fetch from "node-fetch"; // Ensure you have node-fetch installed for backend use

const PINATA_API_KEY = '467c54180f06b4dd708b';
const PINATA_API_SECRET = '09784337d589c567219fa562a2c47443124047dd1259a75d86cdc5a807beabd8';
const PINATA_API_URL = 'https://api.pinata.cloud/pinning/pinJSONToIPFS';

// Possible moves
const MOVES = ["rock", "paper", "scissors"];

// Function to generate a random sequence of pre-moves
function generatePreMoves() {
  const moves = [];
  for (let i = 0; i < 5; i++) { // Generating 5 moves per user
    const randomMove = MOVES[Math.floor(Math.random() * MOVES.length)];
    moves.push(randomMove);
  }
  return moves;
}

// Function to upload moves to Pinata and return the CID
async function uploadMovesToPinata(moves) {
  try {
    const movesData = {
      pinataContent: { moves }
    };

    const response = await fetch(PINATA_API_URL, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "pinata_api_key": PINATA_API_KEY,
        "pinata_secret_api_key": PINATA_API_SECRET
      },
      body: JSON.stringify(movesData)
    });

    const data = await response.json();

    if (!response.ok) {
      throw new Error(data.error?.details || "Failed to upload to Pinata");
    }

    return data.IpfsHash;
  } catch (error) {
    console.error("🚨 Error uploading to Pinata:", error.message);
    throw error;
  }
}

// Function to generate multiple CIDs for users
async function generateUserCIDs(userCount) {
  const userCIDs = [];

  for (let i = 0; i < userCount; i++) {
    const moves = generatePreMoves();
    const cid = await uploadMovesToPinata(moves);
    
    userCIDs.push({ userIndex: i, moves, cid });
    console.log(`✅ User ${i + 1}: Moves: ${moves.join(", ")} | CID: ${cid}`);
  }

  return userCIDs;
}

// Example: Generate CIDs for 10 users
generateUserCIDs(100).then((userCIDs) => {
  console.log("\n🔹 All Generated CIDs:", userCIDs);
});
