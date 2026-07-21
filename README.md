# UIRI Inventory Management System

This repository contains the source code and documentation for a web based Inventory Management System developed for Uganda Industrial Research Institute by the ICT interns as a database project.

The system supports inventory tracking across UIRI Nakawa and Namanve campuses, departments, sections and physical inventory locations. It handles both consumable stock and durable fixed assets.

## Main Features

- User authentication and role based access control
- Campus, department, section and location based inventory tracking
- Inventory item registration
- Asset instance registration using asset tags and serial numbers
- Supplier management
- Stock in and stock out processing
- Stock balance monitoring
- Low stock alerts
- Maintenance record tracking
- Dashboard and reports
- Audit logging of important user actions

## Technology Stack

- PHP
- MySQL or MariaDB
- HTML
- CSS
- JavaScript
- XAMPP for local development

## Default Test Account

Username: admin  
Password: xxxxxxxxxxxx

This account is for testing only and must be changed before any real deployment. If you use this locally, consider updating the password immediately.

## Setup Summary

1. Copy the project folder into the XAMPP htdocs directory.
2. Create a MySQL database named `uiri_ims`.
3. Import `database/01_schema.sql`.
4. Import `database/02_seed_data.sql`.
5. Configure database connection settings.
6. Start Apache and MySQL from XAMPP.
7. Open the system through the browser.

## Academic Deliverables

The repository includes documentation, database scripts, diagrams and implementation files for the UIRI Inventory Management System.
