
--  DDL Script
-- Database: IvorPaineHospital


IF NOT EXISTS (
    SELECT name 
    FROM sys.databases 
    WHERE name = 'IvorPaineHospital'
)
BEGIN
    CREATE DATABASE IvorPaineHospital;
END;
GO

USE IvorPaineHospital;
GO

-- 1. STAFF TABLE (Base Table)
CREATE TABLE Staff (
    st_id INT PRIMARY KEY IDENTITY(1,1),
    fname VARCHAR(50) NOT NULL,
    lname VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    telno VARCHAR(20) NOT NULL,
    address VARCHAR(255) NOT NULL,
    created_date DATETIME DEFAULT GETDATE()
);

-- 2. SPECIALITY TABLE (Lookup Table)
CREATE TABLE Speciality (
    sp_id INT PRIMARY KEY IDENTITY(1,1),
    speciality VARCHAR(100) NOT NULL UNIQUE,
    description TEXT
);

-- 3. TEAM TABLE
CREATE TABLE Team (
    t_id INT PRIMARY KEY IDENTITY(1,1),
    team_name VARCHAR(50) NOT NULL,
    team_lead INT,
    -- Foreign Key will be added after Doctor table creation
    created_date DATETIME DEFAULT GETDATE()
);

-- 4. DOCTOR TABLE (Subclass of Staff)
CREATE TABLE Doctor (
    d_id INT PRIMARY KEY,
    position VARCHAR(50) NOT NULL,
    t_id INT,
    CONSTRAINT FK_Doctor_Staff FOREIGN KEY (d_id) 
        REFERENCES Staff(st_id) ON DELETE CASCADE,
    CONSTRAINT FK_Doctor_Team FOREIGN KEY (t_id) 
        REFERENCES Team(t_id) ON DELETE SET NULL
);

-- Add Foreign Key for team_lead in Team table
ALTER TABLE Team
ADD CONSTRAINT FK_Team_Doctor FOREIGN KEY (team_lead) 
    REFERENCES Doctor(d_id) ON DELETE SET NULL;

-- 5. CONSULTANT TABLE (Subclass of Staff)
CREATE TABLE Consultant (
    c_id INT PRIMARY KEY,
    sp_id INT NOT NULL,
    CONSTRAINT FK_Consultant_Staff FOREIGN KEY (c_id) 
        REFERENCES Staff(st_id) ON DELETE CASCADE,
    CONSTRAINT FK_Consultant_Speciality FOREIGN KEY (sp_id) 
        REFERENCES Speciality(sp_id)
);

-- 6. WARD TABLE
CREATE TABLE Ward (
    w_id INT PRIMARY KEY IDENTITY(1,1),
    name VARCHAR(50) NOT NULL,
    sp_id INT NOT NULL,
    day_sister INT,
    night_sister INT,
    created_date DATETIME DEFAULT GETDATE(),
    CONSTRAINT FK_Ward_Speciality FOREIGN KEY (sp_id) 
        REFERENCES Speciality(sp_id),
    CONSTRAINT FK_Ward_DaySister FOREIGN KEY (day_sister) 
        REFERENCES Staff(st_id) ON DELETE NO ACTION ON UPDATE NO ACTION,
    CONSTRAINT FK_Ward_NightSister FOREIGN KEY (night_sister) 
        REFERENCES Staff(st_id) ON DELETE NO ACTION ON UPDATE NO ACTION
);

-- =====================================================
-- 7. NURSE TABLE (Subclass of Staff)
-- =====================================================
CREATE TABLE Nurse (
    n_id INT PRIMARY KEY,
    reg_id INT UNIQUE,
    w_id INT,
    CONSTRAINT FK_Nurse_Staff FOREIGN KEY (n_id) 
        REFERENCES Staff(st_id) ON DELETE CASCADE,
    CONSTRAINT FK_Nurse_RegStaff FOREIGN KEY (reg_id) 
        REFERENCES Staff(st_id) ON DELETE NO ACTION ON UPDATE NO ACTION,
    CONSTRAINT FK_Nurse_Ward FOREIGN KEY (w_id) 
        REFERENCES Ward(w_id) ON DELETE SET NULL
);

-- 8. CARE UNIT TABLE
CREATE TABLE CareUnit (
    cu_id INT PRIMARY KEY IDENTITY(1,1),
    n_id INT,
    w_id INT NOT NULL,
    in_charge VARCHAR(50),
    created_date DATETIME DEFAULT GETDATE(),
    CONSTRAINT FK_CareUnit_Nurse FOREIGN KEY (n_id) 
        REFERENCES Nurse(n_id) ON DELETE SET NULL,
    CONSTRAINT FK_CareUnit_Ward FOREIGN KEY (w_id) 
        REFERENCES Ward(w_id)
);

-- 9. PATIENT TABLE
CREATE TABLE Patient (
    p_id INT PRIMARY KEY IDENTITY(1,1),
    fname VARCHAR(50) NOT NULL,
    lname VARCHAR(50) NOT NULL,
    dob DATE NOT NULL,
    admission_date DATE NOT NULL,
    discharge_date DATE,
    telno VARCHAR(20),
    address VARCHAR(255),
    bed_no INT,
    w_id INT,
    created_date DATETIME DEFAULT GETDATE(),
    CONSTRAINT FK_Patient_Ward FOREIGN KEY (w_id) 
        REFERENCES Ward(w_id) ON DELETE SET NULL,
    CONSTRAINT CHK_DischargeDateAfterAdmission CHECK (discharge_date IS NULL OR discharge_date >= admission_date),
    CONSTRAINT CHK_DOBNotInFuture CHECK (dob <= CAST(GETDATE() AS DATE))
);


-- 10. COMPLAINT TABLr
CREATE TABLE Complaint (
    c_code INT PRIMARY KEY IDENTITY(1,1),
    title VARCHAR(100) NOT NULL,
    description TEXT,
    p_id INT NOT NULL,
    created_date DATETIME DEFAULT GETDATE(),
    CONSTRAINT FK_Complaint_Patient FOREIGN KEY (p_id) 
        REFERENCES Patient(p_id) ON DELETE CASCADE
);

-- 11. TREATMENT TABLE
CREATE TABLE Treatment (
    t_code INT PRIMARY KEY IDENTITY(1,1),
    startdate DATE NOT NULL,
    enddate DATE,
    p_id INT NOT NULL,
    d_id INT NOT NULL,
    created_date DATETIME DEFAULT GETDATE(),
    CONSTRAINT FK_Treatment_Patient FOREIGN KEY (p_id) 
        REFERENCES Patient(p_id) ON DELETE CASCADE,
    CONSTRAINT FK_Treatment_Doctor FOREIGN KEY (d_id) 
        REFERENCES Doctor(d_id) ON DELETE NO ACTION ON UPDATE NO ACTION,
    CONSTRAINT CHK_TreatmentEndDateAfterStart CHECK (enddate IS NULL OR enddate >= startdate)
);

-- 12. PROGRESS TABLE
-- 
CREATE TABLE Progress (
    c_id INT NOT NULL,
    p_id INT NOT NULL,
    date_grade DATE NOT NULL,
    performance VARCHAR(255),
    created_date DATETIME DEFAULT GETDATE(),
    PRIMARY KEY (c_id, p_id, date_grade),
    CONSTRAINT FK_Progress_Consultant FOREIGN KEY (c_id) 
        REFERENCES Consultant(c_id) ON DELETE NO ACTION ON UPDATE NO ACTION,
    CONSTRAINT FK_Progress_Patient FOREIGN KEY (p_id) 
        REFERENCES Patient(p_id) ON DELETE CASCADE,
    CONSTRAINT CHK_ProgressDateNotInFuture CHECK (date_grade <= CAST(GETDATE() AS DATE))
);

-- 13. PREVIOUS EXPERIENCE TABLE
CREATE TABLE PrevExperience (
    d_id INT NOT NULL,
    establishment VARCHAR(100) NOT NULL,
    position VARCHAR(50),
    from_date DATE NOT NULL,
    to_date DATE,
    PRIMARY KEY (d_id, establishment),
    CONSTRAINT FK_PrevExp_Doctor FOREIGN KEY (d_id) 
        REFERENCES Doctor(d_id) ON DELETE CASCADE,
    CONSTRAINT CHK_ExperienceDateRange CHECK (to_date IS NULL OR to_date >= from_date)
);

-- 14. PATIENT RECORD TABLE (Junction Table)
CREATE TABLE PatientRecord (
    p_id INT NOT NULL,
    d_id INT NOT NULL,
    c_code INT NOT NULL,
    created_date DATETIME DEFAULT GETDATE(),
    PRIMARY KEY (p_id, d_id, c_code),
    CONSTRAINT FK_PatRecord_Patient FOREIGN KEY (p_id) 
        REFERENCES Patient(p_id) ON DELETE CASCADE,
    CONSTRAINT FK_PatRecord_Doctor FOREIGN KEY (d_id) 
        REFERENCES Doctor(d_id) ON DELETE NO ACTION ON UPDATE NO ACTION,
    CONSTRAINT FK_PatRecord_Complaint FOREIGN KEY (c_code) 
        REFERENCES Complaint(c_code) ON DELETE NO ACTION ON UPDATE NO ACTION
);
 
-- 15. NURSE WARD ASSIGNMENT TABLE (Junction Table)
CREATE TABLE Nurse_Ward_Assignment (
    n_id INT NOT NULL,
    w_id INT NOT NULL,
    from_date DATE NOT NULL DEFAULT CAST(GETDATE() AS DATE),
    to_date DATE,
    shift VARCHAR(20),
    created_date DATETIME DEFAULT GETDATE(),
    PRIMARY KEY (n_id, w_id, from_date),
    CONSTRAINT FK_NWA_Nurse FOREIGN KEY (n_id) 
        REFERENCES Nurse(n_id) ON DELETE CASCADE,
    CONSTRAINT FK_NWA_Ward FOREIGN KEY (w_id) 
        REFERENCES Ward(w_id) ON DELETE NO ACTION ON UPDATE NO ACTION,
    CONSTRAINT CHK_AssignmentDateRange CHECK (to_date IS NULL OR to_date >= from_date)
);
 
-- CREATE INDEXES FOR PERFORMANCE
 
-- Patient Indexes
CREATE INDEX IDX_Patient_AdmissionDate ON Patient(admission_date);
CREATE INDEX IDX_Patient_Ward ON Patient(w_id);
CREATE INDEX IDX_Patient_FullName ON Patient(fname, lname);
 
-- Doctor Indexes
CREATE INDEX IDX_Doctor_Team ON Doctor(t_id);
CREATE INDEX IDX_Doctor_Position ON Doctor(position);
 
-- Consultant Indexes
CREATE INDEX IDX_Consultant_Speciality ON Consultant(sp_id);
 
-- Treatment Indexes
CREATE INDEX IDX_Treatment_Patient ON Treatment(p_id);
CREATE INDEX IDX_Treatment_Doctor ON Treatment(d_id);
CREATE INDEX IDX_Treatment_DateRange ON Treatment(startdate, enddate);
 
-- Complaint Indexes
CREATE INDEX IDX_Complaint_Patient ON Complaint(p_id);
 
-- Progress Indexes
CREATE INDEX IDX_Progress_Patient ON Progress(p_id);
CREATE INDEX IDX_Progress_Consultant ON Progress(c_id);
CREATE INDEX IDX_Progress_Date ON Progress(date_grade);
 
-- Nurse Indexes
CREATE INDEX IDX_Nurse_RegID ON Nurse(reg_id);
CREATE INDEX IDX_Nurse_Ward ON Nurse(w_id);
 
-- COMMON QUERIES
GO
-- View: Current Patients (Admitted but not discharged)
CREATE VIEW vw_CurrentPatients AS
SELECT 
    p.p_id,
    p.fname,
    p.lname,
    p.dob,
    p.admission_date,
    p.bed_no,
    w.name AS ward_name,
    s.speciality
FROM Patient p
INNER JOIN Ward w ON p.w_id = w.w_id
INNER JOIN Speciality s ON w.sp_id = s.sp_id
WHERE p.discharge_date IS NULL;
GO
 
-- View: Doctor Details with Team Information
CREATE VIEW vw_DoctorDetails AS
SELECT 
    d.d_id,
    s.fname,
    s.lname,
    s.email,
    s.telno,
    d.position,
    t.team_name,
    t.t_id
FROM Doctor d
INNER JOIN Staff s ON d.d_id = s.st_id
LEFT JOIN Team t ON d.t_id = t.t_id;
GO
 
-- View: Consultant Specializations
CREATE VIEW vw_ConsultantSpecialities AS
SELECT 
    c.c_id,
    s.fname,
    s.lname,
    s.email,
    sp.speciality,
    sp.description
FROM Consultant c
INNER JOIN Staff s ON c.c_id = s.st_id
INNER JOIN Speciality sp ON c.sp_id = sp.sp_id;
GO
 
-- View: Ward Information with Speciality
CREATE VIEW vw_WardInfo AS
SELECT 
    w.w_id,
    w.name AS ward_name,
    sp.speciality,
    sp.description,
    CONCAT(s1.fname, ' ', s1.lname) AS day_sister_name,
    CONCAT(s2.fname, ' ', s2.lname) AS night_sister_name
FROM Ward w
INNER JOIN Speciality sp ON w.sp_id = sp.sp_id
LEFT JOIN Staff s1 ON w.day_sister = s1.st_id
LEFT JOIN Staff s2 ON w.night_sister = s2.st_id;
GO
