# BloodConnect Backend

PHP and MySQL backend for the BloodConnect blood donation management system.

## 🚀 Quick Setup for WAMP Server

### Prerequisites
- WAMP Server installed and running
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Root password set to: `14162121`

### 1. Database Setup

#### Option A: Automatic Setup (Recommended)
1. Open your browser and navigate to:
   ```
   http://localhost/your-project-path/backend/setup/database_setup.php
   ```
2. Follow the on-screen instructions
3. The script will automatically create the database and tables

#### Option B: Manual Setup
1. Open phpMyAdmin: `http://localhost/phpmyadmin/`
2. Create a new database named `bloodconnect`
3. Import the SQL file: `backend/database/bloodconnect_database.sql`

### 2. Configuration

The database configuration is already set for WAMP in `backend/config/database.php`:
- **Host:** localhost
- **Database:** bloodconnect
- **Username:** root
- **Password:** 14162121

### 3. Test Connection

Test your setup by visiting:
```
http://localhost/your-project-path/backend/api/test_connection.php
```

## 📁 Project Structure

```
backend/
├── api/                    # API endpoints
│   ├── auth/              # Authentication endpoints
│   │   ├── login.php      # User login
│   │   └── register.php   # User registration
│   ├── BaseAPI.php        # Base API class
│   └── test_connection.php # Connection test
├── config/
│   └── database.php       # Database configuration
├── database/
│   └── bloodconnect_database.sql # Database schema
├── setup/
│   └── database_setup.php # Setup wizard
└── README.md
```

## 🗄️ Database Schema

### Core Tables
- **users** - Base user accounts (patients, donors, hospitals, admins)
- **patients** - Patient-specific information
- **donors** - Donor-specific information  
- **hospitals** - Hospital-specific information

### Blood Management
- **blood_inventory** - Hospital blood stock levels
- **blood_units** - Individual blood unit tracking
- **blood_requests** - Patient blood requests
- **donation_offers** - Donor donation offers
- **donation_history** - Completed donations

### System Tables
- **notifications** - In-app notifications
- **activity_logs** - Audit trail
- **system_settings** - Configuration settings

## 🔌 API Endpoints

### Authentication
- `POST /api/auth/login.php` - User login
- `POST /api/auth/register.php` - User registration

### Testing
- `GET /api/test_connection.php` - Test database connection

## 📝 API Usage Examples

### User Registration
```javascript
fetch('http://localhost/your-project/backend/api/auth/register.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
    },
    body: JSON.stringify({
        email: 'patient@example.com',
        password: 'password123',
        user_type: 'patient',
        first_name: 'John',
        last_name: 'Doe',
        blood_type: 'A+',
        date_of_birth: '1990-01-01',
        gender: 'male',
        phone: '+1-555-0123'
    })
})
.then(response => response.json())
.then(data => console.log(data));
```

### User Login
```javascript
fetch('http://localhost/your-project/backend/api/auth/login.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
    },
    body: JSON.stringify({
        email: 'patient@example.com',
        password: 'password123'
    })
})
.then(response => response.json())
.then(data => console.log(data));
```

## 🔧 Configuration

### Database Settings
Edit `backend/config/database.php` to modify database connection settings:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'bloodconnect');
define('DB_USER', 'root');
define('DB_PASS', '14162121');
```

### System Settings
Default system settings are automatically inserted:
- Donor eligibility period: 56 days
- Blood unit expiry: 42 days
- Low stock threshold: 10 units
- Critical stock threshold: 5 units

## 🛡️ Security Features

- Password hashing with PHP's `password_hash()`
- SQL injection prevention with prepared statements
- Input sanitization and validation
- CORS headers for cross-origin requests
- Activity logging for audit trails

## 🚨 Troubleshooting

### Common Issues

1. **Database Connection Failed**
   - Check if WAMP services are running
   - Verify MySQL password is set to `14162121`
   - Ensure `bloodconnect` database exists

2. **Permission Denied**
   - Check file permissions
   - Ensure WAMP has write access to project directory

3. **API Returns 500 Error**
   - Check PHP error logs in WAMP
   - Verify all required files are present
   - Check database table structure

### Error Logs
- PHP errors: Check WAMP logs directory
- Application errors: Logged to PHP error log
- Database errors: Check MySQL error log

## 🔄 Next Steps

1. ✅ Database setup complete
2. ✅ Basic authentication APIs ready
3. 🔄 Implement blood request APIs
4. 🔄 Implement donation management APIs
5. 🔄 Add hospital management APIs
6. 🔄 Implement notification system
7. 🔄 Add admin panel APIs

## 📞 Support

If you encounter any issues:
1. Check the setup wizard: `/backend/setup/database_setup.php`
2. Test connection: `/backend/api/test_connection.php`
3. Review error logs in WAMP
4. Verify database structure in phpMyAdmin

## 🎯 Default Admin Account

After setup, you can login with:
- **Email:** admin@bloodconnect.com
- **Password:** admin123

**⚠️ Important:** This is the default admin account for testing. Change the password in production!