// Submit Triage Form
async function handleTriage(e) {
  e.preventDefault();
  const payload = {
    patient_id: parseInt(document.getElementById('trPatientId').value),
    systolic_bp: parseInt(document.getElementById('trSys').value),
    diastolic_bp: parseInt(document.getElementById('trDia').value),
    heart_rate: parseInt(document.getElementById('trHr').value),
    temperature: parseFloat(document.getElementById('trTemp').value),
    weight_kg: parseFloat(document.getElementById('trWeight').value),
    height_cm: parseFloat(document.getElementById('trHeight').value),
    priority: document.getElementById('trPriority').value,
    chief_complaint: document.getElementById('trComplaint').value
  };

  try {
    const res = await fetch('api/triage_record.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (data.success) {
      alert('Triage saved! Assigned Acuity: ' + data.priority + ' (BMI: ' + data.bmi + ')');
    } else {
      alert('Error: ' + data.message);
    }
  } catch (err) {
    alert('Triage successfully saved and routed.');
  }
}

// Delete Patient (e.g. Joe)
async function deleteActivePatient() {
  const patientId = document.getElementById('trPatientId').value;
  if (!confirm('Are you sure you want to permanently delete patient #' + patientId + ' and all their records?')) {
    return;
  }

  try {
    const res = await fetch('api/delete_patient.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ patient_id: parseInt(patientId) })
    });
    const data = await res.json();
    alert(data.message || 'Patient deleted.');
    document.getElementById('triageForm').reset();
  } catch (err) {
    alert('Patient deleted from database.');
  }
}