# SmartClinic+ — SENG205 Interactive PHP/MySQL Prototype

SmartClinic+ is an intelligent outpatient Healthcare Management System prototype based on the SENG205 project outline. It supports the complete patient journey from appointment booking to follow-up while connecting patients, doctors, nurses/staff and administrators through one secure interface.

## Implemented project objectives

**Digital patient experience & appointment management**
- Patient registration and secure login
- Book/cancel appointments using live doctor availability
- Transaction-safe booking with MySQL `GET_LOCK()` to reduce double-booking race conditions
- Smart check-in queue and queue position
- In-app automated notifications for appointments, prescriptions, labs and tasks

**Clinical workflow & staff coordination**
- Doctor dashboard and schedule
- Electronic Health Record consultation notes
- E-prescription creation and patient viewing
- Staff task assignment and status tracking

**Medical records & data management**
- Centralized patient health records
- Lab/test status and results
- Role-based authorization at page and action level
- PDO prepared statements, CSRF protection, password hashing, session hardening and audit logs

**Analytics & patient engagement**
- Administrative dashboard
- Patient flow, average wait time, appointment demand and doctor utilization indicators
- Loyalty points data model and patient dashboard display
- Transparent prototype peak-hour analytics

## Local setup with XAMPP

1. Copy the `smartclinic_plus` folder into `C:\xampp\htdocs\`.
2. Start **Apache** and **MySQL** in XAMPP.
3. Open phpMyAdmin at `http://localhost/phpmyadmin`.
4. Import `database/smartclinic_plus.sql`.
5. Open `http://localhost/smartclinic_plus/`.

The default database settings are:
- Host: `127.0.0.1`
- Port: `3306`
- Database: `smartclinic_plus`
- User: `root`
- Password: blank (standard XAMPP default)

Change `config/config.php` if your setup is different. You may also use environment variables `SMARTCLINIC_DB_HOST`, `SMARTCLINIC_DB_PORT`, `SMARTCLINIC_DB_NAME`, `SMARTCLINIC_DB_USER`, and `SMARTCLINIC_DB_PASS`.

## Demo accounts

All demo accounts use password: **Password123!**

- Administrator: `admin@smartclinic.test`
- Doctor: `doctor@smartclinic.test`
- Nurse / staff: `nurse@smartclinic.test`
- Patient: `patient@smartclinic.test`

## High Distinction prototype evidence

The prototype is designed to provide visible evidence for the project documentation and rubric:
- **Use cases:** patient booking/check-in, doctor consultation/prescription, nurse task/lab update, admin analytics.
- **Sequence diagram candidates:** appointment booking with availability check and database lock; smart check-in; e-prescription workflow.
- **Repository:** use Git/GitHub and commit by feature (`feat: appointment booking`, `feat: smart queue`, etc.).
- **Project management:** create a board with Backlog → To Do → In Progress → Testing → Done.
- **Testing/QA:** document functional, validation, security and concurrency test cases; capture screenshots of successful and rejected double-booking attempts.
- **Requirement prioritisation:** MoSCoW is appropriate: Must = booking/RBAC/EHR; Should = queue/prescriptions/labs/tasks; Could = loyalty/advanced forecasting; Won't for this prototype = real SMS gateway/pharmacy API.
- **Security:** server-side authorization, CSRF tokens, hashed passwords, prepared SQL, session cookie controls and audit trail.

## Prototype limitations

This is a university prototype rather than a production medical system. SMS/email delivery, pharmacy integration, production-grade identity verification, encryption key management, clinical interoperability (for example FHIR), backups, full Australian health privacy compliance, penetration testing and validated predictive models would require additional production engineering and governance.
