# CallowayPharmacy

A comprehensive pharmacy management system for managing medications and prescriptions.

## Features

- **Medication Management**: Add, view, and manage medication inventory
- **Prescription Processing**: Create and track prescriptions
- **User-friendly Interface**: Clean and intuitive web interface
- **RESTful API**: Backend API for all operations

## Technology Stack

- **Frontend**: HTML5, CSS3, JavaScript (Vanilla)
- **Backend**: Node.js, Express.js
- **Deployment**: Docker, Docker Compose

## Prerequisites

- Node.js 18 or higher
- Docker and Docker Compose (for containerized deployment)

## Installation

### Option 1: Local Development

1. Clone the repository:
```bash
git clone https://github.com/celizamariecruz-svg/CallowayPharmacy.git
cd CallowayPharmacy
```

2. Install dependencies:
```bash
npm install
```

3. Start the development server:
```bash
npm start
```

4. Open your browser and navigate to:
```
http://localhost:3000
```

### Option 2: Docker Deployment

1. Clone the repository:
```bash
git clone https://github.com/celizamariecruz-svg/CallowayPharmacy.git
cd CallowayPharmacy
```

2. Build and run with Docker Compose:
```bash
docker-compose up -d
```

3. Access the application:
```
http://localhost:3000
```

## API Endpoints

### Medications
- `GET /api/medications` - Get all medications
- `GET /api/medications/:id` - Get medication by ID
- `POST /api/medications` - Add new medication
- `PUT /api/medications/:id` - Update medication
- `DELETE /api/medications/:id` - Delete medication

### Prescriptions
- `GET /api/prescriptions` - Get all prescriptions
- `POST /api/prescriptions` - Create new prescription
- `PUT /api/prescriptions/:id` - Update prescription status

### Health Check
- `GET /api/health` - Check API health status

## Usage

1. **Managing Medications**:
   - Click on the "Medications" tab
   - Click "+ Add Medication" to add new medications
   - View all medications in the inventory
   - Delete medications as needed

2. **Managing Prescriptions**:
   - Click on the "Prescriptions" tab
   - Click "+ New Prescription" to create a prescription
   - Select patient name, medication, and quantity
   - Track prescription status (Processing → Ready → Completed)

## Deployment

### Deploy to Production

1. Build the Docker image:
```bash
docker build -t calloway-pharmacy .
```

2. Run the container:
```bash
docker run -d -p 3000:3000 calloway-pharmacy
```

### Environment Variables

- `PORT` - Server port (default: 3000)
- `NODE_ENV` - Environment mode (development/production)

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

MIT License - see LICENSE file for details

## Contact

For questions or support, please contact the development team.
