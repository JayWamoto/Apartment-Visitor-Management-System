const express = require('express');
const router = express.Router();
const db = require('../config/db');
const { verifyAuth } = require('../middleware/auth');

// Check-in New Visitor (Sequence 4.1)
router.post('/checkin', verifyAuth, async (req, res) => {
    const { full_name, phone_number, id_number, vehicle_number, resident_id } = req.body;
    const recorded_by_user_id = req.session.user.id;

    try {
        // 1. Save or find Visitor
        let [visitor] = await db.query('SELECT visitor_id FROM visitors WHERE id_number = ?', [id_number]);
        let visitorId;

        if (visitor.length > 0) {
            visitorId = visitor[0].visitor_id;
        } else {
            const [vResult] = await db.query(
                'INSERT INTO visitors (full_name, phone_number, id_number, vehicle_number) VALUES (?, ?, ?, ?)',
                [full_name, phone_number, id_number, vehicle_number || null]
            );
            visitorId = vResult.insertId;
        }

        // 2. Create Visit Log with status ONGOING
        const [logResult] = await db.query(
            'INSERT INTO visit_logs (visitor_id, resident_id, recorded_by_user_id, status) VALUES (?, ?, ?, "ONGOING")',
            [visitorId, resident_id, recorded_by_user_id]
        );

        res.json({ message: 'Visitor registered successfully', visit_id: logResult.insertId });
    } catch (err) {
        res.status(500).json({ error: err.message });
    }
});

// Get Active (Ongoing) Visitors
router.get('/active', verifyAuth, async (req, res) => {
    try {
        const query = `
            SELECT vl.visit_id, v.full_name, v.phone_number, v.vehicle_number, 
                   CONCAT(a.block_number, '-', a.unit_number) AS unit, 
                   r.name AS resident_name, DATE_FORMAT(vl.entry_timestamp, '%H:%i:%s') AS entry_time
            FROM visit_logs vl
            JOIN visitors v ON vl.visitor_id = v.visitor_id
            JOIN residents r ON vl.resident_id = r.resident_id
            JOIN apartments a ON r.apartment_id = a.apartment_id
            WHERE vl.status = 'ONGOING'
            ORDER BY vl.entry_timestamp DESC
        `;
        const [rows] = await db.query(query);
        res.json(rows);
    } catch (err) {
        res.status(500).json({ error: err.message });
    }
});

// Check-Out Visitor (Sequence 4.2)
router.post('/checkout/:id', verifyAuth, async (req, res) => {
    const visitId = req.params.id;
    try {
        await db.query(
            'UPDATE visit_logs SET exit_timestamp = CURRENT_TIMESTAMP, status = "COMPLETED" WHERE visit_id = ?',
            [visitId]
        );
        res.json({ message: 'Visitor checked out successfully' });
    } catch (err) {
        res.status(500).json({ error: err.message });
    }
});

module.exports = router;