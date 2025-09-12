// ---------------- MODAL CONTROL ----------------
function openModal(id){ document.getElementById(id).style.display='block'; }
function closeModal(id){ document.getElementById(id).style.display='none'; }
window.onclick = function(event){
    ['studentModal','medicalModal','transportModal'].forEach(id=>{
        if(event.target==document.getElementById(id)) closeModal(id);
    });
}

// ---------------- SEARCH ----------------
function searchStudents(q){
    let table=document.getElementById('studentsTable');
    Array.from(table.getElementsByTagName('tr')).forEach((row,i)=>{
        if(i==0) return;
        row.style.display = row.textContent.toLowerCase().includes(q.toLowerCase()) ? '' : 'none';
    });
}

// ---------------- EDIT STUDENT ----------------
function editStudent(data){
    openModal('studentModal');
    document.getElementById('studentModalTitle').innerText='Edit Student';
    Object.keys(data).forEach(key=>{
        let el=document.getElementById(key);
        if(el) el.value=data[key];
    });
}

// ---------------- OPEN MEDICAL ----------------
function openMedical(student_id){
    fetch('student_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=fetch_medical&id=${student_id}`
    })
    .then(res => res.json())
    .then(data => {
        openModal('medicalModal');
        document.getElementById('med_student_id').value = student_id;
        document.getElementById('blood_type').value = data?.blood_type ?? '';
        document.getElementById('allergies').value = data?.allergies ?? '';
        document.getElementById('chronic_conditions').value = data?.chronic_conditions ?? '';
        document.getElementById('medications').value = data?.medications ?? '';
    }).catch(err => { console.error(err); alert('Error loading medical info'); });
}

// ---------------- OPEN TRANSPORT ----------------
function openTransport(student_id){
    fetch('student_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=fetch_transport&id=${student_id}`
    })
    .then(res => res.json())
    .then(data => {
        openModal('transportModal');
        document.getElementById('trans_student_id').value = student_id;
        document.getElementById('transport_mode').value = data?.transport_mode ?? '';
        document.getElementById('pickup_point').value = data?.pickup_point ?? '';
        document.getElementById('dropoff_point').value = data?.dropoff_point ?? '';
        document.getElementById('transport_status').value = data?.transport_status ?? 'active';
    }).catch(err => { console.error(err); alert('Error loading transport info'); });
}

// ---------------- AJAX SUBMISSION ----------------
function ajaxForm(formId){
    document.getElementById(formId).addEventListener('submit', function(e){
        e.preventDefault();
        let form = this;
        let data = new FormData(form);

        fetch('student_actions.php', { method:'POST', body:data })
        .then(res => res.text())
        .then(resp => { alert(resp); location.reload(); })
        .catch(err => { console.error(err); alert('AJAX error'); });
    });
}

// Attach to forms
['studentForm','medicalForm','transportForm'].forEach(ajaxForm);
