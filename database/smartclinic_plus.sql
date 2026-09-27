CREATE DATABASE IF NOT EXISTS smartclinic_plus CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE smartclinic_plus;

SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS audit_logs, loyalty_transactions, notifications, tasks, lab_results, prescriptions, medical_records, queue_entries, appointments, patients, doctors, users;
SET FOREIGN_KEY_CHECKS=1;

CREATE TABLE users (
 id INT AUTO_INCREMENT PRIMARY KEY,
 first_name VARCHAR(60) NOT NULL,
 last_name VARCHAR(60) NOT NULL,
 email VARCHAR(160) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 role ENUM('patient','doctor','nurse','admin') NOT NULL,
 status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE doctors (
 id INT AUTO_INCREMENT PRIMARY KEY,
 user_id INT NOT NULL UNIQUE,
 title VARCHAR(20) DEFAULT 'Dr.',
 specialty VARCHAR(100) NOT NULL,
 provider_number VARCHAR(40),
 is_available TINYINT(1) DEFAULT 1,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE patients (
 id INT AUTO_INCREMENT PRIMARY KEY,
 user_id INT NOT NULL UNIQUE,
 dob DATE NOT NULL,
 blood_type VARCHAR(5),
 emergency_contact VARCHAR(120),
 loyalty_points INT NOT NULL DEFAULT 0,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE appointments (
 id INT AUTO_INCREMENT PRIMARY KEY,
 patient_id INT NOT NULL,
 doctor_id INT NOT NULL,
 appointment_date DATE NOT NULL,
 appointment_time TIME NOT NULL,
 reason VARCHAR(500) NOT NULL,
 status ENUM('booked','confirmed','checked_in','in_consultation','completed','cancelled') NOT NULL DEFAULT 'booked',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_slot(doctor_id,appointment_date,appointment_time,status),
 INDEX idx_patient_date(patient_id,appointment_date),
 FOREIGN KEY(patient_id) REFERENCES patients(id),
 FOREIGN KEY(doctor_id) REFERENCES doctors(id)
) ENGINE=InnoDB;

CREATE TABLE queue_entries (
 id INT AUTO_INCREMENT PRIMARY KEY,
 appointment_id INT NOT NULL UNIQUE,
 queue_number INT NOT NULL,
 priority ENUM('normal','high','emergency') DEFAULT 'normal',
 status ENUM('waiting','called','in_consultation','completed','skipped') DEFAULT 'waiting',
 check_in_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 called_at DATETIME NULL,
 completed_at DATETIME NULL,
 wait_minutes INT NULL,
 FOREIGN KEY(appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE medical_records (
 id INT AUTO_INCREMENT PRIMARY KEY,
 patient_id INT NOT NULL,
 doctor_id INT NULL,
 diagnosis VARCHAR(255) NOT NULL,
 notes TEXT NOT NULL,
 allergies VARCHAR(255),
 record_date DATETIME NOT NULL,
 FOREIGN KEY(patient_id) REFERENCES patients(id),
 FOREIGN KEY(doctor_id) REFERENCES doctors(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE prescriptions (
 id INT AUTO_INCREMENT PRIMARY KEY,
 patient_id INT NOT NULL,
 doctor_id INT NOT NULL,
 medication VARCHAR(160) NOT NULL,
 dosage VARCHAR(80) NOT NULL,
 frequency VARCHAR(120) NOT NULL,
 duration_days INT NOT NULL,
 instructions TEXT,
 status ENUM('active','completed','cancelled') DEFAULT 'active',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(patient_id) REFERENCES patients(id),
 FOREIGN KEY(doctor_id) REFERENCES doctors(id)
) ENGINE=InnoDB;

CREATE TABLE lab_results (
 id INT AUTO_INCREMENT PRIMARY KEY,
 patient_id INT NOT NULL,
 test_name VARCHAR(160) NOT NULL,
 result VARCHAR(255),
 reference_range VARCHAR(160),
 status ENUM('pending','processing','ready') DEFAULT 'pending',
 ordered_at DATETIME NOT NULL,
 resulted_at DATETIME NULL,
 FOREIGN KEY(patient_id) REFERENCES patients(id)
) ENGINE=InnoDB;

CREATE TABLE tasks (
 id INT AUTO_INCREMENT PRIMARY KEY,
 assigned_to INT NOT NULL,
 assigned_by INT NOT NULL,
 patient_id INT NULL,
 title VARCHAR(160) NOT NULL,
 description TEXT,
 priority ENUM('low','normal','high','urgent') DEFAULT 'normal',
 due_at DATETIME NULL,
 status ENUM('pending','in_progress','completed') DEFAULT 'pending',
 completed_at DATETIME NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(assigned_to) REFERENCES users(id),
 FOREIGN KEY(assigned_by) REFERENCES users(id),
 FOREIGN KEY(patient_id) REFERENCES patients(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE notifications (
 id INT AUTO_INCREMENT PRIMARY KEY,
 user_id INT NOT NULL,
 title VARCHAR(160) NOT NULL,
 message VARCHAR(500) NOT NULL,
 type VARCHAR(40) DEFAULT 'info',
 is_read TINYINT(1) DEFAULT 0,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 read_at DATETIME NULL,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE loyalty_transactions (
 id INT AUTO_INCREMENT PRIMARY KEY,
 patient_id INT NOT NULL,
 points INT NOT NULL,
 reason VARCHAR(255) NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(patient_id) REFERENCES patients(id)
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
 id BIGINT AUTO_INCREMENT PRIMARY KEY,
 user_id INT NULL,
 action VARCHAR(100) NOT NULL,
 details TEXT,
 ip_address VARCHAR(45),
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_audit_user(user_id,created_at),
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- All demo accounts use Password123!
INSERT INTO users(id,first_name,last_name,email,password_hash,role) VALUES
(1,'Roege Lyon Nycko','Garcia','admin@smartclinic.test','$2y$12$pH.1gt18Xhdr0daahvFD8e9aA9nIkMyONNul.rc5PgUPIr9wHEDB.','admin'),
(2,'Allhan','Genosas ','doctor@smartclinic.test','$2y$12$pH.1gt18Xhdr0daahvFD8e9aA9nIkMyONNul.rc5PgUPIr9wHEDB.','doctor'),
(3,'Adeem Ali','Anwar','patient@smartclinic.test','$2y$12$pH.1gt18Xhdr0daahvFD8e9aA9nIkMyONNul.rc5PgUPIr9wHEDB.','patient'),
(4,'Lovepreet','Kaur','nurse@smartclinic.test','$2y$12$pH.1gt18Xhdr0daahvFD8e9aA9nIkMyONNul.rc5PgUPIr9wHEDB.','nurse'),
(5,'Racel Joy','Alon','priya@smartclinic.test','$2y$12$pH.1gt18Xhdr0daahvFD8e9aA9nIkMyONNul.rc5PgUPIr9wHEDB.','doctor'),
(6,'Anchal','Shrestha','jordan@smartclinic.test','$2y$12$pH.1gt18Xhdr0daahvFD8e9aA9nIkMyONNul.rc5PgUPIr9wHEDB.','patient');

INSERT INTO doctors(id,user_id,title,specialty,provider_number,is_available) VALUES
(1,2,'Dr.','General Practice','SC10001',1),(2,5,'Dr.','Family Medicine','SC10002',1);
INSERT INTO patients(id,user_id,dob,blood_type,emergency_contact,loyalty_points) VALUES
(1,3,'1997-06-18','O+','Taylor Morgan · 0400 111 222',240),(2,6,'1989-11-04','A+','Sam Lee · 0400 333 444',120);

INSERT INTO appointments(id,patient_id,doctor_id,appointment_date,appointment_time,reason,status) VALUES
(1,1,1,CURDATE(),'10:00:00','Follow-up consultation','confirmed'),
(2,2,1,CURDATE(),'10:30:00','Recurring headache','booked'),
(3,1,2,DATE_ADD(CURDATE(),INTERVAL 4 DAY),'14:00:00','Annual health check','booked'),
(4,2,2,DATE_SUB(CURDATE(),INTERVAL 8 DAY),'09:30:00','General consultation','completed');
INSERT INTO queue_entries(appointment_id,queue_number,status,priority,wait_minutes) VALUES (1,1,'waiting','normal',NULL);
INSERT INTO medical_records(patient_id,doctor_id,diagnosis,notes,allergies,record_date) VALUES
(1,1,'Seasonal allergic rhinitis','Patient reports nasal congestion and sneezing. Discussed trigger avoidance and symptomatic management.','Penicillin',DATE_SUB(NOW(),INTERVAL 42 DAY)),
(1,1,'Routine follow-up','Symptoms improved. Continue current management and return if symptoms worsen.','Penicillin',DATE_SUB(NOW(),INTERVAL 15 DAY)),
(2,2,'Tension-type headache','No red-flag symptoms identified in this prototype note. Hydration, sleep and follow-up discussed.',NULL,DATE_SUB(NOW(),INTERVAL 8 DAY));
INSERT INTO prescriptions(patient_id,doctor_id,medication,dosage,frequency,duration_days,instructions,status) VALUES
(1,1,'Cetirizine','10 mg','Once daily',14,'Take in the evening if drowsiness occurs.','active'),
(2,2,'Paracetamol','500 mg','As needed',3,'Do not exceed the label instructions.','completed');
INSERT INTO lab_results(patient_id,test_name,result,reference_range,status,ordered_at,resulted_at) VALUES
(1,'Full Blood Count','Within reference range','Laboratory reference ranges apply','ready',DATE_SUB(NOW(),INTERVAL 10 DAY),DATE_SUB(NOW(),INTERVAL 9 DAY)),
(1,'Lipid Profile',NULL,NULL,'processing',NOW(),NULL),
(2,'HbA1c','5.3%','< 5.7%','ready',DATE_SUB(NOW(),INTERVAL 7 DAY),DATE_SUB(NOW(),INTERVAL 6 DAY));
INSERT INTO tasks(assigned_to,assigned_by,patient_id,title,description,priority,due_at,status) VALUES
(4,2,1,'Record pre-consultation vitals','Blood pressure, temperature and pulse before doctor review.','high',DATE_ADD(NOW(),INTERVAL 1 HOUR),'pending'),
(4,2,2,'Prepare consultation room','Confirm equipment and patient chart are ready.','normal',DATE_ADD(NOW(),INTERVAL 2 HOUR),'in_progress');
INSERT INTO notifications(user_id,title,message,type,is_read) VALUES
(3,'Appointment reminder','Your appointment with Dr. Ethan Chen is scheduled today at 10:00 AM.','appointment',0),
(3,'Lab update','Your Lipid Profile is being processed.','lab',0),
(4,'New staff task','Record pre-consultation vitals for Alex Morgan.','task',0);
