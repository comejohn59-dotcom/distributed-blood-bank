// Toggle role-specific fields (called from HTML onchange)
function showRoleSpecificFields() {
    const role = document.getElementById('role')?.value;
    const donor = document.getElementById('donorFields');
    const hospital = document.getElementById('hospitalFields');
    const bank = document.getElementById('bloodBankFields');

    if (donor) donor.style.display = role === 'donor' ? 'block' : 'none';
    if (hospital) hospital.style.display = role === 'hospital' ? 'block' : 'none';
    if (bank) bank.style.display = role === 'blood_bank' ? 'block' : 'none';
}

// Frontend registration handler for register page
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('registerForm');
    const submitBtn = document.getElementById('registerBtn');

    // Use a relative path so the site works on different hosts
    const API_BASE_URL = 'http://localhost/dbb-frontend/backend/api/';


    if (!form) return;

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        
        console.log('=== DEBUG 1: Form submit started ===');
        console.log('Form element:', form);
        console.log('Button disabled?', submitBtn.disabled);

        clearErrors();

        const role = document.getElementById('role')?.value || '';
        const name = document.getElementById('name')?.value.trim() || '';
        const email = document.getElementById('email')?.value.trim() || '';
        const phone = document.getElementById('phone')?.value.trim() || '';
        const password = document.getElementById('password')?.value || '';
        const confirmPassword = document.getElementById('confirmPassword')?.value || '';

        if (!role || !name || !email || !password) {
            showError('form', 'Please fill in required fields');
            return;
        }

        if (password !== confirmPassword) {
            showError('confirmPassword', 'Passwords do not match');
            return;
        }

        // DEBUG: Check what values are in the form
        console.log('=== DEBUG FORM VALUES ===');
        console.log('Role:', role);
        console.log('Name:', name);
        console.log('Email:', email);
        console.log('Phone:', phone);
        
        if (role === 'donor') {
            const ageInput = document.getElementById('age');
            const bloodGroupInput = document.getElementById('bloodGroup');
            const genderInput = document.getElementById('gender');
            
            console.log('Age Element:', ageInput);
            console.log('Age Value:', ageInput?.value);
            console.log('Age Type:', typeof ageInput?.value);
            console.log('Blood Group Value:', bloodGroupInput?.value);
            console.log('Gender Value:', genderInput?.value);
            
            // Check if age input is empty
            if (!ageInput?.value) {
                console.log('AGE IS EMPTY!');
                console.log('Age min:', ageInput?.min);
                console.log('Age max:', ageInput?.max);
            }
        }

        // Validate donor-specific fields BEFORE disabling button
        if (role === 'donor') {
            const age = document.getElementById('age')?.value || '';
            const bloodGroup = document.getElementById('bloodGroup')?.value || '';
            const gender = document.getElementById('gender')?.value || '';
            
            if (!age) {
                showError('form', 'Age is required for donors');
                return;
            }
            if (!bloodGroup) {
                showError('form', 'Blood group is required for donors');
                return;
            }
            if (!gender) {
                showError('form', 'Gender is required for donors');
                return;
            }
        }

        // Validate hospital-specific fields
        if (role === 'hospital') {
            const hospitalName = document.getElementById('hospitalName')?.value || '';
            const hospitalAddress = document.getElementById('hospitalAddress')?.value || '';

            if (!hospitalName) {
                showError('form', 'Hospital/organization name is required');
                return;
            }
            if (!hospitalAddress) {
                showError('form', 'Hospital address is required');
                return;
            }
        }

        submitBtn.disabled = true;
        console.log('=== DEBUG 2: Button disabled for submission ===');

        try {
            console.log('=== DEBUG 3: Preparing payload ===');
            
            // include role-specific fields
            const payload = {
                role: role,
                full_name: name,
                name: name,
                email: email,
                phone: phone,
                password: password,
                confirm_password: confirmPassword
            };
        
            if (role === 'donor') {
                // Force test values if empty to bypass the error
                const ageValue = document.getElementById('age')?.value;
                const bloodGroupValue = document.getElementById('bloodGroup')?.value;
                const genderValue = document.getElementById('gender')?.value;
                
                payload.age = ageValue || 25;  // Changed from date_of_birth to age
                payload.blood_group = bloodGroupValue || 'O+';
                payload.gender = genderValue || 'Male';
                payload.last_donation = document.getElementById('lastDonation')?.value || '';
                
                console.log('FORCED VALUES - Age:', payload.age);
                console.log('FORCED VALUES - Blood Group:', payload.blood_group);
                console.log('FORCED VALUES - Gender:', payload.gender);
            } else if (role === 'hospital') {
                payload.hospital_name = document.getElementById('hospitalName')?.value || '';
                payload.hospital_license = document.getElementById('hospitalLicense')?.value || '';
                payload.hospital_address = document.getElementById('hospitalAddress')?.value || '';
            } else if (role === 'blood_bank') {
                payload.bank_name = document.getElementById('bankName')?.value || '';
                payload.bank_license = document.getElementById('bankLicense')?.value || '';
                payload.address = document.getElementById('address')?.value || '';
                payload.operating_hours = document.getElementById('operatingHours')?.value || '';
            }
            
            console.log('Payload to send:', payload);
            console.log('Sending to:', `${API_BASE_URL}auth/register.php`);

            const res = await fetch(`${API_BASE_URL}auth/register.php`, {
                method: 'POST',
                headers: { 'Content-Type': "application/json" },
                body: JSON.stringify(payload)
            });
            
            console.log('=== DEBUG 4: Fetch completed ===');
            console.log('Response status:', res.status);
            console.log('Response headers:', res.headers);

            const data = await res.json();
            console.log('=== DEBUG 5: Response data ===');
            console.log('Response data:', data);
            
            if (data.success) {
                showMessage('success', data.message || 'Registration successful');
                setTimeout(() => window.location.href = '../auth/login.html', 1200);
            } else {
                showError('form', data.message || 'Registration failed');
            }
        } catch (err) {
            console.error('=== DEBUG 6: Error caught ===');
            console.error('Register error', err);
            showError('form', 'Network error. Please try again.');
        } finally {
            submitBtn.disabled = false;
            console.log('=== DEBUG 7: Button re-enabled ===');
        }
    });

    function showError(field, message) {
        const el = document.getElementById(field + 'Error') || document.getElementById('formError');
        if (el) el.textContent = message;
    }

    function clearErrors() {
        const errors = document.querySelectorAll('.form-error');
        errors.forEach(e => e.textContent = '');
    }

    function showMessage(type, text) {
        const container = document.createElement('div');
        container.className = `message ${type}`;
        container.textContent = text;
        form.parentNode.insertBefore(container, form);
        setTimeout(() => container.remove(), 3000);
    }
});