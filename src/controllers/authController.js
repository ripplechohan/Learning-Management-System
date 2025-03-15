const jwt = require("jsonwebtoken");
const bcrypt = require("bcryptjs");
const User = require("../models/userModel");
const speakeasy = require("speakeasy");
const db = require("../config/db");
require("dotenv").config();

exports.register = async (req, res) => {
    try {
        const { name, email, password, role } = req.body;
        const profileImage = req.file ? `/uploads/${req.file.filename}` : null; // Store image path
        const existingUser = await User.findUserByEmail(email);
        if (existingUser) return res.status(400).json({ message: "User already exists" });

        const newUser = await User.createUser(name, email, password, role, profileImage);
        res.status(201).json({ message: "User registered successfully", user: newUser });
    } catch (error) {
        res.status(500).json({ message: "Server error", error });
    }
};

exports.login = async (req, res) => {
    try {
        const { email, password } = req.body;
        const user = await User.findUserByEmail(email);
        if (!user) return res.status(400).json({ message: "Invalid email or password" });

        const isMatch = await bcrypt.compare(password, user.password);
        if (!isMatch) return res.status(400).json({ message: "Invalid email or password" });

        // Update last_login timestamp
        await db.execute("UPDATE Users SET last_login = NOW() WHERE user_id = ?", [user.user_id]);

        const token = jwt.sign({ userId: user.user_id, role: user.role }, process.env.JWT_SECRET, { expiresIn: "1h" });
        res.json({ message: "Login successful", token, user: {
            userId: user.user_id,
            name: user.name,  // ✅ Now includes user name
            role: user.role
        } });

    } catch (error) {
        res.status(500).json({ message: "Server error", error });
    }
};