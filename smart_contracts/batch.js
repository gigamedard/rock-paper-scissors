import express from "express"; 
import axios from "axios";

const app = express();
app.use(express.json());

// Function to send the GET request periodically
async function sendBatchPoolProcessingRequest() {
    try {
        console.log("Sending batch pool processing request...");
        const response = await axios.get("http://127.0.0.1:8000/batch_pool_processing");
        console.log("Response:", response.data);
    } catch (error) {
        console.error("Error sending batch pool processing request:", error.message);
        if (error.response) {
            console.error("Response data:", error.response.data);
        }
    }
}

// Send the request every 3 seconds
setInterval(sendBatchPoolProcessingRequest, 3000);

app.listen(3025, () => {
    console.log("Server running on port 3025");
});

