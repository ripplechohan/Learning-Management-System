const speakeasy = require("speakeasy");
const qrcode = require("qrcode");
const db = require("../config/db");

exports.enableMFA = async (req, res) => {
    try {
        const userId = req.user.userId; // Get user ID from JWT
        const secret = speakeasy.generateSecret({ length: 20 });

        // Save the secret in the database
        await db.execute("UPDATE Users SET mfa_secret = ? WHERE user_id = ?", [secret.base32, userId]);

        // Generate QR code for Google Authenticator
        const otpAuthUrl = secret.otpauth_url;
        qrcode.toDataURL(otpAuthUrl, (err, qrCodeUrl) => {
            if (err) return res.status(500).json({ message: "Error generating QR code" });
            res.json({ message: "MFA enabled", secret: secret.base32, qrCodeUrl });
        });

    } catch (error) {
        res.status(500).json({ message: "Server error", error });
    }
};

exports.verifyMFA = async (req, res) => {
    try {
        const { userId, token } = req.body;

        // Get the stored secret key
        const [rows] = await db.execute("SELECT mfa_secret FROM Users WHERE user_id = ?", [userId]);
        if (!rows.length || !rows[0].mfa_secret) return res.status(400).json({ message: "MFA not enabled" });

        const secret = rows[0].mfa_secret;

        // Verify the OTP token
        const verified = speakeasy.totp.verify({
            secret,
            encoding: "base32",
            token
        });

        if (!verified) return res.status(400).json({ message: "Invalid OTP" });

        res.json({ message: "MFA verified successfully" });

    } catch (error) {
        res.status(500).json({ message: "Server error", error });
    }
};