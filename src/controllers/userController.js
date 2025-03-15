const db = require("../config/db");

// Update Profile Image
exports.updateProfileImage = async (req, res) => {
    try {
        const userId = req.user.userId; // Get user ID from JWT
        if (!req.file) {
            return res.status(400).json({ message: "No image uploaded" });
        }

        const profileImage = `/uploads/${req.file.filename}`;

        // Update the database
        await db.execute("UPDATE Users SET profile_image = ? WHERE user_id = ?", [profileImage, userId]);

        res.json({ message: "Profile image updated successfully", profileImage });
    } catch (error) {
        console.error("Error updating profile image:", error);
        res.status(500).json({ message: "Server error", error });
    }
};