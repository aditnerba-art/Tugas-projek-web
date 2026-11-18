const test = require('node:test');
const assert = require('node:assert/strict');
const { technicianScope, isAssignedService } = require('../lib/technician-scope');

test('technician SQL scope restricts records to the signed-in technician', () => {
    assert.deepEqual(technicianScope({ id: 27, role: 'teknisi' }, 's'), {
        clause: ' AND s.teknisi_id = ?', params: [27]
    });
    assert.deepEqual(technicianScope({ id: 4, role: 'admin' }, 's'), { clause: '', params: [] });
});

test('technician can access only services assigned to their account', () => {
    assert.equal(isAssignedService({ teknisi_id: 27 }, { id: 27, role: 'teknisi' }), true);
    assert.equal(isAssignedService({ teknisi_id: 28 }, { id: 27, role: 'teknisi' }), false);
    assert.equal(isAssignedService({ teknisi_id: null }, { id: 27, role: 'teknisi' }), false);
    assert.equal(isAssignedService({ teknisi_id: 28 }, { id: 4, role: 'admin' }), true);
});
