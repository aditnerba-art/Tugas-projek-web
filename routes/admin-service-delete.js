const express = require('express');
const { deleteServiceRecords } = require('../lib/service-deletion');

function createAdminServiceDeleteRouter(db) {
    const router = express.Router();

    router.delete('/service/:id', async (req, res) => {
        if (!/^[1-9]\d*$/.test(req.params.id) || !Number.isSafeInteger(Number(req.params.id))) {
            return res.status(400).json({ success: false, message: 'ID servis tidak valid' });
        }

        const serviceId = Number(req.params.id);
        let connection;
        try {
            connection = await db.getConnection();
            await connection.beginTransaction();
            const [services] = await connection.execute(
                'SELECT id FROM services WHERE id = ? FOR UPDATE',
                [serviceId]
            );
            if (!services.length) {
                await connection.rollback();
                return res.status(404).json({ success: false, message: 'Servis tidak ditemukan' });
            }

            await deleteServiceRecords(connection, [serviceId]);
            await connection.commit();
            return res.json({ success: true, message: 'Data servis berhasil dihapus' });
        } catch (error) {
            if (connection) await connection.rollback();
            console.error('Failed to delete admin service:', error.message);
            return res.status(500).json({ success: false, message: 'Data servis gagal dihapus' });
        } finally {
            if (connection) connection.release();
        }
    });

    return router;
}

module.exports = { createAdminServiceDeleteRouter };
