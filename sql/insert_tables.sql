--Insertion

USE IvorPaineHospital;
GO

-- 1. INSERT SPECIALITIES
INSERT INTO Speciality (speciality, description) VALUES
('Cardiology', 'Heart and cardiovascular diseases treatment'),
('Neurology', 'Nervous system and brain disorders'),
('Orthopedics', 'Bones, joints, and musculoskeletal disorders'),
('Pediatrics', 'Medical care for infants, children, and adolescents'),
('General Surgery', 'General surgical procedures and treatments'),
('Oncology', 'Cancer diagnosis and treatment'),
('Pulmonology', 'Respiratory system diseases'),
('Gastroenterology', 'Digestive system disorders'),
('Dermatology', 'Skin conditions and treatments'),
('Psychiatry', 'Mental health and behavioral disorders'),
('Obstetrics & Gynecology', 'Pregnancy, childbirth, and womens health'),
('Urology', 'Urinary system and male reproductive disorders'),
('Nephrology', 'Kidney and renal system disorders'),
('Endocrinology', 'Hormonal and metabolic disorders');

PRINT 'Specialities inserted: 14 records';
GO

-- 2. INSERT STAFF (Base table for all employees)
INSERT INTO Staff (fname, lname, email, telno, address) VALUES
-- Doctors (10)
('Muhammad', 'Ahmed', 'muhammadahmed@hospital.pk', '03001234567', '123 Medical Street, Rawalpindi'),
('Fatima', 'Khan', 'fatimakhan@hospital.pk', '03009876543', '456 Health Avenue, Rawalpindi'),
('Hassan', 'Ali', 'hassanali@hospital.pk', '03005555666', '789 Care Lane, Islamabad'),
('Ayesha', 'Hassan', 'ayeshahasan@hospital.pk', '03007777888', '321 Doctor Row, Rawalpindi'),
('Ali', 'Raza', 'aliraza@hospital.pk', '03004444555', '654 Healing Street, Islamabad'),
('Zainab', 'Malik', 'zainabmalik@hospital.pk', '03006666777', '987 Medical Plaza, Rawalpindi'),
('Imran', 'Shah', 'imranshah@hospital.pk', '03003333444', '147 Hospital Drive, Islamabad'),
('Hira', 'Ahmad', 'hiraahmad@hospital.pk', '03008888999', '258 Clinical Road, Rawalpindi'),
('Bilal', 'Hassan', 'bilalhassan@hospital.pk', '03002222333', '369 Health Center, Islamabad'),
('Saadia', 'Iqbal', 'saadiaiqbal@hospital.pk', '03001111222', '741 Medical Court, Rawalpindi'),

-- Nurses (15)
('Amina', 'Siddiqui', 'aminasiddiqui@hospital.pk', '03009999000', '852 Nursing Way, Rawalpindi'),
('Naida', 'Mirza', 'naidamirza@hospital.pk', '03008765432', '963 Care Street, Islamabad'),
('Khadija', 'Nasir', 'khadijanasir@hospital.pk', '03007654321', '159 Health Road, Rawalpindi'),
('Bushra', 'Farooq', 'bushafarooq@hospital.pk', '03006543210', '267 Medical Lane, Islamabad'),
('Iqra', 'Hassan', 'iqrahassan@hospital.pk', '03005432109', '375 Nursing Plaza, Rawalpindi'),
('Mariam', 'Khan', 'mariamkhan@hospital.pk', '03004321098', '483 Care Avenue, Islamabad'),
('Nadia', 'Malik', 'nadiamalik@hospital.pk', '03003210987', '591 Hospital Lane, Rawalpindi'),
('Rukhsana', 'Ahmed', 'rukhsanaahmed@hospital.pk', '03002109876', '604 Health Street, Islamabad'),
('Samina', 'Aziz', 'saminaaziz@hospital.pk', '03001098765', '712 Medical Drive, Rawalpindi'),
('Tahira', 'Hussain', 'tahirahussain@hospital.pk', '03009876541', '820 Nursing Road, Islamabad'),
('Zahra', 'Yousaf', 'zahrayousaf@hospital.pk', '03008765430', '938 Care Court, Rawalpindi'),
('Leila', 'Amin', 'leilaamin@hospital.pk', '03007654320', '046 Health Plaza, Islamabad'),
('Maha', 'Rashid', 'maharashid@hospital.pk', '03006543209', '154 Medical Way, Rawalpindi'),
('Noor', 'Abdullah', 'noorabdullah@hospital.pk', '03005432108', '262 Nursing Street, Islamabad'),
('Rania', 'Salim', 'raniasalim@hospital.pk', '03004321097', '370 Care Drive, Rawalpindi');

PRINT 'Staff inserted: 25 records (10 doctors + 15 nurses)';
GO

-- 3. INSERT TEAMS
INSERT INTO Team (team_name) VALUES
('Cardiac Team A'),
('Cardiac Team B'),
('Neurological Team'),
('Orthopedic Team'),
('Surgical Team A'),
('Surgical Team B'),
('Oncology Team'),
('Pediatric Team');

PRINT 'Teams inserted: 8 records';
GO

-- 4. INSERT DOCTORS
INSERT INTO Doctor (d_id, position, t_id) VALUES
(1, 'Cardiologist', 1),
(2, 'Neurologist', 3),
(3, 'Orthopedic Surgeon', 4),
(4, 'General Surgeon', 5),
(5, 'Oncologist', 7),
(6, 'Cardiologist', 2),
(7, 'Pediatrician', 8),
(8, 'General Surgeon', 6),
(9, 'Cardiologist', 1),
(10, 'Neurologist', 3);

PRINT 'Doctors inserted: 10 records';
GO

-- 5. UPDATE TEAM LEADS (Add team leads to teams)
UPDATE Team SET team_lead = 1 WHERE t_id = 1;
UPDATE Team SET team_lead = 6 WHERE t_id = 2;
UPDATE Team SET team_lead = 2 WHERE t_id = 3;
UPDATE Team SET team_lead = 3 WHERE t_id = 4;
UPDATE Team SET team_lead = 4 WHERE t_id = 5;
UPDATE Team SET team_lead = 8 WHERE t_id = 6;
UPDATE Team SET team_lead = 5 WHERE t_id = 7;
UPDATE Team SET team_lead = 7 WHERE t_id = 8;

PRINT 'Team leads updated: 8 records';
GO

-- 6. INSERT CONSULTANTS
INSERT INTO Consultant (c_id, sp_id) VALUES
(1, 1),   -- Muhammad Ahmed - Cardiology
(2, 2),   -- Fatima Khan - Neurology
(3, 3),   -- Hassan Ali - Orthopedics
(4, 5),   -- Ayesha Hassan - General Surgery
(5, 6),   -- Ali Raza - Oncology
(6, 1),   -- Zainab Malik - Cardiology
(7, 4),   -- Imran Shah - Pediatrics
(8, 5),   -- Hira Ahmad - General Surgery
(9, 1),   -- Bilal Hassan - Cardiology
(10, 2);  -- Saadia Iqbal - Neurology

PRINT 'Consultants inserted: 10 records';
GO

-- 7. INSERT WARDS
INSERT INTO Ward (name, sp_id, day_sister, night_sister) VALUES
('Cardiac Ward A', 1, 11, 12),
('Cardiac Ward B', 1, 13, 14),
('Neurological Ward', 2, 15, 16),
('Orthopedic Ward', 3, 17, 18),
('Surgical Ward A', 5, 19, 20),
('Surgical Ward B', 5, 21, 11),
('Pediatric Ward', 4, 22, 23),
('Oncology Ward', 6, 24, 25),
('Gastroenterology Ward', 8, 12, 13),
('Pulmonology Ward', 7, 14, 15);

PRINT 'Wards inserted: 10 records';
GO
-- 8. INSERT NURSES
INSERT INTO Nurse (n_id, reg_id, w_id) VALUES
(11, 11, 1),
(12, 12, 1),
(13, 13, 2),
(14, 14, 2),
(15, 15, 3),
(16, 16, 3),
(17, 17, 4),
(18, 18, 4),
(19, 19, 5),
(20, 20, 5),
(21, 21, 6),
(22, 22, 7),
(23, 23, 7),
(24, 24, 8),
(25, 25, 9);

PRINT 'Nurses inserted: 15 records';
GO

-- 9. INSERT CARE UNITS
INSERT INTO CareUnit (n_id, w_id, in_charge) VALUES
(11, 1, 'ICU - Cardiac Unit A'),
(13, 2, 'ICU - Cardiac Unit B'),
(15, 3, 'HDU - Neurology'),
(17, 4, 'Recovery - Orthopedic'),
(19, 5, 'HDU - Surgical A'),
(21, 6, 'HDU - Surgical B'),
(22, 7, 'Pediatric ICU'),
(24, 8, 'Oncology Unit'),
(12, 1, 'General - Cardiac Ward A'),
(14, 2, 'General - Cardiac Ward B'),
(16, 3, 'General - Neurology'),
(18, 4, 'General - Orthopedic'),
(20, 5, 'General - Surgical A'),
(25, 9, 'General - Gastroenterology'),
(11, 10, 'General - Pulmonology');

PRINT 'Care Units inserted: 15 records';
GO

-- 10. INSERT PREVIOUS EXPERIENCE
INSERT INTO PrevExperience (d_id, establishment, position, from_date, to_date) VALUES
(1, 'Shifa International Hospital', 'Senior Cardiologist', '2010-01-15', '2018-06-30'),
(1, 'National Institute of Cardiology', 'Cardiologist', '2008-03-01', '2010-01-14'),
(2, 'Pakistan Institute of Medical Sciences', 'Neurologist', '2012-05-20', '2017-12-31'),
(3, 'Mayo Hospital Lahore', 'Orthopedic Surgeon', '2009-07-10', '2016-11-30'),
(4, 'Armed Forces Institute of Cardiology', 'Surgeon', '2011-02-01', '2019-08-31'),
(5, 'Aga Khan University Hospital', 'Oncologist', '2013-09-15', '2020-06-30'),
(6, 'Evercare Hospital', 'Cardiologist', '2014-04-01', NULL),
(7, 'Pakistan Institute of Medical Sciences', 'Pediatrician', '2015-06-01', NULL),
(8, 'Shifa International Hospital', 'Surgeon', '2016-01-15', NULL),
(9, 'National Institute of Cardiology', 'Cardiologist', '2017-03-01', NULL),
(10, 'Jinnah Postgraduate Medical Centre', 'Neurologist', '2018-05-20', NULL);

PRINT 'Previous Experience inserted: 11 records';
GO

-- 11. INSERT PATIENTS (30+)
INSERT INTO Patient (fname, lname, dob, admission_date, discharge_date, telno, address, bed_no, w_id) VALUES
-- Cardiac Ward A Patients
('Muhammad', 'Hassan', '1965-03-15', '2024-05-10', '2024-05-25', '03001111111', '1234 Main Street, Rawalpindi', 101, 1),
('Fatima', 'Ahmed', '1972-07-22', '2024-05-12', NULL, '03002222222', '5678 Park Road, Islamabad', 102, 1),
('Ali', 'Khan', '1955-11-08', '2024-05-08', '2024-05-20', '03003333333', '9012 Hospital Lane, Rawalpindi', 103, 1),
('Ayesha', 'Malik', '1968-01-30', '2024-05-15', NULL, '03004444444', '3456 Care Avenue, Islamabad', 104, 1),

-- Cardiac Ward B Patients
('Hassan', 'Abdullah', '1960-06-18', '2024-05-11', '2024-05-24', '03005555555', '7890 Medical Street, Rawalpindi', 201, 2),
('Zainab', 'Iqbal', '1975-09-25', '2024-05-13', NULL, '03006666666', '1234 Health Road, Islamabad', 202, 2),
('Bilal', 'Raza', '1970-04-12', '2024-05-14', NULL, '03007777777', '5678 Nursing Way, Rawalpindi', 203, 2),
('Amina', 'Hassan', '1962-12-03', '2024-05-16', '2024-05-26', '03008888888', '9012 Clinical Drive, Islamabad', 204, 2),

-- Neurological Ward Patients
('Imran', 'Hussain', '1958-08-20', '2024-05-09', NULL, '03009999999', '3456 Neuro Lane, Rawalpindi', 301, 3),
('Hira', 'Nasir', '1973-05-14', '2024-05-12', '2024-05-22', '03001010101', '7890 Brain Street, Islamabad', 302, 3),
('Karim', 'Samir', '1967-10-28', '2024-05-11', NULL, '03001111112', '1234 Mind Road, Rawalpindi', 303, 3),
('Nadia', 'Farooq', '1969-02-07', '2024-05-15', NULL, '03001111113', '5678 Neuro Plaza, Islamabad', 304, 3),

-- Orthopedic Ward Patients
('Saad', 'Aziz', '1945-04-19', '2024-05-10', NULL, '03001111114', '9012 Bone Street, Rawalpindi', 401, 4),
('Rukhsana', 'Malik', '1980-06-11', '2024-05-13', '2024-05-23', '03001111115', '3456 Joint Lane, Islamabad', 402, 4),
('Tariq', 'Ahmed', '1952-09-30', '2024-05-14', NULL, '03001111116', '7890 Ortho Road, Rawalpindi', 403, 4),
('Leila', 'Hassan', '1978-03-25', '2024-05-16', NULL, '03001111117', '1234 Fracture Way, Islamabad', 404, 4),

-- Surgical Ward A Patients
('Rashid', 'Khan', '1950-11-12', '2024-05-09', '2024-05-21', '03001111118', '5678 Surgery Lane, Rawalpindi', 501, 5),
('Mariam', 'Iqbal', '1974-08-05', '2024-05-11', NULL, '03001111119', '9012 Surgery Street, Islamabad', 502, 5),
('Younis', 'Malik', '1960-07-16', '2024-05-12', '2024-05-24', '03001111120', '3456 Clinical Road, Rawalpindi', 503, 5),
('Noor', 'Salim', '1985-01-22', '2024-05-14', NULL, '03001111121', '7890 Surgery Plaza, Islamabad', 504, 5),

-- Surgical Ward B Patients
('Hamza', 'Ali', '1975-05-30', '2024-05-10', NULL, '03001111122', '1234 Operation Lane, Rawalpindi', 601, 6),
('Saida', 'Raza', '1965-12-14', '2024-05-13', '2024-05-25', '03001111123', '5678 Surgical Way, Islamabad', 602, 6),
('Faisal', 'Ahmad', '1968-09-08', '2024-05-15', NULL, '03001111124', '9012 Surgery Road, Rawalpindi', 603, 6),
('Laila', 'Khan', '1982-04-03', '2024-05-16', NULL, '03001111125', '3456 Theatre Lane, Islamabad', 604, 6),

-- Pediatric Ward Patients
('Ahmed', 'Hassan', '2015-03-20', '2024-05-10', '2024-05-18', '03001111126', '7890 Children Way, Rawalpindi', 701, 7),
('Fatima', 'Ali', '2016-07-12', '2024-05-12', NULL, '03001111127', '1234 Kidz Lane, Islamabad', 702, 7),
('Muhammad', 'Khan', '2014-11-25', '2024-05-14', '2024-05-22', '03001111128', '5678 Pediatric Street, Rawalpindi', 703, 7),
('Zainab', 'Ahmed', '2017-02-08', '2024-05-15', NULL, '03001111129', '9012 Children Road, Islamabad', 704, 7),

-- Oncology Ward Patients
('Ibrahim', 'Malik', '1948-06-10', '2024-05-09', NULL, '03001111130', '3456 Oncology Lane, Rawalpindi', 801, 8),
('Aisha', 'Hassan', '1970-10-17', '2024-05-11', NULL, '03001111131', '7890 Cancer Street, Islamabad', 802, 8),
('Waleed', 'Aziz', '1955-08-22', '2024-05-13', NULL, '03001111132', '1234 Treatment Road, Rawalpindi', 803, 8),
('Maryam', 'Salim', '1972-04-14', '2024-05-15', NULL, '03001111133', '5678 Care Way, Islamabad', 804, 8),

-- Gastroenterology Ward Patients
('Nasir', 'Khan', '1963-01-19', '2024-05-10', NULL, '03001111134', '9012 Gastro Lane, Rawalpindi', 901, 9),
('Sana', 'Ahmed', '1975-09-06', '2024-05-12', '2024-05-20', '03001111135', '3456 Digestive Street, Islamabad', 902, 9);

PRINT 'Patients inserted: 35 records';
GO

-- 12. INSERT COMPLAINTS
INSERT INTO Complaint (title, description, p_id) VALUES
('Chest Pain', 'Severe chest pain radiating to left arm', 1),
('Shortness of Breath', 'Acute shortness of breath', 2),
('Irregular Heartbeat', 'Palpitations and irregular heartbeat', 3),
('High Blood Pressure', 'Persistent hypertension', 4),
('Chest Discomfort', 'Mild chest discomfort on exertion', 5),
('Heart Murmur', 'Abnormal heart sound detected', 6),
('Arrhythmia', 'Irregular heart rhythm', 7),
('Edema', 'Swelling in legs and feet', 8),
('Severe Headache', 'Chronic migraine headaches', 9),
('Dizziness', 'Frequent episodes of dizziness', 10),
('Numbness', 'Numbness in hands and feet', 11),
('Weakness', 'General muscle weakness', 12),
('Bone Pain', 'Severe pain in hip and knee', 13),
('Fracture Arm', 'Fractured left arm', 14),
('Spinal Pain', 'Chronic lower back pain', 15),
('Mobility Issues', 'Difficulty walking and moving', 16),
('Abdominal Pain', 'Acute abdominal pain', 17),
('Post-Surgery Care', 'Recovery from appendicectomy', 18),
('Bleeding', 'Post-operative bleeding', 19),
('Infection Risk', 'Post-surgical wound care', 20),
('Fever', 'High fever in child', 21),
('Dehydration', 'Severe dehydration in pediatric patient', 22),
('Cough', 'Persistent cough in child', 23),
('Rash', 'Allergic rash in pediatric patient', 24),
('Tumor', 'Diagnosed stomach cancer', 25),
('Pain Management', 'Chronic cancer pain', 26),
('Lymph Issues', 'Enlarged lymph nodes', 27),
('Nausea', 'Chemotherapy side effects', 28),
('Indigestion', 'Acid reflux and indigestion', 29),
('Nausea GI', 'Nausea and vomiting', 30);

PRINT 'Complaints inserted: 30 records';
GO

-- 13. INSERT TREATMENTS
INSERT INTO Treatment (startdate, enddate, p_id, d_id) VALUES
-- Cardiac treatments
('2024-05-10', '2024-05-25', 1, 1),
('2024-05-12', NULL, 2, 1),
('2024-05-08', '2024-05-20', 3, 6),
('2024-05-15', NULL, 4, 6),
('2024-05-11', '2024-05-24', 5, 9),
('2024-05-13', NULL, 6, 9),
('2024-05-14', NULL, 7, 1),
('2024-05-16', '2024-05-26', 8, 6),
-- Neurological treatments
('2024-05-09', NULL, 9, 2),
('2024-05-12', '2024-05-22', 10, 2),
('2024-05-11', NULL, 11, 10),
('2024-05-15', NULL, 12, 10),
-- Orthopedic treatments
('2024-05-10', NULL, 13, 3),
('2024-05-13', '2024-05-23', 14, 3),
('2024-05-14', NULL, 15, 3),
('2024-05-16', NULL, 16, 3),
-- Surgical treatments
('2024-05-09', '2024-05-21', 17, 4),
('2024-05-11', NULL, 18, 4),
('2024-05-12', '2024-05-24', 19, 8),
('2024-05-14', NULL, 20, 8),
('2024-05-10', NULL, 21, 7),
('2024-05-12', NULL, 22, 7),
('2024-05-14', '2024-05-22', 23, 7),
('2024-05-15', NULL, 24, 7),
-- Oncology treatments
('2024-05-09', NULL, 25, 5),
('2024-05-11', NULL, 26, 5),
('2024-05-13', NULL, 27, 5),
('2024-05-15', NULL, 28, 5),
-- Gastroenterology treatments
('2024-05-10', NULL, 29, 4),
('2024-05-12', '2024-05-20', 30, 4);

PRINT 'Treatments inserted: 30 records';
GO

-- 14. INSERT PROGRESS NOTES
INSERT INTO Progress (c_id, p_id, date_grade, performance) VALUES
-- Cardiac consultants tracking patients
(1, 1, '2024-05-10', 'Stable condition, awaiting ECG results'),
(1, 1, '2024-05-15', 'Good response to medication, continuing treatment'),
(1, 2, '2024-05-12', 'Initial assessment complete, started beta blockers'),
(1, 2, '2024-05-17', 'Improvement noted, oxygen saturation stable'),
(1, 3, '2024-05-08', 'Severe pain managed with morphine'),
(1, 3, '2024-05-14', 'Excellent recovery, discharge planned'),
(6, 5, '2024-05-11', 'Blood pressure controlled'),
(6, 6, '2024-05-13', 'Stable vital signs'),
(9, 7, '2024-05-14', 'Arrhythmia episodes reduced'),
-- Neurological consultants tracking
(2, 9, '2024-05-09', 'MRI scheduled for next week'),
(2, 9, '2024-05-12', 'Imaging shows no structural damage'),
(2, 10, '2024-05-12', 'Medication adjusted, symptoms improving'),
(10, 11, '2024-05-11', 'Nerve conduction studies planned'),
(10, 12, '2024-05-15', 'Good progress with physical therapy'),
-- Orthopedic consultants tracking
(3, 13, '2024-05-10', 'X-ray confirms fracture, casted'),
(3, 14, '2024-05-13', 'Cast applied, pain managed'),
(3, 15, '2024-05-14', 'Physical therapy initiated'),
(3, 16, '2024-05-16', 'Mobility improving steadily'),
-- Surgical consultants tracking
(4, 17, '2024-05-09', 'Post-op day 1, vital signs stable'),
(4, 18, '2024-05-11', 'Wound healing well, infection free'),
(8, 19, '2024-05-12', 'Monitoring bleeding, transfusion given'),
(8, 20, '2024-05-14', 'Recovered from surgery, discharge pending'),
-- Pediatric consultants tracking
(7, 21, '2024-05-10', 'Fever reduced, responding to antibiotics'),
(7, 22, '2024-05-12', 'IV fluids started, improving hydration'),
(7, 23, '2024-05-14', 'Cough improving, chest clear'),
(7, 24, '2024-05-15', 'Rash clearing with treatment'),
-- Oncology consultants tracking
(5, 25, '2024-05-09', 'Chemotherapy cycle 1 started'),
(5, 26, '2024-05-11', 'Pain well controlled'),
(5, 27, '2024-05-13', 'Lymph node reduction noted'),
(5, 28, '2024-05-15', 'Side effects managed, continuing protocol');

PRINT 'Progress notes inserted: 32 records';
GO

-- 15. INSERT PATIENT RECORDS (Junction table)
INSERT INTO PatientRecord (p_id, d_id, c_code) VALUES
(1, 1, 1), (2, 1, 2), (3, 6, 3), (4, 6, 4), (5, 9, 5), (6, 9, 6), (7, 1, 7), (8, 6, 8),
(9, 2, 9), (10, 2, 10), (11, 10, 11), (12, 10, 12),
(13, 3, 13), (14, 3, 14), (15, 3, 15), (16, 3, 16),
(17, 4, 17), (18, 4, 18), (19, 8, 19), (20, 8, 20),
(21, 7, 21), (22, 7, 22), (23, 7, 23), (24, 7, 24),
(25, 5, 25), (26, 5, 26), (27, 5, 27), (28, 5, 28),
(29, 4, 29), (30, 4, 30);

PRINT 'Patient Records inserted: 30 records';
GO

-- 16. INSERT NURSE WARD ASSIGNMENTS
INSERT INTO Nurse_Ward_Assignment (n_id, w_id, from_date, to_date, shift) VALUES
(11, 1, '2024-01-01', NULL, 'Morning'),
(12, 1, '2024-01-01', NULL, 'Evening'),
(13, 2, '2024-01-01', NULL, 'Morning'),
(14, 2, '2024-01-01', NULL, 'Evening'),
(15, 3, '2024-01-01', NULL, 'Morning'),
(16, 3, '2024-01-01', NULL, 'Evening'),
(17, 4, '2024-01-01', NULL, 'Morning'),
(18, 4, '2024-01-01', NULL, 'Evening'),
(19, 5, '2024-01-01', NULL, 'Morning'),
(20, 5, '2024-01-01', NULL, 'Evening'),
(21, 6, '2024-01-01', NULL, 'Morning'),
(22, 7, '2024-01-01', NULL, 'Morning'),
(23, 7, '2024-01-01', NULL, 'Evening'),
(24, 8, '2024-01-01', NULL, 'Morning'),
(25, 9, '2024-01-01', NULL, 'Morning'),
(11, 10, '2024-02-01', NULL, 'Evening'),
(12, 10, '2024-02-01', NULL, 'Morning');

PRINT 'Nurse Ward Assignments inserted: 17 records';
GO

-- SUMMARY AND VERIFICATION
PRINT '';
PRINT '====================================================';
PRINT 'SAMPLE DATA INSERTION COMPLETED SUCCESSFULLY!';
PRINT '====================================================';
PRINT '';
PRINT 'Data Summary:';
PRINT '- Specialities: 14 records';
PRINT '- Staff: 25 records (10 Doctors, 15 Nurses)';
PRINT '- Doctors: 10 records';
PRINT '- Nurses: 15 records';
PRINT '- Teams: 8 records';
PRINT '- Consultants: 10 records';
PRINT '- Wards: 10 records';
PRINT '- Care Units: 15 records';
PRINT '- Patients: 35 records (exceeds 30 minimum)';
PRINT '- Complaints: 30 records';
PRINT '- Treatments: 30 records';
PRINT '- Progress Notes: 32 records';
PRINT '- Patient Records: 30 records';
PRINT '- Previous Experience: 11 records';
PRINT '- Nurse Ward Assignments: 17 records';
PRINT '====================================================';
PRINT '';
GO



-- View Current Patients
PRINT 'Current Patients in Hospital:';
SELECT 
    p.p_id,
    CONCAT(p.fname, ' ', p.lname) AS PatientName,
    p.dob,
    p.admission_date,
    p.bed_no,
    w.name AS Ward
FROM Patient p
INNER JOIN Ward w ON p.w_id = w.w_id
WHERE p.discharge_date IS NULL
ORDER BY p.admission_date DESC;

-- View Doctor and Team Information
PRINT '';
PRINT 'Doctor and Team Information:';
SELECT 
    d.d_id,
    CONCAT(s.fname, ' ', s.lname) AS DoctorName,
    d.position,
    t.team_name,
    sp.speciality
FROM Doctor d
INNER JOIN Staff s ON d.d_id = s.st_id
LEFT JOIN Team t ON d.t_id = t.t_id
LEFT JOIN Consultant c ON d.d_id = c.c_id
LEFT JOIN Speciality sp ON c.sp_id = sp.sp_id
ORDER BY d.d_id;

-- View Ward Census
PRINT '';
PRINT 'Ward Census (Current Patients):';
SELECT 
    w.w_id,
    w.name AS Ward,
    sp.speciality,
    COUNT(p.p_id) AS CurrentPatients
FROM Ward w
LEFT JOIN Speciality sp ON w.sp_id = sp.sp_id
LEFT JOIN Patient p ON w.w_id = p.w_id AND p.discharge_date IS NULL
GROUP BY w.w_id, w.name, sp.speciality
ORDER BY w.w_id;

PRINT '';
PRINT 'All data is ready for testing and demonstration!';
GO

