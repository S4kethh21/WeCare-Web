<?php
/**
 * WeCare Hospital — Database Migration & Seeding Script
 * Safely adds new workflow columns and populates realistic doctors without dropping existing data.
 */
require_once __DIR__ . '/db.php';

echo "Running WeCare Hospital Database Migration...\n";

// Helper to check if a column exists in a table
function columnExists($conn, $table, $column) {
    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return ($res && $res->num_rows > 0);
}

// 1. Upgrade doctors table
$doctorColumns = [
    'age' => 'INT NOT NULL DEFAULT 42',
    'gender' => "VARCHAR(20) NOT NULL DEFAULT 'Male'",
    'experience' => "VARCHAR(50) NOT NULL DEFAULT '12+ years'",
    'consultation_fee' => "DECIMAL(10,2) NOT NULL DEFAULT 800.00",
    'availability' => "VARCHAR(50) NOT NULL DEFAULT 'Available Today'",
    'department' => "VARCHAR(100) NOT NULL DEFAULT 'General Medicine'",
    'languages' => "VARCHAR(100) NOT NULL DEFAULT 'English, Hindi'",
    'education' => "VARCHAR(150) NOT NULL DEFAULT 'MBBS, MD'",
    'bio' => "TEXT NULL",
    'working_hours' => "VARCHAR(100) NOT NULL DEFAULT '09:00 AM - 05:00 PM'"
];

foreach ($doctorColumns as $col => $def) {
    if (!columnExists($conn, 'doctors', $col)) {
        $sql = "ALTER TABLE `doctors` ADD COLUMN `$col` $def";
        if ($conn->query($sql)) {
            echo "  [+] Added column doctors.$col\n";
        } else {
            echo "  [!] Error adding doctors.$col: " . $conn->error . "\n";
        }
    }
}

// 2. Upgrade appointments table
$appointmentColumns = [
    'status' => "VARCHAR(30) NOT NULL DEFAULT 'Confirmed'",
    'created_at' => "DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP",
    'notes' => "TEXT NULL"
];

foreach ($appointmentColumns as $col => $def) {
    if (!columnExists($conn, 'appointments', $col)) {
        $sql = "ALTER TABLE `appointments` ADD COLUMN `$col` $def";
        if ($conn->query($sql)) {
            echo "  [+] Added column appointments.$col\n";
        } else {
            echo "  [!] Error adding appointments.$col: " . $conn->error . "\n";
        }
    }
}

// 3. Upgrade bills table
$billColumns = [
    'appointment_id' => "INT NULL",
    'doctor_id' => "INT NULL",
    'services_breakdown' => "TEXT NULL",
    'created_at' => "DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP"
];

foreach ($billColumns as $col => $def) {
    if (!columnExists($conn, 'bills', $col)) {
        $sql = "ALTER TABLE `bills` ADD COLUMN `$col` $def";
        if ($conn->query($sql)) {
            echo "  [+] Added column bills.$col\n";
        } else {
            echo "  [!] Error adding bills.$col: " . $conn->error . "\n";
        }
    }
}

// 4. Upgrade prescriptions table
$prescriptionColumns = [
    'appointment_id' => "INT NULL",
    'instructions' => "TEXT NULL",
    'follow_up_date' => "DATE NULL",
    'created_at' => "DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP"
];

foreach ($prescriptionColumns as $col => $def) {
    if (!columnExists($conn, 'prescriptions', $col)) {
        $sql = "ALTER TABLE `prescriptions` ADD COLUMN `$col` $def";
        if ($conn->query($sql)) {
            echo "  [+] Added column prescriptions.$col\n";
        } else {
            echo "  [!] Error adding prescriptions.$col: " . $conn->error . "\n";
        }
    }
}

// 5. Ensure unified utf8mb4_unicode_ci collation across all tables
$conn->query("ALTER DATABASE hospital_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
foreach (['patients', 'doctors', 'appointments', 'bills', 'prescriptions'] as $t) {
    $conn->query("ALTER TABLE `$t` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
}

// 6. Seed / Update 14 Realistic Doctors
$doctorsData = [
    [
        'id' => 1,
        'name' => 'Dr. Ananya Sharma',
        'specialization' => 'Cardiology',
        'department' => 'Cardiology & Heart Institute',
        'phone' => '+91 98765 43210',
        'age' => 44,
        'gender' => 'Female',
        'experience' => '16 years',
        'consultation_fee' => 1000.00,
        'availability' => 'Available Today',
        'languages' => 'English, Hindi, Marathi',
        'education' => 'MBBS, MD (General Medicine), DM (Cardiology) - AIIMS New Delhi',
        'working_hours' => '09:00 AM - 02:00 PM',
        'profile_image' => 'assets/doctors/doctor-01.jpg',
        'bio' => 'Senior Interventional Cardiologist specializing in preventive cardiology, coronary angioplasty, hypertension management, and heart failure therapies with over 16 years of clinical excellence.'
    ],
    [
        'id' => 2,
        'name' => 'Dr. Rohit Verma',
        'specialization' => 'Orthopedics',
        'department' => 'Orthopedics & Joint Replacement',
        'phone' => '+91 98765 43211',
        'age' => 48,
        'gender' => 'Male',
        'experience' => '19 years',
        'consultation_fee' => 900.00,
        'availability' => 'Available Today',
        'languages' => 'English, Hindi, Punjabi',
        'education' => 'MBBS, MS (Orthopedics), MCh (Joint Surgery) - PGI Chandigarh',
        'working_hours' => '10:00 AM - 04:00 PM',
        'profile_image' => 'assets/doctors/doctor-02.jpg',
        'bio' => 'Chief Orthopedic Surgeon with deep expertise in primary and revision joint arthroplasty, sports injury reconstruction, spinal disorders, and complex trauma recovery.'
    ],
    [
        'id' => 3,
        'name' => 'Dr. Priya Nair',
        'specialization' => 'Pediatrics',
        'department' => 'Pediatrics & Neonatal Care',
        'phone' => '+91 98765 43212',
        'age' => 39,
        'gender' => 'Female',
        'experience' => '12 years',
        'consultation_fee' => 700.00,
        'availability' => 'Available Today',
        'languages' => 'English, Hindi, Malayalam',
        'education' => 'MBBS, MD (Pediatrics), DNB (Pediatrics) - CMC Vellore',
        'working_hours' => '09:30 AM - 01:30 PM',
        'profile_image' => 'assets/doctors/doctor-03.jpg',
        'bio' => 'Compassionate pediatrician focusing on developmental assessment, childhood vaccinations, adolescent medicine, and acute neonatal care with child-friendly bedside manners.'
    ],
    [
        'id' => 4,
        'name' => 'Dr. Vikram Singh',
        'specialization' => 'General Medicine',
        'department' => 'Internal Medicine & Critical Care',
        'phone' => '+91 98765 43213',
        'age' => 52,
        'gender' => 'Male',
        'experience' => '24 years',
        'consultation_fee' => 600.00,
        'availability' => 'Available Today',
        'languages' => 'English, Hindi, Bengali',
        'education' => 'MBBS, MD (Internal Medicine) - Maulana Azad Medical College',
        'working_hours' => '08:30 AM - 02:30 PM',
        'profile_image' => 'assets/doctors/doctor-04.jpg',
        'bio' => 'Senior Consultant in Internal Medicine with extensive clinical leadership in chronic disease management, diabetes mellitus, lifestyle disorders, and infectious illnesses.'
    ],
    [
        'id' => 5,
        'name' => 'Dr. Sneha Kulkarni',
        'specialization' => 'Dermatology',
        'department' => 'Dermatology & Aesthetic Medicine',
        'phone' => '+91 98765 43214',
        'age' => 37,
        'gender' => 'Female',
        'experience' => '11 years',
        'consultation_fee' => 850.00,
        'availability' => 'Available Today',
        'languages' => 'English, Hindi, Marathi, Gujarati',
        'education' => 'MBBS, MD (Dermatology, Venereology & Leprosy) - KEM Hospital Mumbai',
        'working_hours' => '11:00 AM - 05:00 PM',
        'profile_image' => 'assets/doctors/doctor-05.jpg',
        'bio' => 'Consultant Dermatologist specializing in medical dermatology, eczema, psoriasis, acne protocols, trichology, and advanced cosmetic dermatologic procedures.'
    ],
    [
        'id' => 6,
        'name' => 'Dr. Arvind Menon',
        'specialization' => 'Neurology',
        'department' => 'Institute of Neurosciences',
        'phone' => '+91 98765 43215',
        'age' => 49,
        'gender' => 'Male',
        'experience' => '21 years',
        'consultation_fee' => 1200.00,
        'availability' => 'Available Tomorrow',
        'languages' => 'English, Hindi, Malayalam, Tamil',
        'education' => 'MBBS, MD (Medicine), DM (Neurology) - NIMHANS Bengaluru',
        'working_hours' => '10:00 AM - 03:00 PM',
        'profile_image' => 'assets/doctors/doctor-06.jpg',
        'bio' => 'Renowned Neurologist with expertise in stroke management, epilepsy, Parkinson’s disease, peripheral neuropathies, and migraine care with comprehensive neuro-rehabilitation.'
    ],
    [
        'id' => 7,
        'name' => 'Dr. Kavita Deshmukh',
        'specialization' => 'Gynecology',
        'department' => 'Obstetrics & Women’s Health',
        'phone' => '+91 98765 43216',
        'age' => 46,
        'gender' => 'Female',
        'experience' => '18 years',
        'consultation_fee' => 950.00,
        'availability' => 'Available Today',
        'languages' => 'English, Hindi, Marathi',
        'education' => 'MBBS, MS (Obstetrics & Gynecology), FMAS - Grant Medical College',
        'working_hours' => '09:00 AM - 01:00 PM',
        'profile_image' => 'assets/doctors/doctor-07.jpg',
        'bio' => 'Senior Obstetrician and Laparoscopic Surgeon dedicated to high-risk obstetric care, reproductive endocrinology, minimally invasive gynecological surgery, and women’s wellness.'
    ],
    [
        'id' => 8,
        'name' => 'Dr. Rajesh Iyer',
        'specialization' => 'ENT',
        'department' => 'Ear, Nose & Throat (Otolaryngology)',
        'phone' => '+91 98765 43217',
        'age' => 43,
        'gender' => 'Male',
        'experience' => '15 years',
        'consultation_fee' => 750.00,
        'availability' => 'Available Today',
        'languages' => 'English, Hindi, Tamil',
        'education' => 'MBBS, MS (ENT), DNB (Otorhinolaryngology) - Madras Medical College',
        'working_hours' => '02:00 PM - 07:00 PM',
        'profile_image' => 'assets/doctors/doctor-08.jpg',
        'bio' => 'Consultant ENT Surgeon specializing in endoscopic sinus surgery, micro-ear surgeries, sleep apnea therapies, and allergic rhinitis management.'
    ],
    [
        'id' => 9,
        'name' => 'Dr. Sunita Mehra',
        'specialization' => 'Ophthalmology',
        'department' => 'Eye Care & Ophthalmology',
        'phone' => '+91 98765 43218',
        'age' => 41,
        'gender' => 'Female',
        'experience' => '14 years',
        'consultation_fee' => 700.00,
        'availability' => 'Available Tomorrow',
        'languages' => 'English, Hindi, Punjabi',
        'education' => 'MBBS, MS (Ophthalmology) - RP Centre AIIMS New Delhi',
        'working_hours' => '09:00 AM - 02:00 PM',
        'profile_image' => 'assets/doctors/doctor-09.jpg',
        'bio' => 'Expert Ophthalmic Surgeon specializing in cataract surgery (Phaco), diabetic retinopathy screening, glaucoma therapy, and pediatric refractive care.'
    ],
    [
        'id' => 10,
        'name' => 'Dr. Tarun Kapoor',
        'specialization' => 'Pulmonology',
        'department' => 'Pulmonology & Respiratory Medicine',
        'phone' => '+91 98765 43219',
        'age' => 45,
        'gender' => 'Male',
        'experience' => '17 years',
        'consultation_fee' => 850.00,
        'availability' => 'Available Today',
        'languages' => 'English, Hindi, Bengali',
        'education' => 'MBBS, MD (Pulmonary Medicine), FCCP - VMMC & Safdarjung Hospital',
        'working_hours' => '11:00 AM - 04:00 PM',
        'profile_image' => 'assets/doctors/doctor-10.jpg',
        'bio' => 'Consultant Pulmonologist with specialized focus in asthma, COPD management, interstitial lung diseases, sleep studies, and post-viral respiratory recovery.'
    ],
    [
        'id' => 11,
        'name' => 'Dr. Deepa Nambiar',
        'specialization' => 'Gastroenterology',
        'department' => 'Digestive Diseases & Endoscopy',
        'phone' => '+91 98765 43220',
        'age' => 47,
        'gender' => 'Female',
        'experience' => '19 years',
        'consultation_fee' => 1100.00,
        'availability' => 'Available Today',
        'languages' => 'English, Hindi, Malayalam, Kannada',
        'education' => 'MBBS, MD, DM (Gastroenterology) - SGPGI Lucknow',
        'working_hours' => '09:30 AM - 02:30 PM',
        'profile_image' => 'assets/doctors/doctor-11.jpg',
        'bio' => 'Senior Medical Gastroenterologist with extensive skill in diagnostic/therapeutic endoscopy, liver disorders, inflammatory bowel disease, and reflux syndromes.'
    ],
    [
        'id' => 12,
        'name' => 'Dr. Alok Chatterjee',
        'specialization' => 'Urology',
        'department' => 'Urology & Kidney Care',
        'phone' => '+91 98765 43221',
        'age' => 50,
        'gender' => 'Male',
        'experience' => '22 years',
        'consultation_fee' => 1050.00,
        'availability' => 'Available Tomorrow',
        'languages' => 'English, Hindi, Bengali',
        'education' => 'MBBS, MS (Surgery), MCh (Urology) - IPGMER Kolkata',
        'working_hours' => '10:00 AM - 03:00 PM',
        'profile_image' => 'assets/doctors/doctor-12.jpg',
        'bio' => 'Consultant Urologist and Andrologist with expertise in minimally invasive kidney stone procedures, laser prostatectomy, and reconstructive urology.'
    ],
    [
        'id' => 13,
        'name' => 'Dr. Meenakshi Sundaram',
        'specialization' => 'Oncology',
        'department' => 'Medical Oncology & Cancer Center',
        'phone' => '+91 98765 43222',
        'age' => 45,
        'gender' => 'Female',
        'experience' => '17 years',
        'consultation_fee' => 1300.00,
        'availability' => 'Available Today',
        'languages' => 'English, Hindi, Tamil, Telugu',
        'education' => 'MBBS, MD (Medicine), DM (Medical Oncology) - Tata Memorial Centre',
        'working_hours' => '09:00 AM - 01:30 PM',
        'profile_image' => 'assets/doctors/doctor-13.jpg',
        'bio' => 'Consultant Medical Oncologist specializing in targeted cancer therapies, immunotherapy, clinical trials, and comprehensive palliative patient oncology support.'
    ],
    [
        'id' => 14,
        'name' => 'Dr. Harish Bhat',
        'specialization' => 'Nephrology',
        'department' => 'Nephrology & Renal Medicine',
        'phone' => '+91 98765 43223',
        'age' => 48,
        'gender' => 'Male',
        'experience' => '20 years',
        'consultation_fee' => 1000.00,
        'availability' => 'Available Today',
        'languages' => 'English, Hindi, Kannada',
        'education' => 'MBBS, MD (Medicine), DM (Nephrology) - Kasturba Medical College',
        'working_hours' => '01:00 PM - 06:00 PM',
        'profile_image' => 'assets/doctors/doctor-14.jpg',
        'bio' => 'Senior Nephrologist focusing on chronic kidney disease progression management, hemodialysis protocols, hypertension, and post-transplant renal care.'
    ]
];

foreach ($doctorsData as $d) {
    $check = $conn->prepare("SELECT id FROM doctors WHERE id = ?");
    $check->bind_param("i", $d['id']);
    $check->execute();
    $existing = $check->get_result()->fetch_assoc();
    $check->close();

    if ($existing) {
        $stmt = $conn->prepare("
            UPDATE doctors SET 
                doctor_name = ?,
                specialization = ?,
                department = ?,
                phone = ?,
                age = ?,
                gender = ?,
                experience = ?,
                consultation_fee = ?,
                availability = ?,
                languages = ?,
                education = ?,
                working_hours = ?,
                profile_image = ?,
                bio = ?
            WHERE id = ?
        ");
        $stmt->bind_param(
            "ssssissdssssssi",
            $d['name'],
            $d['specialization'],
            $d['department'],
            $d['phone'],
            $d['age'],
            $d['gender'],
            $d['experience'],
            $d['consultation_fee'],
            $d['availability'],
            $d['languages'],
            $d['education'],
            $d['working_hours'],
            $d['profile_image'],
            $d['bio'],
            $d['id']
        );
        $stmt->execute();
        $stmt->close();
        echo "  [*] Updated doctor #{$d['id']} ({$d['name']} - {$d['specialization']})\n";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO doctors (
                id, doctor_name, specialization, department, phone,
                age, gender, experience, consultation_fee, availability,
                languages, education, working_hours, profile_image, bio
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "issssissdssssss",
            $d['id'],
            $d['name'],
            $d['specialization'],
            $d['department'],
            $d['phone'],
            $d['age'],
            $d['gender'],
            $d['experience'],
            $d['consultation_fee'],
            $d['availability'],
            $d['languages'],
            $d['education'],
            $d['working_hours'],
            $d['profile_image'],
            $d['bio']
        );
        $stmt->execute();
        $stmt->close();
        echo "  [+] Inserted doctor #{$d['id']} ({$d['name']} - {$d['specialization']})\n";
    }
}

echo "Migration & Seeding completed successfully!\n";
