-- WeCare Hospital - Full Database Schema & Seed Data
-- Compatible with Cloud MySQL (Render, Railway, Aiven) and Local MySQL (XAMPP)

-- Note for Cloud MySQL: If your cloud provider already created your database (e.g. 'railway' or 'defaultdb'),
-- simply import this script directly into that database.

CREATE TABLE IF NOT EXISTS `patients` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_name VARCHAR(100) NOT NULL,
    age INT NOT NULL,
    gender VARCHAR(20) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    address TEXT NOT NULL,
    email VARCHAR(100) UNIQUE NULL,
    password VARCHAR(255) NULL,
    blood_group VARCHAR(10) DEFAULT 'O+',
    allergies VARCHAR(255) DEFAULT 'No known drug allergies (NKDA)',
    chronic_conditions VARCHAR(255) DEFAULT 'None reported',
    emergency_name VARCHAR(100) DEFAULT 'Emergency Contact',
    emergency_phone VARCHAR(20) DEFAULT '+91 98765 43210',
    emergency_relation VARCHAR(50) DEFAULT 'Family / Guardian',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS doctors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    doctor_name VARCHAR(100) NOT NULL,
    specialization VARCHAR(100) NOT NULL,
    department VARCHAR(100) NOT NULL DEFAULT 'General Medicine',
    phone VARCHAR(20) NOT NULL,
    age INT NOT NULL DEFAULT 42,
    gender VARCHAR(20) NOT NULL DEFAULT 'Male',
    experience VARCHAR(50) NOT NULL DEFAULT '12+ years',
    consultation_fee DECIMAL(10, 2) NOT NULL DEFAULT 800.00,
    availability VARCHAR(50) NOT NULL DEFAULT 'Available Today',
    languages VARCHAR(100) NOT NULL DEFAULT 'English, Hindi',
    education VARCHAR(150) NOT NULL DEFAULT 'MBBS, MD',
    working_hours VARCHAR(100) NOT NULL DEFAULT '09:00 AM - 05:00 PM',
    profile_image VARCHAR(255) NULL,
    bio TEXT NULL,
    INDEX idx_spec_avail (specialization, availability)
);

CREATE TABLE IF NOT EXISTS appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'Confirmed',
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    INDEX idx_patient_date_status (patient_id, appointment_date, status)
);

CREATE TABLE IF NOT EXISTS bills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    appointment_id INT NULL,
    doctor_id INT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    bill_date DATE NOT NULL,
    payment_status VARCHAR(20) NOT NULL DEFAULT 'Pending',
    services_breakdown TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    INDEX idx_patient_status (patient_id, payment_status)
);

CREATE TABLE IF NOT EXISTS prescriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    appointment_id INT NULL,
    diagnosis TEXT NOT NULL,
    medicines TEXT NOT NULL,
    instructions TEXT NULL,
    follow_up_date DATE NULL,
    prescription_date DATE NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    INDEX idx_patient_date (patient_id, prescription_date)
);

-- Seed 14 Verified Medical Specialists
INSERT INTO doctors (id, doctor_name, specialization, department, phone, age, gender, experience, consultation_fee, availability, languages, education, working_hours, profile_image, bio) VALUES
(1, 'Dr. Ananya Sharma', 'Cardiology', 'Cardiology & Heart Institute', '+91 98765 43210', 44, 'Female', '16 years', 1000.00, 'Available Today', 'English, Hindi, Marathi', 'MBBS, MD (General Medicine), DM (Cardiology) - AIIMS New Delhi', '09:00 AM - 02:00 PM', 'assets/doctors/doctor-01.jpg', 'Senior Interventional Cardiologist specializing in preventive cardiology, coronary angioplasty, hypertension management, and heart failure therapies.'),
(2, 'Dr. Rohit Verma', 'Orthopedics', 'Orthopedics & Joint Replacement', '+91 98765 43211', 48, 'Male', '19 years', 900.00, 'Available Today', 'English, Hindi, Punjabi', 'MBBS, MS (Orthopedics), MCh (Joint Surgery) - PGI Chandigarh', '10:00 AM - 04:00 PM', 'assets/doctors/doctor-02.jpg', 'Chief Orthopedic Surgeon with deep expertise in primary and revision joint arthroplasty, sports injury reconstruction, and spinal disorders.'),
(3, 'Dr. Priya Nair', 'Pediatrics', 'Pediatrics & Neonatal Care', '+91 98765 43212', 39, 'Female', '12 years', 700.00, 'Available Today', 'English, Hindi, Malayalam', 'MBBS, MD (Pediatrics), DNB (Pediatrics) - CMC Vellore', '09:30 AM - 01:30 PM', 'assets/doctors/doctor-03.jpg', 'Compassionate pediatrician focusing on developmental assessment, childhood vaccinations, adolescent medicine, and acute neonatal care.'),
(4, 'Dr. Vikram Singh', 'General Medicine', 'Internal Medicine & Critical Care', '+91 98765 43213', 52, 'Male', '24 years', 600.00, 'Available Today', 'English, Hindi, Bengali', 'MBBS, MD (Internal Medicine) - Maulana Azad Medical College', '08:30 AM - 02:30 PM', 'assets/doctors/doctor-04.jpg', 'Senior Consultant in Internal Medicine with extensive clinical leadership in chronic disease management, diabetes mellitus, and lifestyle disorders.'),
(5, 'Dr. Sneha Kulkarni', 'Dermatology', 'Dermatology & Aesthetic Medicine', '+91 98765 43214', 37, 'Female', '11 years', 850.00, 'Available Today', 'English, Hindi, Marathi, Gujarati', 'MBBS, MD (Dermatology, Venereology & Leprosy) - KEM Hospital Mumbai', '11:00 AM - 05:00 PM', 'assets/doctors/doctor-05.jpg', 'Consultant Dermatologist specializing in medical dermatology, eczema, psoriasis, acne protocols, trichology, and advanced cosmetic dermatologic procedures.'),
(6, 'Dr. Arvind Menon', 'Neurology', 'Institute of Neurosciences', '+91 98765 43215', 49, 'Male', '21 years', 1200.00, 'Available Tomorrow', 'English, Hindi, Malayalam, Tamil', 'MBBS, MD (Medicine), DM (Neurology) - NIMHANS Bengaluru', '10:00 AM - 03:00 PM', 'assets/doctors/doctor-06.jpg', 'Renowned Neurologist with expertise in stroke management, epilepsy, Parkinson’s disease, peripheral neuropathies, and migraine care.'),
(7, 'Dr. Kavita Deshmukh', 'Gynecology', 'Obstetrics & Women’s Health', '+91 98765 43216', 46, 'Female', '18 years', 950.00, 'Available Today', 'English, Hindi, Marathi', 'MBBS, MS (Obstetrics & Gynecology), FMAS - Grant Medical College', '09:00 AM - 01:00 PM', 'assets/doctors/doctor-07.jpg', 'Senior Obstetrician and Laparoscopic Surgeon dedicated to high-risk obstetric care, reproductive endocrinology, and women’s wellness.'),
(8, 'Dr. Rajesh Iyer', 'ENT', 'Ear, Nose & Throat (Otolaryngology)', '+91 98765 43217', 43, 'Male', '15 years', 750.00, 'Available Today', 'English, Hindi, Tamil', 'MBBS, MS (ENT), DNB (Otorhinolaryngology) - Madras Medical College', '02:00 PM - 07:00 PM', 'assets/doctors/doctor-08.jpg', 'Consultant ENT Surgeon specializing in endoscopic sinus surgery, micro-ear surgeries, sleep apnea therapies, and allergic rhinitis management.'),
(9, 'Dr. Sunita Mehra', 'Ophthalmology', 'Eye Care & Ophthalmology', '+91 98765 43218', 41, 'Female', '14 years', 700.00, 'Available Tomorrow', 'English, Hindi, Punjabi', 'MBBS, MS (Ophthalmology) - RP Centre AIIMS New Delhi', '09:00 AM - 02:00 PM', 'assets/doctors/doctor-09.jpg', 'Expert Ophthalmic Surgeon specializing in cataract surgery (Phaco), diabetic retinopathy screening, and glaucoma therapy.'),
(10, 'Dr. Tarun Kapoor', 'Pulmonology', 'Pulmonology & Respiratory Medicine', '+91 98765 43219', 45, 'Male', '17 years', 850.00, 'Available Today', 'English, Hindi, Bengali', 'MBBS, MD (Pulmonary Medicine), FCCP - VMMC & Safdarjung Hospital', '11:00 AM - 04:00 PM', 'assets/doctors/doctor-10.jpg', 'Consultant Pulmonologist with specialized focus in asthma, COPD management, interstitial lung diseases, and sleep studies.'),
(11, 'Dr. Deepa Nambiar', 'Gastroenterology', 'Digestive Diseases & Endoscopy', '+91 98765 43220', 47, 'Female', '19 years', 1100.00, 'Available Today', 'English, Hindi, Malayalam, Kannada', 'MBBS, MD, DM (Gastroenterology) - SGPGI Lucknow', '09:30 AM - 02:30 PM', 'assets/doctors/doctor-11.jpg', 'Senior Medical Gastroenterologist with extensive skill in diagnostic/therapeutic endoscopy, liver disorders, and reflux syndromes.'),
(12, 'Dr. Alok Chatterjee', 'Urology', 'Urology & Kidney Care', '+91 98765 43221', 50, 'Male', '22 years', 1050.00, 'Available Tomorrow', 'English, Hindi, Bengali', 'MBBS, MS (Surgery), MCh (Urology) - IPGMER Kolkata', '10:00 AM - 03:00 PM', 'assets/doctors/doctor-12.jpg', 'Consultant Urologist and Andrologist with expertise in minimally invasive kidney stone procedures and laser prostatectomy.'),
(13, 'Dr. Meenakshi Sundaram', 'Oncology', 'Medical Oncology & Cancer Center', '+91 98765 43222', 45, 'Female', '17 years', 1300.00, 'Available Today', 'English, Hindi, Tamil, Telugu', 'MBBS, MD (Medicine), DM (Medical Oncology) - Tata Memorial Centre', '09:00 AM - 01:30 PM', 'assets/doctors/doctor-13.jpg', 'Consultant Medical Oncologist specializing in targeted cancer therapies, immunotherapy, and comprehensive oncology support.'),
(14, 'Dr. Harish Bhat', 'Nephrology', 'Nephrology & Renal Medicine', '+91 98765 43223', 48, 'Male', '20 years', 1000.00, 'Available Today', 'English, Hindi, Kannada', 'MBBS, MD (Medicine), DM (Nephrology) - Kasturba Medical College', '01:00 PM - 06:00 PM', 'assets/doctors/doctor-14.jpg', 'Senior Nephrologist focusing on chronic kidney disease progression management, hemodialysis protocols, and hypertension.')
ON DUPLICATE KEY UPDATE
doctor_name = VALUES(doctor_name),
specialization = VALUES(specialization),
department = VALUES(department),
phone = VALUES(phone),
age = VALUES(age),
gender = VALUES(gender),
experience = VALUES(experience),
consultation_fee = VALUES(consultation_fee),
availability = VALUES(availability),
languages = VALUES(languages),
education = VALUES(education),
working_hours = VALUES(working_hours),
profile_image = VALUES(profile_image),
bio = VALUES(bio);

-- Seed Demo Patient Account for Immediate Login
-- Email: rahul.sharma@gmail.com | Password: password123 (or 123456789)
INSERT INTO `patients` (`id`, `patient_name`, `age`, `gender`, `phone`, `address`, `email`, `password`, `blood_group`, `allergies`, `chronic_conditions`, `emergency_name`, `emergency_phone`, `emergency_relation`) VALUES
(3, 'Rahul Sharma', 34, 'Male', '+91 98765 43210', '42 Park Avenue, Mumbai, India', 'rahul.sharma@gmail.com', '$2y$10$KF.m4HBjKT7Qc0wWnqOKbOTaR1945lknpIkgLbt9JpzH8Glq/hOwy', 'O+', 'No known drug allergies (NKDA)', 'None reported', 'Anjali Sharma', '+91 98765 43211', 'Spouse')
ON DUPLICATE KEY UPDATE `patient_name` = VALUES(`patient_name`);

