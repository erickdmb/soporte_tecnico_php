const base = db.getSiblingDB(process.env.MONGO_INITDB_DATABASE);
base.createUser({
    user: process.env.MONGO_APP_USER,
    pwd: process.env.MONGO_APP_PASSWORD,
    roles: [{role: 'readWrite', db: base.getName()}]
});
base.createCollection('historial_ticket', {
    validator: {$jsonSchema: {
        bsonType: 'object', required: ['ticket_id', 'historial', 'observaciones'],
        properties: {
            ticket_id: {bsonType: ['int', 'long'], minimum: 1},
            historial: {bsonType: 'array'}, observaciones: {bsonType: 'array'}
        }
    }}
});
base.historial_ticket.createIndex({ticket_id: 1}, {unique: true});
base.createCollection('evidencias');
base.evidencias.createIndex({ticket_id: 1});
base.historial_ticket.insertMany([
    {ticket_id: 1, historial: [{fecha: new Date(), accion: 'REGISTRO', detalle: 'La laptop no enciende al conectar el cargador.', usuario: 'instalación'}], observaciones: []},
    {ticket_id: 2, historial: [{fecha: new Date(), accion: 'REGISTRO', detalle: 'La impresora muestra líneas en las páginas.', usuario: 'instalación'}], observaciones: []}
]);
