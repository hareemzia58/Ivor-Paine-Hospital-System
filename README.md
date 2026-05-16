# 🏥 Ivor Paine Memorial Hospital (IPMH) - Hospital Management System

[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-blue.svg)](https://php.net)
[![SQL Server](https://img.shields.io/badge/SQL%20Server-2012%2B-red.svg)](https://www.microsoft.com/sql-server)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

A comprehensive **database-driven hospital management system** built with PHP, SQL Server, and modern web technologies. This system manages patients, staff, wards, clinical records, and hospital operations through an intuitive web interface.


## Overview

The **Ivor Paine Memorial Hospital Management System** is a full-featured web application designed to digitize and streamline hospital operations. It provides role-based access for managing:

- **Patients** - Registration, admissions, discharge, and medical history
- **Doctors** - Profiles, teams, specializations, and performance tracking
- **Wards** - Bed management, sister assignments, and care units
- **Clinical Records** - Complaints, treatments, progress notes, and doctor experience

---

<img width="2288" height="1262" alt="Ivor_paine_login" src="https://github.com/user-attachments/assets/9636bc7d-aa1d-4e81-8fb6-2c63818f6a0e" />

## Features

### Patient Management
- View all patients with filterable table and card views
- Search patients by name or ID
- Filter by ward and admission status (Active/Discharged)
- View detailed patient medical records including:
  - Personal information and admission details
  - Complaint history with descriptions
  - Treatment records with dates and assigned doctors
  - Progress notes from consultants

### Staff & Ward Management
- Comprehensive staff directory with role-based badges (`Doctor` / `Consultant` / `Nurse`)
- Doctor profiles with specialization, team assignment, and patient load
- Nurse profiles with ward assignment and shift information
- Ward management with:
  - Current patient census
  - Assigned day/night sisters
  - Care unit information
- Medical team organization and team lead assignments

### Clinical Records
| Feature | Description |
|---------|-------------|
| **Complaint View** | Select a complaint to see patients, treatments, and doctor's experience history |
| **Date Range Filter** | Filter treatments by complaint within specific date ranges |
| **Performance & History** | Search doctors to view employment history and treatment outcomes |

### Data Entry Forms
- Patient registration with auto-generated IDs
- Ward creation with specialty assignment
- Staff onboarding with automatic login credential generation

### Authentication
- Secure login system with MD5 password hashing
- Session-based authentication
- Role-based access control

---

## Technology Stack

| Component | Technology |
|-----------|------------|
| **Backend** | PHP 7.4+ |
| **Database** | Microsoft SQL Server 2012+ |
| **Frontend** | HTML5, CSS3, JavaScript |
| **Server** | Apache / XAMPP |
| **Authentication** | PHP Sessions |

---

## Installation Guide

### Prerequisites

| Requirement | Version |
|-------------|---------|
| Web Server | Apache / XAMPP |
| PHP | 7.4 or higher |
| SQL Server | 2012 or higher (including Express) |
| SQLSRV PHP Extension | Enabled |
| Git | (Optional, for cloning) |

### Step-by-Step Installation

#### 1. Clone the Repository
```bash
git clone https://github.com/yourusername/ivor-paine-hospital-system.git
cd ivor-paine-hospital-system
```
#### 2. Configure Database Connection
Edit `php/db_connect.php`:

```php
<?php
$serverName = "YOUR_SERVER_NAME\SQLEXPRESS";  // Your SQL Server instance (server name)
$options = [
    "Database" => "IvorPaineHospital",
    "Uid"      => "your_username",     // Leave empty for Windows Auth
    "PWD"      => "your_password",     // Leave empty for Windows Auth
    "TrustServerCertificate" => true
];
?>
```
#### 3. Create and Populate Database
Run the SQL scripts in order using SQL Server Management Studio (SSMS):

```sql
-- 1. Create tables and views
CREATE_TABLES.sql

-- 2. Insert sample data
INSERT_TABLES.sql
```
#### 4. Configure Web Server using XAMPP

Place project folder in C:\xampp\htdocs\

Access via: http://localhost/ivor-paine-hospital-system/php/login.php

#### 5. Enable PHP SQLSRV Extension
In your php.ini file, uncomment or add:

```ini
extension=php_sqlsrv_74_ts_x64.dll
extension=php_pdo_sqlsrv_74_ts_x64.dll
```
Note: The exact filename depends on your PHP version. Restart your web server after changes.

### Default Login Credentials
After running the sample data, you can log in with any of these credentials:

#### Doctor Accounts

| Role |	Email	| Password |
|------|--------|----------|
| Cardiologist | muhammadahmed@hospital.pk | pass1@IPH |
| Neurologist	| fatimakhan@hospital.pk | pass2@IPH |
| Orthopedic Surgeon | hassanali@hospital.pk | pass3@IPH |
| General Surgeon |	ayeshahasan@hospital.pk |	pass4@IPH |
| Oncologist |	aliraza@hospital.pk |	pass5@IPH |

### Nurse Accounts
| Role |	Email |	Password |
|------|--------|----------|
| Nurse |	aminasiddiqui@hospital.pk |	pass11@IPH |
| Nurse |	naidamirza@hospital.pk | pass12@IPH |
| Nurse |	khadijanasir@hospital.pk | pass13@IPH |

