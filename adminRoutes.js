const express = require('express');
const router = express.Router();
const db = require('../config/db');
const { verifyAuth, verifyRole } = require('../middleware/auth');

// Get All Residents Directory
router.get('/residents', verifyAuth, async (req, res) => {
    try {
        const [rows] = await db.query(`
            SELECT r.resident_id, r.name, r.phone_number, r.emergency_contact, 
                   a.apartment_id, CONCAT(a.block_number, '-', a.unit_number) AS unit, a.floor
            FROM residents r
            JOIN apartments a ON r.apartment_id = a.apartment_id
        `);
        res.json(rows);
    } catch (err) {
        res.status(500).json({ error: err.message });
    }
});

// Add New Resident
router.post('/residents', verifyAuth, verifyRole('ADMIN'), async (req, res) => {
    const { apartment_id, name, phone_number, emergency_contact } = req.body;
    try {
        await db.query(
            'INSERT INTO residents (apartment_id, name, phone_number, emergency_contact) VALUES (?, ?, ?, ?)',
            [apartment_id, name, phone_number, emergency_contact]
        );
        res.json({ message: 'Resident added successfully' });
    } catch (err) {
        res.status(500).json({ error: err.message });
    }
});

module.exports = router;