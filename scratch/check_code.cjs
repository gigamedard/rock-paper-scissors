const http = require('http');

const data = JSON.stringify({
  "jsonrpc": "2.0",
  "method": "eth_getCode",
  "params": ["0x5FC8d32690cc91D4c39d9d3abcBD16989F875707", "latest"],
  "id": 1
});

const req = http.request('http://127.0.0.1:8545', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json'
  }
}, (res) => {
  let responseData = '';
  res.on('data', chunk => responseData += chunk);
  res.on('end', () => console.log(responseData));
});

req.write(data);
req.end();
