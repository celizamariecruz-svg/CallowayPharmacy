const express = require('express');
const bodyParser = require('body-parser');
const cors = require('cors');
const path = require('path');

const app = express();
const PORT = process.env.PORT || 3000;

// Middleware
app.use(cors());
app.use(bodyParser.json());
app.use(bodyParser.urlencoded({ extended: true }));
app.use(express.static('public'));

// In-memory data storage (replace with database in production)
let medications = [
  { id: 1, name: 'Aspirin', quantity: 500, price: 5.99, description: 'Pain reliever and fever reducer' },
  { id: 2, name: 'Ibuprofen', quantity: 300, price: 8.99, description: 'Anti-inflammatory medication' },
  { id: 3, name: 'Amoxicillin', quantity: 200, price: 15.99, description: 'Antibiotic medication' }
];

let prescriptions = [
  { id: 1, patientName: 'John Doe', medicationId: 1, quantity: 30, status: 'Ready', date: '2026-02-15' },
  { id: 2, patientName: 'Jane Smith', medicationId: 3, quantity: 20, status: 'Processing', date: '2026-02-18' }
];

let medicationIdCounter = 3;
let prescriptionIdCounter = 2;

// API Routes
app.get('/api/health', (req, res) => {
  res.json({ status: 'healthy', message: 'Calloway Pharmacy API is running' });
});

app.get('/api/medications', (req, res) => {
  res.json(medications);
});

app.get('/api/medications/:id', (req, res) => {
  const medication = medications.find(m => m.id === parseInt(req.params.id));
  if (medication) {
    res.json(medication);
  } else {
    res.status(404).json({ error: 'Medication not found' });
  }
});

app.post('/api/medications', (req, res) => {
  const newMedication = {
    id: ++medicationIdCounter,
    name: req.body.name,
    quantity: req.body.quantity,
    price: req.body.price,
    description: req.body.description
  };
  medications.push(newMedication);
  res.status(201).json(newMedication);
});

app.put('/api/medications/:id', (req, res) => {
  const medication = medications.find(m => m.id === parseInt(req.params.id));
  if (medication) {
    medication.name = req.body.name !== undefined ? req.body.name : medication.name;
    medication.quantity = req.body.quantity !== undefined ? req.body.quantity : medication.quantity;
    medication.price = req.body.price !== undefined ? req.body.price : medication.price;
    medication.description = req.body.description !== undefined ? req.body.description : medication.description;
    res.json(medication);
  } else {
    res.status(404).json({ error: 'Medication not found' });
  }
});

app.delete('/api/medications/:id', (req, res) => {
  const index = medications.findIndex(m => m.id === parseInt(req.params.id));
  if (index !== -1) {
    medications.splice(index, 1);
    res.json({ message: 'Medication deleted' });
  } else {
    res.status(404).json({ error: 'Medication not found' });
  }
});

app.get('/api/prescriptions', (req, res) => {
  res.json(prescriptions);
});

app.post('/api/prescriptions', (req, res) => {
  const newPrescription = {
    id: ++prescriptionIdCounter,
    patientName: req.body.patientName,
    medicationId: req.body.medicationId,
    quantity: req.body.quantity,
    status: 'Processing',
    date: new Date().toISOString().split('T')[0]
  };
  prescriptions.push(newPrescription);
  res.status(201).json(newPrescription);
});

app.put('/api/prescriptions/:id', (req, res) => {
  const prescription = prescriptions.find(p => p.id === parseInt(req.params.id));
  if (prescription) {
    prescription.status = req.body.status !== undefined ? req.body.status : prescription.status;
    prescription.quantity = req.body.quantity !== undefined ? req.body.quantity : prescription.quantity;
    res.json(prescription);
  } else {
    res.status(404).json({ error: 'Prescription not found' });
  }
});

// Serve frontend
app.get('/', (req, res) => {
  res.sendFile(path.join(__dirname, 'public', 'index.html'));
});

app.listen(PORT, () => {
  console.log(`Calloway Pharmacy server is running on port ${PORT}`);
});
