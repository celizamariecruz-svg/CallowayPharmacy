const API_BASE = window.location.origin;

// Tab management
function showTab(tabName) {
    const tabs = document.querySelectorAll('.tab-content');
    const buttons = document.querySelectorAll('.tab-button');
    
    tabs.forEach(tab => tab.classList.remove('active'));
    buttons.forEach(btn => btn.classList.remove('active'));
    
    document.getElementById(`${tabName}-tab`).classList.add('active');
    event.target.classList.add('active');
    
    if (tabName === 'medications') {
        loadMedications();
    } else if (tabName === 'prescriptions') {
        loadPrescriptions();
        loadMedicationsForDropdown();
    }
}

// Medication Management
function showAddMedicationForm() {
    document.getElementById('add-medication-form').style.display = 'block';
}

function hideAddMedicationForm() {
    document.getElementById('add-medication-form').style.display = 'none';
    document.getElementById('add-medication-form').querySelector('form').reset();
}

async function loadMedications() {
    try {
        const response = await fetch(`${API_BASE}/api/medications`);
        const medications = await response.json();
        
        const container = document.getElementById('medications-list');
        container.innerHTML = medications.map(med => `
            <div class="card">
                <h3>${med.name}</h3>
                <div class="card-content">
                    <div class="card-item">
                        <label>Quantity:</label>
                        <span>${med.quantity}</span>
                    </div>
                    <div class="card-item">
                        <label>Price:</label>
                        <span>$${med.price}</span>
                    </div>
                    <div class="card-item">
                        <label>Description:</label>
                        <span>${med.description}</span>
                    </div>
                </div>
                <div class="card-actions">
                    <button class="btn btn-danger" onclick="deleteMedication(${med.id})">Delete</button>
                </div>
            </div>
        `).join('');
    } catch (error) {
        console.error('Error loading medications:', error);
    }
}

async function addMedication(event) {
    event.preventDefault();
    
    const medication = {
        name: document.getElementById('med-name').value,
        quantity: parseInt(document.getElementById('med-quantity').value),
        price: parseFloat(document.getElementById('med-price').value),
        description: document.getElementById('med-description').value
    };
    
    try {
        const response = await fetch(`${API_BASE}/api/medications`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(medication)
        });
        
        if (response.ok) {
            hideAddMedicationForm();
            loadMedications();
        }
    } catch (error) {
        console.error('Error adding medication:', error);
    }
}

async function deleteMedication(id) {
    if (confirm('Are you sure you want to delete this medication?')) {
        try {
            const response = await fetch(`${API_BASE}/api/medications/${id}`, {
                method: 'DELETE'
            });
            
            if (response.ok) {
                loadMedications();
            }
        } catch (error) {
            console.error('Error deleting medication:', error);
        }
    }
}

// Prescription Management
function showAddPrescriptionForm() {
    document.getElementById('add-prescription-form').style.display = 'block';
}

function hideAddPrescriptionForm() {
    document.getElementById('add-prescription-form').style.display = 'none';
    document.getElementById('add-prescription-form').querySelector('form').reset();
}

async function loadPrescriptions() {
    try {
        const response = await fetch(`${API_BASE}/api/prescriptions`);
        const prescriptions = await response.json();
        
        const medicationsResponse = await fetch(`${API_BASE}/api/medications`);
        const medications = await medicationsResponse.json();
        
        const container = document.getElementById('prescriptions-list');
        container.innerHTML = prescriptions.map(pres => {
            const medication = medications.find(m => m.id === pres.medicationId);
            const statusClass = pres.status.toLowerCase().replace(' ', '-');
            
            return `
                <div class="card">
                    <h3>Prescription #${pres.id}</h3>
                    <div class="card-content">
                        <div class="card-item">
                            <label>Patient:</label>
                            <span>${pres.patientName}</span>
                        </div>
                        <div class="card-item">
                            <label>Medication:</label>
                            <span>${medication ? medication.name : 'Unknown'}</span>
                        </div>
                        <div class="card-item">
                            <label>Quantity:</label>
                            <span>${pres.quantity}</span>
                        </div>
                        <div class="card-item">
                            <label>Date:</label>
                            <span>${pres.date}</span>
                        </div>
                        <div class="card-item">
                            <label>Status:</label>
                            <span class="status-badge status-${statusClass}">${pres.status}</span>
                        </div>
                    </div>
                    <div class="card-actions">
                        ${pres.status === 'Processing' ? `
                            <button class="btn btn-success" onclick="updatePrescriptionStatus(${pres.id}, 'Ready')">Mark Ready</button>
                        ` : ''}
                        ${pres.status === 'Ready' ? `
                            <button class="btn btn-success" onclick="updatePrescriptionStatus(${pres.id}, 'Completed')">Mark Completed</button>
                        ` : ''}
                    </div>
                </div>
            `;
        }).join('');
    } catch (error) {
        console.error('Error loading prescriptions:', error);
    }
}

async function loadMedicationsForDropdown() {
    try {
        const response = await fetch(`${API_BASE}/api/medications`);
        const medications = await response.json();
        
        const select = document.getElementById('prescription-medication');
        select.innerHTML = '<option value="">Select Medication</option>' + 
            medications.map(med => `<option value="${med.id}">${med.name}</option>`).join('');
    } catch (error) {
        console.error('Error loading medications for dropdown:', error);
    }
}

async function addPrescription(event) {
    event.preventDefault();
    
    const prescription = {
        patientName: document.getElementById('patient-name').value,
        medicationId: parseInt(document.getElementById('prescription-medication').value),
        quantity: parseInt(document.getElementById('prescription-quantity').value)
    };
    
    try {
        const response = await fetch(`${API_BASE}/api/prescriptions`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(prescription)
        });
        
        if (response.ok) {
            hideAddPrescriptionForm();
            loadPrescriptions();
        }
    } catch (error) {
        console.error('Error adding prescription:', error);
    }
}

async function updatePrescriptionStatus(id, status) {
    try {
        const response = await fetch(`${API_BASE}/api/prescriptions/${id}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ status })
        });
        
        if (response.ok) {
            loadPrescriptions();
        }
    } catch (error) {
        console.error('Error updating prescription status:', error);
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    loadMedications();
});
