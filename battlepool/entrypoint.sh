#!/bin/sh
# Start Hardhat node in the background
npx hardhat node --hostname 0.0.0.0 &
NODE_PID=$!

echo "Waiting for Hardhat node to start on port 8545..."
while ! nc -z 127.0.0.1 8545; do
  sleep 1
done

echo "Hardhat node is up! Deploying smart contracts..."
npx hardhat run full_deploy.js --network localhost

# Signal to Docker that deployment is complete (used by healthcheck)
touch /app/deployed.txt
echo "✅ Contracts deployed. Signal file written."

echo "Bringing Hardhat node to the foreground..."
wait $NODE_PID
