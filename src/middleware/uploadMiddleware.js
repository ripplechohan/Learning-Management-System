const multer = require("multer");
const path = require("path");

// Set up storage location and filename
const storage = multer.diskStorage({
    destination: (req, file, cb) => {
        cb(null, "uploads/"); // Save images in "uploads" folder
    },
    filename: (req, file, cb) => {
        cb(null, Date.now() + path.extname(file.originalname)); // Unique filename
    }
});

// File filter (allow only images)
const fileFilter = (req, file, cb) => {
    const allowedExtensions = /jpeg|jpg|png/;
    const extname = allowedExtensions.test(path.extname(file.originalname).toLowerCase());
    if (extname) {
        cb(null, true);
    } else {
        cb(new Error("Only images (JPEG, JPG, PNG) are allowed!"), false);
    }
};

const upload = multer({ storage, fileFilter });

module.exports = upload;