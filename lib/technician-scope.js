function technicianScope(user, alias = '') {
    if (!user || user.role !== 'teknisi') return { clause: '', params: [] };
    if (alias && !/^[a-zA-Z_][a-zA-Z0-9_]*$/.test(alias)) throw new TypeError('Alias SQL tidak valid');
    return { clause: ` AND ${alias ? `${alias}.` : ''}teknisi_id = ?`, params: [Number(user.id)] };
}

function isAssignedService(service, user) {
    if (!user || user.role !== 'teknisi') return true;
    return Number.isInteger(Number(service?.teknisi_id)) && Number(service.teknisi_id) === Number(user.id);
}

module.exports = { technicianScope, isAssignedService };
