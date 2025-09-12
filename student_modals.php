<!-- Student Edit Modal -->
<div id="studentModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closeModal('studentModal')">&times;</span>
    <h3>Edit Student Info</h3>
    <form id="studentForm" enctype="multipart/form-data">
      <input type="hidden" name="action" value="save_student">
      <input type="hidden" name="user_id" id="student_id">

      <div class="col">
        <label>Username</label>
        <input type="text" name="username" id="username">
        <label>First Name</label>
        <input type="text" name="firstName" id="firstName">
        <label>Last Name</label>
        <input type="text" name="lastName" id="lastName">
        <label>Gender</label>
        <select name="gender" id="gender">
          <option value="Male">Male</option>
          <option value="Female">Female</option>
        </select>
        <label>ID Number</label>
        <input type="text" name="IDNumber" id="IDNumber">
        <label>Phone</label>
        <input type="text" name="phone" id="phone">
      </div>

      <div class="col">
        <label>Email</label>
        <input type="email" name="email" id="email">
        <label>Status</label>
        <select name="status" id="status">
          <option value="Active">Active</option>
          <option value="Inactive">Inactive</option>
        </select>
        <label>Password (leave blank to keep current)</label>
        <input type="password" name="password">
        <label>Address 1</label>
        <input type="text" name="address1" id="address1">
        <label>Street Name</label>
        <input type="text" name="streetName" id="streetName">
        <label>Postal Code</label>
        <input type="text" name="postalCode" id="postalCode">
        <label>District</label>
        <input type="text" name="district" id="district">
        <label>Country</label>
        <input type="text" name="country" id="country">
        <label>Document Upload</label>
        <input type="file" name="document">
      </div>

      <button type="submit">Save</button>
    </form>
  </div>
</div>

<!-- Medical Modal -->
<div id="medicalModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closeModal('medicalModal')">&times;</span>
    <h3>Medical Info</h3>
    <form id="medicalForm">
      <input type="hidden" name="action" value="save_medical">
      <input type="hidden" name="student_id" id="med_student_id">
      <label>Blood Type</label>
      <input type="text" name="blood_type" id="blood_type">
      <label>Allergies</label>
      <input type="text" name="allergies" id="allergies">
      <label>Chronic Conditions</label>
      <input type="text" name="chronic_conditions" id="chronic_conditions">
      <label>Medications</label>
      <input type="text" name="medications" id="medications">
      <button type="submit">Save</button>
    </form>
  </div>
</div>

<!-- Transport Modal -->
<div id="transportModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closeModal('transportModal')">&times;</span>
    <h3>Transport Info</h3>
    <form id="transportForm">
      <input type="hidden" name="action" value="save_transport">
      <input type="hidden" name="student_id" id="trans_student_id">
      <label>Transport Mode</label>
      <input type="text" name="transport_mode" id="transport_mode">
      <label>Pickup Point</label>
      <input type="text" name="pickup_point" id="pickup_point">
      <label>Drop-off Point</label>
      <input type="text" name="dropoff_point" id="dropoff_point">
      <label>Status</label>
      <input type="text" name="transport_status" id="transport_status">
      <button type="submit">Save</button>
    </form>
  </div>
</div>

<script>
function closeModal(id){ document.getElementById(id).style.display='none'; }
</script>
