document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.querySelector('.sidebar');
    
    // Toggle Mobile Sidebar
    document.getElementById('menuBtn')?.addEventListener('click', () => {
        sidebar?.classList.toggle('open');
    });

    // Open Modals
    document.querySelectorAll('[data-modal]').forEach(button => {
        button.addEventListener('click', () => {
            document.getElementById(button.dataset.modal)?.classList.add('open');
        });
    });

    // Close Modals via Close Button
    document.querySelectorAll('[data-close-modal]').forEach(button => {
        button.addEventListener('click', () => {
            button.closest('.modal')?.classList.remove('open');
        });
    });

    // Close Modals via Backdrop Click
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.remove('open');
            }
        });
    });

    // Dynamic Doctor Availability Slot Loader
    const doctor = document.getElementById('doctor_id');
    const date = document.getElementById('appointment_date');
    const slot = document.getElementById('appointment_time');

    async function loadSlots() {
        if (!doctor || !date || !slot || !doctor.value || !date.value) return;

        slot.innerHTML = '<option>Loading...</option>';
        try {
            const response = await fetch(`api/availability.php?doctor_id=${encodeURIComponent(doctor.value)}&date=${encodeURIComponent(date.value)}`);
            const data = await response.json();

            slot.innerHTML = '<option value="">Select time</option>' + 
                data.slots.map(s => `<option value="${s.time}" ${s.available ? '' : 'disabled'}>${s.label}${s.available ? '' : ' — unavailable'}</option>`).join('');
        } catch (e) {
            slot.innerHTML = '<option value="">Unable to load slots</option>';
        }
    }

    doctor?.addEventListener('change', loadSlots);
    date?.addEventListener('change', loadSlots);

    if (doctor && date && doctor.value && date.value) {
        loadSlots();
    }

    // Confirmation Prompts
    document.querySelectorAll('[data-confirm]').forEach(element => {
        element.addEventListener('click', (e) => {
            if (!confirm(element.dataset.confirm)) {
                e.preventDefault();
            }
        });
    });
});