async function deleteServiceRecords(connection, serviceIds) {
    const ids = [...new Set(serviceIds.map(Number))];
    if (ids.some(id => !Number.isSafeInteger(id) || id < 1)) {
        throw new Error('ID servis tidak valid untuk penghapusan');
    }
    if (!ids.length) return;

    const placeholders = ids.map(() => '?').join(', ');
    const params = ids;

    await connection.execute(
        `DELETE cs FROM customer_sessions cs
         JOIN service_access_tokens at ON at.id = cs.access_token_id
         WHERE at.service_id IN (${placeholders})`,
        params
    );
    await connection.execute(
        `DELETE FROM service_details WHERE service_id IN (${placeholders})`,
        params
    );
    await connection.execute(
        `DELETE FROM service_logs WHERE service_id IN (${placeholders})`,
        params
    );
    await connection.execute(
        `DELETE FROM service_access_tokens WHERE service_id IN (${placeholders})`,
        params
    );
    await connection.execute(
        `DELETE FROM services WHERE id IN (${placeholders})`,
        params
    );
}

module.exports = { deleteServiceRecords };
