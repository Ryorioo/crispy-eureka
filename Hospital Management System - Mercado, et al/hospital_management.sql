-- DATABASE CREATION
CREATE DATABASE IF NOT EXISTS hospital_management;
USE hospital_management;

-- TABLE: Department
-- 1 PK, 0 FK
CREATE TABLE Department (
  department_id INT NOT NULL AUTO_INCREMENT,
  department_name VARCHAR(100) NOT NULL UNIQUE,
  location VARCHAR(100) NOT NULL,
  phone_number VARCHAR(15) UNIQUE,
);

-- TABLE: Doctor
-- 1 PK, 1 FK
CREATE TABLE Doctor (
  doctor_id INT NOT NULL AUTO_INCREMENT,
  last_name VARCHAR(50) NOT NULL,
  first_name VARCHAR(50) NOT NULL,
  specialization VARCHAR(100) NOT NULL,
  license_number VARCHAR(30) NOT NULL UNIQUE,
  email VARCHAR(100) UNIQUE,
  phone_number VARCHAR(15) UNIQUE,
  hire_date DATE NOT NULL,
  department_id INT NOT NULL,
  PRIMARY KEY (doctor_id),
  FOREIGN KEY (department_id) REFERENCES Department (department_id)
    ON DELETE RESTRICT ON UPDATE CASCADE  
);


-- TABLE: Patient
-- 1 PK, 0 FK
-- age_years is DERIVED and was computed from the date_of_birth
CREATE TABLE Patient (
  patient_id INT NOT NULL AUTO_INCREMENT,
  last_name VARCHAR(50) NOT NULL,
  first_name VARCHAR(50) NOT NULL,
  date_of_birth DATE NOT NULL,
  age_years INT AS (TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE())) VIRTUAL,
  gender VARCHAR(10) NOT NULL,
  address TEXT,
  contact_number VARCHAR(15) UNIQUE,
  email VARCHAR(100) UNIQUE,
  blood_type VARCHAR(5),
  registration_date DATE NOT NULL DEFAULT (CURRENT_DATE),
  PRIMARY KEY (patient_id)
);

-- TABLE: Patient Allergy (MULTIVALUED)
-- 1 PK, 0 FK
CREATE TABLE PatientAllergy (
  allergy_id INT NOT NULL AUTO_INCREMENT,
  patient_id INT NOT NULL,
  allergen VARCHAR(100) NOT NULL,
  reaction VARCHAR(100) NOT NULL,
  severity ENUM('Mild', 'Moderate', 'Severe') NOT NULL DEFAULT 'Mild',
  date_noted DATE NOT NULL,
  PRIMARY KEY (allergy_id),
  FOREIGN KEY (patient_id) REFERENCES Patient (patient_id)
    ON DELETE CASCADE ON UPDATE CASCADE
);

-- TABLE: ROOM
-- 1 PK, 0 FK
CREATE TABLE Room (
  room_id INT NOT NULL AUTO_INCREMENT,
  room_number VARCHAR(10) NOT NULL UNIQUE,
  room_type ENUM('ICU', 'General', 'Private', 'Semi-Private') NOT NULL,
  floor_number INT NOT NULL,
  capacity INT NOT NULL DEFAULT 1,
  status ENUM('Available', 'Occupied', 'Maintenance') NOT NULL DEFAULT 'Available',
  daily_rate DECIMAL(10, 2) NOT NULL,
  PRIMARY KEY (room_id)
);

-- TABLE: NURSE
-- 1 PK, 1 FK
CREATE TABLE Nurse (
  nurse_id INT NOT NULL AUTO_INCREMENT,
  last_name VARCHAR(50) NOT NULL,
  first_name VARCHAR(50) NOT NULL,
  license_number VARCHAR(30) NOT NULL UNIQUE,
  phone_number VARCHAR(15) UNIQUE,
  shift ENUM('Morning', 'Afternoon', 'Night') NOT NULL,
  department_id INT NOT NULL,
  PRIMARY KEY (nurse_id),
  FOREIGN KEY (department_id) REFERENCES Department (department_id)
    ON DELETE RESTRICT ON UPDATE CASCADE
);

-- TABLE: Admission (WEAK ENTITY)
-- 1 PK, 3 FK
CREATE TABLE Admission (
  admission_id INT NOT NULL AUTO_INCREMENT,
  admission_date DATE NOT NULL,
  discharge_date DATE,
  reason TEXT NOT NULL,
  status ENUM('Admitted', 'Discharged') NOT NULL DEFAULT 'Admitted',
  patient_id INT NOT NULL,
  room_id INT NOT NULL,
  doctor_id INT NOT NULL,
  PRIMARY KEY (admission_id),
  FOREIGN KEY (patient_id) REFERENCES Patient (patient_id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (room_id) REFERENCES Room (room_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  FOREIGN KEY (doctor_id) REFERENCES Doctor (doctor_id)
    ON DELETE RESTRICT ON UPDATE CASCADE
);

-- TABLE: Appointment
-- 1 PK, 2 FK
CREATE TABLE Appointment (
  appointment_id INT NOT NULL AUTO_INCREMENT,
  appointment_date DATE NOT NULL,
  appointment_time TIME NOT NULL,
  purpose VARCHAR(255) NOT NULL,
  status ENUM('Pending', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Pending',
  notes TEXT,
  patient_id INT NOT NULL,
  doctor_id INT NOT NULL,
  PRIMARY KEY (appointment_id),
  FOREIGN KEY (patient_id) REFERENCES Patient (patient_id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (doctor_id) REFERENCES Doctor (doctor_id)
    ON DELETE RESTRICT ON UPDATE CASCADE
);

-- TABLE: MedicalRecord
-- 1 PK, 2 FK
CREATE TABLE MedicalRecord (
  record_id INT NOT NULL AUTO_INCREMENT,
  record_date DATE NOT NULL,
  diagnosis TEXT NOT NULL,
  treatment TEXT,
  notes TEXT,
  patient_id INT NOT NULL,
  doctor_id INT NOT NULL,
  PRIMARY KEY (record_id),
  FOREIGN KEY (patient_id) REFERENCES Patient (patient_id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (doctor_id) REFERENCES Doctor (doctor_id)
    ON DELETE RESTRICT ON UPDATE CASCADE
);

-- TABLE: Prescription
-- 1 PK, 1 FK
CREATE TABLE Prescription (
  prescription_id INT NOT NULL AUTO_INCREMENT,
  prescription_date DATE NOT NULL,
  medication_name VARCHAR(100) NOT NULL,
  dosage VARCHAR(50) NOT NULL,
  frequency VARCHAR(50) NOT NULL,
  duration_days INT NOT NULL,
  record_id INT NOT NULL,
  PRIMARY KEY (prescription_id),
  FOREIGN KEY (record_id) REFERENCES MedicalRecord (record_id)
    ON DELETE CASCADE ON UPDATE CASCADE
);

-- TABLE: Bill
-- 1 PK, 2 FK
CREATE TABLE Bill (
  bill_id INT NOT NULL AUTO_INCREMENT,
  bill_date DATE NOT NULL,
  total_amount DECIMAL(12, 2) NOT NULL,
  payment_status ENUM('Unpaid', 'Paid', 'Partial') NOT NULL DEFAULT 'Unpaid',
  payment_method ENUM('Cash', 'Card', 'Insurance'),
  patient_id INT NOT NULL,
  admission_id INT,
  PRIMARY KEY (bill_id),
  FOREIGN KEY (patient_id) REFERENCES Patient (patient_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  FOREIGN KEY (admission_id) REFERENCES Admission (admission_id)
    ON DELETE SET NULL ON UPDATE CASCADE
);

-- TABLE: Doctor_Nurse (BRIDGE TABLE)
-- 1 PK, 2 FK
-- HAVE COMPOSITE PRIMARY KEY
CREATE TABLE Doctor_Nurse (
  doctor_id INT NOT NULL,
  nurse_id INT NOT NULL,
  assigned_date DATE NOT NULL,
  role VARCHAR(50) NOT NULL DEFAULT 'Clinical Support',
  end_date DATE,
  notes TEXT,
  PRIMARY KEY (doctor_id, nurse_id),
  FOREIGN KEY (doctor_id) REFERENCES Doctor (doctor_id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (nurse_id) REFERENCES Nurse (nurse_id)
    ON DELETE CASCADE ON UPDATE CASCADE
);

-- TABLE: DischargeReport
-- 1 PK, 1 FK
CREATE TABLE DischargeReport (
  report_id INT NOT NULL AUTO_INCREMENT,
  admission_id INT NOT NULL UNIQUE,
  report_date DATE NOT NULL,
  discharge_condition ENUM('Stable', 'Critical', 'Deceased', 'Transferred') NOT NULL,
  follow_up_date DATE,
  discharge_notes TEXT,
  prepared_by_doctor INT NOT NULL,
  prepared_by_nurse INT NULL,
  PRIMARY KEY (report_id),
  FOREIGN KEY (admission_id) REFERENCES Admission (admission_id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (prepared_by_doctor) REFERENCES Doctor (doctor_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  FOREIGN KEY (prepared_by_nurse) REFERENCES Nurse (nurse_id)
    ON DELETE RESTRICT ON UPDATE CASCADE
);

-- INSERTS 

-- INSERT: Department
INSERT INTO Department 
(department_name, location, phone_number)
VALUES
  ('Cardiology', 'Building A, Floor 2', '09670679301'),
  ('Neurology', 'Building B, Floor 3', '09670679302'),
  ('Orthopedics', 'Building A, Floor 1', '09670679303'),
  ('Pediatrics', 'Building C, Floor 1', '09670679304'),
  ('Emergency Medicine', 'Building D, Floor 1', '09670679305');

-- INSERT: Doctor 
INSERT INTO Doctor 
(last_name, first_name, specialization, license_number, email, phone_number, hire_date, department_id)
VALUES
  ('Tsukishima', 'Kei', 'Cardiologist', 'PRC-MD-67021', 'tsukki@gmail.com', '0923710501', '2018-09-27', 1),
  ('Tomioka', 'Giyu', 'Neurologist', 'PRC-MD-67022', 'giyu@gmail.com', '09231710502', '2019-02-08', 2),
  ('Rukawa', 'Kaede', 'Orthopedic Surgeon', 'PRC-MD-67023', 'rukawa@gmail.com', '09231710503', '2020-01-01', 3),
  ('Nanami', 'Kento', 'Pediatrician', 'PRC-MD-67024', 'nanami@gmail.com', '09231710504', '2021-07-03', 4),
  ('Vinsmoke', 'Sanji', 'Emergency Physician', 'PRC-MD-67025', 'sanji@gmail.com', '0923710505', '2022-03-12', 5);

-- INSERT: Patient
INSERT INTO Patient 
(last_name, first_name, date_of_birth, gender, address, contact_number, email, blood_type, registration_date)
VALUES 
  ('Freecss', 'Gon', '2001-05-05', 'Male', 'Whale Island, Yorknew City', '09766706721', 'gon@gmail.com', 'O+', '2026-01-10'),
  ('Zoldyck', 'Killua', '2001-07-07', 'Male', 'Kukuroo Mountain, Padokea', '09766706722', 'killua@gmail.com', 'A+', '2026-01-11'),
  ('Lucilfer', 'Chrollo', '1988-02-15', 'Male', 'Meteor City', '09766706723', 'chrollo@gmail.com', 'B+', '2026-01-12'),
  ('Morrow', 'Hisoka', '1990-06-06', 'Male', 'Heavens Arena, Republic of Padokea', '09766706724', 'hisoka@gmail.com', 'O-', '2026-01-13'),
  ('Zoldyck', 'Alluka', '2003-09-09', 'Female', 'Kukuroo Mountain, Padokea', '09766706725', 'alluka@gmail.com', 'B-', '2026-01-14');

-- INSERT: Room
INSERT INTO Room
(room_number, room_type, floor_number, capacity, status, daily_rate) 
VALUES 
  ('101', 'General', 1, 4, 'Occupied', 500.00),
  ('202', 'Private', 2, 1, 'Occupied', 2500.00),
  ('303', 'ICU', 3, 2, 'Occupied', 5000.00),
  ('104', 'Semi-Private', 1, 2, 'Available', 1200.00),
  ('205', 'Public', 2, 6, 'Available', 300.00);

-- INSERT: Nurse 
INSERT INTO Nurse
(last_name, first_name, license_number, phone_number, shift, department_id)
VALUES 
  ('Kim', 'Sa-bu', 'RN-67051', '09171234565', 'Morning', 1),
  ('Seo', 'Woo-jin', 'RN-67052', '09171234566', 'Afternoon', 2),
  ('Cha', 'Eun-jae', 'RN-67053', '09171234567', 'Night', 3),
  ('Yoon', 'A-reum', 'RN-67054', '09171234568', 'Morning', 4),
  ('Park', 'Min-guk', 'RN-67055', '09171234569', 'Afternoon', 5);

-- INSERT: PatientAllergy
INSERT INTO PatientAllergy 
(patient_id, allergen, reaction, severity, date_noted)
VALUES
  (1, 'Penicillin', 'Skin rash and hives', 'Moderate', '2026-01-06'),
  (1, 'Peanuts', 'Difficulty breathing', 'Severe', '2026-01-07'),
  (1, 'Dust', 'Sneezing and itchy eyes', 'Mild', '2026-01-08'),
  (2, 'Aspirin', 'Gastric bleeding', 'Severe', '2026-02-15'),
  (2, 'Seafood', 'Swollen lips', 'Moderate', '2026-02-18'),
  (3, 'Milk', 'Sneezing and watery eyes', 'Mild', '2026-03-02'),
  (4, 'Eggs', 'Skin irritation', 'Moderate', '2026-03-05'),
  (5, 'Mold', 'Sneezing and coughing', 'Mild', '2026-04-13'),
  (5, 'Perfume', 'Headache and skin irritation', 'Mild', '2026-04-15');

-- INSERT: Admission
INSERT INTO Admission
(admission_date, discharge_date, reason, status, patient_id, room_id, doctor_id)
VALUES
  ('2026-04-01', '2026-04-07', 'Chest pain and shortness of breath', 'Discharged', 1, 2, 1),
  ('2026-04-05', '2026-04-12', 'Severe migraine with vomiting', 'Discharged', 2, 1, 2),
  ('2026-04-10', NULL, 'Post-surgical knee monitoring', 'Admitted', 3, 3, 3),
  ('2026-04-15', '2026-04-20', 'High fever and febrile seizure', 'Discharged', 4, 4, 4),
  ('2026-04-18', NULL, 'Traumatic head injury', 'Admitted', 5, 3, 5);

-- INSERT: Appointment
INSERT INTO Appointment
(appointment_date, appointment_time, purpose, status, notes, patient_id, doctor_id)
VALUES
  ('2026-05-02', '09:00:00', 'Cardiac follow-up check', 'Completed', 'ECG and BP monitoring done', 1, 1),
  ('2026-05-05', '10:30:00', 'Neurology consultation', 'Completed', 'MRI results reviewed', 2, 2),
  ('2026-05-08', '14:00:00', 'Orthopedic rehabilitation eval', 'Pending', NULL, 3, 3),
  ('2026-05-10', '08:00:00', 'Pediatric growth assessment', 'Cancelled', 'Patient rescheduled', 4, 4),
  ('2026-05-15', '11:00:00', 'Emergency medicine follow-up', 'Pending', NULL, 5, 5);

-- INSERT: Medical Record
INSERT INTO MedicalRecord 
(record_date, diagnosis, treatment, notes, patient_id, doctor_id)
VALUES
  ('2026-04-07', 'Stable angina', 'Nitrates and beta-blockers prescribed', 'Monitor BP weekly', 1, 1),
  ('2026-04-12', 'Chronic migraine', 'Topiramate prescribed, rest advised', 'Avoid screen exposure', 2, 2),
  ('2026-04-10', 'Post-op knee arthroscopy', 'Physical therapy and pain management', 'Weight-bering restricted', 3, 3),
  ('2026-04-18', 'Febrile seizure', 'IV fluids and antipyretics administered', 'Follow-up EEG scheduled', 4, 4),
  ('2026-04-20', 'Traumatic brain injury', 'CT scan ordered, ICU monitoring initiated', 'GCS score 12 on admission', 5, 5);

-- INSERT: Prescription
INSERT INTO Prescription
(prescription_date, medication_name, dosage, frequency, duration_days, record_id)
VALUES
  ('2026-04-07', 'Isosorbide Mononitrate', '20mg', 'Twice daily', 30, 1),
  ('2026-04-12', 'Topiramate', '50mg', 'Once daily at night', 60, 2),
  ('2026-04-10', 'Celecoxib', '200mg', 'Once daily', 14, 3),
  ('2026-04-18', 'Paracetamol', '500mg', 'Every 6 hours', 5, 4),
  ('2026-04-20', 'Mannitol', '100g', 'IV once daily', 7, 5);

-- INSERT: Bill
INSERT INTO Bill
(bill_date, total_amount, payment_status, payment_method, patient_id, admission_id)
VALUES
  ('2026-04-07', 18500.00, 'Paid', 'Insurance', 1, 1),
  ('2026-04-12', 12000.00, 'Paid', 'Cash', 2, 2),
  ('2026-04-15', 35000.00, 'Partial', 'Card', 3, 3),
  ('2026-04-19', 8750.00, 'Paid', 'Cash', 4, 4),
  ('2026-04-29', 42000.00, 'Unpaid', NULL, 5, 5);

-- INSERT: Doctor_Nurse
INSERT INTO Doctor_Nurse
(doctor_id, nurse_id, assigned_date, role, end_date, notes)
VALUES
  (1, 1, '2026-01-01', 'Clinical Support', NULL, 'Assigned to cardiuuology ward'),
  (1, 2, '2026-01-02', 'Clinical Support', NULL, 'Assists during rouunds'),
  (2, 2, '2026-01-01', 'Clinical Support', NULL, 'Neurology assistance'),
  (2, 3, '2026-01-01', 'Clinical Support', NULL, 'MRI and patient monitoring'),
  (3, 3, '2026-01-01', 'Surgical Support', NULL, 'OR support team'),
  (3, 1, '2026-01-01', 'Surgical Support', NULL, 'Post-op assistance'),
  (4, 4, '2026-01-01', 'Clinical Support', NULL, 'Pediatric ward coverage'),
  (4, 5, '2026-01-01', 'Clinical Support', NULL, 'Child patient monitoring'),
  (5, 5, '2026-01-01', 'Triage Support', NULL, 'ER triage'),
  (5, 2, '2026-01-01', 'Triage Support', NULL, 'Emergency assistance');

-- INSERT: DischargeReport 
INSERT INTO DischargeReport 
(admission_id, report_date, discharge_condition, follow_up_date, discharge_notes, prepared_by_doctor, prepared_by_nurse)
VALUES
  (1, '2026-04-07', 'Stable', '2026-05-02', 'Patient discharged in stable condition. Continue medications as prescribed.', 1, 1),
  (2, '2026-04-12', 'Stable', '2026-05-06', 'Migraine managed. Advised to avoid triggeres and limit screen exposure', 2, 2),
  (4, '2026-04-20', 'Stable', '2026-05-10', 'Febrile seizure resolved. EEG follow-up scheduled in 2 weeks', 4, 4);