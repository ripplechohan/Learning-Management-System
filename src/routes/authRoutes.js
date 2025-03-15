const express = require("express");
const { register, login } = require("../controllers/authController");
const { enableMFA, verifyMFA } = require("../controllers/mfaController");
const { authenticate } = require("../middleware/authMiddleware");
const { updateProfileImage } = require("../controllers/userController");
const upload = require("../middleware/uploadMiddleware");

const router = express.Router();

router.post("/register", upload.single("profileImage"), register);
router.post("/login", login);
router.post("/mfa/enable", authenticate, enableMFA);
router.post("/mfa/verify", verifyMFA);
router.post("/update-profile-image", authenticate, upload.single("profileImage"), updateProfileImage);

module.exports = router;