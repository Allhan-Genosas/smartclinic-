<?php
require_once __DIR__ . '/includes/functions.php';

if (current_user()) {
    redirect('dashboard.php');
}

$pageTitle = 'Intelligent Healthcare Management';
include __DIR__ . '/includes/header.php';
?>

<main class="landing">
    <section class="landing-hero">
        <div>
            <span class="kicker">Modern outpatient care, connected</span>
            <h1>One intelligent platform for the complete patient journey.</h1>
            <p>SmartClinic+ connects appointment booking, smart queues, clinical records, e-prescriptions, diagnostics, staff coordination and predictive insights in one secure workflow.</p>
            <div class="quick-actions">
                <a class="btn btn-primary" href="register.php">Create patient account</a>
                <a class="btn btn-ghost" href="login.php">Open demo workspace</a>
            </div>
            <p class="small" style="margin-top:18px">Designed for patients, doctors, nurses and clinic administrators.</p>
        </div>
        <div class="hero-ui">
            <div class="mock-window">
                <div class="mock-top">
                    <i></i><i></i><i></i>
                </div>
                <div class="mock-grid">
                    <div class="mock-card">
                        <small>Patients today</small>
                        <b>48</b>
                    </div>
                    <div class="mock-card">
                        <small>Avg. wait</small>
                        <b>12m</b>
                    </div>
                    <div class="mock-card">
                        <small>Doctor utilization</small>
                        <b>82%</b>
                    </div>
                    <div class="mock-card">
                        <small>Queue status</small>
                        <b style="color:#0fa3a3">Live</b>
                    </div>
                </div>
                <div class="card" style="margin-top:12px;box-shadow:none">
                    <strong>Next patient</strong>
                    <div class="list-item">
                        <span class="queue-number">A04</span>
                        <span>
                            <b>Alex Morgan</b>
                            <small>General consultation · Dr. Chen</small>
                        </span>
                        <span class="badge badge-success">Ready</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="features" id="features">
        <h2>Built around the project objectives</h2>
        <p class="section-lead">The prototype implements the key digital patient, clinical workflow, data management and analytics capabilities required by the SENG205 SmartClinic+ brief.</p>
        <div class="grid grid-3">
            <article class="feature-card">
                <span>◫</span>
                <h3>Digital appointments</h3>
                <p>Patients can book, reschedule and cancel appointments with live doctor availability and conflict prevention.</p>
            </article>
            <article class="feature-card">
                <span>≡</span>
                <h3>Smart queue</h3>
                <p>Check-ins, waiting position, doctor status, delays and emergency priority are presented in a dynamic workflow.</p>
            </article>
            <article class="feature-card">
                <span>♡</span>
                <h3>Electronic health records</h3>
                <p>Medical history, consultation notes, allergies and diagnoses are centralized with role-based privacy controls.</p>
            </article>
            <article class="feature-card">
                <span>Rx</span>
                <h3>E-prescriptions</h3>
                <p>Doctors can issue digital prescriptions that appear immediately in the patient's secure workspace.</p>
            </article>
            <article class="feature-card">
                <span>⌁</span>
                <h3>Lab integration</h3>
                <p>Staff can record test status and results, while authorized doctors and patients can track outcomes.</p>
            </article>
            <article class="feature-card">
                <span>▥</span>
                <h3>Operational analytics</h3>
                <p>Administrators see patient flow, wait time, appointments, doctor utilization and peak-hour indicators.</p>
            </article>
        </div>
    </section>

    <section class="workflow" id="workflow">
        <h2>From booking to follow-up</h2>
        <p class="section-lead">A simple, auditable workflow reduces fragmentation between patients and clinic staff.</p>
        <div class="grid grid-4">
            <div class="step">
                <span class="step-num">1</span>
                <div>
                    <b>Book</b>
                    <p class="muted small">Choose doctor and available slot.</p>
                </div>
            </div>
            <div class="step">
                <span class="step-num">2</span>
                <div>
                    <b>Check in</b>
                    <p class="muted small">Join the smart queue.</p>
                </div>
            </div>
            <div class="step">
                <span class="step-num">3</span>
                <div>
                    <b>Consult</b>
                    <p class="muted small">Update EHR, tasks and tests.</p>
                </div>
            </div>
            <div class="step">
                <span class="step-num">4</span>
                <div>
                    <b>Follow up</b>
                    <p class="muted small">Receive prescriptions and reminders.</p>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>