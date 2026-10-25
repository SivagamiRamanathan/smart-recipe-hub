# 🍽️ Smart Recipe Hub

A web-based recipe management application that helps users explore recipes, calculate ingredient quantities, and manage their cooking experience.

## 📌 Features

- 🔐 **User Authentication** – User registration and login.
- 🍲 **Recipe Management** – Browse and explore recipes.
- 🧮 **Ingredient Calculator** – Adjust ingredient quantities based on the number of servings.
- 👤 **User Profile** – Manage user profile information.
- 🔒 **Session Management** – Support for user sessions and protected pages.
- 📱 **User-Friendly Interface** – Simple and easy-to-use design.

## 🛠️ Technologies Used

- **Frontend:** HTML, CSS, JavaScript
- **Backend:** PHP
- **Database:** PostgreSQL
- **Database Connectivity:** PDO
- **Deployment:** Render
- **Version Control:** Git & GitHub

## 📂 Project Structure

```text
smart-recipe-hub/
├── config/
│   └── db.php
├── database/
│   └── setup.sql
├── public/
│   ├── index.php
│   ├── login.php
│   ├── register.php
│   ├── recipes.php
│   ├── calculator.php
│   ├── profile.php
│   ├── logout.php
│   ├── secret_recipe.php
│   ├── test.php
│   ├── css/
│   │   └── style.css
│   └── js/
│       └── script.js
└── Dockerfile
```

## ⚙️ Installation and Setup

### 1. Clone the Repository

```bash
git clone <repository-url>
cd smart-recipe-hub
```

### 2. Configure the Database

- Create a PostgreSQL database.
- Configure the database connection settings in `config/db.php`.
- Run the SQL setup script from `database/setup.sql` if required.

### 3. Configure Environment Variables

Set the database connection details required by your application and hosting environment.

Do not upload database passwords, API keys, or other sensitive credentials to GitHub.

### 4. Run the Application

Configure PHP and PostgreSQL in your local environment, then start the PHP application using your preferred local server setup.

### 5. Deploy

Deploy the application to Render with the required environment variables and database configuration.

## 🚀 Usage

1. Register a new account.
2. Log in to the application.
3. Browse the available recipes.
4. Select the required serving quantity.
5. Calculate the adjusted ingredient quantities.
6. Access your profile and log out securely.

## 🔒 Security

- User authentication and session handling.
- Database access through PDO.
- Keep sensitive credentials in environment variables.
- Use secure password handling and validate user inputs.

## 👩‍💻 Author

**Sivagami R.**

B.Tech Artificial Intelligence and Data Science  
Kathir College of Engineering

## 📄 License

This project was developed for learning and educational purposes.
