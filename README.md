## Pharma Sales Web Application 

A web-based **Pharma Sales Management System** built to manage medicine sales, customers, invoices, and reports for pharmaceutical businesses.  
The application focuses on simplicity, usability, and efficient sales tracking.

---

## Features
- User authentication & role-based access
- Medicine & product management
- Customer and sales order management
- Invoice generation
- Sales reports and analytics
- Responsive UI for desktop and tablet use

---

## Tech Stack

### Backend
- **Laravel** (PHP Framework)
- **MySQL** (Database)
- MVC architecture

### Frontend
- **Bootstrap** (Responsive UI)
- **jQuery** (DOM manipulation & AJAX)
- HTML5, CSS3

### Other Tools
- Composer (PHP dependency management)
- Git & GitHub (Version control)

---

## Installation

### 1. Clone the repository
```bash
git clone https://github.com/darshitdave/pharma-sales-webapp.git
```

### 2. Navigate to the project directory
```bash
cd pharma-sales-webapp
```

### 3. Install dependencies
```bash
composer install
```

### 4. Environment setup

Copy the example environment file and generate the app key:
```bash
cp .env.example .env
php artisan key:generate

Update the .env file with your database credentials:
DB_DATABASE=your_db_name
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password
```

### 5. Run database migrations
```bash
php artisan migrate
```

### 6. Start the development server
```bash
php artisan serve
