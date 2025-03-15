const db = require("../config/db");
const bcrypt = require("bcryptjs");

const User = {
    async createUser(name, email, password, role, profileImage) {
        const hashedPassword = await bcrypt.hash(password, 10);
        const sql = `INSERT INTO Users (name, email, password, role, profile_image) VALUES (?, ?, ?, ?, ?)`;
        await db.execute(sql, [name, email, hashedPassword, role, profileImage]);
        return { name, email, role, profileImage };
    },

    async findUserByEmail(email) {
        const sql = `SELECT * FROM Users WHERE email = ?`;
        const [rows] = await db.execute(sql, [email]);
        console.log("Executing Query:", sql);
        console.log("Query Parameters:", email);
        console.log("Query Result:", rows);

        return rows.length > 0 ? rows[0] : null;
    },

    async findUserById(id) {
        const [rows] = await db.execute(`SELECT * FROM Users WHERE user_id = ?`, [id]);
        return rows.length > 0 ? rows[0] : null;
    }
};

module.exports = User;