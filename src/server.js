const express = require("express");
const path = require("path");
const cors = require("cors");

const authRoutes = require("./routes/authRoutes");

const app = express();
app.use(express.json());
app.use(cors());

// Serve static HTML files
app.use(express.static(path.join(__dirname, "./public"))); 

// API routes
app.use("/api/auth", authRoutes);

const PORT = process.env.PORT || 5000;
app.listen(PORT, () => console.log(`Server running on port ${PORT}`));